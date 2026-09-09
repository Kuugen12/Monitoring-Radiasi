<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class MatrixController extends Controller
{
    /**
     * Display the 72-Detector Radioscan Matrix Dashboard.
     */
    public function index()
    {
        return view('matrix');
    }

    /**
     * API: Get scan matrix records from TiDB Cloud database (with SQLite fallback).
     * Supports filtering by ?object_name=... and ?session_id=...
     */
    public function getHistory(Request $request)
    {
        $objectFilter = $request->query('object_name');
        $sessionFilter = $request->query('session_id');
        $bustCache = $request->boolean('refresh', false);
        $defaultLimit = (!empty($objectFilter) && $objectFilter !== 'ALL') ? 100 : 24;
        $limit = (int) $request->query('limit', $defaultLimit);

        $rows = [];
        $availableObjects = [];
        $source = 'tidb_cloud';

        // 1. Prioritas Utama: Ambil langsung dari TiDB Cloud (MySQL Connection)
        try {
            if ($bustCache) {
                Cache::forget('tidb_available_objects');
            }

            // Ambil daftar seluruh objek yang pernah di-scan (Query Tunggal & Cache 15s untuk respon instan)
            $availableObjects = Cache::remember('tidb_available_objects', 15, function () {
                $rawObjects = DB::table('scan_matrix')
                    ->select(
                        'object_name',
                        DB::raw('COUNT(*) as total_records'),
                        DB::raw('MAX(timestamp) as last_ts'),
                        DB::raw('MAX(created_at) as last_created'),
                        DB::raw('MAX(max_cps) as peak_cps'),
                        DB::raw('ROUND(AVG(avg_cps), 1) as mean_cps'),
                        DB::raw('MAX(height_level) as max_height'),
                        DB::raw('COALESCE(MAX(total_loops), 1) as loops')
                    )
                    ->whereNotNull('object_name')
                    ->where('object_name', '<>', '')
                    ->groupBy('object_name')
                    ->orderBy('last_created', 'desc')
                    ->get();

                return $rawObjects->toArray();
            });

            // Jika objectFilter kosong atau 'ALL', ambil object paling baru dari daftar
            if (empty($objectFilter) || $objectFilter === 'ALL') {
                if (!empty($availableObjects)) {
                    $firstObj = (array) $availableObjects[0];
                    $objectFilter = $firstObj['object_name'] ?? 'Sample_new';
                } else {
                    $objectFilter = 'Sample_new';
                }
            }

            // Ambil data matriks record untuk objectFilter
            $query = DB::table('scan_matrix');
            if (!empty($sessionFilter) && $sessionFilter !== 'ALL') {
                $query->where('session_id', $sessionFilter);
            } else {
                $latestSession = DB::table('scan_matrix')
                    ->where('object_name', $objectFilter)
                    ->whereNotNull('session_id')
                    ->where('session_id', '<>', '')
                    ->orderBy('id', 'desc')
                    ->value('session_id');

                if ($latestSession) {
                    $query->where('object_name', $objectFilter)->where('session_id', $latestSession);
                } else {
                    $query->where('object_name', $objectFilter);
                }
            }

            $records = $query->orderBy('id', 'desc')->limit($limit)->get();
            if ($records->isNotEmpty()) {
                $rows = $records->map(function ($item) {
                    return (array) $item;
                })->toArray();
            }

        } catch (\Exception $e) {
            // 2. Fallback: Baca dari SQLite lokal jika koneksi TiDB offline
            $dbPath = base_path('arraydata.db');
            if (file_exists($dbPath)) {
                try {
                    $sqlite = new \PDO("sqlite:" . $dbPath);
                    $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    
                    // Objects from SQLite
                    $objStmt = $sqlite->query("SELECT object_name, COUNT(*) as total_records, MAX(timestamp) as last_ts, MAX(max_cps) as peak_cps, ROUND(AVG(avg_cps), 1) as mean_cps, MAX(height_level) as max_height, MAX(total_loops) as total_loops FROM scan_matrix WHERE object_name IS NOT NULL AND object_name != '' GROUP BY object_name ORDER BY MAX(id) DESC");
                    $availableObjects = $objStmt->fetchAll(\PDO::FETCH_ASSOC);

                    if (empty($objectFilter) || $objectFilter === 'ALL') {
                        if (!empty($availableObjects)) {
                            $objectFilter = $availableObjects[0]['object_name'] ?? 'Sample_new';
                        } else {
                            $objectFilter = 'Sample_new';
                        }
                    }

                    if (!empty($sessionFilter) && $sessionFilter !== 'ALL') {
                        $stmt = $sqlite->prepare("SELECT * FROM scan_matrix WHERE session_id = :sess ORDER BY id DESC LIMIT :lim");
                        $stmt->bindValue(':sess', $sessionFilter);
                        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                        $stmt->execute();
                    } else {
                        $latestSessStmt = $sqlite->prepare("SELECT session_id FROM scan_matrix WHERE object_name = :obj AND session_id IS NOT NULL AND session_id != '' ORDER BY id DESC LIMIT 1");
                        $latestSessStmt->bindValue(':obj', $objectFilter);
                        $latestSessStmt->execute();
                        $latestSess = $latestSessStmt->fetchColumn();

                        if ($latestSess) {
                            $stmt = $sqlite->prepare("SELECT * FROM scan_matrix WHERE object_name = :obj AND session_id = :sess ORDER BY id DESC LIMIT :lim");
                            $stmt->bindValue(':obj', $objectFilter);
                            $stmt->bindValue(':sess', $latestSess);
                            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                        } else {
                            $stmt = $sqlite->prepare("SELECT * FROM scan_matrix WHERE object_name = :obj ORDER BY id DESC LIMIT :lim");
                            $stmt->bindValue(':obj', $objectFilter);
                            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                        }
                        $stmt->execute();
                    }
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                    $source = 'sqlite_local';
                } catch (\Exception $ex) {}
            }
        }

        if (empty($rows)) {
            return response()->json([
                'status' => 'empty',
                'source' => $source,
                'current_object' => $objectFilter ?: 'ALL',
                'records' => [],
                'available_objects' => $availableObjects,
                'available_sessions' => $availableSessions,
                'message' => 'Belum ada data scan di database untuk filter ini.'
            ]);
        }

        // Parse format JSON array untuk data detektor
        foreach ($rows as &$row) {
            if (!empty($row['detector_data']) && is_string($row['detector_data'])) {
                $row['detector_data'] = json_decode($row['detector_data'], true);
            }
            if (!empty($row['xs1_data']) && is_string($row['xs1_data'])) {
                $row['xs1_data'] = json_decode($row['xs1_data'], true);
            }
            if (!empty($row['xs2_data']) && is_string($row['xs2_data'])) {
                $row['xs2_data'] = json_decode($row['xs2_data'], true);
            }
            if (!empty($row['xs3_data']) && is_string($row['xs3_data'])) {
                $row['xs3_data'] = json_decode($row['xs3_data'], true);
            }
            if (empty($row['detector_data'])) {
                $d1 = is_array($row['xs1_data']) ? $row['xs1_data'] : [];
                $d2 = is_array($row['xs2_data']) ? $row['xs2_data'] : [];
                $d3 = is_array($row['xs3_data']) ? $row['xs3_data'] : [];
                $row['detector_data'] = array_merge($d1, $d2, $d3);
            }
            if (!isset($row['object_name']) || empty($row['object_name'])) {
                $row['object_name'] = 'Gentong';
            }
        }

        return response()->json([
            'status' => 'success',
            'source' => $source,
            'current_object' => $objectFilter ?: 'ALL',
            'total' => count($rows),
            'records' => $rows,
            'available_objects' => $availableObjects,
            'available_sessions' => $availableSessions
        ]);
    }

    /**
     * API: Save completed scan session matrix records to TiDB Cloud & SQLite fallback.
     */
    public function saveSession(Request $request)
    {
        $objectName = $request->input('object_name', 'Gentong');
        $sessionId = $request->input('session_id', 'SES_' . date('Ymd_His') . '_' . rand(100, 999));
        $totalLoops = (int) $request->input('total_loops', 1);
        $transitionDelay = (float) $request->input('transition_delay', 1.0);
        $records = $request->input('records', []);

        if (empty($records) || !is_array($records)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada data rekaman scan yang dikirim untuk disimpan.'
            ], 400);
        }

        $savedCount = 0;
        $dbStatus = 'tidb_cloud';
        $now = date('Y-m-d H:i:s');

        // Prepare rows for bulk insert
        $insertRows = [];
        foreach ($records as $rec) {
            $h = isset($rec['height']) ? (int)$rec['height'] : 1;
            $loopIdx = isset($rec['loop_index']) ? (int)$rec['loop_index'] : 1;
            $ts = isset($rec['timestamp']) ? $rec['timestamp'] : $now;
            
            $fullData = isset($rec['detector_data']) ? (is_array($rec['detector_data']) ? $rec['detector_data'] : json_decode($rec['detector_data'], true)) : [];
            
            $xs1 = isset($rec['xs1_data']) ? $rec['xs1_data'] : (is_array($fullData) ? array_slice($fullData, 0, 24) : []);
            $xs2 = isset($rec['xs2_data']) ? $rec['xs2_data'] : (is_array($fullData) ? array_slice($fullData, 24, 24) : []);
            $xs3 = isset($rec['xs3_data']) ? $rec['xs3_data'] : (is_array($fullData) ? array_slice($fullData, 48, 24) : []);
            
            $maxCps = isset($rec['max_cps']) ? (float)$rec['max_cps'] : (!empty($fullData) ? max($fullData) : 0);
            $avgCps = isset($rec['avg_cps']) ? (float)$rec['avg_cps'] : (!empty($fullData) ? array_sum($fullData) / count($fullData) : 0);

            $insertRows[] = [
                'timestamp' => $ts,
                'height_level' => $h,
                'object_name' => $objectName,
                'session_id' => $sessionId,
                'loop_index' => $loopIdx,
                'total_loops' => $totalLoops,
                'transition_delay' => $transitionDelay,
                'xs1_data' => is_string($xs1) ? $xs1 : json_encode($xs1),
                'xs2_data' => is_string($xs2) ? $xs2 : json_encode($xs2),
                'xs3_data' => is_string($xs3) ? $xs3 : json_encode($xs3),
                'detector_data' => is_string($fullData) ? $fullData : json_encode($fullData),
                'max_cps' => round($maxCps, 2),
                'avg_cps' => round($avgCps, 2),
                'created_at' => $now,
            ];
        }

        try {
            DB::table('scan_matrix')->insert($insertRows);
            $savedCount = count($insertRows);
            Cache::forget('tidb_available_objects');
        } catch (\Exception $e) {
            // Fallback: SQLite
            $dbStatus = 'sqlite_local';
            $dbPath = base_path('arraydata.db');
            try {
                $sqlite = new \PDO("sqlite:" . $dbPath);
                $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $stmt = $sqlite->prepare("INSERT INTO scan_matrix (timestamp, height_level, object_name, session_id, loop_index, total_loops, transition_delay, xs1_data, xs2_data, xs3_data, detector_data, max_cps, avg_cps) VALUES (:ts, :h, :obj, :sess, :loop_idx, :tot_loops, :trans_delay, :xs1, :xs2, :xs3, :det, :max_c, :avg_c)");
                
                foreach ($insertRows as $row) {
                    $stmt->execute([
                        ':ts' => $row['timestamp'],
                        ':h' => $row['height_level'],
                        ':obj' => $row['object_name'],
                        ':sess' => $row['session_id'],
                        ':loop_idx' => $row['loop_index'],
                        ':tot_loops' => $row['total_loops'],
                        ':trans_delay' => $row['transition_delay'],
                        ':xs1' => $row['xs1_data'],
                        ':xs2' => $row['xs2_data'],
                        ':xs3' => $row['xs3_data'],
                        ':det' => $row['detector_data'],
                        ':max_c' => $row['max_cps'],
                        ':avg_c' => $row['avg_cps'],
                    ]);
                    $savedCount++;
                }
            } catch (\Exception $ex) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal menyimpan ke database: ' . $e->getMessage() . ' | SQLite: ' . $ex->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'status' => 'success',
            'db_target' => $dbStatus,
            'object_name' => $objectName,
            'session_id' => $sessionId,
            'count' => $savedCount,
            'message' => "Sesi pemindaian '$objectName' ($savedCount record) berhasil disimpan ke Database!"
        ]);
    }

    /**
     * API: Download or preview current heatmap_radiation_matrix.csv.
     */
    public function downloadCsv()
    {
        $csvPath = base_path('heatmap_radiation_matrix.csv');
        if (!file_exists($csvPath)) {
            return response()->json(['error' => 'Matrix CSV file not generated yet.'], 404);
        }

        return response()->download($csvPath, 'heatmap_radiation_matrix_' . date('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
