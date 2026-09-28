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

            // Ambil daftar seluruh objek yang pernah di-scan (Query Tunggal & Cache 15s)
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

            // Ambil data matriks record untuk objectFilter (Single fast query)
            $query = DB::table('scan_matrix')->where('object_name', $objectFilter);
            if (!empty($sessionFilter) && $sessionFilter !== 'ALL') {
                $query->where('session_id', $sessionFilter);
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
                    $objStmt = $sqlite->query("SELECT object_name, COUNT(*) as total_records, MAX(timestamp) as last_ts, MAX(max_cps) as peak_cps, ROUND(AVG(avg_cps), 1) as mean_cps, MAX(height_level) as max_height, COALESCE(MAX(total_loops), 1) as loops FROM scan_matrix WHERE object_name IS NOT NULL AND object_name != '' GROUP BY object_name ORDER BY MAX(id) DESC");
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
                    } else {
                        $stmt = $sqlite->prepare("SELECT * FROM scan_matrix WHERE object_name = :obj ORDER BY id DESC LIMIT :lim");
                        $stmt->bindValue(':obj', $objectFilter);
                        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                    }
                    $stmt->execute();
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
                $row['object_name'] = $objectFilter ?: 'Gentong';
            }
        }

        return response()->json([
            'status' => 'success',
            'source' => $source,
            'current_object' => $objectFilter ?: 'ALL',
            'total' => count($rows),
            'records' => $rows,
            'available_objects' => $availableObjects
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
     * API / Web: Download scan matrix data from TiDB Cloud database as CSV.
     * Supports downloading all matrix data or filtered by object_name.
     */
    public function downloadCsv(Request $request)
    {
        $objectFilter = $request->query('object_name');
        $sessionFilter = $request->query('session_id');
        $scope = $request->query('scope', 'all'); // 'all' or 'current'

        $rows = [];
        $source = 'tidb_cloud';

        // 1. Ambil data dari TiDB Cloud (MySQL Connection)
        try {
            $query = DB::table('scan_matrix');

            if ($scope !== 'all' && !empty($objectFilter) && $objectFilter !== 'ALL') {
                $query->where('object_name', $objectFilter);
            }

            if (!empty($sessionFilter) && $sessionFilter !== 'ALL') {
                $query->where('session_id', $sessionFilter);
            }

            $records = $query->orderBy('id', 'asc')->get();
            if ($records->isNotEmpty()) {
                $rows = $records->map(function ($item) {
                    return (array) $item;
                })->toArray();
            }
        } catch (\Exception $e) {
            // 2. Fallback: SQLite lokal jika TiDB offline
            $dbPath = base_path('arraydata.db');
            if (file_exists($dbPath)) {
                try {
                    $sqlite = new \PDO("sqlite:" . $dbPath);
                    $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                    if ($scope !== 'all' && !empty($objectFilter) && $objectFilter !== 'ALL') {
                        $stmt = $sqlite->prepare("SELECT * FROM scan_matrix WHERE object_name = :obj ORDER BY id ASC");
                        $stmt->bindValue(':obj', $objectFilter);
                    } else {
                        $stmt = $sqlite->prepare("SELECT * FROM scan_matrix ORDER BY id ASC");
                    }
                    $stmt->execute();
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $source = 'sqlite_local';
                } catch (\Exception $ex) {}
            }
        }

        if (empty($rows)) {
            // Jika benar-benar kosong, coba fallback ke heatmap_radiation_matrix.csv jika ada
            $csvPath = base_path('heatmap_radiation_matrix.csv');
            if (file_exists($csvPath)) {
                return response()->download($csvPath, 'scan_matrix_tidb_' . date('Ymd_His') . '.csv', [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                ]);
            }
            return response()->json(['error' => 'Belum ada data scan di database TiDB untuk diunduh sebagai CSV.'], 404);
        }

        // Header CSV: Kolom metadata + 72 Nilai Detektor (Det_01 sampai Det_72)
        $detectorHeaders = [];
        for ($i = 1; $i <= 72; $i++) {
            $detectorHeaders[] = 'Det_' . str_pad($i, 2, '0', STR_PAD_LEFT);
        }

        $headers = array_merge([
            'id',
            'timestamp',
            'object_name',
            'session_id',
            'height_level',
            'loop_index',
            'total_loops',
            'transition_delay',
            'max_cps',
            'avg_cps',
        ], $detectorHeaders, [
            'created_at',
        ]);

        $fileNamePrefix = ($scope === 'all' || empty($objectFilter) || $objectFilter === 'ALL')
            ? 'scan_matrix_tidb_all'
            : 'scan_matrix_tidb_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $objectFilter);

        $fileName = $fileNamePrefix . '_' . date('Ymd_His') . '.csv';

        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');

            // Output UTF-8 BOM untuk kompatibilitas Microsoft Excel Windows
            fputs($file, "\xEF\xBB\xBF");

            // Tulis Header Kolom
            fputcsv($file, $headers);

            // Tulis Data Baris
            foreach ($rows as $row) {
                // Parse detector array data
                $detData = [];
                if (!empty($row['detector_data'])) {
                    $detData = is_array($row['detector_data']) ? $row['detector_data'] : json_decode($row['detector_data'], true);
                }
                if (empty($detData)) {
                    $d1 = !empty($row['xs1_data']) ? (is_array($row['xs1_data']) ? $row['xs1_data'] : json_decode($row['xs1_data'], true)) : [];
                    $d2 = !empty($row['xs2_data']) ? (is_array($row['xs2_data']) ? $row['xs2_data'] : json_decode($row['xs2_data'], true)) : [];
                    $d3 = !empty($row['xs3_data']) ? (is_array($row['xs3_data']) ? $row['xs3_data'] : json_decode($row['xs3_data'], true)) : [];
                    $detData = array_merge($d1 ?: [], $d2 ?: [], $d3 ?: []);
                }

                // 72 Nilai Detektor
                $detValues = [];
                for ($i = 0; $i < 72; $i++) {
                    $detValues[] = isset($detData[$i]) ? (int)$detData[$i] : 0;
                }

                $rowValues = array_merge([
                    $row['id'] ?? '',
                    $row['timestamp'] ?? '',
                    $row['object_name'] ?? '',
                    $row['session_id'] ?? '',
                    $row['height_level'] ?? 1,
                    $row['loop_index'] ?? 1,
                    $row['total_loops'] ?? 1,
                    $row['transition_delay'] ?? 1.0,
                    $row['max_cps'] ?? 0,
                    $row['avg_cps'] ?? 0,
                ], $detValues, [
                    $row['created_at'] ?? '',
                ]);

                fputcsv($file, $rowValues);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
