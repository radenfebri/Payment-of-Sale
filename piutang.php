<?php
// ======== SET TIMEZONE KE GMT+7 ==========
date_default_timezone_set('Asia/Jakarta');

// ======== BAGIAN PHP: Handle aksi untuk piutang ========== 
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

$hutangFile = __DIR__ . "/data/hutang.json";
$penjualanFile = __DIR__ . "/data/penjualan.json";

// Pastikan direktori data ada
if (!file_exists(__DIR__ . "/data")) {
    mkdir(__DIR__ . "/data", 0777, true);
}

// Pastikan file JSON ada, jika tidak buat file kosong
if (!file_exists($hutangFile)) file_put_contents($hutangFile, "[]");
if (!file_exists($penjualanFile)) file_put_contents($penjualanFile, "[]");

if (isset($_GET['action'])) {
    $hutang = json_decode(file_get_contents($hutangFile), true) ?? [];
    $penjualan = json_decode(file_get_contents($penjualanFile), true) ?? [];
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($_GET['action']) {
        case 'get_hutang':
            header('Content-Type: application/json');
            echo json_encode($hutang);
            exit;

        case 'tambah_hutang_manual':
            $nama = $input['nama'] ?? '';
            $jumlah = $input['jumlah'] ?? 0;
            $keterangan = $input['keterangan'] ?? '';
            $tanggal = $input['tanggal'] ?? date('Y-m-d H:i:s');

            if (!$nama || $jumlah <= 0) {
                echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
                exit;
            }

            // Pastikan format tanggal konsisten
            $tanggal = date('Y-m-d H:i:s', strtotime($tanggal));

            $newHutang = [
                'id' => uniqid(),
                'id_penjualan' => 'manual-' . uniqid(),
                'nama' => $nama,
                'jumlah' => $jumlah,
                'keterangan' => $keterangan,
                'tanggal' => $tanggal,
                'status' => 'belum lunas',
                'tanggal_bayar' => null,
                'tipe' => 'manual'
            ];

            $hutang[] = $newHutang;
            file_put_contents($hutangFile, json_encode($hutang, JSON_PRETTY_PRINT));

            echo json_encode(['success' => true, 'data' => $newHutang]);
            exit;

        case 'update_status_hutang':
            $id = $input['id'] ?? '';
            $status = $input['status'] ?? '';

            foreach ($hutang as &$item) {
                if ($item['id'] === $id) {
                    if ($status === 'lunas') {
                        $item['status'] = 'lunas';
                        $item['tanggal_bayar'] = date('Y-m-d H:i:s');
                    } else if ($status === 'belum lunas') {
                        $item['status'] = 'belum lunas';
                        $item['tanggal_bayar'] = null;
                    }
                    break;
                }
            }

            file_put_contents($hutangFile, json_encode($hutang, JSON_PRETTY_PRINT));

            header('Content-Type: application/json');
            echo json_encode(["success" => true]);
            exit;

        case 'update_nama_pelanggan':
            $id = $input['id'] ?? '';
            $nama_baru = $input['nama_baru'] ?? '';

            foreach ($hutang as &$item) {
                if ($item['id'] === $id) {
                    $item['nama'] = $nama_baru;
                    break;
                }
            }

            file_put_contents($hutangFile, json_encode($hutang, JSON_PRETTY_PRINT));

            header('Content-Type: application/json');
            echo json_encode(["success" => true]);
            exit;

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
                file_put_contents($hutangFile, json_encode($hutang, JSON_PRETTY_PRINT));
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["success" => false, "message" => "Data tidak ditemukan"]);
            }
            exit;

        case 'get_detail_penjualan':
            $id_penjualan = $input['id_penjualan'] ?? '';
            $detail = [];

            foreach ($penjualan as $p) {
                if ($p['id'] == $id_penjualan) {
                    $detail = $p;
                    break;
                }
            }

            header('Content-Type: application/json');
            echo json_encode($detail);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <title>Kelola Piutang - POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include "partials/sidebar.php"; ?>

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

    <main class="flex-1 p-6">
        <h2 class="text-2xl font-semibold mb-4">📋 Daftar Piutang</h2>

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

            <div class="overflow-x-auto">
                <table class="w-full text-left border border-gray-300">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="p-3 border">ID Hutang</th>
                            <th class="p-3 border">Nama Pelanggan</th>
                            <th class="p-3 border">Jumlah Hutang</th>
                            <th class="p-3 border">Tanggal Transaksi</th>
                            <th class="p-3 border">Status</th>
                            <th class="p-3 border">Tanggal Bayar</th>
                            <th class="p-3 border">Aksi Status</th>
                            <th class="p-3 border">Aksi Hapus</th>
                        </tr>
                    </thead>
                    <tbody id="daftarHutang">
                        <!-- Data hutang akan ditampilkan di sini -->
                        <tr>
                            <td colspan="7" class="p-4 text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
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

        // Fungsi untuk format tanggal Indonesia dengan GMT+7 (DIPERBAIKI)
        function formatTanggalIndonesia(dateString) {
            if (!dateString) return '-';

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
        }


        // Format input datetime-local ke format yang diinginkan (DIPERBAIKI)
        function formatDateTimeForInput(dateTimeString) {
            if (!dateTimeString) return '';

            // Parse tanggal dari string (format: YYYY-MM-DD HH:MM:SS)
            const [datePart, timePart] = dateTimeString.split(' ');
            const [year, month, day] = datePart.split('-');
            const [hours, minutes] = timePart.split(':');

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }


        // Format angka ke Rupiah
        function formatRupiah(angka) {
            return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
        }

        // Memuat data hutang dari server
        async function muatDataHutang() {
            try {
                const response = await fetch('?action=get_hutang');
                dataHutang = await response.json();
                applyFiltersAndSearch();
                updateFilterButtons();
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('daftarHutang').innerHTML = `
                <tr>
                    <td colspan="7" class="p-4 text-center text-red-500">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Gagal memuat data
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

        // Menampilkan data hutang ke tabel (diperbarui dengan tombol hapus)
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

            let html = '';
            filteredHutang.forEach(hutang => {
                const statusClass = hutang.status === 'lunas' ?
                    'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';

                const statusText = hutang.status === 'lunas' ?
                    'Lunas' : 'Belum Lunas';

                const tanggalBayar = hutang.tanggal_bayar ?
                    formatTanggalIndonesia(hutang.tanggal_bayar) : '-';

                // Highlight hasil pencarian
                const namaDisplay = currentSearchTerm ?
                    highlightText(hutang.nama, currentSearchTerm) : hutang.nama;

                const idDisplay = currentSearchTerm ?
                    highlightText(hutang.id, currentSearchTerm) : hutang.id;

                // Tampilkan keterangan jika ada (untuk hutang manual)
                const keteranganDisplay = hutang.keterangan ?
                    `<br><small class="text-gray-500">${hutang.keterangan}</small>` : '';

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
            <td class="p-3 border font-medium">${formatRupiah(hutang.jumlah)}</td>
            <td class="p-3 border">${formatTanggalIndonesia(hutang.tanggal)}</td>
            <td class="p-3 border">
                <span class="text-xs px-2 py-1 rounded ${statusClass}">${statusText}</span>
            </td>
            <td class="p-3 border">${tanggalBayar}</td>
            <td class="p-3 border">
                <div class="flex gap-2">
                    ${hutang.tipe !== 'manual' ? `
                    <button onclick="lihatDetail('${hutang.id_penjualan}')" class="bg-blue-100 text-blue-600 px-2 py-1 rounded text-sm hover:bg-blue-200">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                    ` : ''}
                    ${hutang.status !== 'lunas' ? `
                    <button onclick="updateStatus('${hutang.id}', 'lunas')" class="bg-green-100 text-green-600 px-2 py-1 rounded text-sm hover:bg-green-200">
                        <i class="fas fa-check text-xs"></i> Lunas
                    </button>
                    ` : `
                    <button onclick="updateStatus('${hutang.id}', 'belum lunas')" class="bg-yellow-100 text-yellow-600 px-2 py-1 rounded text-sm hover:bg-yellow-200">
                        <i class="fas fa-undo text-xs"></i> Batal
                    </button>
                    `}
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
                document.getElementById(`input-nama-${id}`).focus();
            }, 100);
        }

        // Fungsi untuk menyimpan nama yang diedit
        async function simpanNama(id) {
            const namaBaru = document.getElementById(`input-nama-${id}`).value.trim();

            if (!namaBaru) {
                alert('Nama pelanggan tidak boleh kosong!');
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
                } else {
                    alert('Gagal mengubah nama pelanggan!');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            }
        }

        // Fungsi untuk membatalkan edit
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
        async function updateStatus(id, status) {
            if (!confirm(`Apakah Anda yakin ingin mengubah status hutang ini menjadi ${status === 'lunas' ? 'lunas' : 'belum lunas'}?`)) {
                return;
            }

            try {
                const response = await fetch('?action=update_status_hutang', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: id,
                        status: status
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Status berhasil diubah!');
                    muatDataHutang(); // Reload data
                } else {
                    alert('Gagal mengubah status!');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            }
        }

        // Lihat detail transaksi
        async function lihatDetail(id_penjualan) {
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

                const detail = await response.json();
                tampilkanDetail(detail);
            } catch (error) {
                console.error('Error:', error);
                alert('Gagal memuat detail transaksi!');
            }
        }

        // Tampilkan modal detail
        function tampilkanDetail(detail) {
            const container = document.getElementById('detailContent');

            let html = `
            <div class="mb-4">
                <h4 class="font-semibold">ID Transaksi: ${detail.id}</h4>
                <p>Tanggal: ${formatTanggalIndonesia(detail.waktu)}</p>
                <p>Nama Pembeli: ${detail.nama_pembeli || 'Pelanggan'}</p>
            </div>
            
            <table class="w-full border border-gray-300 mb-4">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2 border">Barang</th>
                        <th class="p-2 border">Harga</th>
                        <th class="p-2 border">Qty</th>
                        <th class="p-2 border">Jenis</th>
                        <th class="p-2 border">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
        `;

            detail.items.forEach(item => {
                html += `
                <tr class="border-b">
                    <td class="p-2 border">${item.nama}</td>
                    <td class="p-2 border">${formatRupiah(item.harga)}</td>
                    <td class="p-2 border">${item.qty}</td>
                    <td class="p-2 border">${item.jenisHarga}</td>
                    <td class="p-2 border">${formatRupiah(item.harga * item.qty)}</td>
                </tr>
            `;
            });

            html += `
                </tbody>
            </table>
            
            <div class="border-t pt-2">
                <div class="flex justify-between mb-1">
                    <span>Total:</span>
                    <span>${formatRupiah(detail.total)}</span>
                </div>
                <div class="flex justify-between mb-1">
                    <span>Diskon:</span>
                    <span>${formatRupiah(detail.diskon)}</span>
                </div>
                <div class="flex justify-between font-semibold text-lg">
                    <span>Grand Total:</span>
                    <span>${formatRupiah(detail.grandTotal)}</span>
                </div>
                <div class="flex justify-between mt-2">
                    <span>Bayar:</span>
                    <span>${formatRupiah(detail.bayar)}</span>
                </div>
                <div class="flex justify-between ${detail.kembalian >= 0 ? 'text-green-600' : 'text-red-600'}">
                    <span>${detail.kembalian >= 0 ? 'Kembalian' : 'Hutang'}:</span>
                    <span>${formatRupiah(Math.abs(detail.kembalian))}</span>
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

        // Tampilkan modal tambah hutang manual (DIPERBAIKI)
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

        // Tambah hutang manual (DIPERBAIKI)
        async function tambahHutangManual() {
            const nama = document.getElementById('namaPelanggan').value.trim();
            const jumlah = document.getElementById('jumlahHutang').value.trim();
            const keterangan = document.getElementById('keteranganHutang').value.trim();
            const tanggalInput = document.getElementById('tanggalHutang').value;

            if (!nama || !jumlah || !tanggalInput) {
                alert('Nama pelanggan, jumlah hutang, dan tanggal harus diisi!');
                return;
            }

            if (isNaN(jumlah) || parseInt(jumlah) <= 0) {
                alert('Jumlah hutang harus berupa angka yang valid!');
                return;
            }

            try {
                // Konversi format datetime-local (YYYY-MM-DDTHH:MM) ke format database (YYYY-MM-DD HH:MM:SS)
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

                const result = await response.json();

                if (result.success) {
                    alert('Hutang berhasil ditambahkan!');
                    tutupModalTambahHutang();
                    muatDataHutang(); // Reload data
                } else {
                    alert('Gagal menambahkan hutang: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            }
        }

        // Tutup modal tambah hutang
        function tutupModalTambahHutang() {
            document.getElementById('modalTambahHutang').classList.add('hidden');
        }

        // Tambah hutang manual
        async function tambahHutangManual() {
            const nama = document.getElementById('namaPelanggan').value.trim();
            const jumlah = document.getElementById('jumlahHutang').value.trim();
            const keterangan = document.getElementById('keteranganHutang').value.trim();
            const tanggal = document.getElementById('tanggalHutang').value;

            if (!nama || !jumlah || !tanggal) {
                alert('Nama pelanggan, jumlah hutang, dan tanggal harus diisi!');
                return;
            }

            if (isNaN(jumlah) || parseInt(jumlah) <= 0) {
                alert('Jumlah hutang harus berupa angka yang valid!');
                return;
            }

            try {
                const response = await fetch('?action=tambah_hutang_manual', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        nama: nama,
                        jumlah: parseInt(jumlah),
                        keterangan: keterangan,
                        tanggal: tanggal.replace('T', ' ') + ':00'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Hutang berhasil ditambahkan!');
                    tutupModalTambahHutang();
                    muatDataHutang(); // Reload data
                } else {
                    alert('Gagal menambahkan hutang: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            }
        }


        // Hapus hutang
        async function hapusHutang(id, nama) {
            if (!confirm(`Apakah Anda yakin ingin menghapus hutang atas nama "${nama}"?`)) {
                return;
            }

            try {
                const response = await fetch('?action=delete_hutang', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: id
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Hutang berhasil dihapus!');
                    muatDataHutang(); // Reload data
                } else {
                    alert('Gagal menghapus hutang: ' + (result.message || ''));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            }
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
        });
    </script>

</body>

</html>