<?php
// history.php
date_default_timezone_set('Asia/Jakarta'); // SET TIMEZONE KE GMT+7

require "struk_template.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tes_printer']) && isset($_POST['transaksi'])) {
    $transaksi = json_decode($_POST['transaksi'], true);

    $items = $transaksi['items'] ?? [];
    $nama_pelanggan = $transaksi['nama_pembeli'] ?? null;
    $waktu = $transaksi['waktu'] ?? null;
    $bayar = $transaksi['bayar'] ?? null;
    $hutang = $transaksi['hutang'] ?? 0;

    $settings = json_decode(file_get_contents(__DIR__ . '/data/setting.json'), true);

    $result = cetakStruk($items, $settings, $nama_pelanggan, $waktu, $bayar, $hutang);
    echo json_encode($result);
    exit;
}

$penjualanFile = __DIR__ . "/data/penjualan.json";

// Pastikan file JSON ada
if (!file_exists($penjualanFile)) {
    file_put_contents($penjualanFile, "[]");
}

if (isset($_GET['action'])) {
    $penjualan = json_decode(file_get_contents($penjualanFile), true) ?? [];

    switch ($_GET['action']) {
        case 'get_history':
            header('Content-Type: application/json');
            echo json_encode($penjualan);
            exit;

        case 'delete_transaction':
            $id    = $_GET['id']    ?? null; // hapus by id (disarankan)
            $index = $_GET['index'] ?? null; // fallback lama (opsional)

            if ($id !== null) {
                $found = null;
                foreach ($penjualan as $i => $t) {
                    if (strval($t['id'] ?? '') === strval($id)) {
                        $found = $i;
                        break;
                    }
                }
                if ($found !== null) {
                    array_splice($penjualan, $found, 1);
                    file_put_contents($penjualanFile, json_encode($penjualan, JSON_PRETTY_PRINT));
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'ID transaksi tidak ditemukan']);
                }
                exit;
            }

            // Fallback lama: by index (bila masih dipakai)
            if ($index !== null && isset($penjualan[$index])) {
                array_splice($penjualan, $index, 1);
                file_put_contents($penjualanFile, json_encode($penjualan, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Index tidak valid']);
            }
            exit;

        case 'bulk_delete':
            $startDate = $_GET['startDate'] ?? '';
            $endDate = $_GET['endDate'] ?? '';

            if (!$startDate || !$endDate) {
                echo json_encode(['success' => false, 'error' => 'Tanggal tidak valid']);
                exit;
            }

            $startTime = strtotime($startDate . ' 00:00:00');
            $endTime = strtotime($endDate . ' 23:59:59');

            $deletedCount = 0;
            $filteredPenjualan = [];

            foreach ($penjualan as $transaction) {
                $transactionTime = strtotime($transaction['waktu']);
                if ($transactionTime >= $startTime && $transactionTime <= $endTime) {
                    $deletedCount++;
                } else {
                    $filteredPenjualan[] = $transaction;
                }
            }

            file_put_contents($penjualanFile, json_encode($filteredPenjualan, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true, 'deleted' => $deletedCount]);
            exit;
    }
}
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <title>History Transaksi - POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/rangePlugin.js"></script>
    <script src="alert.js"></script>
    <style>
        .flatpickr-day.inRange,
        .flatpickr-day.inRange:focus,
        .flatpickr-day.inRange:hover {
            background: #e5e7eb;
            border-color: #e5e7eb;
            color: #111827;
        }

        .flatpickr-day.startRange,
        .flatpickr-day.endRange {
            background: #9ca3af;
            color: #fff;
        }

        /* CSS untuk tabel sticky */
        .table-container {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            margin-bottom: 1rem;
        }

        .sticky-header {
            position: sticky;
            top: 0;
            background-color: #f3f4f6;
            z-index: 10;
        }

        .sticky-header th {
            border-bottom: 2px solid #d1d5db;
            padding: 0.75rem;
            font-weight: 600;
        }

        #detailContent .max-h-60 {
            max-height: 15rem;
        }

        #detailContent .sticky {
            position: sticky;
            top: 0;
            background-color: #f3f4f6;
            z-index: 10;
        }

        #transactionTable {
            width: 100%;
            border-collapse: collapse;
        }

        #transactionTable th,
        #transactionTable td {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
        }

        /* Menghilangkan border pada container kosong */
        #emptyTransaction {
            border: none;
        }

        /* Modal harus di atas header sticky */
        #modalDetail,
        #confirmBulkDeleteModal {
            z-index: 1000;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex">
    <!-- Sidebar -->
    <?php include "partials/sidebar.php"; ?>

    <main class="flex-1 md:ml-64 ml-0 p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold">📋 History Transaksi</h2>
        </div>

        <!-- Bulk Delete Section (Initially Hidden) -->
        <div id="bulkDeleteSection" class="bg-red-50 p-6 rounded-lg shadow-lg mb-8 border border-red-200 hidden">
            <h3 class="text-lg font-semibold mb-4 text-red-800"><i class="fas fa-exclamation-triangle mr-2"></i>Hapus Transaksi Massal</h3>
            <p class="text-red-700 mb-4">Peringatan: Tindakan ini akan menghapus permanen semua transaksi dalam rentang tanggal yang dipilih.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-red-700 mb-1">Tanggal Mulai</label>
                    <input type="text" id="bulkDeleteStart" class="w-full border border-red-300 rounded-md p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-red-700 mb-1">Tanggal Akhir</label>
                    <input type="text" id="bulkDeleteEnd" class="w-full border border-red-300 rounded-md p-2">
                </div>
                <div class="flex items-end">
                    <button onclick="confirmBulkDelete()" class="bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-500 mr-2">
                        <i class="fas fa-check mr-1"></i> Konfirmasi
                    </button>
                    <button onclick="toggleBulkDelete()" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <h3 class="text-lg font-semibold mb-4">Filter Transaksi</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                    <input type="text" id="filterStart" class="w-full border rounded-md p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                    <input type="text" id="filterEnd" class="w-full border rounded-md p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pembeli</label>
                    <input type="text" id="filterNama" placeholder="Cari nama pembeli" class="w-full border rounded-md p-2">
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="filterTransaksi()" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-500">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
            </div>
        </div>

        <!-- Daftar Transaksi -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Daftar Transaksi</h3>
                <button onclick="toggleBulkDelete()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-500">
                    <i class="fas fa-trash-alt mr-1"></i> Hapus Massal
                </button>
            </div>
            <div class="table-container">
                <table id="transactionTable" class="w-full text-left">
                    <thead>
                        <tr class="sticky-header">
                            <th class="p-3">No</th>
                            <th class="p-3">Waktu</th>
                            <th class="p-3">Pembeli</th>
                            <th class="p-3">Items</th>
                            <th class="p-3">Total</th>
                            <th class="p-3">Laba</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="daftarTransaksi">
                        <tr id="emptyTransaction">
                            <td colspan="8" class="p-4 text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- Modal Detail Transaksi -->
        <div id="modalDetail" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <h3 class="text-xl font-semibold mb-4">Detail Transaksi</h3>
                    <div id="detailContent"></div>
                    <div class="flex justify-end mt-4">
                        <button onclick="cetakStrukDetail()" class="bg-green-300 text-white-700 px-4 py-2 rounded-md hover:bg-green-400 mr-2">
                            <i class="fas fa-print mr-1"></i> Cetak Struk
                        </button>
                        <button onclick="tutupModalDetail()" class="bg-red-300 text-white-700 px-4 py-2 rounded-md hover:bg-red-400 mr-2">
                            <i class="fas fa-close mr-1"></i> Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal for Bulk Delete -->
        <div id="confirmBulkDeleteModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                <div class="p-6">
                    <h3 class="text-xl font-semibold mb-4 text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>Konfirmasi Hapus Massal</h3>
                    <p class="mb-4">Anda akan menghapus semua transaksi dari <span id="confirmDateRange" class="font-semibold"></span>. Tindakan ini tidak dapat dibatalkan.</p>
                    <p class="mb-4 font-semibold">Yakin ingin melanjutkan?</p>
                    <div class="flex justify-end">
                        <button onclick="proceedBulkDelete()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-500 mr-2">
                            <i class="fas fa-check mr-1"></i> Ya, Hapus
                        </button>
                        <button onclick="cancelBulkDelete()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                            <i class="fas fa-times mr-1"></i> Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Global variable to store current transaction data
        let currentTransactions = [];

        // Format angka ke Rupiah
        function formatRupiah(angka) {
            return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
        }

        // Fungsi untuk format tanggal Indonesia dengan GMT+7
        function formatTanggalIndonesia(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).replace(/\./g, ':'); // jaga kalau ada titik di jam
        }

        // Fungsi untuk mendapatkan tanggal dalam format YYYY-MM-DD dari string waktu (GMT+7)
        function getDatePart(dateString) {
            const date = new Date(dateString);
            date.setHours(date.getHours() + 7); // GMT+7
            return date.toISOString().split('T')[0];
        }

        // Set filter ke tanggal paling tua & paling baru dari data
        function setFilterToDataExtent(list) {
            if (!Array.isArray(list) || list.length === 0) return;

            // Ambil tanggal "YYYY-MM-DD" dari field 'waktu' (pakai helper Anda yang sudah GMT+7)
            const dates = list
                .map(t => getDatePart(t.waktu))
                .filter(Boolean)
                .sort(); // aman utk format YYYY-MM-DD

            const min = dates[0];
            const max = dates[dates.length - 1];

            // isi input
            document.getElementById('filterStart').value = formatDMY(new Date(min));
            document.getElementById('filterEnd').value = formatDMY(new Date(max));

            // update highlight Flatpickr kalau ada (fpFilter dari inisialisasi range sebelumnya)
            if (window.fpFilter && typeof fpFilter.setRange === 'function') {
                fpFilter.setRange(new Date(min), new Date(max), false);
            }

            // langsung tampilkan data sesuai rentang
            filterTransaksi();
        }


        // Muat data transaksi
        async function muatHistoryTransaksi() {
            try {
                const response = await fetch('history.php?action=get_history');
                currentTransactions = await response.json();

                // SET DEFAULT RANGE = min..max dari data
                setFilterToDataExtent(currentTransactions);

                // Kalau ingin tetap render full tanpa filter, bisa panggil langsung:
                // tampilkanHistoryTransaksi(currentTransactions);

            } catch (error) {
                console.error('Error:', error);
                document.getElementById('daftarTransaksi').innerHTML = `
            <tr id="emptyTransaction"><td colspan="8" class="p-4 text-center text-red-500">Gagal memuat data transaksi</td></tr>
            `;
            }
        }


        // Filter transaksi
        function filterTransaksi() {
            const startDate = parseDMY(document.getElementById('filterStart').value);
            const endDate = parseDMY(document.getElementById('filterEnd').value);
            const nama = document.getElementById('filterNama').value.toLowerCase();

            let filteredData = [...currentTransactions];

            if (startDate) {
                filteredData = filteredData.filter(transaksi => {
                    const transaksiDate = getDatePart(transaksi.waktu);
                    return transaksiDate >= startDate;
                });
            }

            if (endDate) {
                filteredData = filteredData.filter(transaksi => {
                    const transaksiDate = getDatePart(transaksi.waktu);
                    return transaksiDate <= endDate;
                });
            }

            if (nama) {
                filteredData = filteredData.filter(transaksi => {
                    const nm = (transaksi.nama_pembeli || '').toString().toLowerCase();
                    return nm.includes(nama);
                });
            }

            tampilkanHistoryTransaksi(filteredData);
        }

        function findTxById(id) {
            return currentTransactions.find(t => String(t.id) === String(id));
        }


        // Tampilkan history transaksi
        function tampilkanHistoryTransaksi(transaksi) {
            const container = document.getElementById('daftarTransaksi');
            if (transaksi.length === 0) {
                container.innerHTML = `
            <tr id="emptyTransaction"><td colspan="8" class="p-4 text-center text-gray-500">Tidak ada data transaksi</td></tr>
        `;
                return;
            }

            transaksi.sort((a, b) => new Date(b.waktu) - new Date(a.waktu));

            let html = '';
            transaksi.forEach((item, index) => {
                const status = item.hutang > 0 ?
                    '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">Hutang</span>' :
                    '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Lunas</span>';

                const laba = item.totalLaba !== undefined ? formatRupiah(item.totalLaba) : formatRupiah(0);

                html += `
        <tr class="border-b hover:bg-gray-50">
            <td class="p-3">${index + 1}</td>
            <td class="p-3">${formatTanggalIndonesia(item.waktu)}</td>
            <td class="p-3">${item.nama_pembeli || 'Pelanggan'}</td>
            <td class="p-3">${item.items.length} item</td>
            <td class="p-3 font-medium">${formatRupiah(item.grandTotal)}</td>
            <td class="p-3 font-medium text-green-600">${laba}</td>
            <td class="p-3">${status}</td>
            <td class="p-3 text-center">
                <button onclick="lihatDetailById('${item.id}')" class="text-blue-500 hover:text-blue-700 mr-2">
                    <i class="fas fa-eye"></i> Detail
                </button>
                <button onclick="cetakStrukById('${item.id}')" class="text-green-500 hover:text-green-700 mr-2">
                    <i class="fas fa-print"></i> Cetak
                </button>
                <button onclick="hapusTransaksiById('${item.id}')" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-trash-alt"></i> Hapus
                </button>   
            </td>
        </tr>
        `;
            });

            container.innerHTML = html;
        }

        // Hapus transaksi individual
        function hapusTransaksi(index) {
            showConfirm(
                'Apakah Anda yakin ingin menghapus transaksi ini?',
                () => { // Callback OK
                    fetch(`history.php?action=delete_transaction&index=${index}`)
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                showToast('Transaksi berhasil dihapus', 'success');
                                muatHistoryTransaksi(); // Reload data
                            } else {
                                showToast('Gagal menghapus transaksi: ' + (result.error || ''), 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('Gagal menghapus transaksi', 'error');
                        });
                },
                () => { // Callback Cancel
                    showToast('Aksi dibatalkan', 'info');
                }
            );
        }



        // Toggle bulk delete section
        function toggleBulkDelete() {
            const section = document.getElementById('bulkDeleteSection');
            section.classList.toggle('hidden');

            if (!section.classList.contains('hidden')) {
                const today = new Date();
                // set input + highlight
                if (fpBulk) fpBulk.setRange(today, today, false);
                document.getElementById('bulkDeleteStart').value = formatDMY(today);
                document.getElementById('bulkDeleteEnd').value = formatDMY(today);
            }
        }


        // Confirm bulk delete
        function confirmBulkDelete() {
            const startDate = parseDMY(document.getElementById('bulkDeleteStart').value);
            const endDate = parseDMY(document.getElementById('bulkDeleteEnd').value);

            if (!startDate || !endDate) {
                showToast('Harap pilih tanggal mulai dan tanggal akhir', 'error');
                return;
            }

            if (startDate > endDate) {
                showToast('Tanggal mulai tidak boleh lebih besar dari tanggal akhir', 'error');
                return;
            }

            // Tampilkan tanggal di modal konfirmasi
            document.getElementById('confirmDateRange').textContent =
                `${new Date(startDate).toLocaleDateString('id-ID')} hingga ${new Date(endDate).toLocaleDateString('id-ID')}`;

            // Tampilkan modal konfirmasi bulk delete
            document.getElementById('confirmBulkDeleteModal').classList.remove('hidden');
        }

        function hapusTransaksiById(id) {
            showConfirm(
                'Apakah Anda yakin ingin menghapus transaksi ini?',
                () => {
                    fetch(`history.php?action=delete_transaction&id=${encodeURIComponent(id)}`)
                        .then(r => r.json())
                        .then(result => {
                            if (result.success) {
                                showToast('Transaksi berhasil dihapus', 'success');
                                muatHistoryTransaksi(); // reload
                            } else {
                                showToast('Gagal menghapus transaksi: ' + (result.error || ''), 'error');
                            }
                        })
                        .catch(() => showToast('Gagal menghapus transaksi', 'error'));
                },
                () => {
                    showToast('Aksi dibatalkan', 'info');
                }
            );
        }

        // Cancel bulk delete
        function cancelBulkDelete() {
            document.getElementById('confirmBulkDeleteModal').classList.add('hidden');
        }

        // Proceed with bulk delete
        function proceedBulkDelete() {
            const startDate = document.getElementById('bulkDeleteStart').value;
            const endDate = document.getElementById('bulkDeleteEnd').value;

            showConfirm(
                `Apakah Anda yakin ingin menghapus semua transaksi dari ${startDate} sampai ${endDate}?`,
                () => { // Callback OK
                    fetch(`history.php?action=bulk_delete&startDate=${startDate}&endDate=${endDate}`)
                        .then(response => response.json())
                        .then(result => {
                            document.getElementById('confirmBulkDeleteModal').classList.add('hidden');

                            if (result.success) {
                                showToast(`Berhasil menghapus ${result.deleted} transaksi`, 'success');
                                toggleBulkDelete(); // Hide the bulk delete section
                                muatHistoryTransaksi(); // Reload data
                            } else {
                                showToast('Gagal menghapus transaksi: ' + (result.error || ''), 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            document.getElementById('confirmBulkDeleteModal').classList.add('hidden');
                            showToast('Gagal menghapus transaksi', 'error');
                        });
                },
                () => { // Callback Cancel
                    showToast('Aksi bulk delete dibatalkan', 'info');
                }
            );
        }


        // Lihat detail transaksi
        function lihatDetailById(id) {
            const transaksi = findTxById(id);
            if (!transaksi) return showToast('Transaksi tidak ditemukan', 'error');

            const detail = document.getElementById('detailContent');
            detail.dataset.txId = id; // simpan id untuk cetakStrukDetail()

            const totalLaba = transaksi.totalLaba ?? 0;

            let html = `
                        <div class="mb-4">
                        <h4 class="font-semibold text-lg">Informasi Transaksi</h4>
                        <p><strong>Waktu:</strong> ${formatTanggalIndonesia(transaksi.waktu)}</p>
                        <p><strong>Pembeli:</strong> ${transaksi.nama_pembeli || 'Pelanggan'}</p>
                        <p><strong>Total:</strong> ${formatRupiah(transaksi.total)}</p>
                        <p><strong>Diskon:</strong> ${formatRupiah(transaksi.diskon)}</p>
                        <p><strong>Grand Total:</strong> ${formatRupiah(transaksi.grandTotal)}</p>
                        <p><strong>Bayar:</strong> ${formatRupiah(transaksi.bayar)}</p>
                        <p><strong>Kembalian:</strong> ${formatRupiah(transaksi.kembalian)}</p>
                        <p><strong>Hutang:</strong> ${formatRupiah(transaksi.hutang)}</p>
                        <p><strong>Total Laba:</strong> <span class="text-green-600 font-medium">${formatRupiah(totalLaba)}</span></p>
                        </div>
                        <div>
                        <h4 class="font-semibold text-lg mb-2">Items</h4>
                        <div class="max-h-60 overflow-y-auto border border-gray-200">
                            <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-100 sticky top-0">
                                <th class="p-2 border">Nama Barang</th>
                                <th class="p-2 border">Harga</th>
                                <th class="p-2 border">Jenis Harga</th>
                                <th class="p-2 border">Qty</th>
                                <th class="p-2 border">Subtotal</th>
                                <th class="p-2 border">Laba</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

            transaksi.items.forEach(item => {
                const labaPerItem = item.laba ?? ((item.harga - (item.hargaModal || 0)) * item.qty);
                html += `
                        <tr>
                            <td class="p-2 border">${item.nama}</td>
                            <td class="p-2 border">${formatRupiah(item.harga)}</td>
                            <td class="p-2 border">${item.jenisHarga}</td>
                            <td class="p-2 border">${item.qty}</td>
                            <td class="p-2 border">${formatRupiah(item.harga * item.qty)}</td>
                            <td class="p-2 border text-green-600">${formatRupiah(labaPerItem)}</td>
                        </tr>
                        `;
            });

            html += `
                            </tbody>
                            </table>
                        </div>
                        </div>
                    `;

            detail.innerHTML = html;
            document.getElementById('modalDetail').classList.remove('hidden');
        }


        function tutupModalDetail() {
            document.getElementById('modalDetail').classList.add('hidden');
        }

        // helper
        function toYMD(d) {
            return d.toISOString().split('T')[0];
        }

        function endOfDay(d) {
            d.setHours(23, 59, 59, 999);
            return d;
        }

        function formatDMY(date) {
            const d = new Date(date);
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();
            return `${day}-${month}-${year}`;
        }

        function parseDMY(str) {
            // str: "dd-mm-yyyy" -> "yyyy-mm-dd"
            if (!str) return '';
            const [d, m, y] = str.split('-');
            return `${y}-${m}-${d}`;
        }


        // Inisialisasi range picker untuk pasangan input (startId, endId)
        function initDateRangePair(startId, endId, defaultStart, defaultEnd, onApplied) {
            // set default ke input bila kosong (agar defaultDate tidak error)
            const startEl = document.getElementById(startId);
            const endEl = document.getElementById(endId);

            if (!startEl.value) startEl.value = formatDMY(defaultStart);
            if (!endEl.value) endEl.value = formatDMY(defaultEnd);

            const fp = flatpickr(`#${startId}`, {
                locale: flatpickr.l10ns.id,
                dateFormat: "d-m-Y", // ✅ gunakan format dd-mm-yyyy
                altInput: false, // jangan munculkan input kedua
                plugins: [new rangePlugin({
                    input: `#${endId}`
                })],
                defaultDate: startEl.value,
                onClose: (dates) => {
                    if (dates.length === 2) {
                        const [s, e] = dates;
                        startEl.value = formatDMY(s);
                        endEl.value = formatDMY(e);
                        if (typeof onApplied === 'function') onApplied(s, endOfDay(new Date(e)));
                    }
                }
            });


            // fungsi bantu untuk update highlight dari kode lain (reset dsb.)
            return {
                setRange: (s, e, applyCallback = true) => {
                    startEl.value = formatDMY(s);
                    endEl.value = formatDMY(e);
                    fp.setDate([s, e], applyCallback); // true: trigger onClose/onChange
                }
            };
        }

        // ====== PANGGIL SAAT HALAMAN DIMUAT ======
        let fpFilter, fpBulk;

        document.addEventListener('DOMContentLoaded', function() {
            // Default pakai bulan ini (untuk fallback saja)
            const now = new Date();
            now.setHours(now.getHours() + 7);
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

            document.getElementById('filterStart').value = formatDMY(firstDay);
            document.getElementById('filterEnd').value = formatDMY(lastDay);

            // Init Flatpickr
            fpFilter = initDateRangePair(
                'filterStart', 'filterEnd',
                firstDay, lastDay,
                () => filterTransaksi()
            );

            const today = new Date();
            fpBulk = initDateRangePair(
                'bulkDeleteStart', 'bulkDeleteEnd',
                today, today,
                () => {}
            );

            // Baru load data (dan nanti set ulang min–max lewat setFilterToDataExtent)
            muatHistoryTransaksi();
        });


        function cetakStrukDetail() {
            const id = document.getElementById('detailContent').dataset.txId;
            const transaksi = findTxById(id);

            const formData = new FormData();
            formData.append('tes_printer', '1');
            formData.append('transaksi', JSON.stringify(transaksi));

            // POST ke history.php sendiri
            fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Struk berhasil dicetak!', 'success', 3000);
                    } else {
                        showToast('Gagal mencetak: ' + data.message, 'error', 5000);
                    }
                })
                .catch(err => showToast('Terjadi kesalahan saat koneksi ke printer', 'error', 5000));
        }

        function cetakStrukById(id) {
            const transaksi = findTxById(id);
            if (!transaksi) return showToast('Transaksi tidak ditemukan', 'error');

            const formData = new FormData();
            formData.append('tes_printer', '1');
            formData.append('transaksi', JSON.stringify(transaksi));

            fetch('history.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Struk berhasil dicetak!', 'success', 3000);
                    } else {
                        showToast('Gagal mencetak: ' + data.message, 'error', 5000);
                    }
                })
                .catch(() => showToast('Terjadi kesalahan saat koneksi ke printer', 'error', 5000));
        }
    </script>
</body>

</html>