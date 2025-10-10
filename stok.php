<?php
// stok.php - Halaman khusus manajemen stok
date_default_timezone_set('Asia/Jakarta');

// Pastikan folder data ada
$dataDir = __DIR__ . "/data";
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$barangFile   = $dataDir . "/barang.json";
$riwayatFile  = $dataDir . "/riwayat_stok.json";
$satuanFile   = $dataDir . "/satuan.json";

// Pastikan file JSON ada
if (!file_exists($barangFile)) {
    file_put_contents($barangFile, "[]");
}
if (!file_exists($riwayatFile)) {
    file_put_contents($riwayatFile, "[]");
}
if (!file_exists($satuanFile)) {
    file_put_contents($satuanFile, "[]");
}

/** Helper: cek satuan variabel (qty desimal) */
if (!function_exists('isVarUnit')) {
    function isVarUnit(string $satuanNama, array $satuanList): bool
    {
        $key = strtolower(trim($satuanNama));
        if ($key === '') return false;
        foreach ($satuanList as $s) {
            $nm = strtolower(trim((string)($s['nama'] ?? '')));
            if ($nm === $key) return !empty($s['is_variable']);
        }
        return false;
    }
}
/** Helper: normalisasi qty sesuai tipe satuan */
if (!function_exists('normQty')) {
    function normQty(float $val, bool $isVar): float
    {
        $val = max(0, $val);
        return $isVar ? round($val, 3) : (float)round($val);
    }
}

// Hanya kirim header JSON jika ada parameter action
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type');

    $barang  = json_decode(file_get_contents($barangFile), true) ?? [];
    $satuan  = json_decode(file_get_contents($satuanFile), true) ?? [];

    // Handle POST input
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents("php://input");
        $input = json_decode($raw, true);
        if (!is_array($input)) $input = [];
    }

    switch ($_GET['action']) {
        case 'get_barang':
            echo json_encode($barang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

            // BARU: expose daftar satuan ke frontend
        case 'get_satuan':
            echo json_encode($satuan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;

        case 'tambah_stok':
            $id         = $input['id'] ?? '';
            $jumlahRaw  = (float)($input['jumlah'] ?? 0);
            $keterangan = $input['keterangan'] ?? '';

            if (empty($id) || $jumlahRaw <= 0) {
                echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
                exit;
            }

            $found = false;
            foreach ($barang as &$item) {
                if ($item['id'] == $id) {
                    $isVar       = isVarUnit((string)($item['satuan'] ?? ''), $satuan);
                    $jumlah      = normQty($jumlahRaw, $isVar);
                    $stokSebelum = (float)$item['stok'];
                    $item['stok'] = normQty($stokSebelum + $jumlah, $isVar);

                    // Catat riwayat stok
                    $riwayat = file_exists($riwayatFile) ? json_decode(file_get_contents($riwayatFile), true) : [];
                    if (!is_array($riwayat)) $riwayat = [];

                    $riwayat[] = [
                        'id_barang'     => $id,
                        'kode_produk'   => $item['kodeProduk'],
                        'nama_barang'   => $item['nama'],
                        'jumlah'        => $jumlah,
                        'jenis'         => 'penambahan',
                        'keterangan'    => $keterangan,
                        'waktu'         => date('Y-m-d H:i:s'),
                        'stok_sebelum'  => $stokSebelum,
                        'stok_sesudah'  => $item['stok']
                    ];

                    file_put_contents(
                        $riwayatFile,
                        json_encode($riwayat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    );
                    $found = true;
                    break;
                }
            }

            if ($found) {
                file_put_contents(
                    $barangFile,
                    json_encode($barang, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
                echo json_encode(['success' => true, 'data' => $barang], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan']);
            }
            exit;

        case 'kurangi_stok':
            $id         = $input['id'] ?? '';
            $jumlahRaw  = (float)($input['jumlah'] ?? 0);
            $keterangan = $input['keterangan'] ?? '';

            if (empty($id) || $jumlahRaw <= 0) {
                echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
                exit;
            }

            $found = false;
            foreach ($barang as &$item) {
                if ($item['id'] == $id) {
                    $isVar       = isVarUnit((string)($item['satuan'] ?? ''), $satuan);
                    $jumlah      = normQty($jumlahRaw, $isVar);
                    $stokSebelum = (float)$item['stok'];

                    // Cegah minus (pakai epsilon utk float)
                    if ($stokSebelum + 1e-9 < $jumlah) {
                        echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi']);
                        exit;
                    }

                    $item['stok'] = normQty($stokSebelum - $jumlah, $isVar);

                    // Catat riwayat stok
                    $riwayat = file_exists($riwayatFile) ? json_decode(file_get_contents($riwayatFile), true) : [];
                    if (!is_array($riwayat)) $riwayat = [];

                    $riwayat[] = [
                        'id_barang'     => $id,
                        'kode_produk'   => $item['kodeProduk'],
                        'nama_barang'   => $item['nama'],
                        'jumlah'        => $jumlah,
                        'jenis'         => 'pengurangan',
                        'keterangan'    => $keterangan,
                        'waktu'         => date('Y-m-d H:i:s'),
                        'stok_sebelum'  => $stokSebelum,
                        'stok_sesudah'  => $item['stok']
                    ];

                    file_put_contents(
                        $riwayatFile,
                        json_encode($riwayat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    );
                    $found = true;
                    break;
                }
            }

            if ($found) {
                file_put_contents(
                    $barangFile,
                    json_encode($barang, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
                echo json_encode(['success' => true, 'data' => $barang], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan']);
            }
            exit;

        case 'update_stok':
            $id          = $input['id'] ?? '';
            $stokBaruRaw = (float)($input['stok'] ?? 0);

            if (empty($id) || $stokBaruRaw < 0) {
                echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
                exit;
            }

            $found = false;
            foreach ($barang as &$item) {
                if ($item['id'] == $id) {
                    $isVar       = isVarUnit((string)($item['satuan'] ?? ''), $satuan);
                    $stokBaru    = normQty($stokBaruRaw, $isVar);

                    $stokSebelum = (float)$item['stok'];
                    $perubahan   = $stokBaru - $stokSebelum;

                    // Cek kalau ada perubahan stok
                    if (abs($perubahan) > 1e-9) {
                        $item['stok'] = $stokBaru;

                        // Catat riwayat stok
                        $riwayat = file_exists($riwayatFile) ? json_decode(file_get_contents($riwayatFile), true) : [];
                        if (!is_array($riwayat)) $riwayat = [];

                        $riwayat[] = [
                            'id_barang'     => $id,
                            'kode_produk'   => $item['kodeProduk'],
                            'nama_barang'   => $item['nama'],
                            'jumlah'        => normQty(abs($perubahan), $isVar),
                            'jenis'         => ($perubahan > 0 ? 'penambahan' : 'pengurangan'),
                            'keterangan'    => 'Update stok manual',
                            'waktu'         => date('Y-m-d H:i:s'),
                            'stok_sebelum'  => $stokSebelum,
                            'stok_sesudah'  => $stokBaru
                        ];

                        file_put_contents(
                            $riwayatFile,
                            json_encode($riwayat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        );
                    }

                    $found = true;
                    break;
                }
            }

            if ($found) {
                file_put_contents(
                    $barangFile,
                    json_encode($barang, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
                echo json_encode(['success' => true, 'data' => $barang], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan']);
            }
            exit;

        case 'get_riwayat':
            $id = $_GET['id'] ?? '';

            if (file_exists($riwayatFile)) {
                $riwayat = json_decode(file_get_contents($riwayatFile), true);
                if (!is_array($riwayat)) $riwayat = [];

                if (!empty($id)) {
                    $riwayat = array_filter($riwayat, function ($item) use ($id) {
                        return $item['id_barang'] == $id;
                    });
                }

                // Urutkan berdasarkan waktu terbaru
                usort($riwayat, function ($a, $b) {
                    return strtotime($b['waktu']) - strtotime($a['waktu']);
                });

                echo json_encode(array_values($riwayat), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            exit;

        case 'hapus_riwayat':
            // Terima kedua nama kunci: id_barang (kanonis) atau barangId (kompat lama)
            $id_barang = $input['id_barang'] ?? ($input['barangId'] ?? null);

            if (file_exists($riwayatFile)) {
                $riwayat = json_decode(file_get_contents($riwayatFile), true) ?? [];

                if ($id_barang && $id_barang !== 'all') {
                    // Hapus semua riwayat milik barang tertentu
                    $riwayat = array_filter($riwayat, function ($item) use ($id_barang) {
                        return $item['id_barang'] != $id_barang;
                    });
                    $message = 'Semua riwayat barang berhasil dihapus';
                } else {
                    // Kalau tidak ada id_barang atau id_barang='all', hapus semua riwayat
                    $riwayat = [];
                    $message = 'Semua riwayat stok berhasil dihapus';
                }

                file_put_contents(
                    $riwayatFile,
                    json_encode(array_values($riwayat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
                echo json_encode(['success' => true, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                echo json_encode(['success' => false, 'message' => 'File riwayat tidak ditemukan']);
            }
            exit;

        case 'hapus_riwayat_entry':
            $id_barang = $input['id_barang'] ?? null;
            $waktu     = $input['waktu'] ?? null;

            if (file_exists($riwayatFile)) {
                $riwayat = json_decode(file_get_contents($riwayatFile), true);
                if (!is_array($riwayat)) $riwayat = [];

                if ($id_barang && $waktu) {
                    // Hapus hanya entry dengan id_barang + waktu sesuai
                    $riwayat = array_filter($riwayat, function ($item) use ($id_barang, $waktu) {
                        return !($item['id_barang'] == $id_barang && $item['waktu'] == $waktu);
                    });
                    $message = 'Riwayat berhasil dihapus';
                } else {
                    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
                    exit;
                }

                file_put_contents(
                    $riwayatFile,
                    json_encode(array_values($riwayat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );
                echo json_encode(['success' => true, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                echo json_encode(['success' => false, 'message' => 'File riwayat tidak ditemukan']);
            }
            exit;
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Stok Barang - POS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="alert.js"></script>
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
            transition: opacity .3s ease;
        }

        .modal-container {
            transform: translateY(-50px);
            transition: transform .3s ease;
        }

        .modal-open .modal-container {
            transform: translateY(0);
        }

        .badge {
            display: inline-block;
            padding: .25em .6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .375rem;
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
            animation: fadeIn .3s ease-in-out;
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
            animation: slideIn .3s ease-out;
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

        .hover-row:hover {
            background-color: #f3f4f6 !important;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex">
    <!-- Sidebar -->
    <?php include "partials/sidebar.php"; ?>

    <!-- Main Content -->
    <main class="flex-1 md:ml-64 ml-0 p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">📊 Manajemen Stok Barang</h2>
            <div class="flex space-x-2">
                <button onclick="openPrintModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-file-export mr-2"></i> Print Stok Barang
                </button>
                <button onclick="hapusSemuaRiwayat()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-trash-alt mr-2"></i> Hapus Riwayat
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

        <!-- Stok Table -->
        <div id="stokTableContainer" class="bg-white rounded-lg shadow overflow-hidden hidden">
            <div class="table-container">
                <table class="w-full text-left border border-gray-300 sticky-header">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Barang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Saat Ini</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Minimum</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Satuan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="stokTableBody" class="bg-white divide-y divide-gray-200"></tbody>
                </table>
            </div>
        </div>

        <!-- Error Message -->
        <div id="errorMessage" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4 hidden">
            <strong class="font-bold">Error!</strong>
            <span id="errorText"></span>
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
                    </select>
                </div>
                <div class="mb-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="jumlahStokTambah">
                        Jumlah Stok yang Ditambahkan
                    </label>
                    <!-- step dinamis -->
                    <input type="number" id="jumlahStokTambah" min="0" step="any" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <p id="hintTambah" class="text-xs text-gray-500 mb-4"></p>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="keteranganTambah">
                        Keterangan (Opsional)
                    </label>
                    <textarea id="keteranganTambah" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button onclick="closeTambahStokModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">Batal</button>
                <button onclick="tambahStok()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">Simpan</button>
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
                    </select>
                </div>
                <div class="mb-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="jumlahStokKurangi">
                        Jumlah Stok yang Dikurangi
                    </label>
                    <!-- step dinamis -->
                    <input type="number" id="jumlahStokKurangi" min="0" step="any" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <p id="hintKurangi" class="text-xs text-gray-500 mb-4"></p>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="keteranganKurangi">
                        Keterangan (Opsional)
                    </label>
                    <textarea id="keteranganKurangi" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button onclick="closeKurangiStokModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">Batal</button>
                <button onclick="kurangiStok()" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">Simpan</button>
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
                    <label class="block text-gray-700 text-sm font-bold mb-2">Nama Barang</label>
                    <p id="editNamaBarang" class="text-lg font-medium"></p>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Kode Barang</label>
                    <p id="editKodeBarang" class="text-lg font-medium"></p>
                </div>
                <div class="mb-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="stokBaru">Stok Baru</label>
                    <!-- step dinamis -->
                    <input type="number" id="stokBaru" min="0" step="any" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <p id="hintEdit" class="text-xs text-gray-500 mb-4"></p>
                <input type="hidden" id="editBarangId">
                <input type="hidden" id="stokLama">
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button onclick="closeEditStokModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">Batal</button>
                <button onclick="updateStok()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">Simpan Perubahan</button>
            </div>
        </div>
    </div>

    <!-- Modal Riwayat Stok -->
    <div id="modalRiwayat" class="fixed inset-0 z-50 flex items-center justify-center hidden modal-overlay bg-black bg-opacity-50 transition-opacity duration-300">
        <div class="modal-container bg-white w-11/12 md:max-w-5xl mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto max-h-screen transform transition-all duration-300 scale-95">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6 border-b pb-4">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-box-open text-blue-600"></i>
                            <span id="riwayatTitle">Riwayat Stok Barang</span>
                        </h3>
                        <p id="riwayatInfo" class="text-sm text-gray-500 mt-1"></p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="tutupModalRiwayat()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded-lg shadow-sm">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg shadow">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700 uppercase tracking-wider">Waktu</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700 uppercase tracking-wider">Jenis</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700 uppercase tracking-wider">Jumlah</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700 uppercase tracking-wider">Stok Sebelum</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700 uppercase tracking-wider">Stok Sesudah</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700 uppercase tracking-wider">Keterangan</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="riwayatTableBody" class="bg-white divide-y divide-gray-100"></tbody>
                    </table>
                </div>

                <div id="riwayatEmptyState" class="hidden text-center py-10">
                    <i class="fas fa-history text-5xl text-gray-300 mb-4 animate-pulse"></i>
                    <p class="text-gray-500 font-medium">Tidak ada riwayat stok untuk barang ini</p>
                    <p class="text-sm text-gray-400">Semua perubahan stok akan tercatat di sini secara otomatis</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Riwayat -->
    <div id="hapusRiwayatModal" class="fixed inset-0 z-50 flex items-center justify-center hidden modal-overlay">
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto">
            <div class="px-6 py-4 border-b">
                <h3 class="text-xl font-semibold text-gray-800">Hapus Riwayat Stok</h3>
            </div>
            <div class="px-6 py-4">
                <p class="text-gray-700 mb-4" id="hapusRiwayatMessage">Apa Anda yakin ingin menghapus semua riwayat stok?</p>
                <input type="hidden" id="hapusRiwayatBarangId">
            </div>
            <div class="px-6 py-4 border-t flex justify-end">
                <button onclick="closeHapusRiwayatModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">Batal</button>
                <button onclick="hapusRiwayat()" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">Hapus</button>
            </div>
        </div>
    </div>

    <div id="printData" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl w-96 p-6 transform transition-all scale-95 opacity-0 animate-[fadeIn_0.25s_ease-out_forwards]">
            <h2 class="text-xl font-bold text-gray-800 text-center mb-5">🖨️ Pilih Jenis Cetak</h2>
            <div class="space-y-3">
                <button onclick="printData('semua')" class="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-medium shadow">📄 Cetak Semua Produk</button>
                <button onclick="printData('habis')" class="w-full flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-lg font-medium shadow">🛒 Cetak Produk Habis / Hampir Habis</button>
            </div>
            <button onclick="closePrintModal()" class="mt-5 w-full bg-gray-300 hover:bg-gray-400 text-gray-800 py-2.5 rounded-lg font-medium transition">Batal</button>
        </div>
    </div>

    <script>
        // Variabel global
        let barangData = [];
        let satuanList = [];
        let currentRiwayatBarangId = null;
        let currentRiwayatNama = null;
        let currentRiwayatKode = null;
        let currentRiwayatStok = null;

        // ---- Helper satuan (frontend) ----
        function isVarSatuanName(nama) {
            const key = String(nama || '').trim().toLowerCase();
            if (!key) return false;
            return !!(satuanList.find(s => String(s.nama || '').trim().toLowerCase() === key && !!s.is_variable));
        }

        function setStepHint(elInput, elHint, isVar) {
            if (!elInput) return;
            elInput.step = isVar ? 'any' : '1';
            if (elHint) {
                elHint.textContent = isVar ?
                    'Satuan ini mendukung desimal (contoh: 0.25, 1.5).' :
                    'Satuan ini tidak mendukung desimal. Masukkan bilangan bulat.';
            }
        }

        function applyStepForBarang(barangId, inputId, hintId) {
            const b = barangData.find(x => String(x.id) === String(barangId));
            const isVar = b ? isVarSatuanName(b.satuan) : false;
            setStepHint(document.getElementById(inputId), document.getElementById(hintId), isVar);
        }

        function applyStepForSelect(selectId, inputId, hintId) {
            const sel = document.getElementById(selectId);
            const val = sel ? sel.value : '';
            if (!val) {
                setStepHint(document.getElementById(inputId), document.getElementById(hintId), false);
                return;
            }
            applyStepForBarang(val, inputId, hintId);
        }

        // ---- Loaders ----
        async function loadBarangData() {
            document.getElementById('loadingIndicator').classList.remove('hidden');
            document.getElementById('stokTableContainer').classList.add('hidden');
            document.getElementById('errorMessage').classList.add('hidden');

            try {
                const response = await fetch('stok.php?action=get_barang');
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                const data = await response.json();
                barangData = Array.isArray(data) ? data : [];

                renderBarangTable(barangData);

                document.getElementById('loadingIndicator').classList.add('hidden');
                document.getElementById('stokTableContainer').classList.remove('hidden');

            } catch (error) {
                console.error('Error loading barang data:', error);
                document.getElementById('loadingIndicator').classList.add('hidden');
                document.getElementById('errorText').textContent = `Gagal memuat data: ${error.message}`;
                document.getElementById('errorMessage').classList.remove('hidden');
            }
        }
        async function loadSatuan() {
            try {
                const res = await fetch('stok.php?action=get_satuan');
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                satuanList = Array.isArray(data) ? data : [];
            } catch (e) {
                console.warn('Gagal memuat satuan, default ke non-variabel:', e);
                satuanList = [];
            }
        }

        // ---- Status stok ----
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

        function getRowClass(stok, stokMin) {
            if (stok === 0) return 'stok-habis';
            if (stok <= stokMin) return 'stok-hampir-habis';
            return '';
        }

        // ---- Render tabel ----
        function renderBarangTable(barangList) {
            const tableBody = document.getElementById('stokTableBody');
            tableBody.innerHTML = '';

            if (!barangList.length) {
                const row = document.createElement('tr');
                row.innerHTML = `<td colspan="7" class="px-6 py-4 text-center text-gray-500">Tidak ada data barang</td>`;
                tableBody.appendChild(row);
                return;
            }

            barangList.forEach(barang => {
                const status = getStatusStok(Number(barang.stok) || 0, Number(barang.stokMin) || 0);
                const rowClass = getRowClass(Number(barang.stok) || 0, Number(barang.stokMin) || 0);

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
                        <span class="badge ${status.class} inline-flex items-center justify-center min-w-[80px]">${status.status}</span>
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

        // ---- Select barang di modal ----
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

        // ---- Modal handlers ----
        function openTambahStokModal() {
            document.getElementById('tambahStokModal').classList.remove('hidden');
            document.body.classList.add('modal-open');
            fillBarangSelect('barangSelectTambah');
            applyStepForSelect('barangSelectTambah', 'jumlahStokTambah', 'hintTambah');
        }

        function closeTambahStokModal() {
            document.getElementById('tambahStokModal').classList.add('hidden');
            document.body.classList.remove('modal-open');
            document.getElementById('barangSelectTambah').value = '';
            document.getElementById('jumlahStokTambah').value = '';
            document.getElementById('keteranganTambah').value = '';
            setStepHint(document.getElementById('jumlahStokTambah'), document.getElementById('hintTambah'), false);
        }

        function openKurangiStokModal() {
            document.getElementById('kurangiStokModal').classList.remove('hidden');
            document.body.classList.add('modal-open');
            fillBarangSelect('barangSelectKurangi');
            applyStepForSelect('barangSelectKurangi', 'jumlahStokKurangi', 'hintKurangi');
        }

        function closeKurangiStokModal() {
            document.getElementById('kurangiStokModal').classList.add('hidden');
            document.body.classList.remove('modal-open');
            document.getElementById('barangSelectKurangi').value = '';
            document.getElementById('jumlahStokKurangi').value = '';
            document.getElementById('keteranganKurangi').value = '';
            setStepHint(document.getElementById('jumlahStokKurangi'), document.getElementById('hintKurangi'), false);
        }

        function openEditStokModal() {
            document.getElementById('editStokModal').classList.remove('hidden');
            document.body.classList.add('modal-open');
        }

        function closeEditStokModal() {
            document.getElementById('editStokModal').classList.add('hidden');
            document.body.classList.remove('modal-open');
            setStepHint(document.getElementById('stokBaru'), document.getElementById('hintEdit'), false);
        }

        // Aksi cepat by id
        function tambahStokBarang(barangId) {
            const barang = barangData.find(b => String(b.id) === String(barangId));
            if (barang) {
                openTambahStokModal();
                setTimeout(() => {
                    document.getElementById('barangSelectTambah').value = String(barangId);
                    applyStepForBarang(barangId, 'jumlahStokTambah', 'hintTambah');
                }, 100);
            }
        }

        function kurangiStokBarang(barangId) {
            const barang = barangData.find(b => String(b.id) === String(barangId));
            if (barang) {
                openKurangiStokModal();
                setTimeout(() => {
                    document.getElementById('barangSelectKurangi').value = String(barangId);
                    applyStepForBarang(barangId, 'jumlahStokKurangi', 'hintKurangi');
                }, 100);
            }
        }

        function editStok(barangId, namaBarang, kodeBarang, stokSekarang) {
            document.getElementById('editBarangId').value = barangId;
            document.getElementById('editNamaBarang').textContent = namaBarang;
            document.getElementById('editKodeBarang').textContent = kodeBarang;
            document.getElementById('stokLama').value = stokSekarang;
            document.getElementById('stokBaru').value = stokSekarang;
            openEditStokModal();
            // set step sesuai satuan
            applyStepForBarang(barangId, 'stokBaru', 'hintEdit');
        }

        // Proses tambah stok
        async function tambahStok() {
            const barangId = document.getElementById('barangSelectTambah').value;
            const jumlah = parseFloat(document.getElementById('jumlahStokTambah').value);
            const keterangan = document.getElementById('keteranganTambah').value;

            if (!barangId) {
                showToast('Pilih barang terlebih dahulu!', 'error');
                return;
            }
            if (!isFinite(jumlah) || jumlah <= 0) {
                showToast('Masukkan jumlah stok yang valid!', 'error');
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
                        jumlah,
                        keterangan
                    })
                });
                const result = await response.json();

                if (result.success) {
                    showToast('Stok berhasil ditambahkan!', 'success');
                    closeTambahStokModal();
                    loadBarangData();
                } else {
                    showToast('Gagal menambah stok: ' + (result.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Terjadi kesalahan saat menambah stok', 'error');
            }
        }

        // Proses kurangi stok
        async function kurangiStok() {
            const barangId = document.getElementById('barangSelectKurangi').value;
            const jumlah = parseFloat(document.getElementById('jumlahStokKurangi').value);
            const keterangan = document.getElementById('keteranganKurangi').value;

            if (!barangId) {
                showToast('Pilih barang terlebih dahulu!', 'error');
                return;
            }
            if (!isFinite(jumlah) || jumlah <= 0) {
                showToast('Masukkan jumlah stok yang valid!', 'error');
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
                        jumlah,
                        keterangan
                    })
                });
                const result = await response.json();

                if (result.success) {
                    showToast('Stok berhasil dikurangi!', 'success');
                    closeKurangiStokModal();
                    loadBarangData();
                } else {
                    showToast('Gagal: ' + (result.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Terjadi kesalahan saat mengurangi stok', 'error');
            }
        }

        // Proses update stok (absolute)
        async function updateStok() {
            const barangId = document.getElementById('editBarangId').value;
            const stokBaru = parseFloat(document.getElementById('stokBaru').value);
            const stokLama = parseFloat(document.getElementById('stokLama').value);

            if (!isFinite(stokBaru) || stokBaru < 0) {
                showToast('Masukkan jumlah stok yang valid!', 'error');
                return;
            }
            if (Math.abs(stokBaru - stokLama) < 1e-9) {
                showToast('Tidak ada perubahan stok yang dilakukan.', 'info');
                closeEditStokModal();
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
                    showToast('Stok berhasil diperbarui!', 'success');
                    closeEditStokModal();
                    loadBarangData();
                } else {
                    showToast('Gagal memperbarui stok: ' + (result.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Terjadi kesalahan saat mengupdate stok: ' + error.message, 'error');
            }
        }

        // Hapus riwayat (konfirmasi)
        function openHapusRiwayatModal(barangId = null, barangNama = null) {
            let message = '';
            if (barangId && barangId !== 'all') {
                const barang = barangData.find(b => String(b.id) === String(barangId));
                const nama = barang ? barang.nama : (barangNama || '');
                message = `Apa Anda yakin ingin menghapus semua riwayat stok untuk <strong>${nama}</strong>?\n\nTindakan ini tidak dapat dibatalkan!`;
            } else {
                message = `Apa Anda yakin ingin menghapus semua riwayat stok dari semua barang?\n\nTindakan ini tidak dapat dibatalkan!`;
            }

            showConfirm(
                message,
                async () => {
                        try {
                            const response = await fetch('stok.php?action=hapus_riwayat', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    id_barang: barangId || 'all'
                                })
                            });
                            const result = await response.json();
                            if (result.success) {
                                showToast(result.message || 'Riwayat berhasil dihapus!', 'success');
                                loadBarangData();
                            } else {
                                showToast('Gagal menghapus riwayat: ' + (result.message || ''), 'error');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            showToast('Terjadi kesalahan saat menghapus riwayat', 'error');
                        }
                    },
                    () => {
                        showToast('Aksi dibatalkan', 'info');
                    }
            );
        }

        function closeHapusRiwayatModal() {
            document.getElementById('hapusRiwayatModal').classList.add('hidden');
            document.body.classList.remove('modal-open');
        }
        async function hapusRiwayat() {
            const barangId = document.getElementById('hapusRiwayatBarangId').value;
            try {
                const response = await fetch('stok.php?action=hapus_riwayat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id_barang: (barangId === 'all' ? 'all' : barangId)
                    })
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    closeHapusRiwayatModal();
                    if (currentRiwayatBarangId && !document.getElementById('modalRiwayat').classList.contains('hidden')) {
                        if (barangId === 'all' || barangId === currentRiwayatBarangId) {
                            const barang = barangData.find(b => b.id === currentRiwayatBarangId);
                            if (barang) {
                                lihatRiwayat(currentRiwayatBarangId, barang.nama, barang.kodeProduk, barang.stok);
                            }
                        }
                    }
                    if (barangId === 'all' && !document.getElementById('modalRiwayat').classList.contains('hidden')) {
                        tutupModalRiwayat();
                    }
                } else {
                    alert('Gagal: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus riwayat');
            }
        }

        function hapusRiwayatBarang() {
            if (currentRiwayatBarangId) {
                const barang = barangData.find(b => b.id === currentRiwayatBarangId);
                if (barang) openHapusRiwayatModal(currentRiwayatBarangId, barang.nama);
            }
        }

        // Lihat riwayat
        async function lihatRiwayat(barangId, namaBarang, kodeBarang, stokSekarang) {
            currentRiwayatBarangId = barangId;
            currentRiwayatNama = namaBarang;
            currentRiwayatKode = kodeBarang;
            currentRiwayatStok = stokSekarang;

            try {
                const response = await fetch(`stok.php?action=get_riwayat&id=${barangId}`);
                const riwayat = await response.json();

                document.getElementById('modalRiwayat').classList.remove('hidden');
                document.body.classList.add('modal-open');

                document.getElementById('riwayatTitle').textContent = `Riwayat Stok: ${namaBarang}`;
                document.getElementById('riwayatInfo').textContent = `${kodeBarang} - Stok saat ini: ${stokSekarang}`;

                const tableBody = document.getElementById('riwayatTableBody');
                const emptyState = document.getElementById('riwayatEmptyState');
                tableBody.innerHTML = '';

                if (!riwayat.length) {
                    tableBody.classList.add('hidden');
                    emptyState.classList.remove('hidden');
                    return;
                }
                tableBody.classList.remove('hidden');
                emptyState.classList.add('hidden');

                riwayat.forEach(item => {
                    const row = document.createElement('tr');
                    row.className = 'fade-in';
                    const waktu = new Date(item.waktu);
                    const waktuFormatted = waktu.toLocaleString('id-ID', {
                        timeZone: 'Asia/Jakarta'
                    });

                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap">${waktuFormatted}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold ${item.jenis === 'penambahan' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">
                                ${item.jenis === 'penambahan' ? 'Penambahan' : 'Pengurangan'}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap font-bold ${item.jenis === 'penambahan' ? 'text-green-600' : 'text-red-600'}">
                            ${item.jenis === 'penambahan' ? '+' : '-'}${item.jumlah}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">${item.stok_sebelum}</td>
                        <td class="px-6 py-4 whitespace-nowrap font-bold">${item.stok_sesudah}</td>
                        <td class="px-6 py-4">${item.keterangan || '-'}</td>
                        <td class="px-6 py-4">
                            <button onclick="hapusRiwayatEntry('${item.id_barang}', '${item.waktu}')" class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-100 transition-colors" title="Hapus riwayat ini">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });

            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memuat riwayat stok');
            }
        }

        function tutupModalRiwayat() {
            document.getElementById('modalRiwayat').classList.add('hidden');
            document.body.classList.remove('modal-open');
        }

        // Filter
        function applyFilters() {
            const searchText = (document.getElementById('searchInput').value || '').toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;

            let filteredBarang = barangData;

            if (searchText) {
                filteredBarang = filteredBarang.filter(barang =>
                    (barang.nama || '').toLowerCase().includes(searchText) ||
                    (barang.kodeProduk || '').toLowerCase().includes(searchText)
                );
            }

            if (statusFilter !== 'all') {
                filteredBarang = filteredBarang.filter(barang => {
                    const status = getStatusStok(Number(barang.stok) || 0, Number(barang.stokMin) || 0).status.toLowerCase();
                    if (statusFilter === 'habis') return status === 'habis';
                    if (statusFilter === 'hampir-habis') return status === 'hampir habis';
                    if (statusFilter === 'cukup') return status === 'aman';
                    return true;
                });
            }

            renderBarangTable(filteredBarang);
        }

        function openPrintModal() {
            const modal = document.getElementById("printData");
            modal.classList.remove("hidden");
            modal.classList.add("opacity-100", "scale-100");
        }

        function closePrintModal() {
            const modal = document.getElementById("printData");
            modal.classList.add("hidden");
            modal.classList.remove("opacity-100", "scale-100");
        }

        async function printData(mode) {
            try {
                const response = await fetch("data/barang.json");
                const allBarang = await response.json();

                let dataExport;
                if (mode === "semua") {
                    dataExport = allBarang;
                } else {
                    dataExport = allBarang.filter(b => {
                        const stok = Number(b.stok) || 0;
                        const stokMin = Number(b.stokMin) || 0;
                        return stok <= stokMin;
                    });
                }

                if (!dataExport.length) {
                    showToast("Tidak ada data yang bisa dicetak", "warning");
                    return;
                }

                const res = await fetch("struk_stok_barang.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        mode
                    })
                });
                const result = await res.json();

                if (result.status === "success") {
                    showToast(result.message, "success");
                    closePrintModal();
                } else {
                    showToast("Gagal cetak: " + result.message, "error");
                }

            } catch (error) {
                console.error("Print error:", error);
                showToast("Gagal print data", "error");
            }
        }

        // Inisialisasi
        document.addEventListener('DOMContentLoaded', async function() {
            await loadSatuan(); // muat dulu supaya hint/step akurat
            await loadBarangData();

            document.getElementById('searchInput').addEventListener('keyup', applyFilters);
            document.getElementById('statusFilter').addEventListener('change', applyFilters);

            // Update step ketika pilihan barang berubah di modal
            const selTambah = document.getElementById('barangSelectTambah');
            const selKurangi = document.getElementById('barangSelectKurangi');
            if (selTambah) selTambah.addEventListener('change', () => applyStepForSelect('barangSelectTambah', 'jumlahStokTambah', 'hintTambah'));
            if (selKurangi) selKurangi.addEventListener('change', () => applyStepForSelect('barangSelectKurangi', 'jumlahStokKurangi', 'hintKurangi'));

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

        async function hapusRiwayatEntry(id_barang, waktu) {
            showConfirm(
                "Yakin ingin menghapus riwayat ini?",
                async () => {
                        try {
                            const response = await fetch("stok.php?action=hapus_riwayat_entry", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json"
                                },
                                body: JSON.stringify({
                                    id_barang,
                                    waktu
                                })
                            });
                            const result = await response.json();

                            if (result.success) {
                                showToast(result.message || "Riwayat berhasil dihapus!", "success");
                                const barang = barangData.find(b => String(b.id) === String(id_barang));
                                if (barang) {
                                    lihatRiwayat(id_barang, barang.nama, barang.kodeProduk, barang.stok);
                                }
                            } else {
                                showToast("Gagal: " + (result.message || ""), "error");
                            }
                        } catch (error) {
                            console.error("Error:", error);
                            showToast("Terjadi kesalahan saat menghapus riwayat", "error");
                        }
                    },
                    () => {
                        showToast("Aksi dibatalkan", "info");
                    }
            );
        }

        function hapusSemuaRiwayat() {
            openHapusRiwayatModal('all');
        }
    </script>
</body>

</html>