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
     * API: Get scan matrix records from SQLite / local store.
     */
    public function getHistory()
    {
        $dbPath = base_path('arraydata.db');
        if (!file_exists($dbPath)) {
            return response()->json([
                'status' => 'empty',
                'records' => [],
                'message' => 'No local database found yet.'
            ]);
        }

        try {
            $sqlite = new \PDO("sqlite:" . $dbPath);
            $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $stmt = $sqlite->query("SELECT * FROM scan_matrix ORDER BY id DESC LIMIT 50");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Parse json fields
            foreach ($rows as &$row) {
                if (!empty($row['detector_data'])) {
                    $row['detector_data'] = json_decode($row['detector_data'], true);
                }
                if (!empty($row['xs1_data'])) {
                    $row['xs1_data'] = json_decode($row['xs1_data'], true);
                }
                if (!empty($row['xs2_data'])) {
                    $row['xs2_data'] = json_decode($row['xs2_data'], true);
                }
                if (!empty($row['xs3_data'])) {
                    $row['xs3_data'] = json_decode($row['xs3_data'], true);
                }
            }

            return response()->json([
                'status' => 'success',
                'total' => count($rows),
                'records' => $rows
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
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
