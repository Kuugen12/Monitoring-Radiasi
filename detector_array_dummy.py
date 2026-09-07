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
    import pandas as pd  # type: ignore
    HAS_PANDAS = True
except ImportError:
    HAS_PANDAS = False

try:
    import firebase_admin  # type: ignore
    from firebase_admin import credentials, db  # type: ignore
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
USE_FIREBASE = True
FIREBASE_DATABASE_URL = "https://magang-brin-27225-default-rtdb.asia-southeast1.firebasedatabase.app"
SERVICE_ACCOUNT_PATH = "serviceAccountKey.json"

HAS_FIREBASE_ADMIN_INIT = False
if os.path.exists(SERVICE_ACCOUNT_PATH) and HAS_FIREBASE_ADMIN and firebase_admin is not None and credentials is not None:
    try:
        if not firebase_admin._apps:
            cred = credentials.Certificate(SERVICE_ACCOUNT_PATH)
            firebase_admin.initialize_app(cred, {
                'databaseURL': FIREBASE_DATABASE_URL
            })
        HAS_FIREBASE_ADMIN_INIT = True
        print(f"[FIREBASE] Terhubung via Firebase Admin SDK ({SERVICE_ACCOUNT_PATH})")
    except Exception as e:
        print(f"[FIREBASE WARNING] Gagal inisialisasi Firebase Admin SDK: {e}. Menggunakan mode REST API.")
else:
    print(f"[FIREBASE] Mode REST API aktif (Menghubungkan langsung ke {FIREBASE_DATABASE_URL})")

try:
    import requests
    HAS_REQUESTS = True
except ImportError:
    HAS_REQUESTS = False

def push_to_firebase(path, payload):
    """Mengirim data ke Firebase Realtime Database via Admin SDK atau REST API."""
    if not USE_FIREBASE:
        return False
    # Opsi 1: Admin SDK jika terinisialisasi
    if HAS_FIREBASE_ADMIN_INIT and db is not None:
        try:
            db.reference(path).set(payload)
            return True
        except Exception as err:
            pass
    # Opsi 2: REST API (Universal untuk semua laptop / Raspberry Pi tanpa perlu serviceAccountKey.json)
    if HAS_REQUESTS:
        try:
            url = f"{FIREBASE_DATABASE_URL.rstrip('/')}/{path.strip('/')}.json"
            res = requests.put(url, json=payload, timeout=5)
            return res.status_code == 200
        except Exception as err:
            print(f"   [Firebase Warning] Gagal kirim ke {path}: {err}")
    return False

# Konfigurasi Database (TiDB Cloud Serverless)
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

def save_to_mysql(timestamp_str, height, d1, d2, d3, full_72, max_cps, avg_cps, object_name="Gentong", session_id=None, loop_index=1, total_loops=1, transition_delay=1.0):
    """Menyimpan record hasil scan langsung ke TiDB Cloud."""
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
            connect_timeout=10
        )
        with conn.cursor() as cursor:
            # Pastikan tabel scan_matrix ada
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS scan_matrix (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    timestamp VARCHAR(50),
                    height_level INT,
                    object_name VARCHAR(100) DEFAULT 'Gentong',
                    session_id VARCHAR(64) DEFAULT NULL,
                    loop_index INT DEFAULT 1,
                    total_loops INT DEFAULT 1,
                    transition_delay FLOAT DEFAULT 1.0,
                    xs1_data TEXT,
                    xs2_data TEXT,
                    xs3_data TEXT,
                    detector_data LONGTEXT,
                    max_cps INT,
                    avg_cps FLOAT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """)
            sql = """
                INSERT INTO scan_matrix 
                (timestamp, height_level, object_name, session_id, loop_index, total_loops, transition_delay, xs1_data, xs2_data, xs3_data, detector_data, max_cps, avg_cps) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(sql, (
                timestamp_str,
                height,
                object_name,
                session_id,
                loop_index,
                total_loops,
                transition_delay,
                json.dumps(d1),
                json.dumps(d2),
                json.dumps(d3),
                json.dumps(full_72),
                max_cps,
                avg_cps
            ))
        conn.commit()
        conn.close()
        print(f"   [TiDB Cloud] Berhasil simpan [{object_name}] Level {height} (Loop {loop_index}/{total_loops}) ke TiDB Cloud")
    except Exception as err:
        print(f"   [TiDB Cloud Warning] Gagal simpan ke TiDB ({MYSQL_HOST}): {err}")


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
            object_name TEXT DEFAULT 'Gentong',
            session_id TEXT,
            loop_index INTEGER DEFAULT 1,
            total_loops INTEGER DEFAULT 1,
            transition_delay REAL DEFAULT 1.0,
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
        # Base background radiation: 18 - 42 CPS
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
def run_scanning(
    total_height=TOTAL_HEIGHT_SCAN, 
    delay=SAMPLING_INTERVAL, 
    port="COM5", 
    baud=115200, 
    object_name="Gentong", 
    total_loops=1, 
    transition_delay=1.0
):
    conn = init_local_db()
    cursor = conn.cursor()
    
    session_id = f"SES_{datetime.datetime.now().strftime('%Y%m%d_%H%M%S')}_{random.randint(100, 999)}"
    matrix_heatmap = []
    print("=" * 78)
    print("  RADIOSCAN MATRIX - 72-CH RADIATION ARRAY DETECTOR (SIMULATION)")
    print(f"  Objek Target: {object_name} | Session ID: {session_id}")
    print(f"  Port: {port} | Baud: {baud} | Total Steps: {total_height} | Loops/Baris: {total_loops}")
    print(f"  Delay Transisi Antar Baris: {transition_delay}s | Interval Sampling: {delay}s")
    print(f"  Firebase Sync: {'AKTIF' if USE_FIREBASE else 'NON-AKTIF (Simulasi Lokal)'}")
    print("=" * 78)

    if USE_FIREBASE:
        push_to_firebase('radiation_scans/hardware_config', {
            'port': port,
            'baudrate': baud,
            'protocol': 'modbus',
            'object_name': object_name,
            'session_id': session_id,
            'total_height': total_height,
            'total_loops': total_loops,
            'transition_delay': transition_delay,
            'sampling_interval_ms': int(delay * 1000),
            'last_updated': datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        })
        print(f"[FIREBASE] Hardware config (Objek: {object_name}, Port: {port}) disinkronkan.")

    for height in range(1, total_height + 1):
        for loop_idx in range(1, total_loops + 1):
            print(f"\n>>> [TINGGI: {height}/{total_height} | LOOP: {loop_idx}/{total_loops}] Objek: '{object_name}' - Request data Modbus...")
            
            # Simulasi kemunculan anomali hotspot pada tinggi 4, 5, dan 6
            has_anomaly = (height in [4, 5, 6])
            
            # 1. Mengambil data 24-channel dari masing-masing Xiao Seed
            d1 = generate_xiao_seed_data(module_id=1, is_hotspot=has_anomaly, height=height)
            d2 = generate_xiao_seed_data(module_id=2, is_hotspot=has_anomaly, height=height)
            d3 = generate_xiao_seed_data(module_id=3, is_hotspot=has_anomaly, height=height)
            
            # 2. Merge d1, d2, d3 menjadi 72 detektor
            full_72_detector = d1 + d2 + d3
            
            # Update matrix heatmap representation (simpan iterasi terakhir per baris)
            if len(matrix_heatmap) < height:
                matrix_heatmap.append(full_72_detector)
            else:
                matrix_heatmap[height - 1] = full_72_detector
            
            timestamp_str = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            max_cps = max(full_72_detector)
            avg_cps = sum(full_72_detector) / len(full_72_detector)
            max_ch = full_72_detector.index(max_cps) + 1
            
            # Status log
            status_tag = "[ALERT HOTSPOT]" if max_cps > 100 else "[NORMAL]"
            print(f"[{timestamp_str}] Tinggi {height:2d} (Loop {loop_idx}/{total_loops}) | XS1: 24ch, XS2: 24ch, XS3: 24ch -> 72ch")
            print(f"   Status: {status_tag} | Max: {max_cps:3d} CPS (D{max_ch:02d}) | Avg: {avg_cps:.1f} CPS")
            print(f"   Sample D01-D06 : {full_72_detector[:6]}")
            print(f"   Sample D33-D38 : {full_72_detector[32:38]}")
            print(f"   Sample D67-D72 : {full_72_detector[-6:]}")

            # 3. Simpan ke Database Lokal SQLite
            cursor.execute(
                """INSERT INTO scan_matrix 
                   (timestamp, height_level, object_name, session_id, loop_index, total_loops, transition_delay, xs1_data, xs2_data, xs3_data, detector_data, max_cps, avg_cps) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)""",
                (
                    timestamp_str,
                    height,
                    object_name,
                    session_id,
                    loop_idx,
                    total_loops,
                    transition_delay,
                    json.dumps(d1),
                    json.dumps(d2),
                    json.dumps(d3),
                    json.dumps(full_72_detector),
                    max_cps,
                    avg_cps
                )
            )
            conn.commit()

            # 4. Simpan ke Database TiDB Cloud Serverless
            save_to_mysql(timestamp_str, height, d1, d2, d3, full_72_detector, max_cps, avg_cps, 
                          object_name=object_name, session_id=session_id, loop_index=loop_idx, 
                          total_loops=total_loops, transition_delay=transition_delay)

            # 5. Push ke Firebase Realtime Database
            if USE_FIREBASE:
                try:
                    # Estimasi 3D real-time
                    est_3d = estimate_3d_source_position(matrix_heatmap)
                    
                    # Push status tinggi saat ini
                    push_to_firebase('radiation_scans/latest', {
                        'timestamp': timestamp_str,
                        'object_name': object_name,
                        'session_id': session_id,
                        'current_height': height,
                        'total_height': total_height,
                        'current_loop': loop_idx,
                        'total_loops': total_loops,
                        'transition_delay': transition_delay,
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
                    push_to_firebase(f'radiation_scans/height_levels/level_{height}', {
                        'timestamp': timestamp_str,
                        'object_name': object_name,
                        'height_level': height,
                        'loop_index': loop_idx,
                        'full_72_array': full_72_detector,
                        'max_cps': max_cps,
                        'avg_cps': round(avg_cps, 2)
                    })

                    # Push keseluruhan matriks akumulasi
                    push_to_firebase('radiation_scans/matrix_data', {
                        'last_updated': timestamp_str,
                        'object_name': object_name,
                        'session_id': session_id,
                        'completed_heights': height,
                        'total_heights': total_height,
                        'matrix': matrix_heatmap
                    })
                except Exception as fb_err:
                    print(f"   [Firebase Sync Error] {fb_err}")

            # Jeda sampling waktu dalam loop
            time.sleep(delay)

        # Jeda perpindahan baris (Transition Delay) jika masih ada baris berikutnya
        if height < total_height and transition_delay > 0:
            print(f"--> Selesai Baris {height}. Transisi lift ke Baris {height + 1}... Delay: {transition_delay}s")
            time.sleep(transition_delay)

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
    print(f" SCAN ARRAY DETEKTOR UNTUK OBJEK '{object_name}' SELESAI DENGAN SUKSES!")
    print(f" 1. Database TiDB / SQLite : scan_matrix (Objek: {object_name}, Session: {session_id})")
    print(f" 2. Matrix CSV             : {CSV_EXPORT_FILE} ({total_height} Rows x {TOTAL_DETECTORS} Columns)")
    print(f" 3. Matrix DAT             : {dat_filename}")
    print(f" 4. Estimasi Sumber 3D     : X={est_final['x']}cm, Y={est_final['y']}cm, Z={est_final['z']}cm (Conf: {est_final['confidence']}%)")
    print("=" * 78)
    
    # Tampilkan preview matriks
    print("\n[Preview Matriks Heatmap (8 Detektor Pertama)]:")
    for h_idx in range(min(5, total_height)):
        row_str = " ".join([f"{val:4d}" for val in matrix_heatmap[h_idx][:8]])
        print(f" Tinggi_{h_idx+1:02d} | {row_str}")

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="RADIOSCAN MATRIX - 72-Detector Simulation Gateway")
    parser.add_argument("--object-name", "-o", type=str, default="Gentong", help="Nama/Identitas Objek yang di-scan (Default: Gentong)")
    parser.add_argument("--loops", "-l", type=int, default=1, help="Jumlah loop pemindaian per baris/blok (Default: 1)")
    parser.add_argument("--transition-delay", "-t", type=float, default=1.0, help="Lama delay perpindahan baris dalam detik (Default: 1.0)")
    parser.add_argument("--port", "-p", type=str, default="COM5", help="Nama port serial/COM hardware (Default: COM5)")
    parser.add_argument("--baud", "-b", type=int, default=115200, help="Baudrate komunikasi serial (Default: 115200)")
    parser.add_argument("--steps", "-s", type=int, default=TOTAL_HEIGHT_SCAN, help=f"Jumlah level tinggi (Default: {TOTAL_HEIGHT_SCAN})")
    parser.add_argument("--delay", "-d", type=float, default=SAMPLING_INTERVAL, help="Jeda waktu per-step dalam detik (Default: 1.0)")
    args = parser.parse_args()

    run_scanning(
        total_height=args.steps, 
        delay=args.delay, 
        port=args.port, 
        baud=args.baud,
        object_name=args.object_name,
        total_loops=args.loops,
        transition_delay=args.transition_delay
    )

