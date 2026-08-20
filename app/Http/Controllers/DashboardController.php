<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function getZones()
    {
        if (!session()->has('zones')) {
            session()->put('zones', [
                [ 'name' => "Zona A — Gudang", 'icon' => "box", 'rate' => 142, 'doseRate' => 0.18, 'total' => 4.82, 'status' => "safe" ],
                [ 'name' => "Zona B — R. Kontrol", 'icon' => "server", 'rate' => 305, 'doseRate' => 0.41, 'total' => 9.94, 'status' => "warn" ],
                [ 'name' => "Zona C — Laboratorium", 'icon' => "drop", 'rate' => 88, 'doseRate' => 0.12, 'total' => 3.05, 'status' => "safe" ],
            ]);
        }
        return session()->get('zones');
    }

    public function index()
    {
        return view('dashboard', ['zones' => $this->getZones()]);
    }

    public function zone($id)
    {
        $zones = $this->getZones();
        if (!isset($zones[$id])) {
            abort(404);
        }
        return view('zone', [
            'zones' => $zones,
            'zone' => $zones[$id],
            'zoneId' => $id
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|string|in:box,server,drop',
        ]);

        $zones = $this->getZones();
        
        // Simulate initial reading parameters
        $rate = rand(60, 160);
        $doseRate = round($rate * 0.0013, 2);
        $total = round(rand(100, 300) / 100, 2);
        $status = $doseRate > 0.35 ? 'warn' : 'safe';

        $zones[] = [
            'name' => $request->name,
            'icon' => $request->icon,
            'rate' => $rate,
            'doseRate' => $doseRate,
            'total' => $total,
            'status' => $status
        ];

        session()->put('zones', $zones);

        return redirect()->route('dashboard')->with('success', 'Zona baru berhasil ditambahkan.');
    }
}
