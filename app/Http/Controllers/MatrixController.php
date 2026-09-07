<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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
     */
    public function getHistory()
    {
        $rows = [];
        $source = 'tidb_cloud';

        // 1. Prioritas Utama: Ambil langsung dari TiDB Cloud (MySQL Connection)
        try {
            $records = DB::table('scan_matrix')->orderBy('id', 'desc')->limit(50)->get();
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
                    $stmt = $sqlite->query("SELECT * FROM scan_matrix ORDER BY id DESC LIMIT 50");
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $source = 'sqlite_local';
                } catch (\Exception $ex) {}
            }
        }

        if (empty($rows)) {
            return response()->json([
                'status' => 'empty',
                'source' => $source,
                'records' => [],
                'message' => 'Belum ada data scan di database.'
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
            if (!isset($row['object_name']) || empty($row['object_name'])) {
                $row['object_name'] = 'Gentong';
            }
        }

        return response()->json([
            'status' => 'success',
            'source' => $source,
            'total' => count($rows),
            'records' => $rows
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
