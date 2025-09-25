<?php
// ======== BAGIAN PHP: Handle aksi CRUD ========== 
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

$barangFile = __DIR__ . "/data/barang.json";
$satuanFile = __DIR__ . "/data/satuan.json";
function loadJson(string $path, $default = [])
{
  if (!file_exists($path)) return $default;
  $raw = file_get_contents($path);
  $data = json_decode($raw, true);
  return is_array($data) ? $data : $default;
}

$setting = loadJson(__DIR__ . "/data/setting.json", []);

// Pastikan direktori data ada
if (!file_exists(__DIR__ . "/data")) {
  mkdir(__DIR__ . "/data", 0777, true);
}

// Pastikan file JSON ada, jika tidak buat file kosong
if (!file_exists($barangFile)) file_put_contents($barangFile, "[]");
if (!file_exists($satuanFile)) file_put_contents($satuanFile, "[]");

if (isset($_GET['action'])) {
  $barang = json_decode(file_get_contents($barangFile), true) ?? [];
  $satuan = json_decode(file_get_contents($satuanFile), true) ?? [];
  $input = json_decode(file_get_contents("php://input"), true);

  switch ($_GET['action']) {
    case 'list':
      header('Content-Type: application/json');
      echo json_encode($barang);
      exit;

    case 'add':
      $input['id'] = time();
      $input['satuanHarga'] = $input['satuanHarga'] ?? [];
      $barang[] = $input;
      file_put_contents($barangFile, json_encode($barang, JSON_PRETTY_PRINT));
      header('Content-Type: application/json');
      echo json_encode(["success" => true, "id" => $input['id']]);
      exit;

    case 'edit':
      $found = false;
      foreach ($barang as &$d) {
        if ($d['id'] == $input['id']) {
          $d = $input;
          $found = true;
          break;
        }
      }

      if ($found) {
        file_put_contents($barangFile, json_encode($barang, JSON_PRETTY_PRINT));
        header('Content-Type: application/json');
        echo json_encode(["success" => true]);
      } else {
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "error" => "Barang tidak ditemukan"]);
      }
      exit;

    case 'delete':
      $newBarang = [];
      $deleted = false;
      foreach ($barang as $d) {
        if ($d['id'] != $input['id']) {
          $newBarang[] = $d;
        } else {
          $deleted = true;
        }
      }

      if ($deleted) {
        file_put_contents($barangFile, json_encode($newBarang, JSON_PRETTY_PRINT));
        header('Content-Type: application/json');
        echo json_encode(["success" => true]);
      } else {
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "error" => "Barang tidak ditemukan"]);
      }
      exit;

    case 'satuan_list':
      header('Content-Type: application/json');
      echo json_encode($satuan);
      exit;

    case 'satuan_add':
      $satuan[] = ["id" => time(), "nama" => $input['nama']];
      file_put_contents($satuanFile, json_encode($satuan, JSON_PRETTY_PRINT));
      header('Content-Type: application/json');
      echo json_encode(["success" => true]);
      exit;
  }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <title>Manajemen Barang - POS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="alert.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .modal {
      transition: opacity 0.3s ease;
    }

    .modal.hidden {
      opacity: 0;
      pointer-events: none;
    }

    .modal>div {
      transform: scale(1);
      transition: transform 0.3s ease;
    }

    .modal.hidden>div {
      transform: scale(0.9);
    }

    /* PERBAIKAN: Tabel itu sendiri */
    table.w-full {
      min-height: 300px;
    }

    /* PERBAIKAN: Header yang sticky */
    .sticky-header thead tr {
      position: sticky;
      top: 0;
      z-index: 10;
      background-color: #f8fafc;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* PERBAIKAN: Baris tabel dengan tinggi yang lebih baik */
    .sticky-header tbody tr td {
      padding-top: 0.75rem;
      padding-bottom: 0.75rem;
      vertical-align: middle;
    }

    /* Efek hover untuk baris tabel */
    .hover-row:hover {
      background-color: #f3f4f6 !important;
    }

    table.w-full {
      width: 100%;
      table-layout: auto;
    }

    /* Responsif untuk layar kecil */
    @media (max-width: 768px) {
      .overflow-y-auto {
        max-height: calc(100vh - 300px);
      }

      /* Tambahkan scroll horizontal untuk tabel pada layar kecil */
      .table-container {
        overflow-x: auto;
      }
    }
  </style>
</head>

<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include "partials/sidebar.php"; ?>

  <main class="flex-1 p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-2xl font-semibold">📦 Manajemen Barang</h2>
      <button onclick="bukaModal()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500 transition flex items-center">
        <i class="fas fa-plus mr-2"></i> Tambah Barang
      </button>
    </div>

    <!-- Kembalikan ke struktur awal dengan sedikit modifikasi -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
      <!-- Pencarian dan Filter (tetap sama) -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
          <input id="search" type="text" class="border rounded-md p-2 w-full md:w-64" placeholder="Cari Barang..." oninput="filterTable()">
          <select id="filterSatuan" class="border rounded-md p-2 w-full md:w-48" onchange="filterTable()">
            <option value="">Filter Berdasarkan Satuan</option>
            <!-- Opsi satuan akan diisi oleh JavaScript -->
          </select>
          <button onclick="clearFilters()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-500 w-full md:w-auto">Reset Filter</button>
        </div>
        <span id="stokWarning" class="text-sm text-red-500"></span>
      </div>

      <!-- Tabel Barang - Header Fixed -->
      <div class="border border-gray-200 rounded-lg overflow-hidden">
        <div class="overflow-y-auto max-h-96 relative">
          <table class="w-full text-left">
            <thead class="sticky top-0 z-10">
              <tr class="bg-gray-100">
                <th class="p-3 border-b font-semibold bg-gray-100">Kode</th>
                <th class="p-3 border-b font-semibold bg-gray-100">Nama</th>
                <th class="p-3 border-b font-semibold bg-gray-100">Stok</th>
                <th class="p-3 border-b font-semibold bg-gray-100">Satuan</th>
                <th class="p-3 border-b font-semibold bg-gray-100">Harga Modal</th>
                <th class="p-3 border-b font-semibold bg-gray-100">Harga Ecer</th>
                <th class="p-3 border-b font-semibold bg-gray-100">Harga Jual Ulang</th>
                <th class="p-3 border-b font-semibold bg-gray-100 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody id="tabelBarang" class="divide-y divide-gray-200"></tbody>
          </table>
        </div>
      </div>
    </div>

  </main>

  <!-- Modal Form -->
  <div id="modalForm" class="modal fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-semibold" id="modalTitle">Tambah Barang Baru</h3>
          <button onclick="tutupModal()" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>

        <form id="form" class="space-y-4">
          <input type="hidden" id="id" />

          <!-- Kode Produk & Nama Barang -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="kodeProduk" class="block text-sm font-medium text-gray-700">Kode Produk</label>
              <div class="flex gap-2 mt-1">
                <input id="kodeProduk" placeholder="Scan barcode atau ketik kode"
                  class="border rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:outline-none" />
                <button type="button" onclick="generateKodeProduk()"
                  class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500 transition">Generate</button>
              </div>
            </div>
            <div>
              <label for="nama" class="block text-sm font-medium text-gray-700">Nama Barang</label>
              <input id="nama" placeholder="Masukkan Nama Barang"
                class="border rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:outline-none" required />
            </div>
          </div>

          <!-- Stok & Stok Minimal -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="stok" class="block text-sm font-medium text-gray-700">Stok</label>
              <input id="stok" type="number" placeholder="Jumlah Barang"
                class="border rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:outline-none" required />
            </div>
            <div>
              <label for="stokMin" class="block text-sm font-medium text-gray-700">Stok Minimal</label>
              <input id="stokMin" type="number" placeholder="Jumlah Minimal"
                class="border rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:outline-none" />
            </div>
          </div>

          <!-- Satuan -->
          <div class="flex gap-2 mt-1">
            <div class="flex-1">
              <label for="satuan" class="block text-sm font-medium text-gray-700">Satuan</label>
              <select id="satuan" class="border rounded-md p-2 w-full bg-gray-50 focus:ring-2 focus:ring-blue-400">
                <option value="" disabled selected>Pilih Satuan</option>
              </select>
            </div>
            <div class="flex items-end">
              <button type="button" onclick="tambahSatuan()"
                class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-500 transition">+</button>
            </div>
          </div>

          <!-- Harga -->
          <div>
            <h3 class="font-semibold text-lg text-gray-700 mb-2">Harga</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label for="hargaModal" class="block text-sm font-medium text-gray-700 mb-1">Harga Modal</label>
                <input id="hargaModal" type="number" placeholder="Harga Modal"
                  class="border rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:outline-none" />
              </div>
              <div>
                <label for="hargaEcer" class="block text-sm font-medium text-gray-700 mb-1">Harga Ecer</label>
                <input id="hargaEcer" type="number" placeholder="Harga Ecer"
                  class="border rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:outline-none" />
              </div>
              <div>
                <label for="hargaGrosir" class="block text-sm font-medium text-gray-700 mb-1">Harga Jual Ulang</label>
                <input id="hargaGrosir" type="number" placeholder="Harga Jual Ulang"
                  class="border rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:outline-none" />
              </div>
            </div>
          </div>

          <!-- Tombol Simpan & Reset -->
          <div class="flex flex-col md:flex-row gap-4 mt-6 pt-4 border-t border-gray-200">
            <button type="submit"
              class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-500 transition w-full md:w-auto flex items-center justify-center">
              <i class="fas fa-save mr-2"></i> Simpan
            </button>
            <button type="button" onclick="resetForm()"
              class="bg-gray-500 text-white px-6 py-3 rounded-md hover:bg-gray-400 transition w-full md:w-auto flex items-center justify-center">
              <i class="fas fa-redo mr-2"></i> Reset
            </button>
            <button type="button" onclick="tutupModal()"
              class="bg-red-600 text-white px-6 py-3 rounded-md hover:bg-red-500 transition w-full md:w-auto flex items-center justify-center">
              <i class="fas fa-times mr-2"></i> Batal
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Variabel global untuk menyimpan data barang
    let allBarang = [];
    let filteredBarang = [];
    // Gunakan json_encode agar selalu string JS yang valid
    window.APP = {
      TIPE_KODE: <?php echo json_encode($setting['tipe_kode'] ?? 'barcode'); ?>
    };

    // Fungsi untuk membuka modal
    function bukaModal() {
      resetForm();
      document.getElementById("modalTitle").textContent = "Tambah Barang Baru";
      document.getElementById("modalForm").classList.remove("hidden");
      document.getElementById("nama").focus();
    }

    // Fungsi untuk menutup modal
    function tutupModal() {
      document.getElementById("modalForm").classList.add("hidden");
    }

    // Fungsi untuk generate kode produk otomatis berdasarkan timestamp
    function generateKodeProduk() {
      const timestamp = Date.now().toString().slice(-6);
      document.getElementById("kodeProduk").value = "TMY" + timestamp;
    }

    // Event onLoad
    window.onload = function() {
      loadSatuan();
      loadBarang();
      loadSatuanOptions(); // Memuat opsi satuan untuk filter

      // Fokus pada input pencarian saat halaman dimuat
      document.getElementById("search").focus();
    };

    // Memuat opsi satuan untuk dropdown filter
    async function loadSatuanOptions() {
      try {
        let res = await fetch("?action=satuan_list");
        if (!res.ok) throw new Error("Gagal mengambil data satuan");

        let data = await res.json();
        let filterSatuanSelect = document.getElementById("filterSatuan");
        let satuanSelect = document.getElementById("satuan");

        // Kosongkan dulu opsi yang ada (kecuali opsi default)
        filterSatuanSelect.innerHTML = '<option value="">Filter Berdasarkan Satuan</option>';
        satuanSelect.innerHTML = '<option value="" disabled selected>Pilih Satuan</option>';

        // Tambahkan opsi satuan
        data.forEach(s => {
          filterSatuanSelect.innerHTML += `<option value="${s.nama}">${s.nama}</option>`;
          satuanSelect.innerHTML += `<option value="${s.nama}">${s.nama}</option>`;
        });
      } catch (error) {
        console.error("Error loading satuan options:", error);
      }
    }

    // Memuat daftar barang dan menampilkannya di tabel
    async function loadBarang() {
      try {
        let res = await fetch("?action=list");
        if (!res.ok) throw new Error("Gagal mengambil data barang");

        let data = await res.json();

        // Balik urutan supaya data terbaru muncul di atas
        allBarang = data.reverse();

        filteredBarang = [...allBarang];
        displayBarang(filteredBarang);

        // Tampilkan peringatan stok minimal
        checkStokMinimum();
      } catch (error) {
        console.error("Error loading barang:", error);
        alert("Gagal memuat data barang: " + error.message);
      }
    }


    // Cek stok minimum dan tampilkan peringatan
    function checkStokMinimum() {
      const barangStokMinimal = allBarang.filter(b => b.stok <= b.stokMin && b.stokMin > 0);

      if (barangStokMinimal.length > 0) {
        document.getElementById("stokWarning").textContent =
          `Peringatan: ${barangStokMinimal.length} barang mencapai stok minimal`;
      } else {
        document.getElementById("stokWarning").textContent = "";
      }
    }

    // Menampilkan data barang dalam tabel
    function displayBarang(data) {
      let tbody = document.getElementById("tabelBarang");
      tbody.innerHTML = "";

      if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="p-3 text-center">Tidak ada barang ditemukan</td></tr>`;
        return;
      }

      // Hitung tinggi maksimum berdasarkan viewport
      const tableContainer = document.querySelector('.overflow-y-auto');
      const headerHeight = document.querySelector('main').offsetTop;
      const availableHeight = window.innerHeight - headerHeight - 200; // 200px untuk padding dan elemen lain

      // Set tinggi maksimum untuk container tabel
      tableContainer.style.maxHeight = `${Math.max(300, availableHeight)}px`;

      // Menampilkan barang
      data.forEach(b => {
        // Cari harga berdasarkan satuan
        const harga = b.satuanHarga && b.satuanHarga.length > 0 ?
          b.satuanHarga[0] : {
            hargaModal: 0,
            hargaEcer: 0,
            hargaGrosir: 0
          };

        // Cek apakah stok sudah mencapai atau kurang dari stok minimal
        const isStokMinimum = b.stokMin > 0 && b.stok <= b.stokMin;

        let cetakBtn = "";
        if (window.APP.TIPE_KODE === "barcode") {
          cetakBtn = `
        <button onclick="printAsBarcode(${b.id}, 114, 6)"
          class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-400 text-sm flex items-center justify-center">
          <i class="fas fa-barcode mr-1"></i> Barcode
        </button>`;
        } else {
          cetakBtn = `
        <button onclick="printAsQr(${b.id}, 72, 6)"
          class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-400 text-sm flex items-center justify-center">
          <i class="fas fa-qrcode mr-1"></i> QR Code
        </button>`;
        }

        tbody.innerHTML += `
      <tr class="border-b hover-row ${isStokMinimum ? 'bg-yellow-100' : ''}">
        <td class="p-3 border">${b.kodeProduk || '-'}</td>
        <td class="p-3 border font-medium">${b.nama || '-'}</td>
        <td class="p-3 border ${isStokMinimum ? 'text-red-600 font-bold' : ''}">${b.stok || 0}</td>
        <td class="p-3 border">${b.satuan || '-'}</td>
        <td class="p-3 border">Rp ${parseInt(harga.hargaModal || 0).toLocaleString('id-ID')}</td>
        <td class="p-3 border">Rp ${parseInt(harga.hargaEcer || 0).toLocaleString('id-ID')}</td>
        <td class="p-3 border">Rp ${parseInt(harga.hargaGrosir || 0).toLocaleString('id-ID')}</td>
        <td class="p-3 border">
          <div class="flex flex-col sm:flex-row gap-2">
            <button onclick="editBarang(${b.id})" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-400 text-sm flex items-center justify-center">
              <i class="fas fa-edit mr-1"></i> Edit
            </button>
            ${cetakBtn}
            <button onclick="hapusBarang(${b.id})" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-500 text-sm flex items-center justify-center">
              <i class="fas fa-trash mr-1"></i> Hapus
            </button>
          </div>
        </td>
      </tr>
    `;
      });
    }

    // Tambahkan event listener untuk menangani resize window
    window.addEventListener('resize', function() {
      // Panggil ulang displayBarang untuk menyesuaikan tinggi tabel
      displayBarang(filteredBarang);
    });

    // Update fungsi loadBarang untuk memanggil displayBarang dengan benar
    async function loadBarang() {
      try {
        let res = await fetch("?action=list");
        if (!res.ok) throw new Error("Gagal mengambil data barang");

        let data = await res.json();

        // Balik urutan supaya data terbaru muncul di atas
        allBarang = data.reverse();

        filteredBarang = [...allBarang];
        displayBarang(filteredBarang);

        // Tampilkan peringatan stok minimal
        checkStokMinimum();
      } catch (error) {
        console.error("Error loading barang:", error);
        alert("Gagal memuat data barang: " + error.message);
      }
    }

    // Filter tabel berdasarkan pencarian dan satuan
    function filterTable() {
      let searchQuery = document.getElementById("search").value.toLowerCase();
      let filterSatuan = document.getElementById("filterSatuan").value;

      // Filter data barang berdasarkan pencarian dan satuan
      filteredBarang = allBarang.filter(b => {
        let matchSearch = true;
        let matchSatuan = true;

        // Cek pencarian berdasarkan nama atau kode produk
        if (searchQuery) {
          matchSearch = (
            (b.nama && b.nama.toLowerCase().includes(searchQuery)) ||
            (b.kodeProduk && b.kodeProduk.toLowerCase().includes(searchQuery))
          );
        }

        // Cek filter berdasarkan satuan
        if (filterSatuan) {
          matchSatuan = (b.satuan === filterSatuan);
        }

        return matchSearch && matchSatuan;
      });

      // Tampilkan barang setelah difilter
      displayBarang(filteredBarang);
    }

    // Menghapus filter dan mengembalikan ke keadaan semula
    function clearFilters() {
      document.getElementById("search").value = "";
      document.getElementById("filterSatuan").value = "";

      filteredBarang = [...allBarang];
      displayBarang(filteredBarang);
      document.getElementById("search").focus();
    }

    // Memuat daftar satuan dan menampilkannya di dropdown form
    async function loadSatuan() {
      try {
        let res = await fetch("?action=satuan_list");
        if (!res.ok) throw new Error("Gagal mengambil data satuan");

        let data = await res.json();
        let satuanSelect = document.getElementById("satuan");
        satuanSelect.innerHTML = '<option value="" disabled selected>Pilih Satuan</option>' +
          data.map(s => `<option value="${s.nama}">${s.nama}</option>`).join("");
      } catch (error) {
        console.error("Error loading satuan:", error);
      }
    }

    // Menambah satuan baru
    async function tambahSatuan() {
      let nama = prompt("Nama satuan baru:");
      if (!nama) return;

      try {
        let res = await fetch("?action=satuan_add", {
          method: "POST",
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            nama
          })
        });

        let result = await res.json();
        if (result.success) {
          loadSatuan();
          loadSatuanOptions(); // Memperbarui opsi filter juga
          alert("Satuan berhasil ditambahkan");
        } else {
          alert("Gagal menambahkan satuan");
        }
      } catch (error) {
        console.error("Error adding satuan:", error);
        alert("Error: " + error.message);
      }
    }

    // Fungsi untuk mendapatkan barang berdasarkan ID
    async function getBarangById(id) {
      try {
        let res = await fetch("?action=list");
        if (!res.ok) throw new Error("Gagal mengambil data barang");

        let data = await res.json();
        return data.find(b => b.id == id);
      } catch (error) {
        console.error("Error getting barang:", error);
        return null;
      }
    }

    // Menangani pengeditan barang
    async function editBarang(id) {
      try {
        const barang = await getBarangById(id);
        if (!barang) {
          alert("Barang tidak ditemukan");
          return;
        }

        document.getElementById("id").value = barang.id;
        document.getElementById("kodeProduk").value = barang.kodeProduk || "";
        document.getElementById("nama").value = barang.nama || "";
        document.getElementById("stok").value = barang.stok || "";
        document.getElementById("stokMin").value = barang.stokMin || "";

        // Set satuan
        if (barang.satuan) {
          document.getElementById("satuan").value = barang.satuan;
        }

        // Set harga
        const harga = barang.satuanHarga && barang.satuanHarga.length > 0 ?
          barang.satuanHarga[0] : {
            hargaModal: "",
            hargaEcer: "",
            hargaGrosir: ""
          };

        document.getElementById("hargaModal").value = harga.hargaModal || "";
        document.getElementById("hargaEcer").value = harga.hargaEcer || "";
        document.getElementById("hargaGrosir").value = harga.hargaGrosir || "";

        // Update judul modal
        document.getElementById("modalTitle").textContent = "Edit Barang: " + barang.nama;

        // Buka modal
        document.getElementById("modalForm").classList.remove("hidden");
        document.getElementById("nama").focus();

      } catch (error) {
        console.error("Error editing barang:", error);
        alert("Gagal memuat data barang: " + error.message);
      }
    }

    // Menangani form submission (tambah/edit barang)
    document.getElementById("form").addEventListener("submit", async (e) => {
      e.preventDefault();

      const id = document.getElementById("id").value;
      const isEdit = !!id;

      let barang = {
        id: id || Date.now(),
        kodeProduk: document.getElementById("kodeProduk").value,
        nama: document.getElementById("nama").value,
        stok: parseInt(document.getElementById("stok").value) || 0,
        stokMin: parseInt(document.getElementById("stokMin").value) || 0,
        satuan: document.getElementById("satuan").value,
        satuanHarga: [{
          satuan: document.getElementById("satuan").value,
          hargaModal: parseInt(document.getElementById("hargaModal").value) || 0,
          hargaEcer: parseInt(document.getElementById("hargaEcer").value) || 0,
          hargaGrosir: parseInt(document.getElementById("hargaGrosir").value) || 0
        }]
      };

      let action = isEdit ? "edit" : "add";

      try {
        let res = await fetch("?action=" + action, {
          method: "POST",
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(barang)
        });

        let result = await res.json();

        if (result.success) {
          showToast(isEdit ? "Barang berhasil diupdate!" : "Barang berhasil ditambahkan!", "success");

          if (!isEdit) {
            // Jika menambah barang baru, reset form untuk input berikutnya
            resetForm();
            document.getElementById("nama").focus();
          } else {
            // Jika edit, tutup modal
            tutupModal();
          }

          loadBarang(); // Memuat ulang data barang
        } else {
          showToast("Gagal menyimpan data: " + (result.error || "Unknown error"), "error");
        }
      } catch (error) {
        console.error("Error saving barang:", error);
        showToast("Error: " + error.message, "error");
      }
    });


    // Reset form
    function resetForm() {
      document.getElementById("id").value = "";
      document.getElementById("kodeProduk").value = "";
      document.getElementById("nama").value = "";
      document.getElementById("stok").value = "";
      document.getElementById("stokMin").value = "";
      document.getElementById("satuan").value = "";
      document.getElementById("hargaModal").value = "";
      document.getElementById("hargaEcer").value = "";
      document.getElementById("hargaGrosir").value = "";

      generateKodeProduk();
      document.getElementById("modalTitle").textContent = "Tambah Barang Baru";
    }

    // Menangani penghapusan barang
    async function hapusBarang(id) {
      showConfirm(
        "Yakin hapus barang ini?",
        async () => { // Callback OK
            try {
              let res = await fetch("?action=delete", {
                method: "POST",
                headers: {
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                  id: parseInt(id)
                })
              });

              let result = await res.json();

              if (result.success) {
                showToast("Barang berhasil dihapus!", "success");
                loadBarang(); // Muat ulang data barang
              } else {
                showToast("Gagal menghapus barang: " + (result.error || "Unknown error"), "error");
              }
            } catch (error) {
              console.error("Error deleting barang:", error);
              showToast("Error: " + error.message, "error");
            }
          },
          () => { // Callback Cancel
            showToast("Aksi dibatalkan", "info");
          }
      );
    }


    // Event listener untuk perubahan satuan
    document.getElementById("satuan").addEventListener("change", function() {
      const satuan = this.value;
      if (satuan) {
        document.getElementById("hargaModal").placeholder = "Harga Modal (" + satuan + ")";
        document.getElementById("hargaEcer").placeholder = "Harga Ecer (" + satuan + ")";
        document.getElementById("hargaGrosir").placeholder = "Harga Jual Ulang (" + satuan + ")";
      } else {
        document.getElementById("hargaModal").placeholder = "Harga Modal";
        document.getElementById("hargaEcer").placeholder = "Harga Ecer";
        document.getElementById("hargaGrosir").placeholder = "Harga Jual Ulang";
      }
    });

    // Tangkap input dari scanner (Enter key)
    document.getElementById("kodeProduk").addEventListener("keypress", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();
        document.getElementById("nama").focus();
      }
    });

    // Navigasi form dengan tombol Enter
    const formInputs = document.querySelectorAll('#form input, #form select');
    formInputs.forEach((input, index) => {
      input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          if (index < formInputs.length - 1) {
            formInputs[index + 1].focus();
          } else {
            document.getElementById('form').dispatchEvent(new Event('submit'));
          }
        }
      });
    });

    function generateQrCode(id) {
      window.open(`print_label.php?id=${encodeURIComponent(id)}`, '_blank');
    }

    function printAsQr(id, labels, cols) {
      window.open(
        `print_label.php?id=${encodeURIComponent(id)}&tipe_kode=qr&labels=${labels}&cols=${cols}`,
        '_blank'
      );
    }

    function printAsBarcode(id, labels, cols) {
      window.open(
        `print_label.php?id=${encodeURIComponent(id)}&tipe_kode=barcode&labels=${labels}&cols=${cols}`,
        '_blank'
      );
    }
  </script>

</body>

</html>