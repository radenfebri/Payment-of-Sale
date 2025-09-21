<?php
// ======== BAGIAN PHP: Handle aksi untuk penjualan ========== 
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

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
                if (
                    stripos($item['nama'], $keyword) !== false ||
                    stripos($item['kodeProduk'], $keyword) !== false
                ) {
                    $results[] = $item;
                }
            }

            header('Content-Type: application/json');
            echo json_encode($results);
            exit;

        case 'simpan_penjualan':
            $input['id'] = time();
            $input['waktu'] = date('Y-m-d H:i:s');
            $penjualan[] = $input;

            // Update stok barang
            foreach ($input['items'] as $item) {
                foreach ($barang as &$b) {
                    if ($b['id'] == $item['id']) {
                        $b['stok'] -= $item['qty'];
                        break;
                    }
                }
            }

            // Jika ada hutang, simpan ke data hutang
            if ($input['hutang'] > 0) {
                $hutang[] = [
                    'id' => 'H' . time(),
                    'id_penjualan' => $input['id'],
                    'nama' => $input['nama_pembeli'] ?? 'Pelanggan',
                    'jumlah' => $input['hutang'],
                    'tanggal' => date('Y-m-d H:i:s'),
                    'status' => 'belum lunas',
                    'tanggal_bayar' => null
                ];
            }

            file_put_contents($penjualanFile, json_encode($penjualan, JSON_PRETTY_PRINT));
            file_put_contents($barangFile, json_encode($barang, JSON_PRETTY_PRINT));
            file_put_contents($hutangFile, json_encode($hutang, JSON_PRETTY_PRINT));

            header('Content-Type: application/json');
            echo json_encode(["success" => true, "id" => $input['id'], "hutang" => $input['hutang'] > 0]);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                            class="border rounded-md p-2 w-full" onkeyup="cariBarang()">
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

                    <table class="w-full text-left border border-gray-300 mb-4">
                        <thead>
                            <tr class="bg-gray-200">
                                <th class="p-2 border">Barang</th>
                                <th class="p-2 border">Harga</th>
                                <th class="p-2 border">Qty</th>
                                <th class="p-2 border">Jenis</th>
                                <th class="p-2 border">Subtotal</th>
                                <th class="p-2 border">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="keranjang">
                            <!-- Item keranjang akan ditampilkan di sini -->
                            <tr id="keranjangKosong">
                                <td colspan="6" class="p-3 text-center">Keranjang kosong</td>
                            </tr>
                        </tbody>
                    </table>

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
                            <label for="namaPembeli" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-user mr-1"></i> Nama Pembeli
                            </label>
                            <input type="text" id="namaPembeli" class="border rounded-md p-2 w-full"
                                placeholder="Masukkan nama pembeli">
                        </div>
                        <div>
                            <label for="bayar" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-money-bill-wave mr-1"></i> Jumlah Bayar
                            </label>
                            <input type="number" id="bayar" class="border rounded-md p-2 w-full"
                                placeholder="Jumlah bayar" oninput="hitungKembalian()">
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
                // Ambil harga ecer dan grosir
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

        // Menambah barang ke keranjang dengan jenis harga tertentu
        function tambahKeKeranjang(barang, jenisHarga) {
            // Tentukan harga berdasarkan jenis
            let harga = 0;
            if (barang.satuanHarga && barang.satuanHarga.length > 0) {
                if (jenisHarga === 'ecer') {
                    harga = barang.satuanHarga[0].hargaEcer;
                } else if (jenisHarga === 'grosir') {
                    harga = barang.satuanHarga[0].hargaGrosir;
                }
            }

            // Cek apakah barang dengan jenis harga yang sama sudah ada di keranjang
            const index = keranjang.findIndex(item => item.id === barang.id && item.jenisHarga === jenisHarga);

            if (index !== -1) {
                // Jika sudah ada, tambah kuantitas
                if (keranjang[index].qty < barang.stok) {
                    keranjang[index].qty += 1;
                } else {
                    alert('Stok tidak mencukupi!');
                    return;
                }
            } else {
                // Jika belum ada, tambah item baru
                if (barang.stok > 0) {
                    keranjang.push({
                        id: barang.id,
                        nama: barang.nama,
                        harga: harga,
                        jenisHarga: jenisHarga,
                        qty: 1,
                        stok: barang.stok
                    });
                } else {
                    alert('Stok habis!');
                    return;
                }
            }

            perbaruiKeranjang();
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
                total += subtotal;

                // Tentukan kelas untuk jenis harga
                const jenisClass = item.jenisHarga === 'ecer' ?
                    'bg-blue-100 text-blue-800' :
                    'bg-green-100 text-green-800';

                html += `
          <tr class="border-b hover:bg-gray-50 transition-colors duration-200">
            <td class="p-3">${item.nama}</td>
            <td class="p-3">Rp ${item.harga.toLocaleString('id-ID')}</td>
            <td class="p-3">
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
            <td class="p-3">
              <span class="text-xs px-2 py-1 rounded ${jenisClass}">${item.jenisHarga}</span>
            </td>
            <td class="p-3 font-medium">Rp ${subtotal.toLocaleString('id-ID')}</td>
            <td class="p-3">
              <button onclick="hapusDariKeranjang(${index})" class="bg-red-100 text-red-600 px-2 py-1 rounded text-sm hover:bg-red-200 transition-colors duration-200">
                <i class="fas fa-trash text-xs"></i>
              </button>
            </td>
          </tr>
        `;
            });

            container.innerHTML = html;
            hitungTotal();
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
        function ubahQty(index, delta) {
            const newQty = keranjang[index].qty + delta;

            if (newQty < 1) {
                hapusDariKeranjang(index);
            } else if (newQty > keranjang[index].stok) {
                alert('Stok tidak mencukupi!');
            } else {
                keranjang[index].qty = newQty;
                perbaruiKeranjang();
            }
        }

        // Mengubah kuantitas manual
        function ubahQtyManual(index, value) {
            const newQty = parseInt(value);

            if (isNaN(newQty) || newQty < 1) {
                keranjang[index].qty = 1;
            } else if (newQty > keranjang[index].stok) {
                alert('Stok tidak mencukupi!');
                keranjang[index].qty = keranjang[index].stok;
            } else {
                keranjang[index].qty = newQty;
            }

            perbaruiKeranjang();
        }

        // Menghapus item dari keranjang
        function hapusDariKeranjang(index) {
            keranjang.splice(index, 1);
            perbaruiKeranjang();
        }

        // Mengosongkan keranjang
        function kosongkanKeranjang() {
            if (keranjang.length === 0) return;

            if (confirm('Apakah Anda yakin ingin mengosongkan keranjang?')) {
                keranjang = [];
                perbaruiKeranjang();
            }
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
                alert('Keranjang masih kosong!');
                return;
            }

            const bayar = parseFloat(document.getElementById('bayar').value) || 0;
            const namaPembeli = document.getElementById('namaPembeli').value || 'Pelanggan';
            const kembalian = bayar - grandTotal;
            const hutang = kembalian < 0 ? Math.abs(kembalian) : 0;

            if (namaPembeli.trim() === '') {
                alert('Mohon isi nama pembeli terlebih dahulu!');
                document.getElementById('namaPembeli').focus();
                return;
            }

            if (bayar < grandTotal && !confirm(`Pembayaran kurang Rp ${hutang.toLocaleString('id-ID')}. Apakah ingin melanjutkan dengan hutang?`)) {
                return;
            }

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
                        alert(`Transaksi berhasil! Tercatat hutang sebesar Rp ${hutang.toLocaleString('id-ID')}`);
                    } else {
                        alert('Transaksi berhasil!');
                    }

                    tampilkanStruk(total, diskon, grandTotal, bayar, kembalian, hutang, namaPembeli);
                    keranjang = [];
                    perbaruiKeranjang();
                    document.getElementById('bayar').value = '';
                    document.getElementById('diskon').value = '0';
                    document.getElementById('namaPembeli').value = '';
                    document.getElementById('kembalian').value = '';
                    document.getElementById('hutangContainer').classList.add('hidden');
                } else {
                    alert('Gagal memproses pembayaran: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            }
        }

        // Menampilkan struk
        function tampilkanStruk(total, diskon, grandTotal, bayar, kembalian, hutang, namaPembeli) {
            const strukContent = document.getElementById('strukContent');
            let html = `
        <div class="border-b pb-2 mb-2">
          <p class="text-center font-semibold">TOKO MUDA YAKIN</p>
          <p class="text-center text-sm">${new Date().toLocaleString('id-ID')}</p>
          <p class="text-center text-sm mt-1"><i class="fas fa-user mr-1"></i> ${namaPembeli}</p>
        </div>
        
        <div class="mb-3 max-h-60 overflow-y-auto">
      `;

            keranjang.forEach(item => {
                html += `
          <div class="flex justify-between text-sm py-1">
            <span>${item.nama} <span class="text-xs ${item.jenisHarga === 'ecer' ? 'text-blue-600' : 'text-green-600'}">(${item.jenisHarga})</span> x${item.qty}</span>
            <span>Rp ${(item.harga * item.qty).toLocaleString('id-ID')}</span>
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
          <p><i class="fas fa-thumbs-up mr-1"></i> Terima kasih atas kunjungannya</p>
        </div>
      `;

            strukContent.innerHTML = html;
            document.getElementById('modalStruk').classList.remove('hidden');
        }

        // Menutup modal struk
        function tutupStruk() {
            document.getElementById('modalStruk').classList.add('hidden');
        }

        // Mencetak struk
        function cetakStruk() {
            const content = document.getElementById('strukContent').innerHTML;
            const printWindow = window.open('', '_blank');

            printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Cetak Struk</title>
          <style>
            body { font-family: Arial, sans-serif; font-size: 14px; padding: 15px; }
            .center { text-align: center; }
            .flex { display: flex; }
            .justify-between { justify-content: space-between; }
            .border-b { border-bottom: 1px dashed #000; padding-bottom: 5px; margin-bottom: 5px; }
            .border-t { border-top: 1px dashed #000; padding-top: 5px; margin-top: 5px; }
            .mb-2 { margin-bottom: 10px; }
            .mb-3 { margin-bottom: 15px; }
            .mt-4 { margin-top: 20px; }
            .pt-2 { padding-top: 10px; }
            .pb-2 { padding-bottom: 10px; }
          </style>
        </head>
        <body onload="window.print(); window.close();">
          ${content}
        </body>
        </html>
      `);

            printWindow.document.close();
        }
    </script>

</body>

</html>