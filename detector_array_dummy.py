"""
================================================================================
RADIOSCAN MATRIX v2.0 - 72-Detector Radiation Array Scanner
Python Dummy Data Generator, SQLite Logger & Firebase Sync Gateway
================================================================================
"""

import time
import random
import datetime
import os
import sys
import json
import sqlite3
import csv

try:
    import pandas as pd
    HAS_PANDAS = True
except ImportError:
    HAS_PANDAS = False

try:
    import firebase_admin
    from firebase_admin import credentials, db
    HAS_FIREBASE_ADMIN = True
except ImportError:
    HAS_FIREBASE_ADMIN = False
    firebase_admin = None
    credentials = None
    db = None

# ==========================================
# 1. KONFIGURASI SISTEM & FIREBASE
# ==========================================
TOTAL_DETECTORS_PER_MODULE = 24  # 24 sensor per Xiao Seed module
NUM_MODULES = 3                  # XS1, XS2, XS3 -> Total 72 detector
TOTAL_DETECTORS = TOTAL_DETECTORS_PER_MODULE * NUM_MODULES
TOTAL_HEIGHT_SCAN = 10           # Jumlah level tinggi scan default (1 s/d 10)
SAMPLING_INTERVAL = 1.0          # Detik per cycle scan
DATABASE_FILE = "arraydata.db"
CSV_EXPORT_FILE = "heatmap_radiation_matrix.csv"

# Konfigurasi Firebase Realtime Database
USE_FIREBASE = False
FIREBASE_DATABASE_URL = "https://magang-brin-27225-default-rtdb.asia-southeast1.firebasedatabase.app"
SERVICE_ACCOUNT_PATH = "serviceAccountKey.json"

if os.path.exists(SERVICE_ACCOUNT_PATH) and HAS_FIREBASE_ADMIN and firebase_admin is not None and credentials is not None:
    try:
        if not firebase_admin._apps:
            cred = credentials.Certificate(SERVICE_ACCOUNT_PATH)
            firebase_admin.initialize_app(cred, {
                'databaseURL': FIREBASE_DATABASE_URL
            })
        USE_FIREBASE = True
        print(f"[FIREBASE] Berhasil terhubung menggunakan {SERVICE_ACCOUNT_PATH}")
    except Exception as e:
        print(f"[FIREBASE WARNING] Gagal inisialisasi Firebase Admin: {e}")
        USE_FIREBASE = False
elif not HAS_FIREBASE_ADMIN:
    print(f"[INFO] Modul 'firebase_admin' belum terpasang. Menjalankan mode logging database lokal & MySQL.")
else:
    print(f"[INFO] '{SERVICE_ACCOUNT_PATH}' tidak ditemukan. Jalankan mode simulasi lokal.")

# Konfigurasi MySQL (TiDB Cloud Serverless)
USE_MYSQL = True
MYSQL_HOST = os.environ.get("MYSQL_HOST", "gateway01.ap-southeast-1.prod.aws.tidbcloud.com")
MYSQL_PORT = int(os.environ.get("MYSQL_PORT", 4000))
MYSQL_USER = os.environ.get("MYSQL_USER", "4FxUazxpWaqzAS1.root")
MYSQL_PASSWORD = os.environ.get("MYSQL_PASSWORD", "Dq37CUJZIRiMM4QG")
MYSQL_DB = os.environ.get("MYSQL_DB", "magang")
MYSQL_USE_SSL = True

try:
    import pymysql
    HAS_PYMYSQL = True
except ImportError:
    HAS_PYMYSQL = False

def save_to_mysql(timestamp_str, height, d1, d2, d3, full_72, max_cps, avg_cps):
    """Menyimpan record hasil scan langsung ke MySQL (TiDB Cloud / phpMyAdmin)."""
    if not HAS_PYMYSQL or not USE_MYSQL:
        return
    try:
        ssl_config = {'ssl_mode': 'REQUIRED'} if MYSQL_USE_SSL else None
        conn = pymysql.connect(
            host=MYSQL_HOST,
            port=MYSQL_PORT,
            user=MYSQL_USER,
            password=MYSQL_PASSWORD,
            database=MYSQL_DB,
            charset='utf8mb4',
            ssl=ssl_config,
            connect_timeout=5
        )
        with conn.cursor() as cursor:
            sql = """
                INSERT INTO scan_matrix 
                (timestamp, height_level, xs1_data, xs2_data, xs3_data, detector_data, max_cps, avg_cps) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(sql, (
                timestamp_str,
                height,
                json.dumps(d1),
                json.dumps(d2),
                json.dumps(d3),
                json.dumps(full_72),
                max_cps,
                avg_cps
            ))
        conn.commit()
        conn.close()
        print(f"   [MySQL phpMyAdmin] Berhasil simpan data level {height} ke {MYSQL_HOST}/{MYSQL_DB}")
    except Exception as err:
        print(f"   [MySQL phpMyAdmin Warning] Gagal simpan ke MySQL ({MYSQL_HOST}): {err}")


# ==========================================
# 2. INISIALISASI DATABASE LOKAL (SQLite & MySQL)
# ==========================================
def init_local_db():
    """Membuat database SQLite dan tabel scan_matrix jika belum ada."""
    conn = sqlite3.connect(DATABASE_FILE)
    cursor = conn.cursor()
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS scan_matrix (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT,
            height_level INTEGER,
            xs1_data TEXT,
            xs2_data TEXT,
            xs3_data TEXT,
            detector_data TEXT,
            max_cps INTEGER,
            avg_cps REAL
        )
    """)
    conn.commit()
    return conn

# ==========================================
# 3. FUNGSI GENERATOR DATA DUMMY RS485
# ==========================================
def generate_xiao_seed_data(module_id, is_hotspot=False, height=1):
    """
    Simulasi data dari RS485 Xiao Seed (24 nilai cacah per detik).
    Module 1: D01 - D24 (Address 0x01)
    Module 2: D25 - D48 (Address 0x02) - Area Hotspot Sim
    Module 3: D49 - D72 (Address 0x03)
    """
    readings = []
    
    for ch in range(TOTAL_DETECTORS_PER_MODULE):
        # Base background radiation: 15 - 45 CPS
        base_cps = random.randint(18, 42) + random.randint(-3, 3)
        
        # Simulasi titik anomali radiasi (Hotspot Gaussian di sekitar D34-D42 pada height 4-6)
        if is_hotspot and module_id == 2:
            # Channel 10-18 pada XS2 (Global D34 - D42)
            dist_from_center = abs(ch - 14)
            if dist_from_center <= 5:
                # Hotspot peak 180 - 320 CPS
                boost = int(random.randint(180, 320) * max(0.2, (1.0 - (dist_from_center / 6.0))))
                base_cps += boost
            else:
                base_cps += random.randint(25, 60)
        elif is_hotspot and module_id in [1, 3] and (ch > 18 if module_id == 1 else ch < 6):
            # Radiation scatter effect ke tetangga
            base_cps += random.randint(10, 45)
            
        readings.append(max(0, base_cps))
        
    return readings

# ==========================================
# 4. ESTIMASI POSISI SUMBER (3D BAYESIAN LOCALIZATION)
# ==========================================
def estimate_3d_source_position(matrix_heatmap):
    """
    Menghitung estimasi posisi 3D sumber radiasi (X, Y, Z cm)
    berdasarkan centroid intensitas radiasi 72 channel x N tinggi.
    """
    if not matrix_heatmap:
        return {'x': 400.0, 'y': 0.0, 'z': 200.0, 'confidence': 50.0}
    
    total_weight = 0
    weighted_x = 0
    weighted_z = 0
    max_val = 0
    
    for h_idx, row in enumerate(matrix_heatmap):
        height_cm = (h_idx + 1) * 35.0  # Misal 35cm per height step
        for ch_idx, cps in enumerate(row):
            if cps > 50:  # Threshold di atas background
                weight = (cps - 50) ** 1.5
                ch_x_cm = (ch_idx / 72.0) * 800.0  # Linear array 800cm
                weighted_x += ch_x_cm * weight
                weighted_z += height_cm * weight
                total_weight += weight
            if cps > max_val:
                max_val = cps

    if total_weight > 0:
        est_x = round(weighted_x / total_weight, 1)
        est_z = round(weighted_z / total_weight, 1)
        est_y = round(random.uniform(-35.0, -25.0), 1)  # Depth offset
        confidence = min(98.8, round(70.0 + (max_val / 350.0) * 28.0, 1))
    else:
        est_x = 412.5
        est_y = -32.7
        est_z = 186.4
        confidence = 65.0
        
    return {'x': est_x, 'y': est_y, 'z': est_z, 'confidence': confidence}

# ==========================================
# 5. MAIN SCANNING LOOP
# ==========================================
def run_scanning(total_height=TOTAL_HEIGHT_SCAN, delay=SAMPLING_INTERVAL):
    conn = init_local_db()
    cursor = conn.cursor()
    
    matrix_heatmap = []
    print("=" * 78)
    print("  RADIOSCAN MATRIX v2.0 - 72-CH RADIATION ARRAY DETECTOR")
    print(f"  Total Height Steps: {total_height} | Modul: 3x Xiao Seed (RS485) | Detektor: 72")
    print(f"  Firebase Sync: {'AKTIF' if USE_FIREBASE else 'NON-AKTIF (Simulasi Lokal)'}")
    print("=" * 78)

    for height in range(1, total_height + 1):
        print(f"\n>>> [TINGGI SCAN: {height}/{total_height}] Request data Modbus RS485 (XS1, XS2, XS3)...")
        
        # Simulasi kemunculan anomali hotspot pada tinggi 4, 5, dan 6
        has_anomaly = (height in [4, 5, 6])
        
        # 1. Mengambil data 24-channel dari masing-masing Xiao Seed
        d1 = generate_xiao_seed_data(module_id=1, is_hotspot=has_anomaly, height=height)
        d2 = generate_xiao_seed_data(module_id=2, is_hotspot=has_anomaly, height=height)
        d3 = generate_xiao_seed_data(module_id=3, is_hotspot=has_anomaly, height=height)
        
        # 2. Merge d1, d2, d3 menjadi 72 detektor
        full_72_detector = d1 + d2 + d3
        matrix_heatmap.append(full_72_detector)
        
        timestamp_str = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        max_cps = max(full_72_detector)
        avg_cps = sum(full_72_detector) / len(full_72_detector)
        max_ch = full_72_detector.index(max_cps) + 1
        
        # Status log
        status_tag = "[ALERT HOTSPOT]" if max_cps > 100 else "[NORMAL]"
        print(f"[{timestamp_str}] Tinggi {height:2d} | XS1: {len(d1)}ch, XS2: {len(d2)}ch, XS3: {len(d3)}ch -> Total 72ch")
        print(f"   Status: {status_tag} | Max: {max_cps:3d} CPS (D{max_ch:02d}) | Avg: {avg_cps:.1f} CPS")
        print(f"   Sample D01-D06 : {full_72_detector[:6]}")
        print(f"   Sample D33-D38 : {full_72_detector[32:38]}")
        print(f"   Sample D67-D72 : {full_72_detector[-6:]}")

        # 3. Simpan ke Database Lokal SQLite
        cursor.execute(
            """INSERT INTO scan_matrix 
               (timestamp, height_level, xs1_data, xs2_data, xs3_data, detector_data, max_cps, avg_cps) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
            (
                timestamp_str,
                height,
                json.dumps(d1),
                json.dumps(d2),
                json.dumps(d3),
                json.dumps(full_72_detector),
                max_cps,
                avg_cps
            )
        )
        conn.commit()

        # 4. Simpan ke Database MySQL (phpMyAdmin di PC jika aktif)
        save_to_mysql(timestamp_str, height, d1, d2, d3, full_72_detector, max_cps, avg_cps)

        # 5. Push ke Firebase Realtime Database
        if USE_FIREBASE:
            try:
                # Estimasi 3D real-time
                est_3d = estimate_3d_source_position(matrix_heatmap)
                
                # Push status tinggi saat ini
                ref_latest = db.reference('radiation_scans/latest')
                ref_latest.set({
                    'timestamp': timestamp_str,
                    'current_height': height,
                    'total_height': total_height,
                    'xs1_data': d1,
                    'xs2_data': d2,
                    'xs3_data': d3,
                    'full_72_array': full_72_detector,
                    'max_cps': max_cps,
                    'max_channel': f"D{max_ch:02d}",
                    'avg_cps': round(avg_cps, 2),
                    'status': 'alert' if max_cps > 100 else 'safe',
                    'estimated_source_3d': est_3d
                })

                # Push arsip per level tinggi
                ref_height = db.reference(f'radiation_scans/height_levels/level_{height}')
                ref_height.set({
                    'timestamp': timestamp_str,
                    'height_level': height,
                    'full_72_array': full_72_detector,
                    'max_cps': max_cps,
                    'avg_cps': round(avg_cps, 2)
                })

                # Push keseluruhan matriks akumulasi
                ref_matrix = db.reference('radiation_scans/matrix_data')
                ref_matrix.set({
                    'last_updated': timestamp_str,
                    'completed_heights': height,
                    'total_heights': total_height,
                    'matrix': matrix_heatmap
                })
                print(f"   [Firebase Sync] OK -> level_{height} & matrix_data synced.")
            except Exception as fb_err:
                print(f"   [Firebase Sync Error] {fb_err}")

        # Jeda waktu antar height step
        time.sleep(delay)

    # ==========================================
    # 6. EXPORT KE FILE CSV & DAT (MATRIX FORMAT)
    # ==========================================
    columns = [f"Det_{i+1:02d}" for i in range(TOTAL_DETECTORS)]
    
    if HAS_PANDAS:
        df_matrix = pd.DataFrame(matrix_heatmap, columns=columns)
        df_matrix.index = [f"Tinggi_{h}" for h in range(1, total_height + 1)]
        df_matrix.to_csv(CSV_EXPORT_FILE, index=True)
        dat_filename = "heatmap_radiation_matrix.dat"
        df_matrix.to_csv(dat_filename, sep=" ", index=True)
    else:
        # Fallback menggunakan library standar csv
        with open(CSV_EXPORT_FILE, mode='w', newline='') as f_csv:
            writer = csv.writer(f_csv)
            writer.writerow([''] + columns)
            for h_idx, row in enumerate(matrix_heatmap):
                writer.writerow([f"Tinggi_{h_idx + 1}"] + row)
                
        dat_filename = "heatmap_radiation_matrix.dat"
        with open(dat_filename, mode='w') as f_dat:
            f_dat.write("Height " + " ".join(columns) + "\n")
            for h_idx, row in enumerate(matrix_heatmap):
                f_dat.write(f"Tinggi_{h_idx + 1} " + " ".join(map(str, row)) + "\n")
    
    # Estimasi posisi akhir
    est_final = estimate_3d_source_position(matrix_heatmap)

    print("\n" + "=" * 78)
    print(" SCAN ARRAY DETEKTOR SELESAI DENGAN SUKSES!")
    print(f" 1. Database SQLite : {DATABASE_FILE} (Tabel: scan_matrix, Total Records: {total_height})")
    print(f" 2. Matrix CSV      : {CSV_EXPORT_FILE} ({total_height} Rows x {TOTAL_DETECTORS} Columns)")
    print(f" 3. Matrix DAT      : {dat_filename}")
    print(f" 4. Estimasi Sumber : X={est_final['x']}cm, Y={est_final['y']}cm, Z={est_final['z']}cm (Conf: {est_final['confidence']}%)")
    print("=" * 78)
    
    # Tampilkan preview matriks
    print("\n[Preview Matriks Heatmap (8 Detektor Pertama)]:")
    for h_idx in range(min(5, total_height)):
        row_str = " ".join([f"{val:4d}" for val in matrix_heatmap[h_idx][:8]])
        print(f" Tinggi_{h_idx+1:02d} | {row_str}")

if __name__ == "__main__":
    # Dukungan argumen command line opsional
    total_steps = int(sys.argv[1]) if len(sys.argv) > 1 else TOTAL_HEIGHT_SCAN
    step_delay = float(sys.argv[2]) if len(sys.argv) > 2 else SAMPLING_INTERVAL
    run_scanning(total_height=total_steps, delay=step_delay)
