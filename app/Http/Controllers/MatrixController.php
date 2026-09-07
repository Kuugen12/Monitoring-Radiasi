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
        }

        return response()->json([
            'status' => 'success',
            'source' => $source,
            'total' => count($rows),
            'records' => $rows
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
