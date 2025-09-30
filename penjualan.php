<?php
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

require "struk_template.php";

$settings = json_decode(file_get_contents(__DIR__ . '/data/setting.json'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tes_printer']) && isset($_POST['transaksi'])) {
    $transaksi = json_decode($_POST['transaksi'], true);

    $items = $transaksi['items'] ?? [];
    $nama_pelanggan = $transaksi['namaPembeli'] ?? 'Pelanggan';
    $waktu = $transaksi['waktu'] ?? date('Y-m-d H:i:s');
    $bayar = $transaksi['bayar'] ?? 0;
    $hutang = $transaksi['hutang'] ?? 0;

    try {
        $cetak = cetakStruk($items, $settings, $nama_pelanggan, $waktu, $bayar, $hutang);

        echo json_encode([
            'status' => 'success',
            'message' => 'Struk berhasil dikirim ke printer',
            'data' => $cetak
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal mencetak struk: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Set timezone ke GMT+7
date_default_timezone_set('Asia/Jakarta');

$barangFile = __DIR__ . "/data/barang.json";
$penjualanFile = __DIR__ . "/data/penjualan.json";
$hutangFile = __DIR__ . "/data/hutang.json";

// Pastikan direktori data ada
if (!file_exists(__DIR__ . "/data")) {
    mkdir(__DIR__ . "/data", 0777, true);
}

// Pastikan file JSON ada, jika tidak buat file kosong
if (!file_exists($barangFile)) file_put_contents($barangFile, "[]");
if (!file_exists($penjualanFile)) file_put_contents($penjualanFile, "[]");
if (!file_exists($hutangFile)) file_put_contents($hutangFile, "[]");

if (isset($_GET['action'])) {
    $barang = json_decode(file_get_contents($barangFile), true) ?? [];
    $penjualan = json_decode(file_get_contents($penjualanFile), true) ?? [];
    $hutang = json_decode(file_get_contents($hutangFile), true) ?? [];
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($_GET['action']) {
        case 'cari_barang':
            $keyword = $input['keyword'] ?? '';
            $results = [];

            foreach ($barang as $item) {
                $nama = $item['nama'] ?? '';
                $kode = $item['kodeProduk'] ?? '';
                if (stripos($nama, $keyword) !== false || stripos($kode, $keyword) !== false) {
                    $results[] = $item;
                }
            }


            header('Content-Type: application/json');
            echo json_encode($results);
            exit;

        case 'simpan_penjualan':
            $input['id']    = time();
            $input['waktu'] = date('Y-m-d H:i:s');

            $totalLaba  = 0;
            $totalHarga = 0;

            // 1) Merge baris duplikat berdasarkan id + jenisHarga
            $mergedItems = [];
            foreach ($input['items'] as $item) {
                $key = $item['id'] . '_' . $item['jenisHarga'];
                if (isset($mergedItems[$key])) {
                    $mergedItems[$key]['qty'] += (int)$item['qty'];
                } else {
                    $mergedItems[$key] = $item;
                    $mergedItems[$key]['qty'] = (int)$mergedItems[$key]['qty'];
                }
            }

            // 2) Hitung qty gabungan per ID (tanpa peduli jenisHarga) → untuk validasi & pengurangan stok
            $qtyById = [];
            foreach ($mergedItems as $it) {
                $pid = (int)$it['id'];
                $qtyById[$pid] = ($qtyById[$pid] ?? 0) + (int)$it['qty'];
            }

            // 3) VALIDASI STOK: tolak bila permintaan melebihi stok saat ini
            foreach ($qtyById as $pid => $qtyReq) {
                $found = null;
                foreach ($barang as $b) {
                    if ((int)$b['id'] === $pid) {
                        $found = $b;
                        break;
                    }
                }
                if (!$found) {
                    http_response_code(400);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => "Barang dengan ID {$pid} tidak ditemukan"]);
                    exit;
                }
                $stokNow = (int)($found['stok'] ?? 0);
                if ($qtyReq > $stokNow) {
                    http_response_code(400);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error'   => "Stok tidak cukup untuk {$found['nama']} (stok {$stokNow}, diminta {$qtyReq})"
                    ]);
                    exit;
                }
            }

            // 4) Hitung harga/laba per baris (ecer/grosir) & bentuk ulang $input['items']
            $input['items'] = [];
            foreach ($mergedItems as $item) {
                foreach ($barang as $b) {
                    if ((int)$b['id'] === (int)$item['id']) {
                        $modalDasar = (int)($b['satuanHarga'][0]['hargaModal']  ?? 0);
                        $hargaEcer  = (int)($b['satuanHarga'][0]['hargaEcer']   ?? 0);
                        $hargaGrosir = (int)($b['satuanHarga'][0]['hargaGrosir'] ?? 0);

                        // Tentukan harga sesuai jenis
                        $item['harga'] = ($item['jenisHarga'] === 'grosir') ? $hargaGrosir : $hargaEcer;

                        $item['hargaModal'] = $modalDasar;
                        $item['laba']       = ($item['harga'] - $modalDasar) * (int)$item['qty'];

                        $totalLaba  += $item['laba'];
                        $totalHarga += $item['harga'] * (int)$item['qty'];

                        $input['items'][] = $item;
                        break;
                    }
                }
            }

            // 5) Totalkan & diskon
            $diskon     = (int)($input['diskon'] ?? 0);
            $grandTotal = max(0, $totalHarga - $diskon);

            $input['total']      = $totalHarga;
            $input['grandTotal'] = $grandTotal;
            $input['totalLaba']  = $totalLaba;

            // 6) Simpan transaksi
            $penjualan[] = $input;

            // 7) KURANGI STOK SEKALI PER PRODUK (pakai qty gabungan)
            foreach ($barang as &$b) {
                $pid = (int)$b['id'];
                if (isset($qtyById[$pid])) {
                    $b['stok'] = max(0, (int)$b['stok'] - (int)$qtyById[$pid]);
                }
            }
            unset($b);

            // 8) Catat hutang jika ada
            if ((int)($input['hutang'] ?? 0) > 0) {
                $hutang[] = [
                    'id'            => 'H' . time(),
                    'id_penjualan'  => $input['id'],
                    'nama'          => $input['nama_pembeli'] ?? 'Pelanggan',
                    'jumlah'        => (int)$input['hutang'],
                    'tanggal'       => date('Y-m-d H:i:s'),
                    'status'        => 'belum lunas',
                    'tanggal_bayar' => null
                ];
            }

            // 9) Tulis ke file
            file_put_contents($penjualanFile, json_encode($penjualan, JSON_PRETTY_PRINT));
            file_put_contents($barangFile,    json_encode($barang,    JSON_PRETTY_PRINT));
            file_put_contents($hutangFile,    json_encode($hutang,    JSON_PRETTY_PRINT));

            // 10) Respon OK
            header('Content-Type: application/json');
            echo json_encode([
                "success"    => true,
                "id"         => $input['id'],
                "hutang"     => ((int)($input['hutang'] ?? 0) > 0),
                "total"      => $totalHarga,
                "grandTotal" => $grandTotal,
                "laba"       => $totalLaba
            ]);
            exit;



        case 'riwayat_penjualan':
            header('Content-Type: application/json');
            echo json_encode($penjualan);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <title>Point of Sale - POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="alert.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS untuk tabel sticky */
        .table-container {
            max-height: 300px;
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
        }

        /* Memastikan tabel tetap rapi */
        #keranjangTable {
            width: 100%;
            border-collapse: collapse;
        }

        #keranjangTable th,
        #keranjangTable td {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
        }

        /* Menghilangkan border pada container kosong */
        #keranjangKosong {
            border: none;
        }

        /* Modal struk harus di atas header sticky */
        #modalStruk {
            z-index: 1000;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <?php include "partials/sidebar.php"; ?>

    <main class="flex-1 p-6">
        <h2 class="text-2xl font-semibold mb-4">🛒 Point of Sale</h2>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Panel Kiri: Pencarian dan Daftar Barang -->
            <div class="lg:col-span-1">
                <div class="bg-white p-4 rounded-lg shadow-lg mb-4">
                    <h3 class="text-lg font-semibold mb-3">Cari Barang</h3>
                    <div class="flex gap-2">
                        <input type="text" id="cariBarang" placeholder="Kode atau nama barang"
                            class="border rounded-md p-2 w-full" autocomplete="off"
                            onkeyup="manualKeyup(this)" />
                        <button onclick="cariBarang()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>

                    <div id="hasilPencarian" class="mt-4 max-h-80 overflow-y-auto">
                        <!-- Hasil pencarian akan ditampilkan di sini -->
                    </div>
                </div>

            </div>

            <!-- Panel Tengah: Keranjang Belanja -->
            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-lg font-semibold mb-4">Keranjang Belanja</h3>

                    <div class="table-container">
                        <table id="keranjangTable" class="w-full text-left">
                            <thead>
                                <tr class="sticky-header">
                                    <th class="p-2">Barang</th>
                                    <th class="p-2">Harga</th>
                                    <th class="p-2">Qty</th>
                                    <th class="p-2">Jenis</th>
                                    <th class="p-2">Subtotal</th>
                                    <th class="p-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="keranjang">
                                <!-- Item keranjang akan ditampilkan di sini -->
                                <tr id="keranjangKosong">
                                    <td colspan="6" class="p-3 text-center">Keranjang kosong</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Input Promo -->
                    <div class="mb-4">
                        <label for="diskon" class="block text-sm font-medium text-gray-700">Diskon/Promo (Rp)</label>
                        <input type="number" id="diskon" class="border rounded-md p-2 w-full"
                            placeholder="Jumlah diskon" value="0" min="0" oninput="hitungTotal()">
                    </div>

                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <button onclick="kosongkanKeranjang()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-500 mr-2">
                                <i class="fas fa-trash mr-1"></i> Kosongkan
                            </button>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-semibold">Total: <span id="totalHarga">Rp 0</span></p>
                            <p class="text-sm text-green-600" id="textDiskon">Diskon: Rp 0</p>
                            <p class="text-lg font-semibold mt-1">Grand Total: <span id="grandTotal">Rp 0</span></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                        <div>
                            <label for="bayar" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-money-bill-wave mr-1"></i> Jumlah Bayar
                            </label>
                            <input type="number" id="bayar" class="border rounded-md p-2 w-full"
                                placeholder="Jumlah bayar" data-scan-ignore oninput="hitungKembalian()">
                        </div>
                        <div>
                            <label for="namaPembeli" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-user mr-1"></i> Nama Pembeli
                            </label>
                            <input type="text" id="namaPembeli" class="border rounded-md p-2 w-full"
                                placeholder="Masukkan nama pembeli" data-scan-ignore>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label for="kembalian" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-coins mr-1"></i> Kembalian
                            </label>
                            <input type="text" id="kembalian" class="border rounded-md p-2 w-full bg-gray-100"
                                placeholder="Kembalian" readonly>
                        </div>
                        <div id="hutangContainer" class="hidden">
                            <label for="hutang" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Hutang
                            </label>
                            <input type="text" id="hutang" class="border rounded-md p-2 w-full bg-red-100"
                                placeholder="Hutang" readonly>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button onclick="prosesPembayaran()" class="bg-green-600 text-white px-6 py-3 rounded-md hover:bg-green-500 w-full">
                            <i class="fas fa-check-circle mr-2"></i> Proses Pembayaran
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Struk -->
        <div id="modalStruk" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
            <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
                <h3 class="text-xl font-semibold mb-4">Struk Pembayaran</h3>
                <div id="strukContent" class="mb-4">
                    <!-- Konten struk akan diisi oleh JavaScript -->
                </div>
                <div class="flex justify-between">
                    <button onclick="cetakStruk()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500">
                        <i class="fas fa-print mr-1"></i> Cetak
                    </button>
                    <button onclick="tutupStruk()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-500">
                        <i class="fas fa-times mr-1"></i> Tutup
                    </button>
                </div>
            </div>
        </div>

    </main>

    <script>
        // Variabel global untuk keranjang belanja
        let keranjang = [];
        let total = 0;
        let grandTotal = 0;
        let diskon = 0;
        let lastTransaction = null;
        let manualTimer = null;
        const settings = <?= json_encode($settings) ?>;

        // === HYBRID: ketik manual + auto-scan (Enter ATAU diam) ===
        let manualTypeTimer = null;

        const SCAN = {
            MIN_LEN: 6,
            MAX_INTERVAL: 35,
            END_WAIT: 80
        }; // ms
        let scanBuf = '';
        let scanLastTs = 0;
        let scanTimer = null;

        function resetScan() {
            scanBuf = '';
            scanLastTs = 0;
            if (scanTimer) {
                clearTimeout(scanTimer);
                scanTimer = null;
            }
        }

        function finishScan(raw) {
            const code = String(raw).replace(/[\r\n\t]+/g, '').trim();
            resetScan();
            if (!code) return;
            searchByCodeOrKeyword(code, {
                autoAdd: true
            });
        }


        function manualKeyup(el) {
            // Jika sedang ada alur scan (buffer terisi), jangan pencarian manual dulu
            if (typeof scanBuf !== 'undefined' && scanBuf) return;

            const q = el.value.trim();
            clearTimeout(manualTimer);
            manualTimer = setTimeout(() => {
                if (q.length >= 2) {
                    // mode manual: tampilkan daftar saja, tanpa auto-add
                    searchByCodeOrKeyword(q, {
                        autoAdd: false
                    });
                } else {
                    document.getElementById('hasilPencarian').innerHTML = '';
                }
            }, 220); // debounce kecil biar nggak spam fetch
        }

        // Ketik manual → debounce tampilkan list (tanpa bentrok scanner)
        document.addEventListener('DOMContentLoaded', function() {
            const inp = document.getElementById('cariBarang');
            const container = document.getElementById('hasilPencarian');
            if (!inp) return;

            // Ketik manual → debounce tampilkan list
            inp.addEventListener('input', (e) => {
                // (Opsional) kalau sedang deteksi scanner cepat, jangan panggil daftar dulu
                if (typeof scanBuf !== 'undefined' && scanBuf) return;

                if (manualTypeTimer) clearTimeout(manualTypeTimer);
                manualTypeTimer = setTimeout(() => {
                    const q = e.target.value.trim();
                    if (q.length >= 2) {
                        cariBarang(); // pakai fungsi kamu yang lama
                        // atau: searchByCodeOrKeyword(q); // jika mau satu pintu
                    } else {
                        container.innerHTML = '';
                    }
                }, 280);
            });

            // (Opsional) biar Enter tetap jalan kalau mau
            inp.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') cariBarang();
            });
        });



        // Handler global SATU-SATUNYA
        document.addEventListener('keydown', (e) => {
            const inp = document.getElementById('cariBarang');
            const active = document.activeElement;
            const editable = isEditable(active);
            const isSearch = active === inp;
            const ignoreScan = active?.hasAttribute('data-scan-ignore');

            // Jika modal confirm terbuka atau sedang ngetik di field yang diabaikan → jangan interupsi
            if (confirmOpen() || ignoreScan) return;

            const printable = e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey;

            // Enter/Tab: akhiri scan hanya jika TIDAK sedang edit di field lain
            if (e.key === 'Enter' || e.key === 'Tab') {
                if (scanBuf && !editable) {
                    e.preventDefault();
                    return finishScan(scanBuf);
                }
                // Enter di kolom cari → cari manual
                if (e.key === 'Enter' && isSearch) {
                    e.preventDefault();
                    const q = inp.value.trim();
                    if (q.length >= 2) searchByCodeOrKeyword(q, {
                        autoAdd: false
                    });
                }
                return;
            }

            // Karakter biasa
            if (printable) {
                // Jika lagi mengetik di input lain (bayar/nama/dll) → biarkan saja
                if (editable && !isSearch) return;

                // Jika bukan sedang edit apa pun → arahkan ke kolom cari
                if (!editable && document.activeElement !== inp) inp?.focus();

                // Deteksi alur scanner (kecepatan ketikan)
                const now = performance.now();
                const gap = now - (scanLastTs || now);
                scanLastTs = now;

                if (gap <= SCAN.MAX_INTERVAL) {
                    scanBuf += e.key;
                    if (scanTimer) clearTimeout(scanTimer);
                    scanTimer = setTimeout(() => {
                        if (scanBuf.length >= SCAN.MIN_LEN) {
                            finishScan(scanBuf); // auto-add lewat searchByCodeOrKeyword
                        } else {
                            resetScan();
                        }
                    }, SCAN.END_WAIT);
                } else {
                    resetScan(); // jeda panjang → bukan scanner
                }
            }
        });



        // Fungsi untuk memuat keranjang dari localStorage
        function muatKeranjangDariPenyimpanan() {
            const keranjangTersimpan = localStorage.getItem('keranjangPOS');
            if (keranjangTersimpan) {
                keranjang = JSON.parse(keranjangTersimpan);
                perbaruiKeranjang();
            }
        }

        // Fungsi untuk menyimpan keranjang ke localStorage
        function simpanKeranjangKePenyimpanan() {
            localStorage.setItem('keranjangPOS', JSON.stringify(keranjang));
        }

        // Fungsi untuk mengosongkan keranjang dari localStorage
        function hapusKeranjangDariPenyimpanan() {
            localStorage.removeItem('keranjangPOS');
        }

        // Memuat keranjang saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            muatKeranjangDariPenyimpanan();
            document.getElementById('cariBarang').focus();

            document.getElementById('cariBarang').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    cariBarang();
                }
            });
        });

        // Cari satu keyword; jika cocok 1 barang / kodeProduk eksak → auto-add, kalau tidak → tampilkan list
        async function searchByCodeOrKeyword(keyword, opts = {
            autoAdd: true
        }) {
            const {
                autoAdd = true
            } = opts || {};
            const inp = document.getElementById('cariBarang');
            const container = document.getElementById('hasilPencarian');
            const q = String(keyword ?? inp.value ?? '').trim();
            if (!q) return;

            try {
                const res = await fetch('?action=cari_barang', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        keyword: q
                    })
                });
                const items = await res.json();

                // auto-add hanya jika diizinkan
                const exact = items.find(it => String(it.kodeProduk || '').toLowerCase() === q.toLowerCase());
                if (autoAdd && exact) {
                    tambahKeKeranjang(exact, 'ecer');
                    inp.value = '';
                    container.innerHTML = '';
                    return;
                }
                if (autoAdd && items.length === 1) {
                    tambahKeKeranjang(items[0], 'ecer');
                    inp.value = '';
                    container.innerHTML = '';
                    return;
                }

                // selain itu tampilkan daftar
                tampilkanHasilPencarian(items);
            } catch (err) {
                console.error('searchByCodeOrKeyword error:', err);
            }
        }



        // Fungsi untuk mencari barang
        async function cariBarang() {
            const keyword = document.getElementById('cariBarang').value;

            if (keyword.length < 2) {
                document.getElementById('hasilPencarian').innerHTML = '<p class="text-center p-3 text-gray-500"><i class="fas fa-info-circle mr-1"></i> Masukkan minimal 2 karakter</p>';
                return;
            }

            try {
                const response = await fetch('?action=cari_barang', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        keyword
                    })
                });

                const hasil = await response.json();
                tampilkanHasilPencarian(hasil);
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // Menampilkan hasil pencarian
        function tampilkanHasilPencarian(hasil) {
            const container = document.getElementById('hasilPencarian');

            if (hasil.length === 0) {
                container.innerHTML = '<p class="text-center p-3 text-gray-500"><i class="fas fa-search mr-1"></i> Tidak ada barang ditemukan</p>';
                return;
            }

            let html = '';
            hasil.forEach(barang => {
                const hargaEcer = barang.satuanHarga && barang.satuanHarga.length > 0 ?
                    barang.satuanHarga[0].hargaEcer : 0;
                const hargaGrosir = barang.satuanHarga && barang.satuanHarga.length > 0 ?
                    barang.satuanHarga[0].hargaGrosir : 0;

                html += `
            <div class="border-b p-3 hover:bg-gray-50 transition-colors duration-200">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="font-semibold text-blue-700">${barang.nama}</p>
                        <p class="text-sm text-gray-600 mt-1"><i class="fas fa-box mr-1"></i> Stok: ${barang.stok}</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Ecer: Rp ${hargaEcer.toLocaleString('id-ID')}</span>
                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Grosir: Rp ${hargaGrosir.toLocaleString('id-ID')}</span>
                        </div>
                    </div>
                    <div class="flex flex-col space-y-2 ml-3">
                        <button onclick="tambahKeKeranjang(${JSON.stringify(barang).replace(/"/g, '&quot;')}, 'ecer')" 
                                class="bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-500 transition-colors duration-200 flex items-center">
                            <i class="fas fa-shopping-cart mr-1 text-xs"></i> Ecer
                        </button>
                        <button onclick="tambahKeKeranjang(${JSON.stringify(barang).replace(/"/g, '&quot;')}, 'grosir')" 
                                class="bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-500 transition-colors duration-200 flex items-center">
                            <i class="fas fa-shopping-cart mr-1 text-xs"></i> Grosir
                        </button>
                    </div>
                </div>
            </div>
        `;
            });

            container.innerHTML = html;
        }

        function qtyInCartById(id, exceptIndex = -1) {
            return keranjang.reduce((sum, it, i) => {
                return sum + ((it.id === id && i !== exceptIndex) ? it.qty : 0);
            }, 0);
        }


        function tambahKeKeranjang(barang, jenisHarga) {
            const hargaEcer = Number(barang?.satuanHarga?.[0]?.hargaEcer || 0);
            const hargaGrosir = Number(barang?.satuanHarga?.[0]?.hargaGrosir || 0);

            const jenis = jenisHarga || 'ecer';
            const harga = (jenis === 'grosir') ? hargaGrosir : hargaEcer;

            const idx = keranjang.findIndex(i => i.id === barang.id && i.jenisHarga === jenis);

            // total qty produk ini (semua jenis) yang SUDAH ada di keranjang
            const totalAlready = qtyInCartById(barang.id);

            // stok habis / penuh?
            if (totalAlready >= barang.stok) {
                window.showToast('Stok tidak mencukupi!', 'error', 4000);
                return;
            }

            if (idx !== -1) {
                // mau menambah 1 pada baris yang sama jenisnya
                if (totalAlready + 1 > barang.stok) {
                    window.showToast('Stok tidak mencukupi!', 'error', 4000);
                    return;
                }
                keranjang[idx].qty += 1;
            } else {
                // menambah baris baru (jenis berbeda)
                if (totalAlready + 1 > barang.stok) {
                    window.showToast('Stok tidak mencukupi!', 'error', 4000);
                    return;
                }
                keranjang.unshift({
                    id: barang.id,
                    nama: barang.nama,
                    hargaEcer,
                    hargaGrosir,
                    harga,
                    jenisHarga: jenis,
                    qty: 1,
                    stok: barang.stok
                });
            }

            perbaruiKeranjang();
            simpanKeranjangKePenyimpanan();
            window.showToast(`${barang.nama} berhasil ditambahkan ke keranjang.`, 'success', 3000);
        }


        // function tambahKeKeranjang(barang, jenisHarga) {
        //     const hargaEcer = Number(barang?.satuanHarga?.[0]?.hargaEcer || 0);
        //     const hargaGrosir = Number(barang?.satuanHarga?.[0]?.hargaGrosir || 0);

        //     const jenis = jenisHarga || 'ecer'; // default ecer
        //     const harga = (jenis === 'grosir') ? hargaGrosir : hargaEcer;

        //     const idx = keranjang.findIndex(i => i.id === barang.id && i.jenisHarga === jenis);

        //     if (idx !== -1) {
        //         if (keranjang[idx].qty < barang.stok) {
        //             keranjang[idx].qty += 1;
        //         } else {
        //             window.showToast('Stok tidak mencukupi!', 'error', 4000);
        //             return;
        //         }
        //     } else {
        //         if (barang.stok > 0) {
        //             keranjang.unshift({
        //                 id: barang.id,
        //                 nama: barang.nama,
        //                 hargaEcer,
        //                 hargaGrosir,
        //                 harga, // harga aktif
        //                 jenisHarga: jenis, // 'ecer' | 'grosir'
        //                 qty: 1,
        //                 stok: barang.stok
        //             });
        //         } else {
        //             window.showToast('Stok habis!', 'error', 4000);
        //             return;
        //         }
        //     }

        //     perbaruiKeranjang();
        //     simpanKeranjangKePenyimpanan();
        //     window.showToast(`${barang.nama} berhasil ditambahkan ke keranjang.`, 'success', 3000);
        // }


        function ubahJenis(index, jenis) {
            const item = keranjang[index];
            if (item.hargaEcer == null) item.hargaEcer = Number(item.harga || 0);
            if (item.hargaGrosir == null) item.hargaGrosir = Number(item.harga || 0);

            item.jenisHarga = jenis;
            item.harga = (jenis === 'grosir') ? Number(item.hargaGrosir || 0) : Number(item.hargaEcer || 0);

            // Pastikan qty baris ini + qty baris lain tidak melebihi stok
            const otherQty = qtyInCartById(item.id, index);
            if (otherQty + item.qty > item.stok) {
                item.qty = Math.max(1, item.stok - otherQty);
            }

            // merge bila sudah ada baris dengan jenis yg sama
            const dupIndex = keranjang.findIndex((it, i) => i !== index && it.id === item.id && it.jenisHarga === jenis);
            if (dupIndex !== -1) {
                const combined = keranjang[dupIndex].qty + item.qty;
                keranjang[dupIndex].qty = Math.min(combined, item.stok);
                keranjang.splice(index, 1);
            }

            perbaruiKeranjang();
            simpanKeranjangKePenyimpanan();
        }



        function setJenisSelectColor(sel) {
            sel.classList.remove(
                'bg-blue-100', 'text-blue-800', 'border-blue-300',
                'bg-green-100', 'text-green-800', 'border-green-300'
            );
            if (sel.value === 'grosir') {
                sel.classList.add('bg-green-100', 'text-green-800', 'border-green-300');
            } else {
                sel.classList.add('bg-blue-100', 'text-blue-800', 'border-blue-300');
            }
        }


        // Memperbarui tampilan keranjang
        function perbaruiKeranjang() {
            const container = document.getElementById('keranjang');
            total = 0;

            if (keranjang.length === 0) {
                container.innerHTML = '<tr id="keranjangKosong"><td colspan="6" class="p-4 text-center text-gray-500"><i class="fas fa-shopping-cart mr-2"></i>Keranjang kosong</td></tr>';
                document.getElementById('totalHarga').textContent = 'Rp 0';
                document.getElementById('grandTotal').textContent = 'Rp 0';
                return;
            }

            // Hapus pesan keranjang kosong jika ada
            if (document.getElementById('keranjangKosong')) {
                document.getElementById('keranjangKosong').remove();
            }

            let html = '';

            keranjang.forEach((item, index) => {
                const subtotal = item.harga * item.qty;
                const selectColorClass =
                    item.jenisHarga === 'grosir' ?
                    'bg-green-100 text-green-800 border-green-300' :
                    'bg-blue-100 text-blue-800 border-blue-300';
                total += subtotal;

                const jenisClass = item.jenisHarga === 'ecer' ?
                    'bg-blue-100 text-blue-800' :
                    'bg-green-100 text-green-800';

                html += `
            <tr class="border-b hover:bg-gray-50 transition-colors duration-200">
                <td class="p-2">${item.nama}</td>
                <td class="p-2">Rp ${item.harga.toLocaleString('id-ID')}</td>
                <td class="p-2">
                    <div class="flex items-center">
                        <button onclick="ubahQty(${index}, -1)" class="bg-gray-200 px-2 py-1 rounded-l hover:bg-gray-300 transition-colors duration-200">
                            <i class="fas fa-minus text-xs"></i>
                        </button>
                        <input type="number" value="${item.qty}" min="1" max="${item.stok}" 
                               class="w-12 text-center border-y py-1" onchange="ubahQtyManual(${index}, this.value)">
                        <button onclick="ubahQty(${index}, 1)" class="bg-gray-200 px-2 py-1 rounded-r hover:bg-gray-300 transition-colors duration-200">
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                    </div>
                </td>
                <td class="p-2">
                <select
                    onchange="ubahJenis(${index}, this.value); setJenisSelectColor(this)"
                    class="border rounded px-2 py-1 text-sm ${selectColorClass}"
                >
                    <option value="ecer" ${item.jenisHarga === 'ecer' ? 'selected' : ''}>Ecer</option>
                    <option value="grosir" ${item.jenisHarga === 'grosir' ? 'selected' : ''}>Grosir</option>
                </select>
                </td>
                <td class="p-2 font-medium">Rp ${subtotal.toLocaleString('id-ID')}</td>
                <td class="p-2">
                    <button onclick="hapusDariKeranjang(${index})" class="bg-red-100 text-red-600 px-2 py-1 rounded text-sm hover:bg-red-200 transition-colors duration-200">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </td>
            </tr>
        `;
            });

            container.innerHTML = html;
            document.querySelectorAll('#keranjang select').forEach(setJenisSelectColor);
            hitungTotal();
            simpanKeranjangKePenyimpanan();
        }

        // Hitung total dengan diskon
        function hitungTotal() {
            diskon = parseInt(document.getElementById('diskon').value) || 0;
            grandTotal = total - diskon;
            if (grandTotal < 0) grandTotal = 0;

            document.getElementById('totalHarga').textContent = `Rp ${total.toLocaleString('id-ID')}`;
            document.getElementById('textDiskon').textContent = `Diskon: Rp ${diskon.toLocaleString('id-ID')}`;
            document.getElementById('grandTotal').textContent = `Rp ${grandTotal.toLocaleString('id-ID')}`;

            hitungKembalian();
        }

        // Mengubah kuantitas item
        function ubahQtyManual(index, value) {
            const item = keranjang[index];
            const stok = item.stok;
            const otherQty = qtyInCartById(item.id, index);
            let newQty = parseInt(value);

            if (isNaN(newQty) || newQty < 1) newQty = 1;
            if (otherQty + newQty > stok) {
                const sisa = Math.max(0, stok - otherQty);
                window.showToast(`Stok tidak mencukupi! Maksimal bisa ${sisa}`, 'error', 4000);
                newQty = sisa; // clamp
                if (newQty < 1) newQty = 1;
            }

            item.qty = newQty;
            perbaruiKeranjang();
            simpanKeranjangKePenyimpanan();
        }


        // Mengubah kuantitas manual
        function ubahQtyManual(index, value) {
            const newQty = parseInt(value);

            if (isNaN(newQty) || newQty < 1) {
                keranjang[index].qty = 1;
            } else if (newQty > keranjang[index].stok) {
                // alert('Stok tidak mencukupi!');
                window.showToast('Stok tidak mencukupi!', 'error', 4000);
                keranjang[index].qty = keranjang[index].stok;
            } else {
                keranjang[index].qty = newQty;
            }

            perbaruiKeranjang();
            simpanKeranjangKePenyimpanan();
        }

        // Menghapus item dari keranjang
        function hapusDariKeranjang(index) {
            keranjang.splice(index, 1);
            perbaruiKeranjang();
            simpanKeranjangKePenyimpanan();
        }

        // Mengosongkan keranjang
        function kosongkanKeranjang() {
            if (keranjang.length === 0) return;

            showConfirm(
                'Apakah Anda yakin ingin mengosongkan keranjang?',
                () => { // Callback OK
                    keranjang = [];
                    perbaruiKeranjang();
                    hapusKeranjangDariPenyimpanan();
                    showToast('Keranjang berhasil dikosongkan', 'success');
                },
                () => { // Callback Cancel
                    showToast('Aksi dibatalkan', 'info');
                }
            );
        }


        // Menghitung kembalian
        function hitungKembalian() {
            const bayar = parseFloat(document.getElementById('bayar').value) || 0;
            const kembalian = bayar - grandTotal;
            const hutangContainer = document.getElementById('hutangContainer');
            const hutangInput = document.getElementById('hutang');

            if (kembalian >= 0) {
                hutangContainer.classList.add('hidden');
                document.getElementById('kembalian').value = `Rp ${kembalian.toLocaleString('id-ID')}`;
            } else {
                hutangContainer.classList.remove('hidden');
                document.getElementById('kembalian').value = 'Jumlah bayar kurang!';
                hutangInput.value = `Rp ${Math.abs(kembalian).toLocaleString('id-ID')}`;
            }
        }

        // Memproses pembayaran
        async function prosesPembayaran() {
            if (keranjang.length === 0) {
                showToast('Keranjang masih kosong!', 'error');
                return;
            }

            const bayar = parseFloat(document.getElementById('bayar').value) || 0;
            const namaPembeli = document.getElementById('namaPembeli').value || 'Pelanggan';
            const kembalian = bayar - grandTotal;
            const hutang = kembalian < 0 ? Math.abs(kembalian) : 0;

            if (namaPembeli.trim() === '') {
                showToast('Mohon isi nama pembeli terlebih dahulu!', 'error');
                document.getElementById('namaPembeli').focus();
                return;
            }

            // Fungsi internal untuk kirim transaksi
            async function kirimTransaksi() {
                try {
                    const response = await fetch('?action=simpan_penjualan', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            items: keranjang,
                            total: total,
                            diskon: diskon,
                            grandTotal: grandTotal,
                            bayar: bayar,
                            kembalian: kembalian >= 0 ? kembalian : 0,
                            hutang: hutang,
                            nama_pembeli: namaPembeli
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        if (result.hutang) {
                            showToast(`Transaksi berhasil! Tercatat hutang sebesar Rp ${hutang.toLocaleString('id-ID')}`, 'warning');
                        } else {
                            showToast('Transaksi berhasil!', 'success');
                        }

                        // SIMPAN TRANSAKSI TERAKHIR UNTUK CETAK - PERBAIKAN DI SINI
                        lastTransaction = {
                            items: JSON.parse(JSON.stringify(keranjang)), // Deep copy
                            total: total,
                            diskon: diskon,
                            grandTotal: grandTotal,
                            bayar: bayar,
                            kembalian: kembalian >= 0 ? kembalian : 0,
                            hutang: hutang,
                            namaPembeli: namaPembeli,
                            waktu: new Date().toLocaleString('id-ID', {
                                timeZone: 'Asia/Jakarta',
                                year: 'numeric',
                                month: '2-digit',
                                day: '2-digit',
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit'
                            })
                        };

                        tampilkanStruk(lastTransaction.items, total, diskon, grandTotal, bayar, kembalian, hutang, namaPembeli);

                        // Reset form
                        hapusKeranjangDariPenyimpanan();
                        keranjang = [];
                        perbaruiKeranjang();
                        document.getElementById('bayar').value = '';
                        document.getElementById('diskon').value = '0';
                        document.getElementById('namaPembeli').value = '';
                        document.getElementById('kembalian').value = '';
                        document.getElementById('hutangContainer').classList.add('hidden');
                    } else {
                        showToast('Gagal memproses pembayaran: ' + (result.error || 'Unknown error'), 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Terjadi kesalahan: ' + error.message, 'error');
                }
            }

            // Konfirmasi jika bayar kurang
            if (bayar < grandTotal) {
                showConfirm(
                    `Pembayaran kurang Rp ${hutang.toLocaleString('id-ID')}. Apakah ingin melanjutkan dengan hutang?`,
                    async () => {
                            await kirimTransaksi();
                        },
                        () => {
                            showToast('Aksi pembayaran dibatalkan', 'info');
                        }
                );
            } else {
                showConfirm(
                    `Bayar: <b>Rp ${bayar.toLocaleString('id-ID')}</b><br>
                    Grand Total: <b>Rp ${grandTotal.toLocaleString('id-ID')}</b><br>
                    Kembalian: <b>Rp ${kembalian.toLocaleString('id-ID')}</b><br><br>
                    Proses transaksi sekarang?`,
                    async () => {
                            await kirimTransaksi();
                        },
                        () => {
                            showToast('Aksi pembayaran dibatalkan', 'info');
                        }
                );
            }
        }

        function isEditable(el) {
            if (!el) return false;
            const tag = el.tagName;
            return (
                tag === 'INPUT' ||
                tag === 'TEXTAREA' ||
                tag === 'SELECT' ||
                el.isContentEditable === true
            );
        }

        function confirmOpen() {
            return !!document.querySelector('.confirm-mask');
        }

        // Menampilkan struk
        function tampilkanStruk(items, total, diskon, grandTotal, bayar, kembalian, hutang, namaPembeli) {
            const strukContent = document.getElementById('strukContent');
            const now = new Date();

            // format waktu
            const options = {
                timeZone: 'Asia/Jakarta',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const waktuJakarta = now.toLocaleString('id-ID', options);

            let html = `
        <div class="border-b pb-2 mb-2">
           <p class="text-center font-semibold">${settings.nama_toko}</p>
            <p class="text-center text-sm">${waktuJakarta}</p>
            <p class="text-center text-sm">${settings.alamat}</p>
            <p class="text-center text-sm">${settings.telepon}</p>
            <p class="text-center text-sm mt-1"><i class="fas fa-user mr-1"></i> ${namaPembeli}</p>
        </div>
        
        <div class="mb-3 max-h-60 overflow-y-auto">
    `;

            items.forEach(item => {
                const subtotal = item.harga * item.qty;
                html += `
            <div class="flex justify-between text-sm py-1">
                <span>${item.nama} <span class="text-xs ${item.jenisHarga === 'ecer' ? 'text-blue-600' : 'text-green-600'}">(${item.jenisHarga})</span> x${item.qty}</span>
                <span>Rp ${subtotal.toLocaleString('id-ID')}</span>
            </div>
        `;
            });

            html += `
        </div>
        
        <div class="border-t pt-2">
            <div class="flex justify-between text-sm py-1">
                <span>Total:</span>
                <span>Rp ${total.toLocaleString('id-ID')}</span>
            </div>
            <div class="flex justify-between text-sm py-1">
                <span>Diskon:</span>
                <span class="text-green-600">- Rp ${diskon.toLocaleString('id-ID')}</span>
            </div>
            <div class="flex justify-between font-semibold py-1 border-t mt-1">
                <span>Grand Total:</span>
                <span>Rp ${grandTotal.toLocaleString('id-ID')}</span>
            </div>
            <div class="flex justify-between text-sm py-1">
                <span>Bayar:</span>
                <span>Rp ${bayar.toLocaleString('id-ID')}</span>
            </div>
    `;

            if (kembalian >= 0) {
                html += `
            <div class="flex justify-between text-sm py-1">
                <span>Kembalian:</span>
                <span class="text-blue-600">Rp ${kembalian.toLocaleString('id-ID')}</span>
            </div>
        `;
            } else {
                html += `
            <div class="flex justify-between text-sm py-1 text-red-600">
                <span><i class="fas fa-exclamation-triangle mr-1"></i> Hutang:</span>
                <span>Rp ${hutang.toLocaleString('id-ID')}</span>
            </div>
        `;
            }

            html += `
        </div>
        
        <div class="text-center mt-4 pt-2 border-t text-sm text-gray-600">
            <p>${settings.footer || 'Terima kasih atas kunjungannya'}</p>
        </div>
    `;

            strukContent.innerHTML = html;
            document.getElementById('modalStruk').classList.remove('hidden');

            // === AUTO PRINT ===
            if (settings.auto_print) {
                cetakStruk(); // langsung panggil fungsi cetak
            }
        }



        // Menutup modal struk
        function tutupStruk() {
            document.getElementById('modalStruk').classList.add('hidden');
        }

        // Mencetak struk
        function cetakStruk() {
            if (!lastTransaction) {
                showToast('Tidak ada transaksi terakhir untuk dicetak!', 'error');
                return;
            }

            console.log('Data yang dikirim ke printer:', lastTransaction); // Debug

            const formData = new FormData();
            formData.append('tes_printer', '1');
            formData.append('transaksi', JSON.stringify(lastTransaction));

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response dari server:', data); // Debug
                    if (data.status === 'success') {
                        showToast('Struk berhasil dikirim ke printer!', 'success', 3000);
                    } else {
                        showToast('Gagal mencetak: ' + (data.message || 'Unknown error'), 'error', 5000);
                    }
                })
                .catch(error => {
                    console.error('Error saat mencetak:', error);
                    showToast('Terjadi kesalahan saat koneksi ke server: ' + error.message, 'error', 5000);
                });
        }
    </script>

</body>

</html>