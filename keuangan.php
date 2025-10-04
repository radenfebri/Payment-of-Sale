<?php
// ======== SET TIMEZONE KE GMT+7 ==========
date_default_timezone_set('Asia/Jakarta');

// ======== BAGIAN PHP: Handle aksi untuk laporan keuangan ========== 
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

$barangFile = __DIR__ . "/data/barang.json";
$penjualanFile = __DIR__ . "/data/penjualan.json";
$hutangFile = __DIR__ . "/data/hutang.json";
$keuanganFile = __DIR__ . "/data/keuangan.json";

// Pastikan file JSON ada, jika tidak buat file kosong
if (!file_exists($barangFile)) file_put_contents($barangFile, "[]");
if (!file_exists($penjualanFile)) file_put_contents($penjualanFile, "[]");
if (!file_exists($hutangFile)) file_put_contents($hutangFile, "[]");
if (!file_exists($keuanganFile)) file_put_contents($keuanganFile, "[]");

if (isset($_GET['action'])) {
    $barang = json_decode(file_get_contents($barangFile), true) ?? [];
    $penjualan = json_decode(file_get_contents($penjualanFile), true) ?? [];
    $hutang = json_decode(file_get_contents($hutangFile), true) ?? [];
    $keuangan = json_decode(file_get_contents($keuanganFile), true) ?? [];
    $input = json_decode(file_get_contents("php://input"), true);

    function syncAuto()
    {
        global $penjualan, $keuangan, $penjualanFile, $keuanganFile;

        $updated = false;
        $count = 0;

        foreach ($penjualan as &$p) {
            $alreadyRecorded = isset($p['recorded_in_keuangan']) && $p['recorded_in_keuangan'] === true;
            if ($alreadyRecorded) continue;

            $totalLaba = $p['totalLaba'] ?? $p['grandTotal'];
            $hutang = $p['hutang'] ?? 0;

            $nama = $p['nama_pembeli'] ?? 'Pelanggan';
            $keterangan = 'Penjualan: ' . $nama;
            if ($hutang > 0) {
                $keterangan .= $hutang == ($p['grandTotal'] ?? 0) ? " (Hutang penuh: $hutang)" : " (Sebagian, hutang: $hutang)";
            }

            $newTransaction = [
                'id' => uniqid(),
                'jenis' => 'pemasukan',
                'jumlah' => $totalLaba, // catat seluruh laba
                'keterangan' => $keterangan,
                'tanggal' => $p['waktu'] ?? date('Y-m-d H:i:s')
            ];

            $keuangan[] = $newTransaction;
            $p['recorded_in_keuangan'] = true;
            $updated = true;
            $count++;
        }

        if ($updated) {
            file_put_contents($keuanganFile, json_encode($keuangan, JSON_PRETTY_PRINT));
            file_put_contents($penjualanFile, json_encode($penjualan, JSON_PRETTY_PRINT));
        }

        return $count;
    }


    switch ($_GET['action']) {
        case 'get_dashboard_data':
            // Hitung total penjualan hari ini
            $today = date('Y-m-d');
            $penjualanHariIni = 0;
            $transaksiHariIni = 0;

            foreach ($penjualan as $p) {
                $tanggalTransaksi = date('Y-m-d', strtotime($p['waktu']));
                if ($tanggalTransaksi === $today) {
                    $penjualanHariIni += $p['grandTotal'];
                    $transaksiHariIni++;
                }
            }

            // Hitung total piutang
            $totalPiutang = 0;
            foreach ($hutang as $h) {
                if ($h['status'] === 'belum lunas') {
                    $totalPiutang += $h['jumlah'];
                }
            }

            // Hitung total penjualan bulan ini
            $currentMonth = date('Y-m');
            $penjualanBulanIni = 0;

            foreach ($penjualan as $p) {
                $tanggalTransaksi = date('Y-m', strtotime($p['waktu']));
                if ($tanggalTransaksi === $currentMonth) {
                    $penjualanBulanIni += $p['grandTotal'];
                }
            }

            // Produk terlaris (top 5)
            $produkTerjual = [];
            foreach ($penjualan as $p) {
                foreach ($p['items'] as $item) {
                    $produkId = $item['id'];
                    if (!isset($produkTerjual[$produkId])) {
                        $produkTerjual[$produkId] = [
                            'nama' => $item['nama'],
                            'terjual' => 0,
                            'pendapatan' => 0
                        ];
                    }
                    $produkTerjual[$produkId]['terjual'] += $item['qty'];
                    $produkTerjual[$produkId]['pendapatan'] += ($item['harga'] * $item['qty']);
                }
            }

            // Urutkan berdasarkan jumlah terjual
            usort($produkTerjual, function ($a, $b) {
                return $b['terjual'] - $a['terjual'];
            });

            $topProduk = array_slice($produkTerjual, 0, 5);

            // Data untuk chart 7 hari terakhir
            $chartData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $chartData[$date] = 0;
            }

            foreach ($penjualan as $p) {
                $tanggalTransaksi = date('Y-m-d', strtotime($p['waktu']));
                if (array_key_exists($tanggalTransaksi, $chartData)) {
                    $chartData[$tanggalTransaksi] += $p['grandTotal'];
                }
            }

            // Transaksi terbaru (10 transaksi)
            $transaksiTerbaru = array_slice(array_reverse($penjualan), 0, 10);

            // Pelanggan dengan piutang terbanyak
            $piutangPelanggan = [];
            foreach ($hutang as $h) {
                if ($h['status'] === 'belum lunas') {
                    $nama = $h['nama'];
                    if (!isset($piutangPelanggan[$nama])) {
                        $piutangPelanggan[$nama] = 0;
                    }
                    $piutangPelanggan[$nama] += $h['jumlah'];
                }
            }

            arsort($piutangPelanggan);
            $topPiutang = array_slice($piutangPelanggan, 0, 5, true);

            // Hitung saldo hanya dari keuangan.json
            $saldo = 0;
            foreach ($keuangan as $trx) {
                if ($trx['jenis'] === 'pemasukan') {
                    $saldo += $trx['jumlah'];
                } else {
                    $saldo -= $trx['jumlah'];
                }
            }


            header('Content-Type: application/json');
            echo json_encode([
                'penjualanHariIni' => $penjualanHariIni,
                'transaksiHariIni' => $transaksiHariIni,
                'totalPiutang' => $totalPiutang,
                'penjualanBulanIni' => $penjualanBulanIni,
                'topProduk' => $topProduk,
                'chartData' => $chartData,
                'transaksiTerbaru' => $transaksiTerbaru,
                'topPiutang' => $topPiutang,
                'saldo' => $saldo
            ]);
            exit;

        case 'get_laporan_bulanan':
            $bulanTahun = $input['bulan_tahun'] ?? date('Y-m');
            list($tahun, $bulan) = explode('-', $bulanTahun);

            // Filter penjualan berdasarkan bulan dan tahun
            $penjualanBulanan = array_filter($penjualan, function ($p) use ($tahun, $bulan) {
                $tanggalTransaksi = date('Y-m', strtotime($p['waktu']));
                return $tanggalTransaksi === "$tahun-$bulan";
            });

            // Filter transaksi keuangan berdasarkan bulan dan tahun
            $keuanganBulanan = array_filter($keuangan, function ($k) use ($tahun, $bulan) {
                $tanggal = $k['tanggal'];
                if (strpos($tanggal, 'T') !== false) {
                    // Format ISO, ambil bagian tanggal saja
                    $tanggal = substr($tanggal, 0, 10);
                }
                $tanggalTransaksi = date('Y-m', strtotime($tanggal));
                return $tanggalTransaksi === "$tahun-$bulan";
            });

            // Hitung total penjualan
            $totalPenjualan = array_reduce($penjualanBulanan, function ($sum, $p) {
                return $sum + $p['grandTotal'];
            }, 0);

            // Hitung total laba dari penjualan
            $totalLaba = array_reduce($penjualanBulanan, function ($sum, $p) {
                return $sum + ($p['totalLaba'] ?? $p['grandTotal']);
            }, 0);

            // Hitung total transaksi
            $totalTransaksi = count($penjualanBulanan);

            // Hitung total piutang
            $piutangBulanan = 0;
            foreach ($penjualanBulanan as $p) {
                if (!empty($p['hutang']) && $p['hutang'] > 0) {
                    $piutangBulanan += $p['hutang'];
                }
            }

            // Hitung pemasukan & pengeluaran lain (hindari double-count penjualan)
            $pemasukanLain = 0;
            $pengeluaranLain = 0;

            foreach ($keuanganBulanan as $k) {
                $jenis = $k['jenis'] ?? '';
                $ket   = $k['keterangan'] ?? '';
                $isPenjualan = stripos($ket, 'Penjualan:') === 0;

                if ($jenis === 'pemasukan' && !$isPenjualan) {
                    $pemasukanLain += $k['jumlah'];
                } elseif ($jenis === 'pengeluaran') {
                    $pengeluaranLain += $k['jumlah'];
                }
            }

            // Total pemasukan = laba penjualan + pemasukan lain
            $totalPemasukan   = $totalLaba + $pemasukanLain;
            $totalPengeluaran = $pengeluaranLain;
            $saldoBulanan     = $totalPemasukan - $totalPengeluaran;

            // Produk terlaris bulanan
            $produkTerjualBulanan = [];
            foreach ($penjualanBulanan as $p) {
                foreach ($p['items'] as $item) {
                    $produkId = $item['id'];
                    if (!isset($produkTerjualBulanan[$produkId])) {
                        $produkTerjualBulanan[$produkId] = [
                            'nama' => $item['nama'],
                            'terjual' => 0,
                            'pendapatan' => 0
                        ];
                    }
                    $produkTerjualBulanan[$produkId]['terjual'] += $item['qty'];
                    $produkTerjualBulanan[$produkId]['pendapatan'] += ($item['harga'] * $item['qty']);
                }
            }

            // Urutkan berdasarkan jumlah terjual
            usort($produkTerjualBulanan, function ($a, $b) {
                return $b['terjual'] - $a['terjual'];
            });

            $topProdukBulanan = array_slice($produkTerjualBulanan, 0, 5);

            header('Content-Type: application/json');
            echo json_encode([
                'totalPenjualan'   => $totalPenjualan,
                'totalLaba'        => $totalLaba,
                'totalTransaksi'   => $totalTransaksi,
                'piutangBulanan'   => $piutangBulanan,
                'pemasukanLain'    => $pemasukanLain,
                'pengeluaranLain'  => $pengeluaranLain,
                'totalPemasukan'   => $totalPemasukan,
                'totalPengeluaran' => $totalPengeluaran,
                'saldoBulanan'     => $saldoBulanan,
                'topProdukBulanan' => $topProdukBulanan,
                'transaksiKeuangan' => $keuanganBulanan
            ]);
            exit;

        case 'auto_sync_keuangan':
            $count = syncAuto();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'count' => $count,
                'message' => "Berhasil sinkronisasi $count transaksi penjualan ke keuangan"
            ]);
            exit;

        case 'get_keuangan_data':
            syncAuto();
            header('Content-Type: application/json');
            echo json_encode($keuangan);
            exit;

        case 'tambah_transaksi_keuangan':
            $jenis = $input['jenis'] ?? '';
            $jumlah = $input['jumlah'] ?? 0;
            $keterangan = $input['keterangan'] ?? '';
            $tanggal = $input['tanggal'] ?? date('Y-m-d H:i:s');

            if (!$jenis || $jumlah <= 0) {
                echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
                exit;
            }

            // Konversi tanggal ke format yang konsisten dengan timezone
            $tanggalObj = new DateTime($tanggal, new DateTimeZone('Asia/Jakarta'));
            $tanggalFormatted = $tanggalObj->format('Y-m-d H:i:s');

            $newTransaction = [
                'id' => uniqid(),
                'jenis' => $jenis,
                'jumlah' => $jumlah,
                'keterangan' => $keterangan,
                'tanggal' => $tanggalFormatted  // Simpan dengan format yang konsisten
            ];

            $keuangan[] = $newTransaction;
            file_put_contents($keuanganFile, json_encode($keuangan, JSON_PRETTY_PRINT));

            echo json_encode(['success' => true, 'data' => $newTransaction]);
            exit;

        case 'update_keuangan':
            $newKeuanganData = $input; // Data yang dikirim dari frontend

            // Pastikan semua tanggal dalam format yang konsisten
            foreach ($newKeuanganData as &$transaction) {
                if (isset($transaction['tanggal']) && strpos($transaction['tanggal'], 'T') !== false) {
                    // Konversi dari format ISO ke format datetime konsisten
                    $date = new DateTime($transaction['tanggal']);
                    $transaction['tanggal'] = $date->format('Y-m-d H:i:s');
                }
            }

            file_put_contents($keuanganFile, json_encode($newKeuanganData, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
            exit;

        case 'sync_saldo_penjualan':
            $updated = false;
            $count = 0;
            $debug = [];

            foreach ($penjualan as &$p) {
                $hasDebt = isset($p['hutang']) && $p['hutang'] > 0;
                $alreadyRecorded = isset($p['recorded_in_keuangan']) && $p['recorded_in_keuangan'] === true;

                $debug[] = [
                    'id' => $p['id'],
                    'waktu' => $p['waktu'],
                    'grandTotal' => $p['grandTotal'],
                    'totalLaba' => $p['totalLaba'] ?? 'tidak ada',
                    'hutang' => $p['hutang'] ?? 0,
                    'hasDebt' => $hasDebt,
                    'alreadyRecorded' => $alreadyRecorded,
                    'shouldSync' => !$alreadyRecorded && !$hasDebt
                ];

                if (!$alreadyRecorded) {
                    $newTransaction = [
                        'id' => uniqid(),
                        'jenis' => 'pemasukan',
                        'jumlah' => $p['totalLaba'] ?? $p['grandTotal'],
                        'keterangan' => 'Penjualan: ' . ($p['nama_pembeli'] ?? 'Pelanggan') .
                            ($p['hutang'] > 0 ? ' (Hutang: ' . $p['hutang'] . ')' : ''),
                        'tanggal' => $p['waktu'] ?? date('Y-m-d H:i:s')
                    ];

                    $keuangan[] = $newTransaction;
                    $p['recorded_in_keuangan'] = true;
                    $updated = true;
                    $count++;
                }
            }

            if ($updated) {
                file_put_contents($keuanganFile, json_encode($keuangan, JSON_PRETTY_PRINT));
                file_put_contents($penjualanFile, json_encode($penjualan, JSON_PRETTY_PRINT));
            }

            echo json_encode([
                'success' => true,
                'updated' => $updated,
                'count' => $count,
                'message' => $updated ? "Berhasil menambahkan $count transaksi keuangan dari penjualan" : "Tidak ada penjualan lunas yang belum tercatat",
                'debug' => $debug // Hapus ini di production
            ]);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <title>Laporan Keuangan - POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="alert.js"></script>
    <style>
        #modalEdit {
            transition: opacity 0.3s ease;
        }

        #modalEdit.hidden {
            opacity: 0;
            pointer-events: none;
        }

        #modalEdit>div {
            transform: scale(1);
            transition: transform 0.3s ease;
        }

        #modalEdit.hidden>div {
            transform: scale(0.9);
        }

        /* Responsive design untuk select bulan */
        @media (max-width: 768px) {
            .flex-col.md\:flex-row {
                flex-direction: column;
            }

            .w-full.md\:w-48 {
                width: 100%;
                margin-bottom: 12px;
            }

            .flex.gap-2 {
                width: 100%;
                justify-content: space-between;
            }

            .flex.gap-2 button {
                flex: 1;
                margin: 0 4px;
            }
        }

        /* Style untuk optgroup */
        optgroup {
            font-weight: bold;
        }

        optgroup option {
            font-weight: normal;
            padding-left: 10px;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex">
    <!-- Sidebar -->
    <?php include "partials/sidebar.php"; ?>

    <main class="flex-1 md:ml-64 ml-0 p-6">
        <h2 class="text-2xl font-semibold mb-6">💰 Dashboard Keuangan</h2>

        <!-- Ringkasan Keuangan -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-shopping-cart text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">Penjualan Hari Ini</p>
                        <h3 class="text-2xl font-bold" id="penjualanHariIni">Rp 0</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2" id="transaksiHariIni">0 transaksi</p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">Penjualan Bulan Ini</p>
                        <h3 class="text-2xl font-bold" id="penjualanBulanIni">Rp 0</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2">Sampai hari ini</p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                        <i class="fas fa-money-bill-wave text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">Total Piutang</p>
                        <h3 class="text-2xl font-bold" id="totalPiutang">Rp 0</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2">Belum diterima</p>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                        <i class="fas fa-wallet text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">Saldo</p>
                        <h3 class="text-2xl font-bold" id="saldo">Rp 0</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2">Saldo terkini</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Grafik Pendapatan -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-4">Pendapatan 7 Hari Terakhir</h3>
                <canvas id="revenueChart"></canvas>
            </div>

            <!-- Produk Terlaris -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-4">5 Produk Terlaris</h3>
                <div id="topProducts">
                    <div class="flex justify-center items-center h-40">
                        <i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Transaksi Terbaru -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-4">Transaksi Terbaru</h3>
                <div class="overflow-x-auto">
                    <!-- Atur tinggi tetap untuk 7 baris data -->
                    <div class="h-[320px] overflow-y-auto">
                        <table class="w-full text-left border-collapse text-base">
                            <thead class="sticky top-0 bg-gray-100 z-10 text-sm">
                                <tr>
                                    <th class="px-4 py-3 border-b border-gray-200">Waktu</th>
                                    <th class="px-4 py-3 border-b border-gray-200">Customer</th>
                                    <th class="px-4 py-3 border-b border-gray-200">Total</th>
                                    <th class="px-4 py-3 border-b border-gray-200">Status</th>
                                </tr>
                            </thead>
                            <tbody id="recentTransactions" class="text-sm">
                                <tr>
                                    <td colspan="4" class="p-4 text-center text-gray-500">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Piutang Terbanyak -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-4">Pelanggan dengan Piutang Terbanyak</h3>
                <div id="topDebtors">
                    <div class="flex justify-center items-center h-40">
                        <i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Tambah Transaksi Keuangan -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <h3 class="text-lg font-semibold mb-4">Tambah Transaksi Keuangan</h3>
            <form id="formTransaksiKeuangan" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Transaksi</label>
                    <select name="jenis" class="w-full border rounded-md p-2" required>
                        <option value="pemasukan">Pemasukan</option>
                        <option value="pengeluaran">Pengeluaran</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp)</label>
                    <input type="number" name="jumlah" min="1" step="1" class="w-full border rounded-md p-2" placeholder="0" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                    <input type="text" name="keterangan" class="w-full border rounded-md p-2" placeholder="Deskripsi transaksi" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                    <input type="datetime-local" name="tanggal" class="w-full border rounded-md p-2" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
                <div class="md:col-span-4 flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500">
                        <i class="fas fa-save mr-1"></i> Tambah Transaksi
                    </button>
                </div>
            </form>

            <!-- Daftar Transaksi Keuangan -->
            <div class="mt-8">
                <h3 class="text-lg font-semibold mb-4">Daftar Transaksi Keuangan</h3>
                <div class="overflow-x-auto border rounded-lg">
                    <div class="overflow-y-auto max-h-96"> <!-- scroll vertikal -->
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-gray-100 z-10">
                                <tr>
                                    <th class="p-3 border-b">Tanggal</th>
                                    <th class="p-3 border-b">Jenis</th>
                                    <th class="p-3 border-b">Keterangan</th>
                                    <th class="p-3 border-b">Jumlah</th>
                                    <th class="p-3 border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="daftarTransaksi">
                                <tr>
                                    <td colspan="5" class="p-4 text-center text-gray-500">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Edit Transaksi -->
        <div id="modalEdit" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                <div class="p-6">
                    <h3 class="text-xl font-semibold mb-4">Edit Transaksi Keuangan</h3>
                    <form id="formEditTransaksi">
                        <input type="hidden" id="editId" name="id">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Transaksi</label>
                            <select id="editJenis" name="jenis" class="w-full border rounded-md p-2" required>
                                <option value="pemasukan">Pemasukan</option>
                                <option value="pengeluaran">Pengeluaran</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah (Rp)</label>
                            <input type="number" id="editJumlah" name="jumlah" min="1" step="1" class="w-full border rounded-md p-2" placeholder="0" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                            <input type="text" id="editKeterangan" name="keterangan" class="w-full border rounded-md p-2" placeholder="Deskripsi transaksi" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                            <input type="datetime-local" id="editTanggal" name="tanggal" class="w-full border rounded-md p-2" required>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="tutupModalEdit()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                                Batal
                            </button>
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Laporan Bulanan -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <h3 class="text-lg font-semibold mb-4">Laporan Bulanan</h3>
            <div class="flex flex-col md:flex-row gap-4 mb-4 items-start md:items-center">
                <div class="w-full md:w-auto">
                    <select id="selectMonth" class="border rounded-md p-2 w-full md:w-48">
                        <?php
                        $months = [
                            '01' => 'Jan',
                            '02' => 'Feb',
                            '03' => 'Mar',
                            '04' => 'Apr',
                            '05' => 'Mei',
                            '06' => 'Jun',
                            '07' => 'Jul',
                            '08' => 'Ags',
                            '09' => 'Sep',
                            '10' => 'Okt',
                            '11' => 'Nov',
                            '12' => 'Des'
                        ];
                        $currentYear = date('Y');
                        $currentMonth = date('m');

                        // Hanya tampilkan 2 tahun terakhir
                        for ($year = $currentYear; $year >= $currentYear - 1; $year--) {
                            echo "<optgroup label='Tahun $year'>";
                            foreach ($months as $num => $name) {
                                $selected = ($year == $currentYear && $num == $currentMonth) ? 'selected' : '';
                                echo "<option value='$year-$num' $selected>$name $year</option>";
                            }
                            echo "</optgroup>";
                        }
                        ?>
                    </select>
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <button onclick="muatLaporanBulanan()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500 flex items-center justify-center w-full md:w-auto">
                        <i class="fas fa-sync-alt mr-1"></i> Muat
                    </button>
                    <button onclick="cetakLaporan()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-500 flex items-center justify-center w-full md:w-auto">
                        <i class="fas fa-print mr-1"></i> Cetak
                    </button>
                </div>
            </div>
            <div id="monthlyReport">
                <p class="text-center text-gray-500 py-4">Pilih bulan dan tahun untuk melihat laporan</p>
            </div>
        </div>

    </main>

    <script>
        // Variabel global untuk data chart
        let revenueChart;

        // Fungsi untuk format tanggal Indonesia dengan GMT+7
        function formatTanggalIndonesia(dateString) {
            // Jika format sudah mengandung 'T' (ISO format), parse langsung
            if (dateString.includes('T')) {
                const date = new Date(dateString);
                return date.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    timeZone: 'Asia/Jakarta'
                });
            } else {
                // Jika format traditional, tambahkan informasi timezone
                const date = new Date(dateString + ' GMT+7');
                return date.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        }

        // Fungsi untuk konversi ke format datetime-local (GMT+7)
        function toLocalDateTimeString(dateString) {
            let date;

            // Handle kedua format tanggal
            if (dateString.includes('T')) {
                date = new Date(dateString);
            } else {
                date = new Date(dateString + ' GMT+7');
            }

            // Pastikan kita menggunakan waktu lokal Jakarta
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        // Memuat data dashboard
        async function muatDashboardData() {
            try {
                const response = await fetch('?action=get_dashboard_data');
                const data = await response.json();

                // Update ringkasan keuangan
                document.getElementById('penjualanHariIni').textContent = formatRupiah(data.penjualanHariIni);
                document.getElementById('transaksiHariIni').textContent = `${data.transaksiHariIni} transaksi`;
                document.getElementById('penjualanBulanIni').textContent = formatRupiah(data.penjualanBulanIni);
                document.getElementById('totalPiutang').textContent = formatRupiah(data.totalPiutang);
                document.getElementById('saldo').textContent = formatRupiah(data.saldo);

                // Update produk terlaris
                tampilkanProdukTerlaris(data.topProduk);

                // Update transaksi terbaru
                tampilkanTransaksiTerbaru(data.transaksiTerbaru);

                // Update piutang pelanggan
                tampilkanPiutangPelanggan(data.topPiutang);

                // Buat grafik pendapatan
                buatGrafikPendapatan(data.chartData);

            } catch (error) {
                console.error('Error:', error);
                alert('Gagal memuat data dashboard');
            }
        }

        // Format angka ke Rupiah
        function formatRupiah(angka) {
            return 'Rp ' + parseInt(angka).toLocaleString('id-ID');
        }

        // Tampilkan produk terlaris
        function tampilkanProdukTerlaris(produk) {
            const container = document.getElementById('topProducts');

            if (produk.length === 0) {
                container.innerHTML = '<p class="text-center text-gray-500 py-4">Tidak ada data penjualan</p>';
                return;
            }

            let html = '';
            produk.forEach((item, index) => {
                html += `
            <div class="flex justify-between items-center border-b py-3">
                <div class="flex items-center">
                    <span class="bg-blue-100 text-blue-800 text-sm font-semibold w-6 h-6 rounded-full flex items-center justify-center mr-3">${index + 1}</span>
                    <div>
                        <p class="font-medium">${item.nama}</p>
                        <p class="text-sm text-gray-600">${item.terjual} terjual</p>
                    </div>
                </div>
                <span class="font-semibold">${formatRupiah(item.pendapatan)}</span>
            </div>
        `;
            });

            container.innerHTML = html;
        }

        // Tampilkan transaksi terbaru
        function tampilkanTransaksiTerbaru(transaksi) {
            const container = document.getElementById('recentTransactions');

            if (transaksi.length === 0) {
                container.innerHTML = '<tr><td colspan="4" class="p-4 text-center text-gray-500">Tidak ada transaksi</td></tr>';
                return;
            }

            let html = '';
            // urutkan desc + ambil 10 data saja
            const terbaru = transaksi
                .sort((a, b) => new Date(b.waktu) - new Date(a.waktu))
                .slice(0, 10);

            terbaru.forEach(t => {
                const waktu = formatTanggalIndonesia(t.waktu);
                const status = t.hutang > 0 ?
                    '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">Hutang</span>' :
                    '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Lunas</span>';

                html += `
                    <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-3">${waktu}</td>
                    <td class="px-4 py-3">${t.nama_pembeli || 'Pelanggan'}</td>
                    <td class="px-4 py-3 font-medium">${formatRupiah(t.grandTotal)}</td>
                    <td class="px-4 py-3">${status}</td>
                    </tr>
                    `;

            });

            container.innerHTML = html;
        }


        // Tampilkan piutang pelanggan
        function tampilkanPiutangPelanggan(piutang) {
            const container = document.getElementById('topDebtors');

            if (Object.keys(piutang).length === 0) {
                container.innerHTML = '<p class="text-center text-gray-500 py-4">Tidak ada piutang</p>';
                return;
            }

            let html = '';
            let rank = 1;
            for (const [nama, jumlah] of Object.entries(piutang)) {
                html += `
            <div class="flex justify-between items-center border-b py-3">
                <div class="flex items-center">
                    <span class="bg-red-100 text-red-800 text-sm font-semibold w-6 h-6 rounded-full flex items-center justify-center mr-3">${rank}</span>
                    <div>
                        <p class="font-medium">${nama}</p>
                        <p class="text-sm text-gray-600">Piutang</p>
                    </div>
                </div>
                <span class="font-semibold text-red-600">${formatRupiah(jumlah)}</span>
            </div>
        `;
                rank++;
            }

            container.innerHTML = html;
        }

        // Buat grafik pendapatan
        function buatGrafikPendapatan(data) {
            const ctx = document.getElementById('revenueChart').getContext('2d');

            // Hancurkan chart sebelumnya jika ada
            if (revenueChart) {
                revenueChart.destroy();
            }

            const labels = Object.keys(data).map(date => {
                const d = new Date(date);
                return d.toLocaleDateString('id-ID', {
                    weekday: 'short',
                    day: 'numeric',
                    month: 'short'
                });
            });

            const values = Object.values(data);

            revenueChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Pendapatan (Rp)',
                        data: values,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Rp ' + context.raw.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
        }

        // Fungsi untuk memuat data transaksi keuangan
        async function muatDaftarTransaksi() {
            try {
                // Muat data keuangan dari endpoint PHP
                const response = await fetch('?action=get_keuangan_data');
                const keuanganData = await response.json();

                tampilkanDaftarTransaksi(keuanganData);
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('daftarTransaksi').innerHTML = `
            <tr>
                <td colspan="5" class="p-4 text-center text-red-500">
                    Gagal memuat data transaksi: ${error.message}
                </td>
            </tr>
        `;
            }
        }

        // Fungsi untuk menampilkan daftar transaksi
        function tampilkanDaftarTransaksi(transaksi) {
            const container = document.getElementById('daftarTransaksi');

            if (!transaksi || transaksi.length === 0) {
                container.innerHTML = `
            <tr>
                <td colspan="5" class="p-4 text-center text-gray-500">
                    Tidak ada data transaksi
                </td>
            </tr>
        `;
                return;
            }

            // Urutkan transaksi berdasarkan tanggal (terbaru pertama)
            transaksi.sort((a, b) => new Date(b.tanggal) - new Date(a.tanggal));

            let html = '';
            transaksi.forEach(trx => {
                const jenisClass = trx.jenis === 'pemasukan' ?
                    'text-green-600 bg-green-50' : 'text-red-600 bg-red-50';
                const jenisIcon = trx.jenis === 'pemasukan' ?
                    '↑' : '↓';

                // Format tanggal dengan GMT+7
                const tanggal = formatTanggalIndonesia(trx.tanggal);

                html += `
            <tr class="border-b hover:bg-gray-50">
                <td class="p-3">${tanggal}</td>
                <td class="p-3"><span class="${jenisClass} px-2 py-1 rounded-full text-xs font-medium">${trx.jenis}</span></td>
                <td class="p-3">${trx.keterangan}</td>
                <td class="p-3 font-medium ${trx.jenis === 'pemasukan' ? 'text-green-600' : 'text-red-600'}">
                    ${formatRupiah(trx.jumlah)}
                </td>
                <td class="p-3">
                    <div class="flex space-x-2">
                        <button onclick="bukaModalEdit('${trx.id}')" class="text-blue-500 hover:text-blue-700" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="hapusTransaksi('${trx.id}')" class="text-red-500 hover:text-red-700" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
            });

            container.innerHTML = html;
        }

        // Fungsi untuk membuka modal edit
        async function bukaModalEdit(id) {
            try {
                // Muat data keuangan dari endpoint PHP
                const response = await fetch('?action=get_keuangan_data');
                const keuanganData = await response.json();

                // Cari transaksi berdasarkan ID
                const transaksi = keuanganData.find(trx => trx.id === id);

                if (!transaksi) {
                    alert('Transaksi tidak ditemukan!');
                    return;
                }

                // Isi form edit dengan data transaksi
                document.getElementById('editId').value = transaksi.id;
                document.getElementById('editJenis').value = transaksi.jenis;
                document.getElementById('editJumlah').value = transaksi.jumlah;
                document.getElementById('editKeterangan').value = transaksi.keterangan;

                // Format tanggal untuk input datetime-local dengan GMT+7
                document.getElementById('editTanggal').value = toLocalDateTimeString(transaksi.tanggal);

                // Tampilkan modal
                document.getElementById('modalEdit').classList.remove('hidden');
            } catch (error) {
                console.error('Error:', error);
                alert('Gagal membuka form edit: ' + error.message);
            }
        }

        // Fungsi untuk menutup modal edit
        function tutupModalEdit() {
            document.getElementById('modalEdit').classList.add('hidden');
        }

        // Fungsi untuk menyimpan perubahan transaksi
        async function simpanEditTransaksi(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            const data = {
                id: formData.get('id'),
                jenis: formData.get('jenis'),
                jumlah: parseInt(formData.get('jumlah').replace(/\D/g, '')),
                keterangan: formData.get('keterangan'),
                tanggal: formData.get('tanggal')
            };

            try {
                // Muat data keuangan dari server
                const response = await fetch('?action=get_keuangan_data');
                let keuanganData = await response.json();

                // Cari index transaksi yang akan diedit
                const index = keuanganData.findIndex(trx => trx.id === data.id);

                if (index === -1) {
                    showToast('Transaksi tidak ditemukan!', 'error');
                    return;
                }

                // Update data transaksi
                keuanganData[index] = {
                    ...keuanganData[index],
                    jenis: data.jenis,
                    jumlah: data.jumlah,
                    keterangan: data.keterangan,
                    tanggal: data.tanggal
                };

                // Simpan kembali ke server
                const saveResponse = await fetch('?action=update_keuangan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(keuanganData)
                });

                const result = await saveResponse.json();

                if (result.success) {
                    tutupModalEdit();
                    muatDaftarTransaksi();
                    muatDashboardData();
                    showToast('Transaksi berhasil diupdate!', 'success');
                } else {
                    showToast('Gagal mengupdate transaksi: ' + (result.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Gagal mengupdate transaksi: ' + error.message, 'error');
            }
        }


        // Fungsi untuk menghapus transaksi
        function hapusTransaksi(id) {
            showConfirm(
                'Apakah Anda yakin ingin menghapus transaksi ini?',
                async () => { // Callback OK
                        try {
                            // Muat data keuangan
                            const response = await fetch('data/keuangan.json');
                            let keuanganData = await response.json();

                            // Filter transaksi yang akan dihapus
                            keuanganData = keuanganData.filter(trx => trx.id !== id);

                            // Simpan kembali ke file menggunakan endpoint PHP
                            const saveResponse = await fetch('?action=update_keuangan', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify(keuanganData)
                            });

                            const result = await saveResponse.json();

                            if (result.success) {
                                // Muat ulang daftar transaksi & dashboard
                                muatDaftarTransaksi();
                                muatDashboardData();
                                showToast('Transaksi berhasil dihapus!', 'success');
                            } else {
                                showToast('Gagal menghapus transaksi: ' + (result.message || ''), 'error');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            showToast('Gagal menghapus transaksi: ' + error.message, 'error');
                        }
                    },
                    () => { // Callback Cancel
                        showToast('Aksi dibatalkan', 'info');
                    }
            );
        }



        // Event listener untuk form edit
        document.getElementById('formEditTransaksi').addEventListener('submit', simpanEditTransaksi);

        // Fungsi untuk sinkronisasi saldo dengan penjualan
        async function syncSaldoPenjualan() {
            try {
                await fetch('?action=sync_saldo_penjualan');
                muatDashboardData();
                muatDaftarTransaksi();
            } catch (error) {
                console.error('Error sinkronisasi saldo:', error);
            }
        }

        // Sinkronisasi otomatis saat halaman keuangan dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Jalankan sinkronisasi otomatis
            fetch('?action=auto_sync_keuangan')
                .then(response => {
                    // Cek jika response adalah JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        // Jika bukan JSON, mungkin error HTML
                        return response.text().then(text => {
                            throw new Error('Server returned HTML instead of JSON');
                        });
                    }
                })
                .then(data => {
                    if (data.success && data.count > 0) {
                        console.log(`✅ ${data.message}`);
                    }
                })
                .catch(error => {
                    console.error('Error sinkronisasi otomatis:', error);
                })
                .finally(() => {
                    // Tetap load data meski sync gagal
                    const now = new Date();
                    const offset = 7;
                    const localNow = new Date(now.getTime() + offset * 60 * 60 * 1000);
                    const localDateTime = localNow.toISOString().slice(0, 16);
                    document.querySelector('input[name="tanggal"]').value = localDateTime;

                    muatDashboardData();
                    muatDaftarTransaksi();
                });
        });

        // Muat laporan bulanan
        async function muatLaporanBulanan() {
            const bulanTahun = document.getElementById('selectMonth').value;
            document.getElementById('monthlyReport').innerHTML = `
        <div class="flex justify-center items-center py-6">
            <i class="fas fa-spinner fa-spin text-gray-400 text-2xl mr-2"></i>
            <span>Memuat laporan...</span>
        </div>
    `;

            try {
                const response = await fetch('?action=get_laporan_bulanan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        bulan_tahun: bulanTahun
                    })
                });

                const data = await response.json();

                // Format nama bulan dan tahun
                const [tahun, bulan] = bulanTahun.split('-');
                const namaBulan = new Date(`${tahun}-${bulan}-01`).toLocaleDateString('id-ID', {
                    month: 'long',
                    year: 'numeric'
                });

                let html = `
                            <div class="mb-6">
                                <h4 class="font-semibold mb-3">Ringkasan Bulanan</h4>
                                <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                    <tr class="bg-gray-100">
                                        <th class="p-2 border">Keterangan</th>
                                        <th class="p-2 border text-right">Jumlah</th>
                                        <th class="p-2 border text-right">Catatan</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td class="p-2 border">Total Penjualan</td>
                                        <td class="p-2 border text-right font-semibold">${formatRupiah(data.totalPenjualan)}</td>
                                        <td class="p-2 border text-right">${data.totalTransaksi} transaksi</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 border">Total Pemasukan</td>
                                        <td class="p-2 border text-right font-semibold text-green-600">${formatRupiah(data.totalPemasukan)}</td>
                                        <td class="p-2 border text-right">+ ${formatRupiah(data.pemasukanLain)} lainnya</td>
                                    </tr>
                                    <tr>
                                        <td class="p-2 border">Total Pengeluaran</td>
                                        <td class="p-2 border text-right font-semibold text-red-600">${formatRupiah(data.totalPengeluaran)}</td>
                                        <td class="p-2 border text-right">Piutang: ${formatRupiah(data.piutangBulanan)}</td>
                                    </tr>
                                    <tr class="bg-gray-50 font-bold">
                                        <td class="p-2 border">Saldo Bulanan</td>
                                        <td class="p-2 border text-right ${
                                        data.saldoBulanan >= 0 ? 'text-green-600' : 'text-red-600'
                                        }">${formatRupiah(data.saldoBulanan)}</td>
                                        <td class="p-2 border text-right">—</td>
                                    </tr>
                                    </tbody>
                                </table>
                                </div>
                            </div>
            
                        <div class="mb-6">
                            <h4 class="font-semibold mb-3 text-center md:text-left">Produk Terlaris Bulan Ini</h4>
                    `;

                if (data.topProdukBulanan.length > 0) {
                    html += `
                        <div class="mb-6">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-100">
                                            <th class="p-2 border">No</th>
                                            <th class="p-2 border">Produk</th>
                                            <th class="p-2 border text-right">Terjual</th>
                                            <th class="p-2 border text-right">Pendapatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.topProdukBulanan.map((item, index) => `
                                            <tr>
                                                <td class="p-2 border">${index + 1}</td>
                                                <td class="p-2 border">${item.nama}</td>
                                                <td class="p-2 border text-right">${item.terjual}</td>
                                                <td class="p-2 border text-right">${formatRupiah(item.pendapatan)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        `;
                } else {
                    html += `<p class="text-gray-500 text-center py-4">Tidak ada data penjualan</p>`;
                }


                html += `</div>`;

                // Tampilkan transaksi keuangan jika ada
                if (data.transaksiKeuangan.length > 0) {
                    html += `
                <div class="mb-6">
                    <h4 class="font-semibold mb-3">Transaksi Keuangan Lainnya</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-2">Tanggal</th>
                                    <th class="p-2">Jenis</th>
                                    <th class="p-2">Keterangan</th>
                                    <th class="p-2">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

                    data.transaksiKeuangan.forEach(trx => {
                        const jenisClass = trx.jenis === 'pemasukan' ?
                            'text-green-600' : 'text-red-600';
                        const jenisIcon = trx.jenis === 'pemasukan' ?
                            '↑' : '↓';

                        html += `
                    <tr class="border-b">
                        <td class="p-2">${formatTanggalIndonesia(trx.tanggal)}</td>
                        <td class="p-2"><span class="${jenisClass}">${jenisIcon} ${trx.jenis}</span></td>
                        <td class="p-2">${trx.keterangan}</td>
                        <td class="p-2 font-medium ${jenisClass}">${formatRupiah(trx.jumlah)}</td>
                    </tr>
                `;
                    });

                    html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
                }

                html += `<p class="text-gray-600 text-sm">Laporan periode: ${namaBulan}</p>`;

                document.getElementById('monthlyReport').innerHTML = html;

            } catch (error) {
                console.error('Error:', error);
                document.getElementById('monthlyReport').innerHTML = `
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">
                            Gagal memuat laporan: ${error.message}
                        </p>
                    </div>
                </div>
            </div>
        `;
            }
        }

        // Cetak laporan
        function cetakLaporan() {
            const bulanTahun = document.getElementById('selectMonth').value;
            const [tahun, bulan] = bulanTahun.split('-');
            const namaBulan = new Date(`${tahun}-${bulan}-01`).toLocaleDateString('id-ID', {
                month: 'long',
                year: 'numeric'
            });

            const reportContent = document.getElementById('monthlyReport').innerHTML;

            // Bersihkan elemen interaktif (jika ada)
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = reportContent;
            tempDiv.querySelectorAll('button, .no-print').forEach(el => el.remove());
            const cleanedContent = tempDiv.innerHTML;

            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8" />
            <title>Laporan Keuangan ${namaBulan} - Toko Muda Yakin</title>
            <style>
                :root {
                --fs-base: 12px;
                --fs-sm: 11px;
                --fs-lg: 14px;
                }
                @page { size: A4 portrait; margin: 12mm; }
                @media print {
                body { margin: 0; }
                .no-print { display: none !important; }
                .avoid-break { break-inside: avoid; page-break-inside: avoid; }
                table { break-inside: auto; page-break-inside: auto; }
                tr, img, .summary-box, .saldo-box { break-inside: avoid; page-break-inside: avoid; }
                }
                body {
                font-family: Arial, Helvetica, sans-serif;
                font-size: var(--fs-base);
                color: #000;
                }
                .header {
                text-align: center;
                margin-bottom: 16px;
                border-bottom: 2px solid #333;
                padding-bottom: 8px;
                }
                .header h1 { font-size: 18px; margin: 0; font-weight: bold; }
                .header h2 { font-size: 15px; margin: 4px 0 0; color: #444; }
                .grid-cols-3 {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
                margin-bottom: 12px;
                }
                .summary-box {
                border: 1px solid #ddd;
                padding: 8px;
                border-radius: 5px;
                text-align: center;
                }
                .summary-box p:first-child {
                font-weight: 700;
                margin: 0 0 4px 0;
                font-size: var(--fs-sm);
                }
                .summary-box p:nth-child(2) {
                font-size: var(--fs-lg);
                font-weight: 700;
                margin: 4px 0;
                }
                .summary-box p:last-child {
                font-size: 10px;
                margin: 0;
                color: #666;
                }
                .saldo-box {
                background: #f9fafb;
                border: 1px solid #ddd;
                padding: 8px;
                border-radius: 5px;
                text-align: center;
                margin: 10px 0 12px;
                }
                .saldo-box p { font-weight: 700; font-size: var(--fs-lg); margin: 0; }
                table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12px;
                font-size: var(--fs-sm);
                }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background: #f5f5f5; font-weight: 700; }
                .text-green-600 { color: #16a34a; }
                .text-red-600 { color: #dc2626; }
                .text-blue-600 { color: #2563eb; }
                .footer {
                margin-top: 14px;
                text-align: right;
                font-size: 10px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 8px;
                }
            </style>
            </head>
            <body>
            <div class="header avoid-break">
                <h1>TOKO MUDA YAKIN</h1>
                <h2>Laporan Keuangan ${namaBulan}</h2>
            </div>

            <div class="content">
                ${cleanedContent}
            </div>

            <div class="footer avoid-break">
                <p>Dicetak pada: ${(() => {
                const now = new Date();
                const dd = String(now.getDate()).padStart(2,'0');
                const mm = String(now.getMonth()+1).padStart(2,'0');
                const yyyy = now.getFullYear();
                const hh = String(now.getHours()).padStart(2,'0');
                const min = String(now.getMinutes()).padStart(2,'0');
                return dd + '-' + mm + '-' + yyyy + ' ' + hh + ':' + min;
                })()}</p>
            </div>
            </body>
            </html>
            `);
            printWindow.document.close();
            setTimeout(() => printWindow.print(), 250);
        }


        // Handle form transaksi keuangan
        document.getElementById('formTransaksiKeuangan').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {
                jenis: formData.get('jenis'),
                jumlah: parseInt(formData.get('jumlah').replace(/\D/g, '')),
                keterangan: formData.get('keterangan'),
                tanggal: formData.get('tanggal')
            };

            if (!data.jenis || isNaN(data.jumlah) || !data.tanggal) {
                showToast('Jenis, jumlah, dan tanggal harus diisi dengan benar!', 'error');
                return;
            }

            try {
                const response = await fetch('?action=tambah_transaksi_keuangan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Transaksi berhasil ditambahkan!', 'success');
                    this.reset();

                    // Set ulang nilai tanggal ke waktu sekarang (GMT+7)
                    const now = new Date();
                    now.setHours(now.getHours() + 7);
                    document.querySelector('input[name="tanggal"]').value = now.toISOString().slice(0, 16);

                    muatDashboardData(); // Reload data dashboard
                    muatDaftarTransaksi(); // Muat ulang daftar transaksi
                } else {
                    showToast('Gagal menambahkan transaksi: ' + (result.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Terjadi kesalahan: ' + error.message, 'error');
            }
        });


        // Muat data saat halaman dimuat
        document.addEventListener('DOMContentLoaded', muatDashboardData);
    </script>

</body>

</html>