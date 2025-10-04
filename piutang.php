<?php
// ======== SET TIMEZONE KE GMT+7 ==========
date_default_timezone_set('Asia/Jakarta');

// ======== BAGIAN PHP: Handle aksi untuk piutang ========== 
// Pastikan tidak ada output sebelum header
if (ob_get_level()) ob_clean();

// Enable error reporting untuk debugging (matikan di production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Jangan tampilkan error di output

// Function untuk mengembalikan response JSON dengan konsisten
function jsonResponse($data, $statusCode = 200)
{
    // Pastikan tidak ada output sebelumnya
    if (ob_get_level()) ob_clean();

    // Set headers
    header("Content-Type: application/json");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    http_response_code($statusCode);

    echo json_encode($data);
    exit;
}

$hutangFile = __DIR__ . "/data/hutang.json";
$penjualanFile = __DIR__ . "/data/penjualan.json";

// Pastikan direktori data ada
if (!file_exists(__DIR__ . "/data")) {
    if (!mkdir(__DIR__ . "/data", 0777, true)) {
        jsonResponse(['success' => false, 'message' => 'Gagal membuat direktori data'], 500);
    }
}

// Pastikan file JSON ada, jika tidak buat file kosong
if (!file_exists($hutangFile)) {
    if (file_put_contents($hutangFile, "[]") === false) {
        jsonResponse(['success' => false, 'message' => 'Gagal membuat file hutang'], 500);
    }
}

if (!file_exists($penjualanFile)) {
    if (file_put_contents($penjualanFile, "[]") === false) {
        jsonResponse(['success' => false, 'message' => 'Gagal membuat file penjualan'], 500);
    }
}

// Fungsi untuk membaca JSON dengan error handling
function bacaJSON($file)
{
    if (!file_exists($file)) {
        return [];
    }

    $content = file_get_contents($file);
    if ($content === false) {
        return [];
    }

    $data = json_decode($content, true);
    return json_last_error() === JSON_ERROR_NONE ? $data : [];
}

// Fungsi untuk menulis JSON dengan error handling
function tulisJSON($file, $data)
{
    $result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    return $result !== false;
}

// Script migrasi otomatis untuk data yang belum punya jumlah_awal
$hutang = bacaJSON($hutangFile);
$needMigration = false;

foreach ($hutang as &$item) {
    if (!isset($item['jumlah_awal'])) {
        // Buat field jumlah_awal dari jumlah_lama atau jumlah
        $item['jumlah_awal'] = isset($item['jumlah_lama']) ? $item['jumlah_lama'] : $item['jumlah'];

        // Hapus field jumlah_lama jika ada
        if (isset($item['jumlah_lama'])) {
            unset($item['jumlah_lama']);
        }

        $needMigration = true;
    }
}

if ($needMigration) {
    tulisJSON($hutangFile, $hutang);
}

// HANYA jalankan API logic jika ada parameter action
if (isset($_GET['action'])) {
    try {
        $hutang = bacaJSON($hutangFile);
        $penjualan = bacaJSON($penjualanFile);

        // Tangani input JSON
        $input = json_decode(file_get_contents("php://input"), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $input = [];
        }

        switch ($_GET['action']) {
            case 'get_hutang':
                jsonResponse($hutang);

            case 'tambah_hutang_manual':
                $nama = $input['nama'] ?? '';
                $jumlah = $input['jumlah'] ?? 0;
                $keterangan = $input['keterangan'] ?? '';
                $tanggal = $input['tanggal'] ?? date('Y-m-d H:i:s');

                if (!$nama || $jumlah <= 0) {
                    jsonResponse(['success' => false, 'message' => 'Data tidak valid'], 400);
                }

                // Pastikan format tanggal konsisten
                $tanggal = date('Y-m-d H:i:s', strtotime($tanggal));

                $newHutang = [
                    'id' => uniqid(),
                    'id_penjualan' => 'manual-' . uniqid(),
                    'nama' => $nama,
                    'jumlah' => $jumlah,
                    'jumlah_awal' => $jumlah, // Simpan sebagai jumlah_awal
                    'keterangan' => $keterangan,
                    'tanggal' => $tanggal,
                    'status' => 'belum lunas',
                    'tanggal_bayar' => null,
                    'tipe' => 'manual',
                    'perubahan' => null // Tidak ada perubahan awal
                ];

                $hutang[] = $newHutang;

                if (tulisJSON($hutangFile, $hutang)) {
                    jsonResponse(['success' => true, 'data' => $newHutang]);
                } else {
                    jsonResponse(['success' => false, 'message' => 'Gagal menyimpan data'], 500);
                }

            case 'update_status_hutang':
                $id = $input['id'] ?? '';
                $status = $input['status'] ?? '';

                $updated = false;
                foreach ($hutang as &$item) {
                    if ($item['id'] === $id) {
                        if ($status === 'lunas') {
                            $item['status'] = 'lunas';
                            $item['tanggal_bayar'] = date('Y-m-d H:i:s');
                        } else if ($status === 'belum lunas') {
                            $item['status'] = 'belum lunas';
                            $item['tanggal_bayar'] = null;
                        }
                        $updated = true;
                        break;
                    }
                }

                if ($updated && tulisJSON($hutangFile, $hutang)) {
                    jsonResponse(["success" => true]);
                } else {
                    jsonResponse(["success" => false, "message" => "Gagal update status"], 400);
                }

            case 'update_nama_pelanggan':
                $id = $input['id'] ?? '';
                $nama_baru = $input['nama_baru'] ?? '';

                $updated = false;
                foreach ($hutang as &$item) {
                    if ($item['id'] === $id) {
                        $item['nama'] = $nama_baru;
                        $updated = true;
                        break;
                    }
                }

                if ($updated && tulisJSON($hutangFile, $hutang)) {
                    jsonResponse(["success" => true]);
                } else {
                    jsonResponse(["success" => false, "message" => "Gagal update nama"], 400);
                }

            case 'update_jumlah_hutang':
                $id = $input['id'] ?? '';
                $jumlah_baru = $input['jumlah_baru'] ?? 0;
                $alasan = $input['alasan'] ?? '';

                if ($jumlah_baru <= 0) {
                    jsonResponse(['success' => false, 'message' => 'Jumlah tidak valid'], 400);
                }

                $updated = false;
                foreach ($hutang as &$item) {
                    if ($item['id'] === $id) {
                        $jumlah_lama = $item['jumlah'];
                        $selisih = $jumlah_baru - $jumlah_lama;

                        // PERBAIKAN: Buat field jumlah_awal jika belum ada
                        if (!isset($item['jumlah_awal'])) {
                            // Jika ada jumlah_lama, gunakan itu, jika tidak gunakan jumlah saat ini
                            $item['jumlah_awal'] = isset($item['jumlah_lama']) ? $item['jumlah_lama'] : $item['jumlah'];

                            // Hapus field jumlah_lama jika ada (untuk konsistensi)
                            if (isset($item['jumlah_lama'])) {
                                unset($item['jumlah_lama']);
                            }
                        }

                        // PERBAIKAN: Gunakan jumlah_awal untuk pengecekan
                        if ($jumlah_baru == $item['jumlah_awal']) {
                            $item['perubahan'] = null;
                        } else {
                            // Simpan hanya perubahan terakhir (bukan riwayat)
                            $item['perubahan'] = [
                                'tanggal' => date('Y-m-d H:i:s'),
                                'dari' => $jumlah_lama,
                                'ke' => $jumlah_baru,
                                'selisih' => $selisih,
                                'alasan' => $alasan,
                                'tipe' => $selisih > 0 ? 'tax' : 'potongan'
                            ];
                        }

                        $item['jumlah'] = $jumlah_baru;
                        $updated = true;
                        break;
                    }
                }

                if ($updated && tulisJSON($hutangFile, $hutang)) {
                    jsonResponse(["success" => true]);
                } else {
                    jsonResponse(["success" => false, "message" => "Gagal update jumlah"], 400);
                }

            case 'delete_hutang':
                $id = $input['id'] ?? '';

                // Cari index data yang akan dihapus
                $indexToDelete = -1;
                foreach ($hutang as $index => $item) {
                    if ($item['id'] === $id) {
                        $indexToDelete = $index;
                        break;
                    }
                }

                // Hapus data jika ditemukan
                if ($indexToDelete >= 0) {
                    array_splice($hutang, $indexToDelete, 1);
                    if (tulisJSON($hutangFile, $hutang)) {
                        jsonResponse(["success" => true]);
                    } else {
                        jsonResponse(["success" => false, "message" => "Gagal menghapus data"], 500);
                    }
                } else {
                    jsonResponse(["success" => false, "message" => "Data tidak ditemukan"], 404);
                }

            case 'get_detail_penjualan':
                $id_penjualan = $input['id_penjualan'] ?? '';
                $detail = [];

                foreach ($penjualan as $p) {
                    if ($p['id'] == $id_penjualan) {
                        $detail = $p;
                        break;
                    }
                }

                jsonResponse($detail);

            default:
                jsonResponse(['success' => false, 'message' => 'Action tidak dikenali'], 404);
        }
    } catch (Exception $e) {
        // Tangani error secara graceful
        jsonResponse(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
    }
}

// Jika bukan request API, tampilkan halaman HTML
// Pastikan tidak ada output sebelum ini
if (ob_get_level()) ob_clean();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <title>Kelola Piutang - POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="alert.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include "partials/sidebar.php"; ?>

    <!-- Alert Notification -->
    <div id="alertNotification" class="alert hidden">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative shadow-lg">
            <strong class="font-bold" id="alertTitle">Error!</strong>
            <span class="block sm:inline" id="alertMessage"></span>
            <button onclick="hideAlert()" class="absolute top-0 right-0 p-2">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Modal Tambah Hutang Manual -->
    <div id="modalTambahHutang" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <h3 class="text-xl font-semibold mb-4">➕ Tambah Hutang Manual</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pelanggan</label>
                    <input type="text" id="namaPelanggan" class="w-full border rounded-md p-2" placeholder="Nama pelanggan" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Hutang</label>
                    <input type="number" id="jumlahHutang" class="w-full border rounded-md p-2" placeholder="Jumlah hutang" min="1" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Transaksi</label>
                    <input type="datetime-local" id="tanggalHutang" class="w-full border rounded-md p-2" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan (Opsional)</label>
                    <textarea id="keteranganHutang" class="w-full border rounded-md p-2" placeholder="Keterangan hutang" rows="3"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button onclick="tutupModalTambahHutang()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                    Batal
                </button>
                <button onclick="tambahHutangManual()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500">
                    <i class="fas fa-save mr-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Edit Jumlah Hutang -->
    <div id="modalEditJumlah" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <h3 class="text-xl font-semibold mb-4">✏️ Edit Jumlah Hutang</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pelanggan</label>
                    <input type="text" id="editNamaPelanggan" class="w-full border rounded-md p-2 bg-gray-100" readonly>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Hutang Saat Ini</label>
                    <input type="text" id="editJumlahSaatIni" class="w-full border rounded-md p-2 bg-gray-100" readonly>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Hutang Baru</label>
                    <input type="number" id="editJumlahBaru" class="w-full border rounded-md p-2" placeholder="Masukkan jumlah baru" min="1" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Perubahan</label>
                    <select id="editAlasan" class="w-full border rounded-md p-2">
                        <option value="">Pilih alasan perubahan</option>
                        <option value="Penambahan biaya administrasi">Penambahan biaya administrasi</option>
                        <option value="Penambahan denda keterlambatan">Penambahan denda keterlambatan</option>
                        <option value="Koreksi kesalahan hitung">Koreksi kesalahan hitung</option>
                        <option value="Penyesuaian harga">Penyesuaian harga</option>
                        <option value="Potongan loyalitas">Potongan loyalitas</option>
                        <option value="Diskon khusus">Diskon khusus</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>

                <div id="alasanLainnyaContainer" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan Lainnya</label>
                    <textarea id="editAlasanLainnya" class="w-full border rounded-md p-2" placeholder="Jelaskan alasan perubahan" rows="2"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button onclick="tutupModalEditJumlah()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                    Batal
                </button>
                <button onclick="simpanEditJumlah()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500">
                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                </button>
            </div>
        </div>
    </div>

    <main class="flex-1 md:ml-64 ml-0 p-6">
        <h2 class="text-2xl font-semibold mb-4">📑 Daftar Piutang</h2>

        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Daftar Hutang Pelanggan</h3>
            </div>

            <!-- Baris Pencarian dan Filter -->
            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex flex-col md:flex-row md:items-center gap-4">
                    <div class="relative w-full md:w-64">
                        <input type="text" id="searchInput" placeholder="Cari nama atau ID..."
                            class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <div class="absolute left-3 top-2.5 text-gray-400">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button onclick="filterHutang('all')" id="filterAll" class="bg-blue-100 text-blue-700 px-4 py-2 rounded hover:bg-blue-200 transition-all">
                            <i class="fas fa-list mr-1"></i> Semua
                        </button>
                        <button onclick="filterHutang('belum lunas')" id="filterBelumLunas" class="bg-red-100 text-red-700 px-4 py-2 rounded hover:bg-red-200 transition-all">
                            <i class="fas fa-clock mr-1"></i> Belum Lunas
                        </button>
                        <button onclick="filterHutang('lunas')" id="filterLunas" class="bg-green-100 text-green-700 px-4 py-2 rounded hover:bg-green-200 transition-all">
                            <i class="fas fa-check-circle mr-1"></i> Lunas
                        </button>
                        <button onclick="tampilkanModalTambahHutang()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-500">
                            <i class="fas fa-plus-circle mr-1"></i> Tambah Hutang
                        </button>
                    </div>
                </div>

                <button onclick="resetSearch()" class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300 transition-all">
                    <i class="fas fa-sync-alt mr-1"></i> Reset
                </button>
            </div>

            <!-- Scrollable tabel -->
            <div class="overflow-x-auto border rounded-lg">
                <div class="overflow-x-auto max-h-[80vh]">
                    <table class="w-full text-left border border-gray-300">
                        <thead class="sticky top-0 bg-gray-200">
                            <tr>
                                <th class="p-3 border">ID Hutang</th>
                                <th class="p-3 border">Nama Pelanggan</th>
                                <th class="p-3 border">Jumlah Hutang</th>
                                <th class="p-3 border">Tanggal Transaksi</th>
                                <th class="p-3 border">Status</th>
                                <th class="p-3 border">Tanggal Bayar</th>
                                <th class="p-3 border">Aksi Status</th>
                                <th class="p-3 border">Aksi Lainnya</th>
                            </tr>
                        </thead>
                        <tbody id="daftarHutang">
                            <tr>
                                <td colspan="8" class="p-4 text-center text-gray-500">
                                    <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex flex-col md:flex-row md:justify-between md:items-center gap-2">
                <p class="text-sm text-gray-600" id="totalInfo"></p>
                <p class="text-sm text-gray-600" id="searchInfo"></p>
            </div>
        </div>


        <!-- Modal Detail Hutang -->
        <div id="modalDetail" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
            <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-2xl">
                <h3 class="text-xl font-semibold mb-4">Detail Transaksi</h3>
                <div id="detailContent" class="mb-4">
                    <!-- Konten detail akan diisi oleh JavaScript -->
                </div>
                <div class="flex justify-end">
                    <button onclick="tutupDetail()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-500">
                        <i class="fas fa-times mr-1"></i> Tutup
                    </button>
                </div>
            </div>
        </div>

    </main>

    <script>
        // Variabel global untuk data hutang
        let dataHutang = [];
        let filteredHutang = [];
        let currentFilter = 'all';
        let currentSearchTerm = '';
        let currentEditId = '';

        // Fungsi untuk menampilkan alert
        function showAlert(title, message, type = 'error') {
            const alert = document.getElementById('alertNotification');
            const alertTitle = document.getElementById('alertTitle');
            const alertMessage = document.getElementById('alertMessage');

            // Set background color based on type
            if (type === 'success') {
                alert.querySelector('div').className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative shadow-lg';
            } else {
                alert.querySelector('div').className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative shadow-lg';
            }

            alertTitle.textContent = title;
            alertMessage.textContent = message;
            alert.classList.remove('hidden');

            // Auto hide after 5 seconds
            setTimeout(hideAlert, 5000);
        }

        function hideAlert() {
            document.getElementById('alertNotification').classList.add('hidden');
        }

        // Fungsi untuk format tanggal Indonesia dengan GMT+7
        function formatTanggalIndonesia(dateString) {
            if (!dateString) return '-';

            try {
                // Parse tanggal dari string (format: YYYY-MM-DD HH:MM:SS)
                const [datePart, timePart] = dateString.split(' ');
                const [year, month, day] = datePart.split('-');
                const [hours, minutes, seconds] = timePart.split(':');

                // Buat objek Date dengan waktu GMT+7
                const date = new Date(year, month - 1, day, hours, minutes, seconds);

                return date.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });
            } catch (e) {
                console.error('Error formatting date:', e);
                return dateString;
            }
        }

        // Format angka ke Rupiah
        function formatRupiah(angka) {
            if (!angka || isNaN(angka)) return 'Rp 0';
            return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
        }

        // Memuat data hutang dari server
        async function muatDataHutang() {
            try {
                const response = await fetch('?action=get_hutang');

                // Periksa status response
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Periksa jika response adalah JSON yang valid
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Response bukan JSON:', text.substring(0, 200));
                    throw new Error('Response dari server bukan format JSON yang valid');
                }

                dataHutang = await response.json();

                // Pastikan dataHutang adalah array
                if (!Array.isArray(dataHutang)) {
                    console.error('Data hutang bukan array:', dataHutang);
                    dataHutang = [];
                }

                applyFiltersAndSearch();
                updateFilterButtons();
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error', 'Gagal memuat data: ' + error.message);
                document.getElementById('daftarHutang').innerHTML = `
            <tr>
                <td colspan="8" class="p-4 text-center text-red-500">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Gagal memuat data: ${error.message}
                </td>
            </tr>
        `;
            }
        }

        // Update tampilan tombol filter
        function updateFilterButtons() {
            // Reset semua tombol
            document.getElementById('filterAll').classList.remove('bg-blue-500', 'text-white');
            document.getElementById('filterAll').classList.add('bg-blue-100', 'text-blue-700');

            document.getElementById('filterBelumLunas').classList.remove('bg-red-500', 'text-white');
            document.getElementById('filterBelumLunas').classList.add('bg-red-100', 'text-red-700');

            document.getElementById('filterLunas').classList.remove('bg-green-500', 'text-white');
            document.getElementById('filterLunas').classList.add('bg-green-100', 'text-green-700');

            // Aktifkan tombol yang dipilih
            if (currentFilter === 'all') {
                document.getElementById('filterAll').classList.remove('bg-blue-100', 'text-blue-700');
                document.getElementById('filterAll').classList.add('bg-blue-500', 'text-white');
            } else if (currentFilter === 'belum lunas') {
                document.getElementById('filterBelumLunas').classList.remove('bg-red-100', 'text-red-700');
                document.getElementById('filterBelumLunas').classList.add('bg-red-500', 'text-white');
            } else if (currentFilter === 'lunas') {
                document.getElementById('filterLunas').classList.remove('bg-green-100', 'text-green-700');
                document.getElementById('filterLunas').classList.add('bg-green-500', 'text-white');
            }
        }

        // Menerapkan filter dan pencarian
        function applyFiltersAndSearch() {
            // Terapkan filter status
            if (currentFilter === 'all') {
                filteredHutang = [...dataHutang];
            } else {
                filteredHutang = dataHutang.filter(item => item.status === currentFilter);
            }

            // Terapkan pencarian jika ada
            if (currentSearchTerm) {
                const searchTerm = currentSearchTerm.toLowerCase();
                filteredHutang = filteredHutang.filter(item =>
                    item.nama.toLowerCase().includes(searchTerm) ||
                    item.id.toLowerCase().includes(searchTerm) ||
                    (item.keterangan && item.keterangan.toLowerCase().includes(searchTerm))
                );
            }

            tampilkanHutang();
            updateTotalInfo();
            updateSearchInfo();
        }

        // Menampilkan data hutang ke tabel
        function tampilkanHutang() {
            const container = document.getElementById('daftarHutang');

            if (filteredHutang.length === 0) {
                let message = 'Tidak ada data hutang';
                if (currentSearchTerm) {
                    message = `Tidak ada hasil untuk "${currentSearchTerm}"`;
                    if (currentFilter !== 'all') {
                        message += ` dengan status ${currentFilter}`;
                    }
                } else if (currentFilter !== 'all') {
                    message = `Tidak ada data dengan status ${currentFilter}`;
                }

                container.innerHTML = `
            <tr>
                <td colspan="8" class="p-4 text-center text-gray-500">
                    <i class="fas fa-search mr-2"></i> ${message}
                </td>
            </tr>
        `;
                return;
            }

            // ✅ Urutkan data terbaru di atas (descending) berdasarkan tanggal transaksi
            filteredHutang.sort((a, b) => new Date(b.tanggal) - new Date(a.tanggal));

            let html = '';
            filteredHutang.forEach(hutang => {
                const statusClass = hutang.status === 'lunas' ?
                    'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';

                const statusText = hutang.status === 'lunas' ? 'Lunas' : 'Belum Lunas';
                const tanggalBayar = hutang.tanggal_bayar ?
                    formatTanggalIndonesia(hutang.tanggal_bayar) : '-';

                const namaDisplay = currentSearchTerm ?
                    highlightText(hutang.nama, currentSearchTerm) : hutang.nama;

                const idDisplay = currentSearchTerm ?
                    highlightText(hutang.id, currentSearchTerm) : hutang.id;

                const keteranganDisplay = hutang.keterangan ?
                    `<br><small class="text-gray-500">${hutang.keterangan}</small>` : '';

                const jumlahAwal = hutang.jumlah_awal !== undefined ? hutang.jumlah_awal :
                    (hutang.jumlah_lama !== undefined ? hutang.jumlah_lama : hutang.jumlah);

                const adaPerubahan = hutang.perubahan && parseInt(hutang.jumlah) !== parseInt(jumlahAwal);

                const jumlahDisplay = adaPerubahan ? `
            <div class="flex flex-col">
                <span class="font-medium">${formatRupiah(hutang.jumlah)}</span>
                <small class="text-xs ${hutang.perubahan.selisih > 0 ? 'text-red-600' : 'text-green-600'}">
                    ${hutang.perubahan.selisih > 0 ? '+' : ''}${formatRupiah(hutang.perubahan.selisih)} 
                    (${hutang.perubahan.tipe === 'tax' ? 'Tax' : 'Potongan'})
                </small>
                <small class="text-xs text-gray-500">Awal: ${formatRupiah(jumlahAwal)}</small>
            </div>` :
                    formatRupiah(hutang.jumlah);

                html += `
            <tr class="border-b hover:bg-gray-50" data-id="${hutang.id}">
                <td class="p-3 border">${idDisplay}</td>
                <td class="p-3 border nama-pelanggan">
                    <div class="flex items-center justify-between">
                        <span>${namaDisplay}${keteranganDisplay}</span>
                        <button onclick="editNama('${hutang.id}', '${hutang.nama.replace(/'/g, "\\'")}')" 
                                class="ml-2 text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </td>
                <td class="p-3 border font-medium jumlah-hutang">
                    <div class="flex items-center justify-between">
                        ${jumlahDisplay}
                        <button onclick="editJumlah('${hutang.id}', '${hutang.nama.replace(/'/g, "\\'")}', ${hutang.jumlah})" 
                                class="ml-2 text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </td>
                <td class="p-3 border">${formatTanggalIndonesia(hutang.tanggal)}</td>
                <td class="p-3 border">
                    <span class="text-xs px-2 py-1 rounded ${statusClass}">${statusText}</span>
                </td>
                <td class="p-3 border">${tanggalBayar}</td>
                <td class="p-3 border">
                    <div class="flex gap-2">
                        ${hutang.tipe !== 'manual' ? `
                        <button onclick="lihatDetail('${hutang.id}', '${hutang.id_penjualan}')" class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-sm hover:bg-blue-200">
                            <i class="fas fa-eye text-xs"></i>
                        </button>` : ''}
                        ${hutang.status !== 'lunas' ? `
                        <button onclick="updateStatus('${hutang.id}', 'lunas')" class="bg-green-100 text-green-600 px-2 py-1 rounded text-sm hover:bg-green-200">
                            <i class="fas fa-check text-xs"></i> Lunas
                        </button>` : `
                        <button onclick="updateStatus('${hutang.id}', 'belum lunas')" class="bg-yellow-100 text-yellow-600 px-2 py-1 rounded text-sm hover:bg-yellow-200">
                            <i class="fas fa-undo text-xs"></i> Batal
                        </button>`}
                    </div>
                </td>
                <td class="p-3 border">
                    <button onclick="hapusHutang('${hutang.id}', '${hutang.nama.replace(/'/g, "\\'")}')" 
                            class="bg-red-100 text-red-600 px-2 py-1 rounded text-sm hover:bg-red-200">
                        <i class="fas fa-trash text-xs"></i> Hapus
                    </button>
                </td>
            </tr>
        `;
            });

            container.innerHTML = html;
        }


        // Fungsi untuk menyoroti teks hasil pencarian
        function highlightText(text, searchTerm) {
            if (!searchTerm) return text;

            const regex = new RegExp(`(${searchTerm})`, 'gi');
            return text.replace(regex, '<span class="bg-yellow-200">$1</span>');
        }

        // Fungsi untuk mengedit nama pelanggan
        function editNama(id, namaSekarang) {
            const baris = document.querySelector(`tr[data-id="${id}"]`);
            const selNama = baris.querySelector('.nama-pelanggan');

            // Ganti teks dengan input field
            selNama.innerHTML = `
                <div class="flex items-center">
                    <input type="text" value="${namaSekarang}" 
                           class="border p-1 rounded w-full" 
                           id="input-nama-${id}">
                    <button onclick="simpanNama('${id}')" class="ml-2 text-green-600 hover:text-green-800">
                        <i class="fas fa-check"></i>
                    </button>
                    <button onclick="batalkanEditNama('${id}', '${namaSekarang.replace(/'/g, "\\'")}')" class="ml-1 text-red-600 hover:text-red-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            // Fokus ke input field
            setTimeout(() => {
                const input = document.getElementById(`input-nama-${id}`);
                if (input) input.focus();
            }, 100);
        }

        // Fungsi untuk menyimpan nama yang diedit
        async function simpanNama(id) {
            const input = document.getElementById(`input-nama-${id}`);
            if (!input) return;

            const namaBaru = input.value.trim();

            if (!namaBaru) {
                showAlert('Error', 'Nama pelanggan tidak boleh kosong!');
                return;
            }

            try {
                const response = await fetch('?action=update_nama_pelanggan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: id,
                        nama_baru: namaBaru
                    })
                });

                // Validasi response JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('Response dari server bukan JSON: ' + text.substring(0, 100));
                }

                const result = await response.json();

                if (result.success) {
                    // Perbarui UI tanpa reload seluruh halaman
                    const baris = document.querySelector(`tr[data-id="${id}"]`);
                    const namaDisplay = currentSearchTerm ?
                        highlightText(namaBaru, currentSearchTerm) : namaBaru;

                    baris.querySelector('.nama-pelanggan').innerHTML = `
                        <div class="flex items-center justify-between">
                            <span>${namaDisplay}</span>
                            <button onclick="editNama('${id}', '${namaBaru.replace(/'/g, "\\'")}')" 
                                    class="ml-2 text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    `;

                    // Perbarui juga data di memori
                    const item = dataHutang.find(h => h.id === id);
                    if (item) {
                        item.nama = namaBaru;
                    }

                    showAlert('Sukses', 'Nama pelanggan berhasil diubah!', 'success');
                } else {
                    showAlert('Error', 'Gagal mengubah nama pelanggan!');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error', 'Terjadi kesalahan: ' + error.message);
            }
        }

        // Fungsi untuk membatalkan edit nama
        function batalkanEditNama(id, namaLama) {
            const baris = document.querySelector(`tr[data-id="${id}"]`);
            const namaDisplay = currentSearchTerm ?
                highlightText(namaLama, currentSearchTerm) : namaLama;

            baris.querySelector('.nama-pelanggan').innerHTML = `
                <div class="flex items-center justify-between">
                    <span>${namaDisplay}</span>
                    <button onclick="editNama('${id}', '${namaLama.replace(/'/g, "\\'")}')" 
                            class="ml-2 text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            `;
        }

        // Fungsi untuk mengedit jumlah hutang
        function editJumlah(id, nama, jumlahSekarang) {
            currentEditId = id;

            // Isi form edit
            document.getElementById('editNamaPelanggan').value = nama;
            document.getElementById('editJumlahSaatIni').value = formatRupiah(jumlahSekarang);
            document.getElementById('editJumlahBaru').value = jumlahSekarang;
            document.getElementById('editAlasan').value = '';
            document.getElementById('editAlasanLainnya').value = '';
            document.getElementById('alasanLainnyaContainer').classList.add('hidden');

            // Tampilkan modal
            document.getElementById('modalEditJumlah').classList.remove('hidden');
        }

        // Handle perubahan select alasan
        function handleAlasanChange() {
            const alasanLainnyaContainer = document.getElementById('alasanLainnyaContainer');
            const select = document.getElementById('editAlasan');
            alasanLainnyaContainer.classList.toggle('hidden', select.value !== 'Lainnya');
        }

        // Tutup modal edit jumlah
        function tutupModalEditJumlah() {
            document.getElementById('modalEditJumlah').classList.add('hidden');
            currentEditId = '';
        }

        // Simpan perubahan jumlah hutang
        async function simpanEditJumlah() {
            const jumlahBaru = document.getElementById('editJumlahBaru').value.trim();
            const alasanSelect = document.getElementById('editAlasan').value;
            const alasanLainnya = document.getElementById('editAlasanLainnya').value.trim();

            let alasan = alasanSelect;
            if (alasanSelect === 'Lainnya' && alasanLainnya) {
                alasan = alasanLainnya;
            }

            // Validasi jumlah
            if (!jumlahBaru || isNaN(jumlahBaru) || parseInt(jumlahBaru) <= 0) {
                showToast('Jumlah hutang harus berupa angka yang valid!', 'error');
                return;
            }

            // Validasi alasan
            if (!alasan) {
                showToast('Harap pilih atau isi alasan perubahan!', 'warning');
                return;
            }

            try {
                const response = await fetch('?action=update_jumlah_hutang', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: currentEditId,
                        jumlah_baru: parseInt(jumlahBaru),
                        alasan: alasan
                    })
                });

                // Pastikan response JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('Response dari server bukan JSON: ' + text.substring(0, 100));
                }

                const result = await response.json();

                if (result.success) {
                    showToast('Jumlah hutang berhasil diubah.', 'success');
                    tutupModalEditJumlah();
                    muatDataHutang(); // refresh tabel
                } else {
                    showToast('Gagal mengubah jumlah hutang: ' + (result.message || 'Tidak diketahui'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Terjadi masalah saat menghubungi server: ' + error.message, 'error');
            }
        }

        // Filter data hutang berdasarkan status
        function filterHutang(status) {
            currentFilter = status;
            applyFiltersAndSearch();
            updateFilterButtons();
        }

        // Fungsi pencarian
        function cariHutang() {
            currentSearchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
            applyFiltersAndSearch();
        }

        // Reset pencarian dan filter
        function resetSearch() {
            document.getElementById('searchInput').value = '';
            currentSearchTerm = '';
            currentFilter = 'all';
            applyFiltersAndSearch();
            updateFilterButtons();
        }

        // Update informasi total hutang
        function updateTotalInfo() {
            const totalHutang = dataHutang
                .filter(item => item.status === 'belum lunas')
                .reduce((sum, item) => sum + parseInt(item.jumlah), 0);

            const totalItem = filteredHutang.length;
            const totalSemua = dataHutang.length;

            document.getElementById('totalInfo').innerHTML = `
                Menampilkan ${totalItem} dari ${totalSemua} item | 
                Total Hutang Belum Lunas: <span class="font-semibold text-red-600">${formatRupiah(totalHutang)}</span>
            `;
        }

        // Update informasi pencarian
        function updateSearchInfo() {
            const searchInfo = document.getElementById('searchInfo');

            if (currentSearchTerm) {
                let infoText = `Pencarian: "${currentSearchTerm}"`;
                if (currentFilter !== 'all') {
                    infoText += ` | Status: ${currentFilter}`;
                }
                searchInfo.innerHTML = infoText;
            } else if (currentFilter !== 'all') {
                searchInfo.innerHTML = `Status: ${currentFilter}`;
            } else {
                searchInfo.innerHTML = '';
            }
        }

        // Update status hutang (lunas/belum lunas)
        function updateStatus(id, status) {
            showConfirm(
                `Apakah Anda yakin ingin mengubah status hutang ini menjadi ${status === 'lunas' ? 'lunas' : 'belum lunas'}?`,
                async () => { // callback OK bisa async
                        try {
                            const response = await fetch('?action=update_status_hutang', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    id,
                                    status
                                })
                            });

                            const contentType = response.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                const text = await response.text();
                                throw new Error('Response dari server bukan JSON: ' + text.substring(0, 100));
                            }

                            const result = await response.json();

                            if (result.success) {
                                showToast('Status berhasil diubah!', 'success');
                                muatDataHutang();
                            } else {
                                showToast('Gagal mengubah status!', 'error');
                            }
                        } catch (error) {
                            console.error(error);
                            showToast('Terjadi kesalahan: ' + error.message, 'error');
                        }
                    },
                    () => {
                        showToast("Aksi dibatalkan", "info");
                    }
            );
        }


        // Lihat detail transaksi
        async function lihatDetail(id_hutang, id_penjualan) {
            try {
                const response = await fetch('?action=get_detail_penjualan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id_penjualan: id_penjualan
                    })
                });

                // Periksa jika response adalah JSON yang valid
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Response dari server bukan JSON');
                }

                const detail = await response.json();

                // Cari data hutang yang sesuai
                const hutang = dataHutang.find(h => h.id === id_hutang);
                tampilkanDetail(detail, hutang);
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error', 'Gagal memuat detail transaksi!');
            }
        }

        // Tampilkan modal detail dengan informasi perubahan
        function tampilkanDetail(detail, hutang) {
            const container = document.getElementById('detailContent');

            // Pastikan detail adalah objek yang valid
            if (!detail || typeof detail !== 'object') {
                container.innerHTML = `
                    <div class="text-red-500">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Data detail transaksi tidak valid
                    </div>
                `;
                document.getElementById('modalDetail').classList.remove('hidden');
                return;
            }

            let html = `
            <div class="mb-4">
                <h4 class="font-semibold">ID Transaksi: ${detail.id || 'Tidak diketahui'}</h4>
                <p>Tanggal: ${detail.waktu ? formatTanggalIndonesia(detail.waktu) : 'Tidak diketahui'}</p>
                <p>Nama Pembeli: ${detail.nama_pembeli || 'Pelanggan'}</p>
            </div>
            `;

            // Tampilkan items jika ada
            if (detail.items && Array.isArray(detail.items)) {
                html += `
                <table class="w-full border border-gray-300 mb-4">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="p-2 border">Barang</th>
                            <th class="p-2 border">Harga</th>
                            <th class="p-2 border">Qty</th>
                            <th class="p-2 border">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                `;

                detail.items.forEach(item => {
                    const subtotal = (item.harga || 0) * (item.qty || 0);
                    html += `
                    <tr class="border-b">
                        <td class="p-2 border">${item.nama || 'Tidak diketahui'}</td>
                        <td class="p-2 border">${formatRupiah(item.harga || 0)}</td>
                        <td class="p-2 border">${item.qty || 0}</td>
                        <td class="p-2 border">${formatRupiah(subtotal)}</td>
                    </tr>
                    `;
                });

                html += `</tbody></table>`;
            }

            // Hitung total asli dari transaksi
            const totalAsli = detail.items && Array.isArray(detail.items) ?
                detail.items.reduce((sum, item) => sum + ((item.harga || 0) * (item.qty || 0)), 0) : 0;

            html += `
                <div class="border-t pt-2">
                    <div class="flex justify-between mb-1">
                        <span>Total Transaksi:</span>
                        <span>${formatRupiah(totalAsli)}</span>
                    </div>
            `;

            // Tampilkan perubahan jika ada
            if (hutang && hutang.perubahan && hutang.jumlah !== hutang.jumlah_awal) {
                html += `
                    <div class="flex justify-between mb-1 ${hutang.perubahan.selisih > 0 ? 'text-red-600' : 'text-green-600'}">
                        <span>${hutang.perubahan.selisih > 0 ? 'Penambahan (Tax)' : 'Pengurangan (Potongan)'}:</span>
                        <span>${hutang.perubahan.selisih > 0 ? '+' : ''}${formatRupiah(hutang.perubahan.selisih)}</span>
                    </div>
                    <div class="text-sm text-gray-600 mb-2">
                        <i class="fas fa-info-circle mr-1"></i> Alasan: ${hutang.perubahan.alasan || 'Tidak disebutkan'}
                    </div>
                `;
            }

            // Tampilkan informasi lainnya
            const diskon = detail.diskon || 0;
            const grandTotal = detail.grandTotal || totalAsli;
            const bayar = detail.bayar || 0;
            const kembalian = detail.kembalian !== undefined ? detail.kembalian : (bayar - grandTotal);

            html += `
                    <div class="flex justify-between mb-1">
                        <span>Diskon:</span>
                        <span>${formatRupiah(diskon)}</span>
                    </div>
                    
                    <div class="flex justify-between font-semibold text-lg">
                        <span>Grand Total:</span>
                        <span>${formatRupiah(grandTotal)}</span>
                    </div>
                    
                    <div class="flex justify-between mt-2">
                        <span>Bayar:</span>
                        <span>${formatRupiah(bayar)}</span>
                    </div>
                    
                    <div class="flex justify-between ${kembalian >= 0 ? 'text-green-600' : 'text-red-600'}">
                        <span>${kembalian >= 0 ? 'Kembalian' : 'Hutang'}:</span>
                        <span>${formatRupiah(Math.abs(kembalian))}</span>
                    </div>
                </div>
            `;

            container.innerHTML = html;
            document.getElementById('modalDetail').classList.remove('hidden');
        }

        // Tutup modal detail
        function tutupDetail() {
            document.getElementById('modalDetail').classList.add('hidden');
        }

        // Tampilkan modal tambah hutang manual
        function tampilkanModalTambahHutang() {
            const modal = document.getElementById('modalTambahHutang');
            modal.classList.remove('hidden');

            // Reset form dan set nilai default
            document.getElementById('namaPelanggan').value = '';
            document.getElementById('jumlahHutang').value = '';
            document.getElementById('keteranganHutang').value = '';

            // Set tanggal dan waktu saat ini sebagai default (format: YYYY-MM-DDTHH:MM)
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');

            document.getElementById('tanggalHutang').value = `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        // Tambah hutang manual
        async function tambahHutangManual() {
            const nama = document.getElementById('namaPelanggan').value.trim();
            const jumlah = document.getElementById('jumlahHutang').value.trim();
            const keterangan = document.getElementById('keteranganHutang').value.trim();
            const tanggalInput = document.getElementById('tanggalHutang').value;

            if (!nama || !jumlah || !tanggalInput) {
                showToast('Nama pelanggan, jumlah hutang, dan tanggal harus diisi!', 'error');
                return;
            }

            if (isNaN(jumlah) || parseInt(jumlah) <= 0) {
                showToast('Jumlah hutang harus berupa angka yang valid!', 'error');
                return;
            }

            try {
                const formattedDate = tanggalInput.replace('T', ' ') + ':00';

                const response = await fetch('?action=tambah_hutang_manual', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        nama: nama,
                        jumlah: parseInt(jumlah),
                        keterangan: keterangan,
                        tanggal: formattedDate
                    })
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('Response dari server bukan JSON: ' + text.substring(0, 100));
                }

                const result = await response.json();

                if (result.success) {
                    showToast('Hutang berhasil ditambahkan!', 'success');
                    tutupModalTambahHutang();
                    muatDataHutang();
                } else {
                    showToast('Gagal menambahkan hutang: ' + (result.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Terjadi kesalahan: ' + error.message, 'error');
            }
        }


        // Tutup modal tambah hutang
        function tutupModalTambahHutang() {
            document.getElementById('modalTambahHutang').classList.add('hidden');
        }

        // Hapus hutang
        function hapusHutang(id, nama) {
            showConfirm(
                `Apakah Anda yakin ingin menghapus hutang atas nama "${nama}"?`,
                async () => { // callback OK bisa async
                        try {
                            const response = await fetch('?action=delete_hutang', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    id
                                })
                            });

                            // Validasi response JSON
                            const contentType = response.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                const text = await response.text();
                                throw new Error('Response dari server bukan JSON: ' + text.substring(0, 100));
                            }

                            const result = await response.json();

                            if (result.success) {
                                showToast('Hutang berhasil dihapus!', 'success');
                                muatDataHutang(); // Reload data
                            } else {
                                showToast('Gagal menghapus hutang: ' + (result.message || ''), 'error');
                            }
                        } catch (error) {
                            console.error(error);
                            showToast('Terjadi kesalahan: ' + error.message, 'error');
                        }
                    },
                    () => {
                        showToast('Aksi dibatalkan', 'info'); // Callback cancel (opsional)
                    }
            );
        }


        // Muat data saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            muatDataHutang();

            // Tambahkan event listener untuk pencarian real-time
            document.getElementById('searchInput').addEventListener('input', cariHutang);

            // Tambahkan event listener untuk tombol Enter di input pencarian
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    cariHutang();
                }
            });

            // Event listener untuk select alasan
            document.getElementById('editAlasan').addEventListener('change', handleAlasanChange);
        });
    </script>

</body>

</html>