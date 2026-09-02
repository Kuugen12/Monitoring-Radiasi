<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DetectorReading;

class DashboardController extends Controller
{
    /**
     * Get list of detectors.
     */
    private function getDetectors()
    {
        return [
            'detektor1' => [ 'name' => "Detektor 1 — Zona A (Gudang)", 'icon' => "box", 'key' => "detektor1" ],
            'detektor2' => [ 'name' => "Detektor 2 — Zona B (R. Kontrol)", 'icon' => "server", 'key' => "detektor2" ],
            'detektor3' => [ 'name' => "Detektor 3 — Zona C (Laboratorium)", 'icon' => "drop", 'key' => "detektor3" ],
            'detektor4' => [ 'name' => "Detektor 4 — Zona D (R. Arsip)", 'icon' => "server", 'key' => "detektor4" ],
        ];
    }

    /**
     * Display the dashboard view.
     */
    public function index()
    {
        return view('dashboard', ['detectors' => $this->getDetectors()]);
    }

    /**
     * Display the details page for a specific detector.
     */
    public function zone($id)
    {
        $detectors = $this->getDetectors();
        if (!isset($detectors[$id])) {
            abort(404);
        }
        
        $detector = $detectors[$id];
        
        // Fetch last 20 readings from local DB for this detector (for initial chart & table display)
        $readings = DetectorReading::where('detector_name', $id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->reverse(); // chronological order for chart

        return view('zone', [
            'detector' => $detector,
            'detectorId' => $id,
            'readings' => $readings,
            'detectors' => $detectors
        ]);
    }

    /**
     * API to fetch historical readings of a specific detector.
     */
    public function history($id)
    {
        $detectors = $this->getDetectors();
        if (!isset($detectors[$id])) {
            return response()->json(['error' => 'Detector not found'], 404);
        }

        // Fetch last 20 readings from local DB
        $readings = DetectorReading::where('detector_name', $id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->reverse(); // chronological order for chart
            
        return response()->json($readings->values());
    }
}
