<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\DetectorReading;

class SyncFirebaseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Firebase Realtime Database to local magang phpMyAdmin database every 40 seconds';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting Firebase Realtime Database Sync to local 'magang' DB...");
        $this->info("Interval: every 40 seconds.");
        
        while (true) {
            $startTime = microtime(true);
            try {
                $response = Http::withoutVerifying()->get('https://magang-brin-27225-default-rtdb.asia-southeast1.firebasedatabase.app/detectors.json');
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (is_array($data)) {
                        foreach ($data as $detectorKey => $detectorData) {
                            $rate = $detectorData['rate'] ?? 0.0;
                            $doseRate = $detectorData['dose_rate'] ?? 0.0;
                            $total = $detectorData['total'] ?? 0.0;
                            $lastUpdated = $detectorData['last_updated'] ?? null;
                            $status = $doseRate > 0.35 ? 'warn' : 'safe';

                            // Check status transition to avoid spamming alerts
                            $lastReading = DetectorReading::where('detector_name', $detectorKey)
                                ->orderBy('created_at', 'desc')
                                ->first();

                            $isNewWarning = ($status === 'warn') && (!$lastReading || $lastReading->status !== 'warn');

                            DetectorReading::create([
                                'detector_name' => $detectorKey,
                                'rate' => $rate,
                                'dose_rate' => $doseRate,
                                'total' => $total,
                                'status' => $status,
                                'firebase_last_updated' => $lastUpdated,
                            ]);

                            if ($isNewWarning) {
                                try {
                                    $users = \App\Models\User::all();
                                    foreach ($users as $user) {
                                        if (!empty($user->email)) {
                                            \Illuminate\Support\Facades\Mail::to($user->email)->send(
                                                new \App\Mail\RadiationAlertMail($detectorKey, $rate, $doseRate, $user)
                                            );
                                        }
                                    }
                                    $this->info(date('Y-m-d H:i:s') . " - Alert email sent to users for " . $detectorKey);
                                } catch (\Exception $e) {
                                    $this->error(date('Y-m-d H:i:s') . " - Alert email failed: " . $e->getMessage());
                                }
                            }
                        }
                        $this->info(date('Y-m-d H:i:s') . " - Synchronized " . count($data) . " detectors.");
                    } else {
                        $this->warn(date('Y-m-d H:i:s') . " - Received empty or invalid data from Firebase.");
                    }
                } else {
                    $this->error(date('Y-m-d H:i:s') . " - Firebase API HTTP Error: " . $response->status());
                }
            } catch (\Exception $e) {
                $this->error(date('Y-m-d H:i:s') . " - Sync Error: " . $e->getMessage());
            }

            // Calculate remaining time to wait, ensuring 40s intervals
            $elapsedTime = microtime(true) - $startTime;
            $sleepTime = max(1, 40 - $elapsedTime);
            
            sleep((int)$sleepTime);
        }
    }
}
