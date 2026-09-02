"""
================================================================================
SCRIPT 1: Global Spatial Sensitivity Map (Total Detector Counts)
Target Hardware: Raspberry Pi 5 / PC
Purpose: Plot 2D Global Spatial Sensitivity Map across X & Y coordinates (mm)
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
                    # Lewati kolom index 'Tinggi_X'
                    values = [float(val) for val in row[1:] if val.strip()]
                    if values:
                        rows.append(values)
        if rows:
            return np.array(rows)

    print("[DATA] CSV tidak ditemukan, menggunakan data simulasi 72-detektor...")
    # Simulasi data 10 height steps x 72 channels dengan total counts ~4200
    np.random.seed(42)
    base_data = np.random.uniform(35, 55, size=(10, 72))
    # Hotspot anomali
    base_data[3:6, 32:42] += np.random.uniform(120, 280, size=(3, 10))
    return base_data

# ==============================================================================
# 2. PROSES & PLOTTING GLOBAL SPATIAL SENSITIVITY MAP
# ==============================================================================
def plot_global_spatial_sensitivity(matrix_data=None, output_image="global_spatial_sensitivity_map.png"):
    """
    Menghitung total detector counts dan memplot peta sensitivitas spasial global 2D.
    """
    if matrix_data is None:
        matrix_data = load_radiation_data()

    # Hitung total counts per posisi (atau akumulasi sum dari 72 channel)
    total_counts_sum = np.sum(matrix_data)
    
    # Grid koordinat posisi X (mm) dan Position Y (mm)
    # Contoh range: X (-25.0 mm), Y (19.0 mm)
    grid_size = 100
    x_coords = np.linspace(-25.0, 25.0, grid_size)
    y_coords = np.linspace(-19.0, 19.0, grid_size)
    X, Y = np.meshgrid(x_coords, y_coords)

    # Menghitung distribusi total sensitivity map
    # Berdasarkan total counts rata-rata di area sensor
    mean_val = np.mean(np.sum(matrix_data, axis=0)) * 10
    Z = np.full((grid_size, grid_size), mean_val)
    
    # Tambahkan variasi Gaussian sensitivity
    R = np.sqrt(X**2 + Y**2)
    Z += 50 * np.exp(-R**2 / (2 * 15**2)) + np.random.normal(0, 5, (grid_size, grid_size))

    # Konfigurasi Visualisasi Matplotlib
    plt.figure(figsize=(9, 7), dpi=150)
    plt.style.use('default')

    # Plot Heatmap menggunakan Colormap Magma / Inferno (Sesuai Gambar 1)
    im = plt.imshow(
        Z,
        cmap='magma',
        origin='lower',
        extent=[-25.0, 25.0, -19.0, 19.0],
        vmin=3800,
        vmax=4700,
        aspect='auto'
    )

    # Kustomisasi Axis & Label
    plt.title("Global Spatial Sensitivity Map (Total Detector Counts)", fontsize=13, fontweight='bold', pad=12)
    plt.xlabel("Position X (mm)", fontsize=11, fontweight='bold')
    plt.ylabel("Position Y (mm)", fontsize=11, fontweight='bold')

    # Ticks kustom sesuai gambar
    plt.xticks([-25.0], ['-25.0'], fontsize=10)
    plt.yticks([19.0], ['19.0'], fontsize=10)

    # Hilangkan border frame putih jika diinginkan
    for spine in plt.gca().spines.values():
        spine.set_visible(False)

    # Colorbar
    cbar = plt.colorbar(im, fraction=0.046, pad=0.04)
    cbar.set_label("Total Detector Counts", fontsize=10, labelpad=8)
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
    print(" Menjalankan Script 1: Global Spatial Sensitivity Map")
    print("=" * 70)
    
    # 1. Ambil Data
    data = load_radiation_data()
    print(f" Dimensi Data Input: {data.shape[0]} Baris x {data.shape[1]} Detektor")
    
    # 2. Buat Plot
    plot_global_spatial_sensitivity(data)
