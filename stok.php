<?php
// stok.php - Halaman khusus manajemen stok

// Pastikan folder data ada
$dataDir = __DIR__ . "/data";
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$barangFile = $dataDir . "/barang.json";
$riwayatFile = $dataDir . "/riwayat_stok.json";

// Pastikan file JSON ada
if (!file_exists($barangFile)) {
    file_put_contents($barangFile, "[]");
}
if (!file_exists($riwayatFile)) {
    file_put_contents($riwayatFile, "[]");
}

// Hanya kirim header JSON jika ada parameter action
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type');

    $barang = json_decode(file_get_contents($barangFile), true) ?? [];

    // Handle POST input
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);
    }

    switch ($_GET['action']) {
        case 'get_barang':
            echo json_encode($barang);
            exit;

        case 'tambah_stok':
            $id = $input['id'] ?? '';
            $jumlah = intval($input['jumlah'] ?? 0);
            $keterangan = $input['keterangan'] ?? '';

            if (empty($id) || $jumlah <= 0) {
                echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
                exit;
            }

            $found = false;
            foreach ($barang as &$item) {
                if ($item['id'] == $id) {
                    $stokSebelum = $item['stok'];
                    $item['stok'] += $jumlah;

                    // Catat riwayat stok
                    $riwayat = file_exists($riwayatFile) ? json_decode(file_get_contents($riwayatFile), true) : [];

                    $riwayat[] = [
                        'id_barang' => $id,
                        'kode_produk' => $item['kodeProduk'],
                        'nama_barang' => $item['nama'],
                        'jumlah' => $jumlah,
                        'jenis' => 'penambahan',
                        'keterangan' => $keterangan,
                        'waktu' => date('Y-m-d H:i:s'),
                        'stok_sebelum' => $stokSebelum,
                        'stok_sesudah' => $item['stok']
                    ];

                    file_put_contents($riwayatFile, json_encode($riwayat, JSON_PRETTY_PRINT));
                    $found = true;
                    break;
                }
            }

            if ($found) {
                file_put_contents($barangFile, json_encode($barang, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'data' => $barang]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan']);
            }
            exit;

        case 'kurangi_stok':
            $id = $input['id'] ?? '';
            $jumlah = intval($input['jumlah'] ?? 0);
            $keterangan = $input['keterangan'] ?? '';

            if (empty($id) || $jumlah <= 0) {
                echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
                exit;
            }

            $found = false;
            foreach ($barang as &$item) {
                if ($item['id'] == $id) {
                    if ($item['stok'] < $jumlah) {
                        echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi']);
                        exit;
                    }

                    $stokSebelum = $item['stok'];
                    $item['stok'] -= $jumlah;

                    // Catat riwayat stok
                    $riwayat = file_exists($riwayatFile) ? json_decode(file_get_contents($riwayatFile), true) : [];

                    $riwayat[] = [
                        'id_barang' => $id,
                        'kode_produk' => $item['kodeProduk'],
                        'nama_barang' => $item['nama'],
                        'jumlah' => $jumlah,
                        'jenis' => 'pengurangan',
                        'keterangan' => $keterangan,
                        'waktu' => date('Y-m-d H:i:s'),
                        'stok_sebelum' => $stokSebelum,
                        'stok_sesudah' => $item['stok']
                    ];

                    file_put_contents($riwayatFile, json_encode($riwayat, JSON_PRETTY_PRINT));
                    $found = true;
                    break;
                }
            }

            if ($found) {
                file_put_contents($barangFile, json_encode($barang, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'data' => $barang]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan']);
            }
            exit;

        case 'update_stok':
            $id = $input['id'] ?? '';
            $stokBaru = intval($input['stok'] ?? 0);

            if (empty($id) || $stokBaru < 0) {
                echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
                exit;
            }

            $found = false;
            foreach ($barang as &$item) {
                if ($item['id'] == $id) {
                    $stokSebelum = $item['stok'];
                    $perubahan = $stokBaru - $stokSebelum;
                    $item['stok'] = $stokBaru;

                    // Catat riwayat stok
                    $riwayat = file_exists($riwayatFile) ? json_decode(file_get_contents($riwayatFile), true) : [];

                    $riwayat[] = [
                        'id_barang' => $id,
                        'kode_produk' => $item['kodeProduk'],
                        'nama_barang' => $item['nama'],
                        'jumlah' => abs($perubahan),
                        'jenis' => $perubahan >= 0 ? 'penambahan' : 'pengurangan',
                        'keterangan' => 'Update stok manual',
                        'waktu' => date('Y-m-d H:i:s'),
                        'stok_sebelum' => $stokSebelum,
                        'stok_sesudah' => $item['stok']
                    ];

                    file_put_contents($riwayatFile, json_encode($riwayat, JSON_PRETTY_PRINT));
                    $found = true;
                    break;
                }
            }

            if ($found) {
                file_put_contents($barangFile, json_encode($barang, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'data' => $barang]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan']);
            }
            exit;

        case 'get_riwayat':
            $id = $_GET['id'] ?? '';

            if (file_exists($riwayatFile)) {
                $riwayat = json_decode(file_get_contents($riwayatFile), true);

                if (!empty($id)) {
                    $riwayat = array_filter($riwayat, function ($item) use ($id) {
                        return $item['id_barang'] == $id;
                    });
                }

                // Urutkan berdasarkan waktu terbaru
                usort($riwayat, function ($a, $b) {
                    return strtotime($b['waktu']) - strtotime($a['waktu']);
                });

                echo json_encode(array_values($riwayat));
            } else {
                echo json_encode([]);
            }
            exit;
    }
}

// Jika tidak ada parameter action, tampilkan halaman HTML
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Stok Barang - POS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        danger: '#dc3545',
                        warning: '#ffc107',
                        success: '#28a745',
                        info: '#17a2b8',
                        primary: '#007bff'
                    }
                }
            }
        }
    </script>
    <style>
        .stok-hampir-habis {
            background-color: #fffbeb;
        }

        .stok-habis {
            background-color: #fef2f2;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease;
        }

        .modal-container {
            transform: translateY(-50px);
            transition: transform 0.3s ease;
        }

        .modal-open .modal-container {
            transform: translateY(0);
        }

        .badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
        }

        .badge-success {
            color: #fff;
            background-color: #28a745;
        }

        .badge-warning {
            color: #212529;
            background-color: #ffc107;
        }

        .badge-danger {
            color: #fff;
            background-color: #dc3545;
        }

        .badge-info {
            color: #fff;
            background-color: #17a2b8;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .slide-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* PERBAIKAN: Sticky header untuk tabel - DIPERBAIKI */
        .table-container {
            max-height: 70vh;
            overflow-y: auto;
            position: relative;
        }
        
        .sticky-header thead th {
            position: sticky;
            top: 0;
            z-index: 20;
            background-color: #f8f9fa !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding-top: 12px;
            padding-bottom: 12px;
        }

        /* Efek hover untuk baris tabel */
        .hover-row:hover {
            background-color: #f3f4f6 !important;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex">
    <!-- Sidebar -->
    <?php include "partials/sidebar.php"; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Manajemen Stok Barang</h2>
                <div class="flex space-x-2">
                    <button onclick="exportData()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-file-export mr-2"></i> Export
                    </button>
                    <button onclick="openTambahStokModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-plus mr-2"></i> Tambah Stok
                    </button>
                    <button onclick="openKurangiStokModal()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-minus mr-2"></i> Kurangi Stok
                    </button>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="bg-white p-4 rounded-lg shadow mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cari Barang</label>
                        <input type="text" id="searchInput" placeholder="Nama atau kode barang" class="w-full p-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status Stok</label>
                        <select id="statusFilter" class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="all">Semua</option>
                            <option value="habis">Stok Habis</option>
                            <option value="hampir-habis">Hampir Habis</option>
                            <option value="cukup">Stok Cukup</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="applyFilters()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md w-full">
                            Terapkan Filter
                        </button>
                    </div>
                    <div class="flex items-end">
                        <button onclick="loadBarangData()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md w-full flex items-center justify-center">
                            <i class="fas fa-sync-alt mr-2"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading Indicator -->
            <div id="loadingIndicator" class="loading-spinner"></div>

            <!-- Stok Table - DIPERBAIKI dengan container untuk sticky header -->
            <div id="stokTableContainer" class="bg-white rounded-lg shadow overflow-hidden hidden">
                <div class="table-container"> <!-- Container dengan max-height dan overflow -->
                    <table class="w-full text-left border border-gray-300 sticky-header">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Barang</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Saat Ini</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Minimum</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Satuan</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="stokTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Data akan diisi oleh JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Error Message -->
            <div id="errorMessage" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4 hidden">
                <strong class="font-bold">Error!</strong>
                <span id="errorText"></span>
            </div>
        </div>
    </main>

    <!-- Modal Tambah Stok -->
    <div id="tambahStokModal" class="fixed inset-0 z-50 flex items-center justify-center hidden modal-overlay">
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
            <div class="px-6 py-4 border-b">
                <h3 class="text-xl font-semibold text-gray-800">Tambah Stok Barang</h3>
            </div>
            <div class="px-6 py-4">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="barangSelectTambah">
                        Pilih Barang
                    </label>
                    <select id="barangSelectTambah" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">-- Pilih Barang --</option>
                        <!-- Options akan diisi oleh JavaScript -->
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="jumlahStokTambah">
                        Jumlah Stok yang Ditambahkan
                    </label>
                    <input type="number" id="jumlahStokTambah" min="1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="keteranganTambah">
                        Keterangan (Opsional)
                    </label>
                    <textarea id="keteranganTambah" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button onclick="closeTambahStokModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                    Batal
                </button>
                <button onclick="tambahStok()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                    Simpan
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Kurangi Stok -->
    <div id="kurangiStokModal" class="fixed inset-0 z-50 flex items-center justify-center hidden modal-overlay">
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
            <div class="px-6 py-4 border-b">
                <h3 class="text-xl font-semibold text-gray-800">Kurangi Stok Barang</h3>
            </div>
            <div class="px-6 py-4">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="barangSelectKurangi">
                        Pilih Barang
                    </label>
                    <select id="barangSelectKurangi" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">-- Pilih Barang --</option>
                        <!-- Options akan diisi oleh JavaScript -->
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="jumlahStokKurangi">
                        Jumlah Stok yang Dikurangi
                    </label>
                    <input type="number" id="jumlahStokKurangi" min="1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="keteranganKurangi">
                        Keterangan (Opsional)
                    </label>
                    <textarea id="keteranganKurangi" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button onclick="closeKurangiStokModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                    Batal
                </button>
                <button onclick="kurangiStok()" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">
                    Simpan
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Edit Stok -->
    <div id="editStokModal" class="fixed inset-0 z-50 flex items-center justify-center hidden modal-overlay">
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
            <div class="px-6 py-4 border-b">
                <h3 class="text-xl font-semibold text-gray-800">Edit Stok Barang</h3>
            </div>
            <div class="px-6 py-4">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Nama Barang
                    </label>
                    <p id="editNamaBarang" class="text-lg font-medium"></p>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        Kode Barang
                    </label>
                    <p id="editKodeBarang" class="text-lg font-medium"></p>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="stokBaru">
                        Stok Baru
                    </label>
                    <input type="number" id="stokBaru" min="0" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <input type="hidden" id="editBarangId">
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button onclick="closeEditStokModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                    Batal
                </button>
                <button onclick="updateStok()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    Simpan Perubahan
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Riwayat Stok -->
    <div id="modalRiwayat" class="fixed inset-0 z-50 flex items-center justify-center hidden modal-overlay">
        <div class="modal-container bg-white w-11/12 md:max-w-4xl mx-auto rounded shadow-lg z-50 overflow-y-auto max-h-screen">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-semibold text-gray-800" id="riwayatTitle">Riwayat Stok Barang</h3>
                    <button onclick="tutupModalRiwayat()" class="text-gray-500 hover:text-gray-700 text-xl">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="mb-4 bg-blue-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="mr-4 text-blue-500">
                            <i class="fas fa-info-circle text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-blue-800 font-medium">Informasi Stok</p>
                            <p id="riwayatInfo" class="text-blue-600"></p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Waktu</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Jenis</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Jumlah</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Stok Sebelum</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Stok Sesudah</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody id="riwayatTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Data riwayat akan diisi oleh JavaScript -->
                        </tbody>
                    </table>

                </div>

                <div id="riwayatEmptyState" class="hidden text-center py-8">
                    <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">Tidak ada riwayat stok untuk barang ini</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variabel global untuk menyimpan data barang
        let barangData = [];

        // Fungsi untuk memuat data dari server
        async function loadBarangData() {
            // Tampilkan loading indicator
            document.getElementById('loadingIndicator').classList.remove('hidden');
            document.getElementById('stokTableContainer').classList.add('hidden');
            document.getElementById('errorMessage').classList.add('hidden');

            try {
                const response = await fetch('stok.php?action=get_barang');

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                barangData = data;

                // Render tabel dengan data yang diperoleh
                renderBarangTable(barangData);

                // Sembunyikan loading indicator, tampilkan tabel
                document.getElementById('loadingIndicator').classList.add('hidden');
                document.getElementById('stokTableContainer').classList.remove('hidden');

            } catch (error) {
                console.error('Error loading barang data:', error);

                // Tampilkan pesan error
                document.getElementById('loadingIndicator').classList.add('hidden');
                document.getElementById('errorText').textContent = `Gagal memuat data: ${error.message}`;
                document.getElementById('errorMessage').classList.remove('hidden');
            }
        }

        // Fungsi untuk menentukan status stok
        function getStatusStok(stok, stokMin) {
            if (stok === 0) {
                return {
                    status: 'Habis',
                    class: 'badge-danger'
                };
            } else if (stok <= stokMin) {
                return {
                    status: 'Hampir Habis',
                    class: 'badge-warning'
                };
            } else {
                return {
                    status: 'Aman',
                    class: 'badge-success'
                };
            }
        }

        // Fungsi untuk menentukan class baris berdasarkan status stok
        function getRowClass(stok, stokMin) {
            if (stok === 0) {
                return 'stok-habis';
            } else if (stok <= stokMin) {
                return 'stok-hampir-habis';
            }
            return '';
        }

        // Fungsi untuk menampilkan data barang ke tabel
        function renderBarangTable(barangList) {
            const tableBody = document.getElementById('stokTableBody');
            tableBody.innerHTML = '';

            if (barangList.length === 0) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                        Tidak ada data barang
                    </td>
                `;
                tableBody.appendChild(row);
                return;
            }

            barangList.forEach(barang => {
                const status = getStatusStok(barang.stok, barang.stokMin);
                const rowClass = getRowClass(barang.stok, barang.stokMin);

                const row = document.createElement('tr');
                row.className = `${rowClass} hover-row`;
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap">${barang.kodeProduk}</td>
                    <td class="px-6 py-4 whitespace-nowrap">${barang.nama}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="font-bold">${barang.stok}</span>
                        <button onclick="editStok('${barang.id}', '${barang.nama}', '${barang.kodeProduk}', ${barang.stok})" class="ml-2 text-blue-500 hover:text-blue-700">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">${barang.stokMin}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="badge ${status.class}">${status.status}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">${barang.satuan}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex space-x-2">
                            <button onclick="tambahStokBarang('${barang.id}')" class="text-green-600 hover:text-green-900 p-2 rounded-full hover:bg-green-100 transition-colors" title="Tambah Stok">
                                <i class="fas fa-plus-circle text-lg"></i>
                            </button>
                            <button onclick="kurangiStokBarang('${barang.id}')" class="text-yellow-600 hover:text-yellow-900 p-2 rounded-full hover:bg-yellow-100 transition-colors" title="Kurangi Stok">
                                <i class="fas fa-minus-circle text-lg"></i>
                            </button>
                            <button onclick="lihatRiwayat('${barang.id}', '${barang.nama}', '${barang.kodeProduk}', ${barang.stok})" class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-blue-100 transition-colors" title="Riwayat Stok">
                                <i class="fas fa-history text-lg"></i>
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        }

        // Fungsi untuk mengisi dropdown pilih barang di modal
        function fillBarangSelect(selectId) {
            const select = document.getElementById(selectId);
            select.innerHTML = '<option value="">-- Pilih Barang --</option>';

            barangData.forEach(barang => {
                const option = document.createElement('option');
                option.value = barang.id;
                option.textContent = `${barang.kodeProduk} - ${barang.nama} (Stok: ${barang.stok})`;
                option.setAttribute('data-stok', barang.stok);
                select.appendChild(option);
            });
        }

        // Fungsi untuk membuka modal tambah stok
        function openTambahStokModal() {
            document.getElementById('tambahStokModal').classList.remove('hidden');
            document.body.classList.add('modal-open');
            fillBarangSelect('barangSelectTambah');
        }

        // Fungsi untuk menutup modal tambah stok
        function closeTambahStokModal() {
            document.getElementById('tambahStokModal').classList.add('hidden');
            document.body.classList.remove('modal-open');
            document.getElementById('barangSelectTambah').value = '';
            document.getElementById('jumlahStokTambah').value = '';
            document.getElementById('keteranganTambah').value = '';
        }

        // Fungsi untuk membuka modal kurangi stok
        function openKurangiStokModal() {
            document.getElementById('kurangiStokModal').classList.remove('hidden');
            document.body.classList.add('modal-open');
            fillBarangSelect('barangSelectKurangi');
        }

        // Fungsi untuk menutup modal kurangi stok
        function closeKurangiStokModal() {
            document.getElementById('kurangiStokModal').classList.add('hidden');
            document.body.classList.remove('modal-open');
            document.getElementById('barangSelectKurangi').value = '';
            document.getElementById('jumlahStokKurangi').value = '';
            document.getElementById('keteranganKurangi').value = '';
        }

        // Fungsi untuk membuka modal edit stok
        function openEditStokModal() {
            document.getElementById('editStokModal').classList.remove('hidden');
            document.body.classList.add('modal-open');
        }

        // Fungsi untuk menutup modal edit stok
        function closeEditStokModal() {
            document.getElementById('editStokModal').classList.add('hidden');
            document.body.classList.remove('modal-open');
        }

        // Fungsi untuk menambah stok barang tertentu
        function tambahStokBarang(barangId) {
            const barang = barangData.find(b => b.id === barangId);
            if (barang) {
                openTambahStokModal();
                document.getElementById('barangSelectTambah').value = barangId;
            }
        }

        // Fungsi untuk mengurangi stok barang tertentu
        function kurangiStokBarang(barangId) {
            const barang = barangData.find(b => b.id === barangId);
            if (barang) {
                openKurangiStokModal();
                document.getElementById('barangSelectKurangi').value = barangId;
            }
        }

        // Fungsi untuk membuka modal edit stok
        function editStok(barangId, namaBarang, kodeBarang, stokSekarang) {
            document.getElementById('editBarangId').value = barangId;
            document.getElementById('editNamaBarang').textContent = namaBarang;
            document.getElementById('editKodeBarang').textContent = kodeBarang;
            document.getElementById('stokBaru').value = stokSekarang;
            openEditStokModal();
        }

        // Fungsi untuk memproses penambahan stok
        async function tambahStok() {
            const barangId = document.getElementById('barangSelectTambah').value;
            const jumlah = parseInt(document.getElementById('jumlahStokTambah').value);
            const keterangan = document.getElementById('keteranganTambah').value;

            if (!barangId) {
                alert('Pilih barang terlebih dahulu!');
                return;
            }

            if (!jumlah || jumlah < 1) {
                alert('Masukkan jumlah stok yang valid!');
                return;
            }

            try {
                const response = await fetch('stok.php?action=tambah_stok', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: barangId,
                        jumlah: jumlah,
                        keterangan: keterangan
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Stok berhasil ditambahkan!');
                    closeTambahStokModal();
                    loadBarangData(); // Muat ulang data dari server
                } else {
                    alert('Gagal: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambah stok');
            }
        }

        // Fungsi untuk memproses pengurangan stok
        async function kurangiStok() {
            const barangId = document.getElementById('barangSelectKurangi').value;
            const jumlah = parseInt(document.getElementById('jumlahStokKurangi').value);
            const keterangan = document.getElementById('keteranganKurangi').value;

            if (!barangId) {
                alert('Pilih barang terlebih dahulu!');
                return;
            }

            if (!jumlah || jumlah < 1) {
                alert('Masukkan jumlah stok yang valid!');
                return;
            }

            try {
                const response = await fetch('stok.php?action=kurangi_stok', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: barangId,
                        jumlah: jumlah,
                        keterangan: keterangan
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Stok berhasil dikurangi!');
                    closeKurangiStokModal();
                    loadBarangData(); // Muat ulang data dari server
                } else {
                    alert('Gagal: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengurangi stok');
            }
        }

        // Fungsi untuk memproses update stok
        async function updateStok() {
            const barangId = document.getElementById('editBarangId').value;
            const stokBaru = parseInt(document.getElementById('stokBaru').value);

            if (isNaN(stokBaru) || stokBaru < 0) {
                alert('Masukkan jumlah stok yang valid!');
                return;
            }

            try {
                const response = await fetch('stok.php?action=update_stok', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: barangId,
                        stok: stokBaru
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Stok berhasil diupdate!');
                    closeEditStokModal();
                    loadBarangData(); // Muat ulang data dari server
                } else {
                    alert('Gagal: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengupdate stok');
            }
        }

        // Fungsi untuk melihat riwayat stok
        async function lihatRiwayat(barangId, namaBarang, kodeBarang, stokSekarang) {
            try {
                const response = await fetch(`stok.php?action=get_riwayat&id=${barangId}`);
                const riwayat = await response.json();

                // Tampilkan modal riwayat
                document.getElementById('modalRiwayat').classList.remove('hidden');
                document.body.classList.add('modal-open');

                // Update judul dan info
                document.getElementById('riwayatTitle').textContent = `Riwayat Stok: ${namaBarang}`;
                document.getElementById('riwayatInfo').textContent = `${kodeBarang} - Stok saat ini: ${stokSekarang}`;

                // Isi tabel riwayat
                const tableBody = document.getElementById('riwayatTableBody');
                const emptyState = document.getElementById('riwayatEmptyState');

                tableBody.innerHTML = '';

                if (riwayat.length === 0) {
                    tableBody.classList.add('hidden');
                    emptyState.classList.remove('hidden');
                    return;
                }

                tableBody.classList.remove('hidden');
                emptyState.classList.add('hidden');

                riwayat.forEach(item => {
                    const row = document.createElement('tr');
                    row.className = 'fade-in';

                    // Format waktu menjadi lebih mudah dibaca
                    const waktu = new Date(item.waktu);
                    const waktuFormatted = waktu.toLocaleString('id-ID');

                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap">${waktuFormatted}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="badge ${item.jenis === 'penambahan' ? 'badge-success' : 'badge-danger'}">
                                ${item.jenis === 'penambahan' ? 'Penambahan' : 'Pengurangan'}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap font-bold ${item.jenis === 'penambahan' ? 'text-green-600' : 'text-red-600'}">
                            ${item.jenis === 'penambahan' ? '+' : '-'}${item.jumlah}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">${item.stok_sebelum}</td>
                        <td class="px-6 py-4 whitespace-nowrap font-bold">${item.stok_sesudah}</td>
                        <td class="px-6 py-4">${item.keterangan || '-'}</td>
                    `;
                    tableBody.appendChild(row);
                });

            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memuat riwayat stok');
            }
        }

        // Fungsi untuk menutup modal riwayat
        function tutupModalRiwayat() {
            document.getElementById('modalRiwayat').classList.add('hidden');
            document.body.classList.remove('modal-open');
        }

        // Fungsi untuk menerapkan filter
        function applyFilters() {
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;

            let filteredBarang = barangData;

            // Filter berdasarkan pencarian
            if (searchText) {
                filteredBarang = filteredBarang.filter(barang =>
                    barang.nama.toLowerCase().includes(searchText) ||
                    barang.kodeProduk.toLowerCase().includes(searchText)
                );
            }

            // Filter berdasarkan status stok
            if (statusFilter !== 'all') {
                filteredBarang = filteredBarang.filter(barang => {
                    const status = getStatusStok(barang.stok, barang.stokMin).status.toLowerCase();
                    if (statusFilter === 'habis') return status === 'habis';
                    if (statusFilter === 'hampir-habis') return status === 'hampir habis';
                    if (statusFilter === 'cukup') return status === 'aman';
                    return true;
                });
            }

            renderBarangTable(filteredBarang);
        }

        // Fungsi untuk export data
        function exportData() {
            // Implementasi export data ke CSV atau Excel
            alert('Fitur export akan diimplementasikan di sini');
        }

        // Inisialisasi halaman saat pertama kali dimuat
        document.addEventListener('DOMContentLoaded', function() {
            loadBarangData();

            // Tambahkan event listener untuk input pencarian
            document.getElementById('searchInput').addEventListener('keyup', applyFilters);

            // Tambahkan event listener untuk filter status
            document.getElementById('statusFilter').addEventListener('change', applyFilters);

            // Tutup modal saat klik di luar konten modal
            document.querySelectorAll('.modal-overlay').forEach(overlay => {
                overlay.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.add('hidden');
                        document.body.classList.remove('modal-open');
                    }
                });
            });
        });
    </script>
</body>

</html>