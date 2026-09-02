<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peringatan Bahaya Radiasi</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0a0e14;
            color: #e8edf5;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #121822;
            border: 1px solid #ff5c6b;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        .header {
            background: linear-gradient(135deg, #ff5c6b, #d9383a);
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .content {
            padding: 30px 24px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 12px;
        }
        .alert-desc {
            font-size: 14px;
            color: #7c8798;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .stats-card {
            background-color: #161e2b;
            border: 1px solid #212b3a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #212b3a;
        }
        .stat-row:last-child {
            border-bottom: none;
        }
        .stat-label {
            font-size: 13px;
            color: #7c8798;
            font-weight: 500;
        }
        .stat-value {
            font-size: 14px;
            color: #ffffff;
            font-weight: 700;
            font-family: 'Courier New', Courier, monospace;
        }
        .stat-value.danger {
            color: #ff5c6b;
        }
        .warning-box {
            background-color: rgba(255, 92, 107, 0.1);
            border-left: 4px solid #ff5c6b;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 24px;
        }
        .warning-box h4 {
            margin: 0 0 6px 0;
            color: #ff5c6b;
            font-size: 14px;
            font-weight: 700;
        }
        .warning-box p {
            margin: 0;
            color: #e8edf5;
            font-size: 13px;
            line-height: 1.5;
        }
        .btn-action {
            display: block;
            text-align: center;
            background-color: #ffc53d;
            color: #171000;
            text-decoration: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            margin-top: 10px;
        }
        .btn-action:hover {
            filter: brightness(1.08);
        }
        .footer {
            background-color: #0c1017;
            padding: 18px;
            text-align: center;
            font-size: 11px;
            color: #4d5768;
            border-top: 1px solid #212b3a;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>☢️ NOTIFIKASI RADIASI TINGGI ☢️</h1>
        </div>
        <div class="content">
            <div class="greeting">Halo, {{ $user->name }}</div>
            <p class="alert-desc">
                Sistem RadiaTrack mendeteksi peningkatan level radiasi melebihi ambang batas aman di area instalasi detektor. Harap segera periksa status wilayah tersebut.
            </p>

            <div class="stats-card">
                @php
                    $detectorLabels = [
                        'detektor1' => 'Detektor 1 — Zona A (Gudang)',
                        'detektor2' => 'Detektor 2 — Zona B (R. Kontrol)',
                        'detektor3' => 'Detektor 3 — Zona C (Laboratorium)',
                        'detektor4' => 'Detektor 4 — Zona D (R. Arsip)',
                    ];
                    $label = $detectorLabels[$detectorKey] ?? $detectorKey;
                @endphp
                <div class="stat-row">
                    <span class="stat-label">Area Detektor</span>
                    <span class="stat-value">{{ $label }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Count Rate</span>
                    <span class="stat-value danger">{{ number_format($rate, 1) }} cpm</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Dose Rate</span>
                    <span class="stat-value danger">{{ number_format($doseRate, 2) }} µSv/h</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Status Detektor</span>
                    <span class="stat-value danger">ELEVATED / WARNING</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Waktu Deteksi</span>
                    <span class="stat-value">{{ date('d-m-Y H:i:s') }} WIB</span>
                </div>
            </div>

            <div class="warning-box">
                <h4>⚠️ TINDAKAN YANG DIREKOMENDASIKAN</h4>
                <p>
                    Segera batasi durasi tinggal pekerja di dekat area detektor, pastikan perisai radiasi (shielding) terpasang dengan baik, dan lakukan inspeksi jarak jauh menggunakan peralatan pemantau.
                </p>
            </div>

            <a href="{{ route('zone', $detectorKey) }}" class="btn-action">Buka Dashboard Detektor</a>
        </div>
        <div class="footer">
            Dikirim secara otomatis oleh Sistem RadiaTrack. Tolong jangan membalas email ini.
        </div>
    </div>
</body>
</html>
