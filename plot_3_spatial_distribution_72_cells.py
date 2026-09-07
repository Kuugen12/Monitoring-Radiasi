"""
================================================================================
SCRIPT 3: Spatial Response Distribution Across All 72 Detector Cells
Target Hardware: Raspberry Pi 5 / PC
Purpose: Generate 8x9 grid (72 individual subplots: Cell 11 to Cell 89) showing
         the spatial response distribution for each detector cell with shared colorbar.
================================================================================
"""

import os
import csv
import numpy as np  # type: ignore
import matplotlib.pyplot as plt  # type: ignore
from matplotlib.cm import ScalarMappable  # type: ignore
from matplotlib.colors import Normalize  # type: ignore

# ==============================================================================
# 1. BAGIAN PENGAMBILAN DATA (DATA INTAKE SECTION)
# Mendukung: Firebase RTDB Cloud -> TiDB Cloud -> File CSV -> Simulasi Fallback
# ==============================================================================
def load_radiation_data(csv_path="heatmap_radiation_matrix.csv"):
    """
    Mengambil data matriks radiasi (N Baris x 72 Detektor).
    Prioritas:
      1. Ambil data terbaru dari Firebase Realtime Database
      2. Ambil data dari TiDB Cloud (scan_matrix)
      3. Baca dari file CSV lokal jika offline
      4. Simulasi data jika tidak ada koneksi/file
    """
    # 1. Coba ambil dari Firebase RTDB
    try:
        import requests
        url = "https://magang-brin-27225-default-rtdb.asia-southeast1.firebasedatabase.app/radiation_scans/matrix_data.json"
        res = requests.get(url, timeout=3)
        if res.status_code == 200 and res.json():
            matrix = res.json().get('matrix')
            if matrix and len(matrix) > 0:
                print(f"[DATA] Berhasil mengambil data live dari Firebase RTDB ({len(matrix)} level x {len(matrix[0])} detektor).")
                return np.array(matrix)
    except Exception:
        pass

    # 2. Coba ambil dari TiDB Cloud
    try:
        import pymysql
        import json
        conn = pymysql.connect(
            host="gateway01.ap-southeast-1.prod.aws.tidbcloud.com",
            port=4000,
            user="4FxUazxpWaqzAS1.root",
            password="Dq37CUJZIRiMM4QG",
            database="magang",
            ssl={'ssl_mode': 'REQUIRED'},
            connect_timeout=3
        )
        with conn.cursor() as cur:
            cur.execute("SELECT detector_data FROM scan_matrix ORDER BY id DESC LIMIT 10")
            rows = cur.fetchall()
            if rows:
                matrix = [json.loads(r[0]) for r in reversed(rows)]
                conn.close()
                print(f"[DATA] Berhasil mengambil data dari TiDB Cloud ({len(matrix)} record scan).")
                return np.array(matrix)
        conn.close()
    except Exception:
        pass

    # 3. Baca dari CSV
    if os.path.exists(csv_path):
        print(f"[DATA] Membaca data dari file: {csv_path}")
        rows = []
        with open(csv_path, 'r') as f:
            reader = csv.reader(f)
            headers = next(reader, None)
            for row in reader:
                if row and len(row) > 1:
                    values = [float(val) for val in row[1:] if val.strip()]
                    if values:
                        rows.append(values)
        if rows:
            return np.array(rows)

    print("[DATA] Database & CSV tidak ditemukan, menggunakan data simulasi 72-detektor...")
    np.random.seed(42)
    base_data = np.random.uniform(10, 45, size=(10, 72))
    # Hotspot simulasi di Cell 59, 63, 64, 65
    base_data[:, 44] += np.random.uniform(400, 600, size=10) # Cell 59
    base_data[:, 47] += np.random.uniform(300, 500, size=10) # Cell 63
    base_data[:, 48] += np.random.uniform(600, 900, size=10) # Cell 64
    base_data[:, 49] += np.random.uniform(200, 400, size=10) # Cell 65
    return base_data

# ==============================================================================
# 2. PROSES & PLOTTING 72 DETECTOR CELLS GRID
# ==============================================================================
def plot_72_detector_cells_distribution(matrix_data=None, output_image="spatial_response_distribution_72_cells.png"):
    """
    Membuat grid 8x9 (72 subplots) untuk menampilkan distribusi spasial setiap sel detektor.
    """
    if matrix_data is None:
        matrix_data = load_radiation_data()

    # Hitung rata-rata tiap channel
    if matrix_data.ndim == 2:
        mean_72 = np.mean(matrix_data, axis=0)
    else:
        mean_72 = matrix_data

    # Konfigurasi Subplots 8 baris x 9 kolom = 72 subplots
    fig, axes = plt.subplots(8, 9, figsize=(16, 14), dpi=150)
    fig.suptitle("Spatial Response Distribution Across All 72 Detector Cells", fontsize=14, fontweight='bold', y=0.96)

    # Sub-grid lokal X dan Y di dalam tiap sel (mm)
    sub_x = np.linspace(-26.0, -24.0, 10)
    sub_y = np.linspace(18.25, 19.75, 10)
    SubX, SubY = np.meshgrid(sub_x, sub_y)

    norm = Normalize(vmin=0, vmax=900)
    cmap = plt.get_cmap('viridis')

    for r in range(8):
        for c in range(9):
            idx = r * 9 + c
            ax = axes[r, c]

            cell_id = f"Cell {r+1}{c+1}"
            val = mean_72[idx] if idx < len(mean_72) else 0

            # Generate 2D sub-distribusi lokal
            dist = np.full((10, 10), val)
            if val > 100:
                dist += np.random.normal(0, val * 0.05, (10, 10))

            im = ax.imshow(
                dist,
                cmap=cmap,
                norm=norm,
                extent=[-26.0, -24.0, 18.25, 19.75],
                origin='lower',
                aspect='auto'
            )

            # Judul Tiap Subplot (Cell 11, Cell 12, ... Cell 89)
            ax.set_title(cell_id, fontsize=7.5, fontweight='bold', pad=3)

            # Format axis ticks mikro sesuai gambar referensi
            ax.tick_params(axis='both', which='major', labelsize=4.5, pad=1, length=2)

            if r == 7:
                ax.set_xlabel("X", fontsize=5.5, labelpad=1)
                ax.set_xticks([-26.0, -25.5, -25.0, -24.5, -24.0])
            else:
                ax.set_xticklabels([])

            if c == 0:
                ax.set_ylabel("Y", fontsize=5.5, labelpad=1)
                ax.set_yticks([18.25, 18.50, 18.75, 19.00, 19.25, 19.50, 19.75])
            else:
                ax.set_yticklabels([])

    # Tambahkan Shared Colorbar di sebelah kanan
    plt.subplots_adjust(right=0.92, top=0.92, bottom=0.06, left=0.06, hspace=0.35, wspace=0.25)
    cbar_ax = fig.add_axes([0.94, 0.08, 0.015, 0.82])
    sm = ScalarMappable(cmap=cmap, norm=norm)
    sm.set_array([])
    cbar = fig.colorbar(sm, cax=cbar_ax)
    cbar.set_label("Detector Value (Counts)", fontsize=9, labelpad=6)
    cbar.ax.tick_params(labelsize=8)

    # Simpan Gambar
    plt.savefig(output_image, dpi=300, bbox_inches='tight')
    print(f"[SUKSES] Gambar berhasil disimpan di: {output_image}")
    plt.show()

# ==============================================================================
# 3. MAIN EXECUTION
# ==============================================================================
if __name__ == "__main__":
    print("=" * 70)
    print(" Menjalankan Script 3: Spatial Response Distribution Across 72 Cells")
    print("=" * 70)
    
    # 1. Ambil Data
    data = load_radiation_data()
    
    # 2. Buat Plot
    plot_72_detector_cells_distribution(data)
