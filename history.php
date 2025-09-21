<?php
// history.php
date_default_timezone_set('Asia/Jakarta'); // SET TIMEZONE KE GMT+7

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
            $index = $_GET['index'] ?? null;
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

            // Tambahkan waktu untuk mencakup seluruh hari
            $startDateTime = $startDate . ' 00:00:00';
            $endDateTime = $endDate . ' 23:59:59';

            $deletedCount = 0;
            $filteredPenjualan = [];

            foreach ($penjualan as $transaction) {
                $transactionDate = $transaction['waktu'];

                if ($transactionDate >= $startDateTime && $transactionDate <= $endDateTime) {
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
</head>

<body class="bg-gray-100 min-h-screen flex">
    <!-- Sidebar -->
    <?php include "partials/sidebar.php"; ?>

    <main class="flex-1 p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold">📋 History Transaksi</h2>
            <button onclick="toggleBulkDelete()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-500">
                <i class="fas fa-trash-alt mr-1"></i> Hapus Massal
            </button>
        </div>

        <!-- Bulk Delete Section (Initially Hidden) -->
        <div id="bulkDeleteSection" class="bg-red-50 p-6 rounded-lg shadow-lg mb-8 border border-red-200 hidden">
            <h3 class="text-lg font-semibold mb-4 text-red-800"><i class="fas fa-exclamation-triangle mr-2"></i>Hapus Transaksi Massal</h3>
            <p class="text-red-700 mb-4">Peringatan: Tindakan ini akan menghapus permanen semua transaksi dalam rentang tanggal yang dipilih.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-red-700 mb-1">Tanggal Mulai</label>
                    <input type="date" id="bulkDeleteStart" class="w-full border border-red-300 rounded-md p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-red-700 mb-1">Tanggal Akhir</label>
                    <input type="date" id="bulkDeleteEnd" class="w-full border border-red-300 rounded-md p-2">
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
                    <input type="date" id="filterStart" class="w-full border rounded-md p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                    <input type="date" id="filterEnd" class="w-full border rounded-md p-2">
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
            <h3 class="text-lg font-semibold mb-4">Daftar Transaksi</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3 border-b">No</th>
                            <th class="p-3 border-b">Waktu</th>
                            <th class="p-3 border-b">Pembeli</th>
                            <th class="p-3 border-b">Items</th>
                            <th class="p-3 border-b">Total</th>
                            <th class="p-3 border-b">Status</th>
                            <th class="p-3 border-b">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="daftarTransaksi">
                        <tr>
                            <td colspan="7" class="p-4 text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Detail Transaksi -->
        <div id="modalDetail" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <h3 class="text-xl font-semibold mb-4">Detail Transaksi</h3>
                    <div id="detailContent"></div>
                    <div class="flex justify-end mt-4">
                        <button onclick="tutupModalDetail()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 mr-2">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal for Bulk Delete -->
        <div id="confirmBulkDeleteModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50">
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
            // Tambahkan 7 jam untuk GMT+7
            date.setHours(date.getHours() + 7);

            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Fungsi untuk mendapatkan tanggal dalam format YYYY-MM-DD dari string waktu (GMT+7)
        function getDatePart(dateString) {
            const date = new Date(dateString);
            date.setHours(date.getHours() + 7); // GMT+7
            return date.toISOString().split('T')[0];
        }

        // Muat data transaksi
        async function muatHistoryTransaksi() {
            try {
                const response = await fetch('history.php?action=get_history');
                currentTransactions = await response.json();
                tampilkanHistoryTransaksi(currentTransactions);
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('daftarTransaksi').innerHTML = `
                <tr><td colspan="7" class="p-4 text-center text-red-500">Gagal memuat data transaksi</td></tr>
            `;
            }
        }

        // Filter transaksi
        function filterTransaksi() {
            const startDate = document.getElementById('filterStart').value;
            const endDate = document.getElementById('filterEnd').value;
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
                    return transaksi.nama_pembeli.toLowerCase().includes(nama);
                });
            }

            tampilkanHistoryTransaksi(filteredData);
        }

        // Tampilkan history transaksi
        function tampilkanHistoryTransaksi(transaksi) {
            const container = document.getElementById('daftarTransaksi');

            if (transaksi.length === 0) {
                container.innerHTML = `
                <tr><td colspan="7" class="p-4 text-center text-gray-500">Tidak ada data transaksi</td></tr>
            `;
                return;
            }

            // Urutkan dari yang terbaru
            transaksi.sort((a, b) => new Date(b.waktu) - new Date(a.waktu));

            let html = '';
            transaksi.forEach((item, index) => {
                // Find the original index in the global currentTransactions array
                const originalIndex = currentTransactions.findIndex(t =>
                    t.waktu === item.waktu &&
                    t.nama_pembeli === item.nama_pembeli &&
                    t.grandTotal === item.grandTotal
                );

                const status = item.hutang > 0 ?
                    '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">Hutang</span>' :
                    '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Lunas</span>';

                html += `
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3">${index + 1}</td>
                    <td class="p-3">${formatTanggalIndonesia(item.waktu)}</td>
                    <td class="p-3">${item.nama_pembeli || 'Pelanggan'}</td>
                    <td class="p-3">${item.items.length} item</td>
                    <td class="p-3 font-medium">${formatRupiah(item.grandTotal)}</td>
                    <td class="p-3">${status}</td>
                    <td class="p-3">
                        <button onclick="lihatDetail(${originalIndex})" class="text-blue-500 hover:text-blue-700 mr-2">
                            <i class="fas fa-eye"></i> Detail
                        </button>
                        <button onclick="hapusTransaksi(${originalIndex})" class="text-red-500 hover:text-red-700">
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
            if (confirm('Apakah Anda yakin ingin menghapus transaksi ini?')) {
                fetch(`history.php?action=delete_transaction&index=${index}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            alert('Transaksi berhasil dihapus');
                            muatHistoryTransaksi(); // Reload data
                        } else {
                            alert('Gagal menghapus transaksi: ' + result.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Gagal menghapus transaksi');
                    });
            }
        }

        // Toggle bulk delete section
        function toggleBulkDelete() {
            const section = document.getElementById('bulkDeleteSection');
            section.classList.toggle('hidden');

            // Reset dates when showing
            if (!section.classList.contains('hidden')) {
                document.getElementById('bulkDeleteStart').value = '';
                document.getElementById('bulkDeleteEnd').value = '';
            }
        }

        // Confirm bulk delete
        function confirmBulkDelete() {
            const startDate = document.getElementById('bulkDeleteStart').value;
            const endDate = document.getElementById('bulkDeleteEnd').value;

            if (!startDate || !endDate) {
                alert('Harap pilih tanggal mulai dan tanggal akhir');
                return;
            }

            if (startDate > endDate) {
                alert('Tanggal mulai tidak boleh lebih besar dari tanggal akhir');
                return;
            }

            // Show confirmation modal
            document.getElementById('confirmDateRange').textContent =
                `${new Date(startDate).toLocaleDateString('id-ID')} hingga ${new Date(endDate).toLocaleDateString('id-ID')}`;
            document.getElementById('confirmBulkDeleteModal').classList.remove('hidden');
        }

        // Cancel bulk delete
        function cancelBulkDelete() {
            document.getElementById('confirmBulkDeleteModal').classList.add('hidden');
        }

        // Proceed with bulk delete
        function proceedBulkDelete() {
            const startDate = document.getElementById('bulkDeleteStart').value;
            const endDate = document.getElementById('bulkDeleteEnd').value;

            fetch(`history.php?action=bulk_delete&startDate=${startDate}&endDate=${endDate}`)
                .then(response => response.json())
                .then(result => {
                    document.getElementById('confirmBulkDeleteModal').classList.add('hidden');

                    if (result.success) {
                        alert(`Berhasil menghapus ${result.deleted} transaksi`);
                        toggleBulkDelete(); // Hide the bulk delete section
                        muatHistoryTransaksi(); // Reload data
                    } else {
                        alert('Gagal menghapus transaksi: ' + result.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('confirmBulkDeleteModal').classList.add('hidden');
                    alert('Gagal menghapus transaksi');
                });
        }

        // Lihat detail transaksi
        function lihatDetail(index) {
            const transaksi = currentTransactions[index];
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
            </div>
            
            <div>
                <h4 class="font-semibold text-lg mb-2">Items</h4>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-2 border">Nama Barang</th>
                            <th class="p-2 border">Harga</th>
                            <th class="p-2 border">Jenis Harga</th>
                            <th class="p-2 border">Qty</th>
                            <th class="p-2 border">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

            transaksi.items.forEach(item => {
                html += `
                <tr>
                    <td class="p-2 border">${item.nama}</td>
                    <td class="p-2 border">${formatRupiah(item.harga)}</td>
                    <td class="p-2 border">${item.jenisHarga}</td>
                    <td class="p-2 border">${item.qty}</td>
                    <td class="p-2 border">${formatRupiah(item.harga * item.qty)}</td>
                </tr>
            `;
            });

            html += `
                    </tbody>
                </table>
            </div>
        `;

            document.getElementById('detailContent').innerHTML = html;
            document.getElementById('modalDetail').classList.remove('hidden');
        }

        function tutupModalDetail() {
            document.getElementById('modalDetail').classList.add('hidden');
        }

        // Muat data saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Set default filter dates to current month (GMT+7)
            const now = new Date();
            now.setHours(now.getHours() + 7); // GMT+7

            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

            document.getElementById('filterStart').value = firstDay.toISOString().split('T')[0];
            document.getElementById('filterEnd').value = lastDay.toISOString().split('T')[0];

            muatHistoryTransaksi();
        });
    </script>
</body>

</html>