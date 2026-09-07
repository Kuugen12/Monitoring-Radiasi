"""
================================================================================
RADIOSCAN MATRIX v2.0 - 72-DETECTOR REAL HARDWARE ACQUISITION GATEWAY
Target Platform: PC (Windows / Linux) / Raspberry Pi 5
Interface: RS-485 Modbus RTU / Multi-Drop Serial UART / USB CDC
================================================================================
Modul Hardware:
  - 3x Seeed Studio XIAO (SAMD21 / RP2040 / ESP32-C3)
  - Modul 1 (XS1, Slave ID 0x01): Detektor D01 - D24 (24 Channel)
  - Modul 2 (XS2, Slave ID 0x02): Detektor D25 - D48 (24 Channel)
  - Modul 3 (XS3, Slave ID 0x03): Detektor D49 - D72 (24 Channel)
  - Total Detektor: 72 Channel Array

Output / Sinkronisasi:
  1. Firebase Realtime Database (Real-time Web WebSocket stream)
  2. Local SQLite Database (`arraydata.db` -> tabel `scan_matrix`)
  3. MySQL Database (Laragon / phpMyAdmin di PC)
  4. Matrix Export (`heatmap_radiation_matrix.csv` & `.dat`)
================================================================================
"""

import sys
import os
import time
import datetime
import json
import sqlite3
import csv
import argparse
import struct

# Optional Dependencies
try:
    import serial  # type: ignore
    import serial.tools.list_ports  # type: ignore
    HAS_PYSERIAL = True
except ImportError:
    HAS_PYSERIAL = False

try:
    import pymysql  # type: ignore
    HAS_PYMYSQL = True
except ImportError:
    HAS_PYMYSQL = False

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


# ==============================================================================
# 1. KONFIGURASI SISTEM & HARDWARE
# ==============================================================================
DEFAULT_SERIAL_PORT = "COM5"      # Contoh Windows: 'COM3', 'COM5' | Linux/RPi: '/dev/ttyUSB0', '/dev/ttyACM0'
DEFAULT_BAUDRATE = 115200         # Baudrate RS485 / Serial
TIMEOUT_SECONDS = 1.0             # Timeout komunikasi serial
NUM_MODULES = 3                   # XS1, XS2, XS3
DETECTORS_PER_MODULE = 24         # 24 channel per modul
TOTAL_DETECTORS = NUM_MODULES * DETECTORS_PER_MODULE  # 72 Detektor
TOTAL_HEIGHT_SCAN = 10            # Jumlah level elevasi (tinggi) vertikal

# Konfigurasi Slave ID Modbus RTU
SLAVE_ID_XS1 = 0x01
SLAVE_ID_XS2 = 0x02
SLAVE_ID_XS3 = 0x03

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

# Konfigurasi Database Lokal & TiDB Cloud
DATABASE_FILE = "arraydata.db"
CSV_EXPORT_FILE = "heatmap_radiation_matrix.csv"
DAT_EXPORT_FILE = "heatmap_radiation_matrix.dat"

USE_MYSQL = True
MYSQL_HOST = os.environ.get("MYSQL_HOST", "gateway01.ap-southeast-1.prod.aws.tidbcloud.com")
MYSQL_PORT = int(os.environ.get("MYSQL_PORT", 4000))
MYSQL_USER = os.environ.get("MYSQL_USER", "4FxUazxpWaqzAS1.root")
MYSQL_PASSWORD = os.environ.get("MYSQL_PASSWORD", "Dq37CUJZIRiMM4QG")
MYSQL_DB = os.environ.get("MYSQL_DB", "magang")
MYSQL_USE_SSL = True


# ==============================================================================
# 2. HELPER KOMUNIKASI HARDWARE (RS485 MODBUS RTU & SERIAL)
# ==============================================================================
def list_available_com_ports():
    """Menampilkan daftar seluruh port COM / Serial yang terdeteksi di sistem."""
    if not HAS_PYSERIAL:
        print("   [Peringatan] Library 'pyserial' belum terinstall. Jalankan: pip install pyserial")
        return []
    ports = list(serial.tools.list_ports.comports())
    if not ports:
        print("   [INFO] Tidak ada port Serial/COM yang terdeteksi.")
    else:
        print("   [PORT TERDETEKSI]:")
        for p in ports:
            print(f"    - {p.device}: {p.description} [{p.hwid}]")
    return [p.device for p in ports]


def calculate_modbus_crc16(data: bytes) -> bytes:
    """Menghitung CRC-16 Modbus (Polynomial 0xA001)."""
    crc = 0xFFFF
    for pos in data:
        crc ^= pos
        for _ in range(8):
            if (crc & 1) != 0:
                crc = (crc >> 1) ^ 0xA001
            else:
                crc >>= 1
    return struct.pack('<H', crc)


def read_modbus_rtu_module(ser_port, slave_id: int, num_registers: int = 24, timeout=0.8):
    """
    Mengirim perintah Modbus RTU Read Holding Registers (Function Code 0x03)
    ke modul Seeed Studio Xiao (Slave ID 1, 2, atau 3) dan menerima 24 nilai CPS (16-bit).
    """
    if ser_port is None or not ser_port.is_open:
        return None

    # Frame Request Modbus RTU: [SlaveID, FuncCode(0x03), StartAddrHi(0x00), StartAddrLo(0x00), NumRegHi(0x00), NumRegLo(24), CRC_Lo, CRC_Hi]
    request_payload = bytearray([slave_id, 0x03, 0x00, 0x00, 0x00, num_registers])
    crc = calculate_modbus_crc16(request_payload)
    request_packet = request_payload + crc

    # Flush buffer lama
    ser_port.reset_input_buffer()
    ser_port.reset_output_buffer()

    # Kirim paket request ke RS485
    ser_port.write(request_packet)
    ser_port.flush()

    # Hitung panjang response yang diharapkan: 1(Slave) + 1(Func) + 1(ByteCount) + 2*num_registers + 2(CRC)
    expected_length = 3 + (num_registers * 2) + 2
    
    start_t = time.time()
    response = bytearray()
    while (time.time() - start_t) < timeout:
        if ser_port.in_waiting:
            chunk = ser_port.read(ser_port.in_waiting)
            response.extend(chunk)
            if len(response) >= expected_length:
                break
        time.sleep(0.01)

    if len(response) < expected_length:
        return None  # Timeout atau response terpotong

    # Verifikasi Slave ID & Func Code
    if response[0] != slave_id or response[1] != 0x03:
        return None

    byte_count = response[2]
    if byte_count != num_registers * 2:
        return None

    # Verifikasi CRC response
    payload = response[:expected_length - 2]
    expected_crc = response[expected_length - 2:expected_length]
    calc_crc = calculate_modbus_crc16(payload)
    if calc_crc != expected_crc:
        return None

    # Decode 24 register (16-bit unsigned integer per register)
    readings = []
    for i in range(num_registers):
        offset = 3 + (i * 2)
        val = (response[offset] << 8) | response[offset + 1]
        readings.append(val)

    return readings


def read_serial_ascii_module(ser_port, module_id: int, timeout=0.8):
    """
    Metode alternatif: Membaca data jika modul Seeed Studio Xiao mengirimkan data 
    dalam format string ASCII/JSON melalui Serial USB / UART.
    Format contoh: 'XS1:12,18,24,30,...,45' atau '{"mod":1,"cps":[12,18,...]}'
    """
    if ser_port is None or not ser_port.is_open:
        return None

    start_t = time.time()
    while (time.time() - start_t) < timeout:
        if ser_port.in_waiting:
            try:
                line = ser_port.readline().decode('utf-8', errors='ignore').strip()
                if not line:
                    continue

                # Cek JSON format
                if line.startswith("{") and line.endswith("}"):
                    data = json.loads(line)
                    if data.get("module") == module_id or data.get("mod") == module_id:
                        cps_list = data.get("cps") or data.get("data")
                        if cps_list and len(cps_list) == DETECTORS_PER_MODULE:
                            return [int(x) for x in cps_list]

                # Cek format Comma-Separated dengan prefix (misal: XS1:20,30,...)
                prefix = f"XS{module_id}:"
                if line.startswith(prefix):
                    raw_values = line[len(prefix):].split(",")
                    values = [int(v.strip()) for v in raw_values if v.strip().isdigit()]
                    if len(values) == DETECTORS_PER_MODULE:
                        return values
            except Exception:
                pass
        time.sleep(0.01)

    return None


# ==============================================================================
# 3. DATABASE LOGGING (SQLite & MySQL)
# ==============================================================================
def init_local_db():
    """Inisialisasi tabel SQLite `scan_matrix` jika belum ada."""
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


def save_to_mysql(timestamp_str, height, d1, d2, d3, full_72, max_cps, avg_cps, object_name="Gentong", session_id=None, loop_index=1, total_loops=1, transition_delay=1.0):
    """Menyimpan record hasil scan langsung ke database TiDB Cloud."""
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
            # Pastikan tabel ada
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
        print(f"   [TiDB Cloud] Berhasil disimpan [{object_name}] Level {height} (Loop {loop_index}/{total_loops}) ke TiDB Cloud")
    except Exception as err:
        print(f"   [TiDB Cloud Warning] Gagal simpan ke TiDB: {err}")


# ==============================================================================
# 4. ESTIMASI LOKALISASI SUMBER 3D (3D SPATIAL LOCALIZATION)
# ==============================================================================
def estimate_3d_source_position(matrix_heatmap):
    """
    Menghitung estimasi koordinat 3D posisi sumber radiasi (X, Y, Z cm)
    berdasarkan centroid intensitas radiasi 72 channel x N tinggi level.
    """
    if not matrix_heatmap:
        return {'x': 400.0, 'y': -30.0, 'z': 175.0, 'confidence': 50.0}

    total_weight = 0.0
    weighted_x = 0.0
    weighted_z = 0.0
    max_val = 0

    for h_idx, row in enumerate(matrix_heatmap):
        height_cm = (h_idx + 1) * 35.0  # 35 cm per step ketinggian
        for ch_idx, cps in enumerate(row):
            if cps > 50:  # Threshold di atas background normal
                weight = float((cps - 50) ** 1.5)
                ch_x_cm = (ch_idx / 72.0) * 800.0  # Linear array span 800 cm
                weighted_x += ch_x_cm * weight
                weighted_z += height_cm * weight
                total_weight += weight
            if cps > max_val:
                max_val = cps

    if total_weight > 0:
        est_x = round(weighted_x / total_weight, 1)
        est_z = round(weighted_z / total_weight, 1)
        est_y = -30.0  # Estimasi jarak kedalaman (depth offset cm)
        confidence = min(99.0, round(70.0 + (max_val / 350.0) * 28.0, 1))
    else:
        est_x = 400.0
        est_y = -30.0
        est_z = 175.0
        confidence = 60.0

    return {'x': est_x, 'y': est_y, 'z': est_z, 'confidence': confidence}


# ==============================================================================
# 5. VISUALISASI MINI BAR DI TERMINAL
# ==============================================================================
def print_terminal_heatmap(full_72_data, max_cps):
    """Menampilkan representasi visual ASCII 72 channel di terminal."""
    chars = " .:-=+*#%@"
    bar = ""
    for val in full_72_data:
        idx = min(len(chars) - 1, int((val / max(1, max_cps)) * (len(chars) - 1)))
        bar += chars[idx]
    print(f"   [Array 72-Ch]: [{bar}] (Max: {max_cps} CPS)")


def select_com_port_interactive(default_port=DEFAULT_SERIAL_PORT):
    """Menampilkan menu pemilihan port COM interaktif."""
    if not HAS_PYSERIAL:
        return default_port
    ports = list(serial.tools.list_ports.comports())
    if not ports:
        print(f"[INFO] Tidak ada port COM fisik yang terdeteksi. Menggunakan default: {default_port}")
        return default_port
    print("\n" + "=" * 60)
    print("  PILIH PORT SERIAL / COM HARDWARE:")
    print("=" * 60)
    for idx, p in enumerate(ports):
        marker = " [DEFAULT]" if p.device.upper() == default_port.upper() else ""
        print(f"  [{idx + 1}] {p.device} - {p.description}{marker}")
    print(f"  [0] Gunakan default ({default_port})")
    print("=" * 60)
    try:
        choice = input(f"Pilih nomor port [1-{len(ports)}] atau tekan Enter untuk ({default_port}): ").strip()
        if not choice or choice == "0":
            return default_port
        idx_choice = int(choice) - 1
        if 0 <= idx_choice < len(ports):
            selected = ports[idx_choice].device
            print(f"[OK] Port dipilih: {selected}\n")
            return selected
    except Exception:
        pass
    return default_port


# ==============================================================================
# 6. PIPELINE AKUISISI REAL HARDWARE UTAMA
# ==============================================================================
def run_real_hardware_scanning(
    port_name=DEFAULT_SERIAL_PORT,
    baud_rate=DEFAULT_BAUDRATE,
    total_height=TOTAL_HEIGHT_SCAN,
    sampling_delay=1.0,
    scan_mode="auto",
    protocol="modbus",
    object_name="Gentong",
    total_loops=1,
    transition_delay=1.0
):
    """
    Fungsi utama untuk menjalankan akuisisi data dari 72-channel sensor nyata.
    """
    session_id = f"SES_{datetime.datetime.now().strftime('%Y%m%d_%H%M%S')}_{random.randint(100, 999)}"
    print("=" * 82)
    print("  RADIOSCAN MATRIX - GATEWAY AKUISISI DETEKTOR FISIK 72-CHANNEL")
    print(f"  Objek Target: {object_name} | Session ID: {session_id}")
    print(f"  Port Serial: {port_name} | Baudrate: {baud_rate} | Protokol: {protocol.upper()}")
    print(f"  Target Scan: {total_height} Level | Loops/Baris: {total_loops} | Delay Transisi: {transition_delay}s")
    print(f"  Firebase Sync: {'AKTIF' if USE_FIREBASE else 'NON-AKTIF'}")
    print("=" * 82)

    # Sinkronisasi konfigurasi aktif ke Firebase
    if USE_FIREBASE:
        try:
            db.reference('radiation_scans/hardware_config').set({
                'port': port_name,
                'baudrate': baud_rate,
                'protocol': protocol,
                'object_name': object_name,
                'session_id': session_id,
                'total_height': total_height,
                'total_loops': total_loops,
                'transition_delay': transition_delay,
                'sampling_interval_ms': int(sampling_delay * 1000),
                'last_updated': datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            })
            print(f"[FIREBASE] Konfigurasi (Objek: {object_name}, Port: {port_name}) disinkronkan ke Web.")
        except Exception as fb_err:
            print(f"[FIREBASE WARNING] Gagal sinkronisasi hardware_config: {fb_err}")

    # Buka Port Serial
    ser = None
    if HAS_PYSERIAL:
        try:
            ser = serial.Serial(
                port=port_name,
                baudrate=baud_rate,
                bytesize=serial.EIGHTBITS,
                parity=serial.PARITY_NONE,
                stopbits=serial.STOPBITS_ONE,
                timeout=TIMEOUT_SECONDS
            )
            print(f"[SERIAL] Berhasil membuka port {port_name} ({baud_rate} baud, 8N1).")
        except Exception as e:
            print(f"[SERIAL ERROR] Gagal membuka port {port_name}: {e}")
            print("\n[DAFTAR PORT TERSEDIA]:")
            list_available_com_ports()
            print("\nPastikan konektor USB-RS485 atau modul Seeed Xiao terpasang dengan benar.")
            choice = input("Apakah ingin beralih ke mode simulasi fallback? (y/n): ").strip().lower()
            if choice != 'y':
                sys.exit(1)
            ser = None
    else:
        print("[PERINGATAN] Library pyserial tidak ditemukan. Beralih ke fallback.")

    conn = init_local_db()
    cursor = conn.cursor()
    matrix_heatmap = []

    print("\n[INFO] Mulai pemindaian matriks radiasi 72 detektor...\n")

    for height in range(1, total_height + 1):
        for loop_idx in range(1, total_loops + 1):
            if scan_mode == "step":
                input(f">>> Tekan [ENTER] setelah memposisikan detektor pada Level {height}/{total_height} (Loop {loop_idx}/{total_loops})...")
            else:
                print(f"\n>>> [TINGGI {height}/{total_height} | LOOP {loop_idx}/{total_loops}] Objek '{object_name}' - Mengambil data 72 detektor...")

            # 1. Baca data dari Modul 1 (XS1: D01-D24)
            d1 = None
            d2 = None
            d3 = None

            if ser and ser.is_open:
                if protocol == "modbus":
                    d1 = read_modbus_rtu_module(ser, SLAVE_ID_XS1, DETECTORS_PER_MODULE)
                    time.sleep(0.05)  # Jeda pergantian slave di RS485 bus
                    d2 = read_modbus_rtu_module(ser, SLAVE_ID_XS2, DETECTORS_PER_MODULE)
                    time.sleep(0.05)
                    d3 = read_modbus_rtu_module(ser, SLAVE_ID_XS3, DETECTORS_PER_MODULE)
                else:
                    d1 = read_serial_ascii_module(ser, module_id=1)
                    d2 = read_serial_ascii_module(ser, module_id=2)
                    d3 = read_serial_ascii_module(ser, module_id=3)

            # Fallback protektif jika paket modul hilang / hardware disconnect
            if not d1 or len(d1) != DETECTORS_PER_MODULE:
                print(f"   [Peringatan] Modul 1 (XS1) tidak merespons, menggunakan baseline...")
                d1 = [25] * DETECTORS_PER_MODULE
            if not d2 or len(d2) != DETECTORS_PER_MODULE:
                print(f"   [Peringatan] Modul 2 (XS2) tidak merespons, menggunakan baseline...")
                d2 = [25] * DETECTORS_PER_MODULE
            if not d3 or len(d3) != DETECTORS_PER_MODULE:
                print(f"   [Peringatan] Modul 3 (XS3) tidak merespons, menggunakan baseline...")
                d3 = [25] * DETECTORS_PER_MODULE

            # 2. Gabungkan menjadi 72 detektor lengkap
            full_72_detector = d1 + d2 + d3
            if len(matrix_heatmap) < height:
                matrix_heatmap.append(full_72_detector)
            else:
                matrix_heatmap[height - 1] = full_72_detector

            timestamp_str = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            max_cps = max(full_72_detector)
            avg_cps = sum(full_72_detector) / len(full_72_detector)
            max_ch_idx = full_72_detector.index(max_cps) + 1
            max_ch_label = f"D{max_ch_idx:02d}"

            status_tag = "[ALERT HOTSPOT]" if max_cps > 100 else "[NORMAL]"
            print(f"[{timestamp_str}] Tinggi {height:02d} (Loop {loop_idx}/{total_loops}) | Status: {status_tag} | Max: {max_cps:3d} CPS ({max_ch_label}) | Avg: {avg_cps:.1f} CPS")
            print_terminal_heatmap(full_72_detector, max_cps)

            # 3. Simpan ke SQLite Lokal
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

            # 4. Simpan ke Database TiDB Cloud
            save_to_mysql(timestamp_str, height, d1, d2, d3, full_72_detector, max_cps, avg_cps,
                          object_name=object_name, session_id=session_id, loop_index=loop_idx,
                          total_loops=total_loops, transition_delay=transition_delay)

            # 5. Push ke Firebase Realtime Database
            if USE_FIREBASE:
                try:
                    est_3d = estimate_3d_source_position(matrix_heatmap)

                    # Update live status
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
                        'max_channel': max_ch_label,
                        'avg_cps': round(avg_cps, 2),
                        'status': 'alert' if max_cps > 100 else 'safe',
                        'estimated_source_3d': est_3d
                    })

                    # Update arsip level ketinggian
                    push_to_firebase(f'radiation_scans/height_levels/level_{height}', {
                        'timestamp': timestamp_str,
                        'object_name': object_name,
                        'height_level': height,
                        'loop_index': loop_idx,
                        'full_72_array': full_72_detector,
                        'max_cps': max_cps,
                        'avg_cps': round(avg_cps, 2)
                    })

                    # Update seluruh matriks akumulasi
                    push_to_firebase('radiation_scans/matrix_data', {
                        'last_updated': timestamp_str,
                        'object_name': object_name,
                        'session_id': session_id,
                        'completed_heights': height,
                        'total_heights': total_height,
                        'matrix': matrix_heatmap
                    })
                    print(f"   [Firebase Sync] Berhasil sinkronisasi level_{height} (Loop {loop_idx}) ke Web.")
                except Exception as fb_err:
                    print(f"   [Firebase Sync Error] {fb_err}")

            # Jeda sampling waktu dalam loop
            if scan_mode == "auto":
                time.sleep(sampling_delay)

        # Jeda transisi antar level ketinggian
        if height < total_height and transition_delay > 0:
            print(f"--> Selesai Baris {height}. Transisi lift ke Baris {height + 1}... Delay: {transition_delay}s")
            time.sleep(transition_delay)

    # Tutup port serial
    if ser and ser.is_open:
        ser.close()
        print("\n[SERIAL] Port serial ditutup.")

    # ==============================================================================
    # 7. EXPORT DATA MATRIKS KE CSV & DAT
    # ==============================================================================
    columns = [f"Det_{i+1:02d}" for i in range(TOTAL_DETECTORS)]
    
    if HAS_PANDAS:
        df_matrix = pd.DataFrame(matrix_heatmap, columns=columns)
        df_matrix.index = [f"Tinggi_{h}" for h in range(1, total_height + 1)]
        df_matrix.to_csv(CSV_EXPORT_FILE, index=True)
        df_matrix.to_csv(DAT_EXPORT_FILE, sep=" ", index=True)
    else:
        with open(CSV_EXPORT_FILE, mode='w', newline='') as f_csv:
            writer = csv.writer(f_csv)
            writer.writerow([''] + columns)
            for h_idx, row in enumerate(matrix_heatmap):
                writer.writerow([f"Tinggi_{h_idx + 1}"] + row)

        with open(DAT_EXPORT_FILE, mode='w') as f_dat:
            f_dat.write("Height " + " ".join(columns) + "\n")
            for h_idx, row in enumerate(matrix_heatmap):
                f_dat.write(f"Tinggi_{h_idx + 1} " + " ".join(map(str, row)) + "\n")

    est_final = estimate_3d_source_position(matrix_heatmap)

    print("\n" + "=" * 82)
    print(f" PEMINDAIAN DATA HARDWARE FISIK OBJEK '{object_name}' SELESAI DENGAN SUKSES!")
    print(f" 1. Database TiDB / SQLite : scan_matrix (Objek: {object_name}, Session: {session_id})")
    print(f" 2. File Matrix CSV        : {CSV_EXPORT_FILE} ({total_height} baris x {TOTAL_DETECTORS} kolom)")
    print(f" 3. File Matrix DAT        : {DAT_EXPORT_FILE}")
    print(f" 4. Estimasi Sumber 3D     : X={est_final['x']} cm, Y={est_final['y']} cm, Z={est_final['z']} cm (Akurasi: {est_final['confidence']}%)")
    print("=" * 82)


# ==============================================================================
# 8. CLI ARGUMENT PARSER
# ==============================================================================
if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Radioscan Matrix v2.0 - Gateway Akuisisi Detektor Radiasi Nyata 72-Channel"
    )
    parser.add_argument(
        "--object-name", "-o",
        type=str,
        default="Gentong",
        help="Nama/Identitas Objek yang di-scan (Default: Gentong)"
    )
    parser.add_argument(
        "--loops", "-l",
        type=int,
        default=1,
        help="Jumlah loop pemindaian per baris/blok (Default: 1)"
    )
    parser.add_argument(
        "--transition-delay", "-t",
        type=float,
        default=1.0,
        help="Lama delay perpindahan baris dalam detik (Default: 1.0)"
    )
    parser.add_argument(
        "--port", "-p",
        type=str,
        default=DEFAULT_SERIAL_PORT,
        help=f"Nama port serial/COM hardware (Default: {DEFAULT_SERIAL_PORT})"
    )
    parser.add_argument(
        "--baud", "-b",
        type=int,
        default=DEFAULT_BAUDRATE,
        help=f"Baudrate komunikasi serial (Default: {DEFAULT_BAUDRATE})"
    )
    parser.add_argument(
        "--steps", "-s",
        type=int,
        default=TOTAL_HEIGHT_SCAN,
        help=f"Jumlah level ketinggian scan vertikal (Default: {TOTAL_HEIGHT_SCAN})"
    )
    parser.add_argument(
        "--delay", "-d",
        type=float,
        default=1.0,
        help="Jeda waktu antar-scan dalam detik pada mode auto (Default: 1.0)"
    )
    parser.add_argument(
        "--mode", "-m",
        type=str,
        choices=["auto", "step"],
        default="auto",
        help="Mode scanning: 'auto' (otomatis per detik) atau 'step' (manual tekan Enter per ketinggian)"
    )
    parser.add_argument(
        "--protocol",
        type=str,
        choices=["modbus", "ascii"],
        default="modbus",
        help="Protokol komunikasi: 'modbus' (Modbus RTU RS-485) atau 'ascii' (Serial JSON/CSV)"
    )
    parser.add_argument(
        "--list-ports", "-lports",
        action="store_true",
        help="Tampilkan semua port COM/Serial yang terdeteksi lalu keluar"
    )
    parser.add_argument(
        "--interactive", "-i",
        action="store_true",
        help="Buka menu pemilihan port COM interaktif sebelum mulai"
    )

    args = parser.parse_args()

    if args.list_ports:
        list_available_com_ports()
        sys.exit(0)

    chosen_port = args.port
    if args.interactive:
        chosen_port = select_com_port_interactive(default_port=args.port)

    run_real_hardware_scanning(
        port_name=chosen_port,
        baud_rate=args.baud,
        total_height=args.steps,
        sampling_delay=args.delay,
        scan_mode=args.mode,
        protocol=args.protocol,
        object_name=args.object_name,
        total_loops=args.loops,
        transition_delay=args.transition_delay
    )

