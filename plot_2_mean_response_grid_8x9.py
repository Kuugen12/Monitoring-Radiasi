"""
================================================================================
SCRIPT 2: Detector Array Mean Response Grid (8x9 Cell Matrix)
Target Hardware: Raspberry Pi 5 / PC
Purpose: Reshape 72 detectors into an 8x9 spatial matrix and plot mean response
         grid with numerical text overlays (Viridis Palette).
================================================================================
"""

import os
import csv
import numpy as np
import matplotlib.pyplot as plt

# ==============================================================================
# 1. BAGIAN PENGAMBILAN DATA (DATA INTAKE SECTION)
# Anda dapat mengganti fungsi ini dengan pembacaan sensor real dari Raspberry Pi 5
# ==============================================================================
def load_radiation_data(csv_path="heatmap_radiation_matrix.csv"):
    """
    Mengambil data matriks radiasi (N Baris x 72 Detektor).
    Jika file CSV ditemukan, baca dari CSV.
    Jika tidak, generate data dummy untuk simulasi.
    """
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

    print("[DATA] CSV tidak ditemukan, menggunakan data simulasi 72-detektor...")
    # Simulasi 72 channel dengan hotspot spesifik
    dummy_72 = np.zeros(72)
    # Row 1 (D1-D9)
    dummy_72[0:9] = [99.0, 80.0, 35.0, 33.0, 6.0, 3.0, 1.0, 0.0, 0.0]
    # Row 2 (D10-D18)
    dummy_72[9:18] = [12.0, 32.0, 39.0, 56.0, 84.0, 87.0, 93.0, 66.0, 49.0]
    # Row 3 (D19-D27)
    dummy_72[18:27] = [1.0, 0.0, 1.0, 0.0, 0.0, 2.0, 17.0, 39.0, 77.0]
    # Row 4 (D28-D36)
    dummy_72[27:36] = [0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 1.0]
    # Row 5 (D37-D45)
    dummy_72[36:45] = [0.0, 0.0, 0.0, 0.0, 0.0, 2.0, 0.0, 36.0, 592.0]
    # Row 6 (D46-D54) - Hotspot Peak!
    dummy_72[45:54] = [1.0, 2.0, 544.0, 1763.0, 354.0, 3.0, 2.0, 0.0, 1.0]
    # Row 7 (D55-D63)
    dummy_72[54:63] = [11.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]
    # Row 8 (D64-D72)
    dummy_72[63:72] = [18.0, 1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]
    
    return np.array([dummy_72])

# ==============================================================================
# 2. PROSES & PLOTTING 8x9 MEAN RESPONSE GRID
# ==============================================================================
def plot_8x9_mean_response_grid(matrix_data=None, output_image="detector_array_mean_response_grid.png"):
    """
    Mengubah array 72 channel menjadi matriks 8x9 dan memplot heatmap dengan text nilai.
    """
    if matrix_data is None:
        matrix_data = load_radiation_data()

    # Hitung rata-rata mean counts per channel jika ada multi-height scans
    if matrix_data.ndim == 2:
        if matrix_data.shape[0] > 1:
            mean_72 = np.mean(matrix_data, axis=0)
        else:
            mean_72 = matrix_data[0]
    else:
        mean_72 = matrix_data

    # Pastikan ukuran data tepat 72 channel
    if len(mean_72) < 72:
        padded = np.zeros(72)
        padded[:len(mean_72)] = mean_72
        mean_72 = padded
    else:
        mean_72 = mean_72[:72]

    # Reshape 72 detektor menjadi 8 baris x 9 kolom (8x9 = 72)
    grid_8x9 = mean_72.reshape((8, 9))

    # Konfigurasi Visualisasi Matplotlib
    plt.figure(figsize=(10, 7), dpi=150)
    plt.style.use('default')

    # Plot Heatmap menggunakan Colormap Viridis (Sesuai Gambar 2)
    im = plt.imshow(
        grid_8x9,
        cmap='viridis',
        aspect='auto',
        interpolation='nearest'
    )

    # Label Sumbu X dan Y
    row_labels = [f"Row {i+1}" for i in range(8)]
    col_labels = [f"Col {i+1}" for i in range(9)]

    plt.xticks(np.arange(9), col_labels, fontsize=10)
    plt.yticks(np.arange(8), row_labels, fontsize=10)

    plt.xlabel("Cell Column Index", fontsize=11, fontweight='bold', labelpad=8)
    plt.ylabel("Cell Row Index", fontsize=11, fontweight='bold', labelpad=8)
    plt.title("Detector Array Mean Response Grid (8x9 Cell Matrix)", fontsize=12, fontweight='bold', pad=14)

    # Overlay Nilai Numerik di Setiap Sel (Contoh: 1763.0, 544.0, 0.0)
    max_val = np.max(grid_8x9) if np.max(grid_8x9) > 0 else 1.0
    for r in range(8):
        for c in range(9):
            val = grid_8x9[r, c]
            # Pilih warna teks putih/hitam agar kontras
            text_color = "black" if (val / max_val) > 0.6 else "white"
            plt.text(
                c, r, f"{val:.1f}",
                ha="center", va="center",
                color=text_color,
                fontsize=9.5
            )

    # Hilangkan garis border luar kotak
    for spine in plt.gca().spines.values():
        spine.set_visible(False)

    # Colorbar
    cbar = plt.colorbar(im, fraction=0.046, pad=0.04)
    cbar.set_label("Mean Counts", fontsize=10, labelpad=8)
    cbar.outline.set_visible(False)

    plt.tight_layout()

    # Simpan Gambar
    plt.savefig(output_image, dpi=300, bbox_inches='tight')
    print(f"[SUKSES] Gambar berhasil disimpan di: {output_image}")
    plt.show()

# ==============================================================================
# 3. MAIN EXECUTION
# ==============================================================================
if __name__ == "__main__":
    print("=" * 70)
    print(" Menjalankan Script 2: Detector Array Mean Response Grid (8x9)")
    print("=" * 70)
    
    # 1. Ambil Data
    data = load_radiation_data()
    
    # 2. Buat Plot
    plot_8x9_mean_response_grid(data)
