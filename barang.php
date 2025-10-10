<?php
// ======== BAGIAN PHP: Handle aksi CRUD ========== 
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

$barangFile = __DIR__ . "/data/barang.json";
$satuanFile = __DIR__ . "/data/satuan.json";

// --- DEFINISI loadJson (pastikan ada) ---
if (!function_exists('loadJson')) {
  function loadJson(string $path, $default = [])
  {
    if (!is_file($path)) return $default;
    $raw = @file_get_contents($path);
    if ($raw === false) return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
  }
}

// Pastikan direktori data ada
if (!is_dir(__DIR__ . "/data")) {
  @mkdir(__DIR__ . "/data", 0777, true);
}

// Muat setting.json (boleh kosong, akan di-merge default)
$settingPath = __DIR__ . "/data/setting.json";
$setting = loadJson($settingPath, []);

// default agar key selalu ada
$defaults = [
  'tipe_kode' => 'barcode',
  'label' => [
    'tipe_kode' => 'barcode',
    'barcode'   => ['width_px' => 114, 'per_row' => 6],
    'qr'        => ['size_px'  =>  72, 'per_row' => 6, 'ecc' => 'M'],
  ],
];

$setting = array_replace_recursive($defaults, $setting);

// Pastikan file JSON ada, jika tidak buat file kosong
if (!is_file($barangFile))  file_put_contents($barangFile, "[]", LOCK_EX);
if (!is_file($satuanFile))  file_put_contents($satuanFile, "[]", LOCK_EX);

if (!function_exists('isVarUnit')) {
  function isVarUnit($satuanNama, array $satuanList): bool
  {
    $key = strtolower(trim((string)$satuanNama));
    if ($key === '') return false;
    foreach ($satuanList as $s) {
      $nm = strtolower(trim((string)($s['nama'] ?? '')));
      if ($nm === $key) {
        return !empty($s['is_variable']); // true kalau support desimal
      }
    }
    return false;
  }
}

if (isset($_GET['action'])) {
  $barang = json_decode(file_get_contents($barangFile), true) ?? [];
  $satuan = json_decode(file_get_contents($satuanFile), true) ?? [];
  $input  = json_decode(file_get_contents("php://input"), true) ?? [];

  switch ($_GET['action']) {
    case 'list':
      header('Content-Type: application/json');
      echo json_encode($barang);
      exit;

    case 'add':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
      }
      $input['id'] = time();
      $input['satuanHarga'] = $input['satuanHarga'] ?? [];
      $satuanNama = $input['satuan'] ?? ($input['satuanHarga'][0]['satuan'] ?? '');
      $isVar = isVarUnit($satuanNama, $satuan);
      $stokRaw = (float)($input['stok'] ?? 0);
      $input['stok'] = $isVar ? max(0, round($stokRaw, 3)) : max(0, (int)round($stokRaw));
      $barang[] = $input;
      file_put_contents(
        $barangFile,
        json_encode($barang, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
      );
      header('Content-Type: application/json');
      echo json_encode(["success" => true, "id" => $input['id']]);
      exit;

    case 'edit':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
      }
      $found = false;
      foreach ($barang as &$d) {
        $satuanNama = $input['satuan'] ?? ($input['satuanHarga'][0]['satuan'] ?? '');
        $isVar = isVarUnit($satuanNama, $satuan);
        $stokRaw = (float)($input['stok'] ?? 0);
        $input['stok'] = $isVar ? max(0, round($stokRaw, 3)) : max(0, (int)round($stokRaw));

        if ((int)$d['id'] === (int)($input['id'] ?? 0)) {
          $d = $input;
          $found = true;
          break;
        }
      }
      header('Content-Type: application/json');
      if ($found) {
        file_put_contents(
          $barangFile,
          json_encode($barang, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          LOCK_EX
        );
        echo json_encode(["success" => true]);
      } else {
        echo json_encode(["success" => false, "error" => "Barang tidak ditemukan"]);
      }
      exit;

    case 'delete':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
      }
      $idHapus = (int)($input['id'] ?? 0);
      $newBarang = [];
      $deleted = false;
      foreach ($barang as $d) {
        if ((int)$d['id'] !== $idHapus) {
          $newBarang[] = $d;
        } else {
          $deleted = true;
        }
      }
      header('Content-Type: application/json');
      if ($deleted) {
        file_put_contents(
          $barangFile,
          json_encode($newBarang, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          LOCK_EX
        );
        echo json_encode(["success" => true]);
      } else {
        echo json_encode(["success" => false, "error" => "Barang tidak ditemukan"]);
      }
      exit;

    case 'satuan_list':
      header('Content-Type: application/json');
      echo json_encode($satuan);
      exit;

    case 'satuan_add':
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
      }
      $namaBaru = trim((string)($input['nama'] ?? ''));
      $isVariable = !empty($input['is_variable']); // <-- baru

      if ($namaBaru === '') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Nama satuan wajib diisi']);
        exit;
      }

      $satuan[] = [
        "id" => time(),
        "nama" => $namaBaru,
        "is_variable" => (bool)$isVariable, // <-- baru
      ];

      file_put_contents(
        $satuanFile,
        json_encode($satuan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
      );
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
  <script>
    window.APP = <?= json_encode([
                    'TIPE_KODE' => strtolower($setting['tipe_kode'] ?? 'barcode'),
                    'settings'  => $setting,
                  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  </script>
</head>

<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include "partials/sidebar.php"; ?>

  <main class="flex-1 md:ml-64 ml-0 p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl font-semibold">📦 Manajemen Barang</h2>

      <div class="flex items-center gap-2">
        <button id="btnCetakSemua" type="button"
          class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-500 transition flex items-center">
          <i class="fas fa-print mr-2"></i> Cetak Semua
        </button>

        <button onclick="bukaModal()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500 transition flex items-center">
          <i class="fas fa-plus mr-2"></i> Tambah Barang
        </button>
      </div>
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
        <div class="overflow-y-auto max-h-[calc(100vh-16rem)] relative">
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
    let SATUAN_MAP = new Map();

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
      loadSatuanOptions();
      document.getElementById("search").focus();
      setupCetakSemuaButton();
    };

    // Memuat opsi satuan untuk dropdown filter
    async function loadSatuanOptions() {
      try {
        let res = await fetch("?action=satuan_list");
        if (!res.ok) throw new Error("Gagal mengambil data satuan");

        let data = await res.json();
        SATUAN_MAP.clear();
        data.forEach(s => {
          SATUAN_MAP.set(String(s.nama || '').toLowerCase(), {
            is_variable: !!s.is_variable
          });
        });

        let filterSatuanSelect = document.getElementById("filterSatuan");
        let satuanSelect = document.getElementById("satuan");

        filterSatuanSelect.innerHTML = '<option value="">Filter Berdasarkan Satuan</option>';
        satuanSelect.innerHTML = '<option value="" disabled selected>Pilih Satuan</option>';

        data.forEach(s => {
          filterSatuanSelect.innerHTML += `<option value="${s.nama}">${s.nama}</option>`;
          satuanSelect.innerHTML += `<option value="${s.nama}">${s.nama}${s.is_variable ? ' (timbang)' : ''}</option>`;
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

        tbody.innerHTML += `
      <tr class="border-b hover-row ${isStokMinimum ? 'bg-yellow-100' : ''}">
        <td class="p-3 border">${b.kodeProduk || '-'}</td>
        <td class="p-3 border font-medium">${b.nama || '-'}</td>
        <td class="p-3 border ${isStokMinimum ? 'text-red-600 font-bold' : ''}">${b.stok || 0}</td>
        <td class="p-3 border">${b.satuan || '-'}</td>
        <td class="p-3 border">Rp ${parseInt(harga.hargaModal || 0).toLocaleString('id-ID')}</td>
        <td class="p-3 border">Rp ${parseInt(harga.hargaEcer || 0).toLocaleString('id-ID')}</td>
        <td class="p-3 border">Rp ${parseInt(harga.hargaGrosir || 0).toLocaleString('id-ID')}</td>
        <td class="p-3 border text-center">
          <div class="flex flex-col sm:flex-row gap-2 justify-center">
            <button onclick="editBarang(${b.id})" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-400 text-sm flex items-center justify-center">
              <i class="fas fa-edit mr-1"></i> Edit
            </button>
            ${buildCetakBtn(b)}
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

      let isVar = confirm("Apakah satuan ini support timbang (qty desimal)?");

      try {
        let res = await fetch("?action=satuan_add", {
          method: "POST",
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            nama,
            is_variable: isVar
          }) // <-- kirim flag
        });

        let result = await res.json();
        if (result.success) {
          await loadSatuanOptions();
          await loadSatuan(); // refresh dropdown form
          showToast("Satuan berhasil ditambahkan", "success");
        } else {
          showToast("Gagal menambahkan satuan", "error");
        }
      } catch (error) {
        console.error("Error adding satuan:", error);
        showToast("Error: " + error.message, "error");
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
          document.getElementById("satuan").dispatchEvent(new Event('change'));
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

      const satuanNama = document.getElementById("satuan").value;
      const isVar = SATUAN_MAP.get(String(satuanNama || '').toLowerCase())?.is_variable === true;

      let stokInput = document.getElementById("stok").value;
      let stokVal = parseFloat(stokInput);

      if (!isFinite(stokVal)) stokVal = 0;

      if (isVar) {
        // izinkan desimal, simpan 3 angka di belakang koma
        stokVal = Math.max(0, Math.round(stokVal * 1000) / 1000);
      } else {
        // wajib bulat
        stokVal = Math.max(0, Math.round(stokVal));
      }

      let barang = {
        id: id || Date.now(),
        kodeProduk: document.getElementById("kodeProduk").value,
        nama: document.getElementById("nama").value,
        stok: stokVal,
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
      const stokEl = document.getElementById("stok");
      const key = String(satuan || '').toLowerCase();
      const isVar = SATUAN_MAP.get(key)?.is_variable === true;

      // placeholder harga (sudah ada)
      if (satuan) {
        document.getElementById("hargaModal").placeholder = "Harga Modal (" + satuan + ")";
        document.getElementById("hargaEcer").placeholder = "Harga Ecer (" + satuan + ")";
        document.getElementById("hargaGrosir").placeholder = "Harga Jual Ulang (" + satuan + ")";
      } else {
        document.getElementById("hargaModal").placeholder = "Harga Modal";
        document.getElementById("hargaEcer").placeholder = "Harga Ecer";
        document.getElementById("hargaGrosir").placeholder = "Harga Jual Ulang";
      }

      // <-- baru: atur input stok
      if (isVar) {
        stokEl.step = "0.01";
        stokEl.min = "0.01";
        stokEl.placeholder = "Jumlah (" + satuan + ", boleh 2-3 desimal)";
      } else {
        stokEl.step = "1";
        stokEl.min = "0";
        stokEl.placeholder = "Jumlah (" + satuan + ", bilangan bulat)";
      }
    });



    // ==== Auto-capture scanner (global) untuk form Tambah Barang ====
    const SCAN_ADD = {
      MIN_LEN: 6,
      MAX_INTERVAL: 50,
      END_WAIT: 120
    }; // ms
    let addBuf = '',
      addTimer = null,
      ADD_SCANNING = false;
    let lastKeyChar = null,
      lastKeyTime = 0;

    function addResetScan() {
      addBuf = '';
      ADD_SCANNING = false;
      if (addTimer) {
        clearTimeout(addTimer);
        addTimer = null;
      }
    }

    function handleScannedForAdd(raw) {
      const code = String(raw).replace(/[\r\n\t]+/g, '').trim();
      if (!code) return;

      // Buka modal kalau belum terbuka
      const modal = document.getElementById('modalForm');
      if (modal && modal.classList.contains('hidden')) bukaModal();

      // Isi ke #kodeProduk
      const kodeEl = document.getElementById('kodeProduk');
      if (kodeEl) {
        kodeEl.value = code;
        kodeEl.focus();
      }

      // Cek duplikat
      const exist = (window.allBarang || []).find(b =>
        (b.kodeProduk || '').toLowerCase() === code.toLowerCase()
      );
      if (exist) {
        showConfirm(
          `Kode ${code} sudah dipakai untuk "${exist.nama ?? '-'}". Buka Edit?`,
          () => editBarang(exist.id),
          () => {
            kodeEl?.focus();
          }
        );
      }
    }

    function addFinishScan(raw) {
      handleScannedForAdd(raw);
      addResetScan();
    }

    // PENTING: pakai capturing agar bisa cegah input lebih awal
    document.addEventListener('keydown', (e) => {
      const el = document.activeElement;
      const printable = (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey);

      // ENTER/TAB: akhiri scan kalau sedang scanning
      if (e.key === 'Enter' || e.key === 'Tab') {
        if (ADD_SCANNING || addBuf) {
          e.preventDefault();
          e.stopPropagation();
          return addFinishScan(addBuf);
        }
        // kalau tidak scanning, biarkan default (mis. pindah fokus)
        return;
      }

      if (!printable) return;

      const now = performance.now();
      const gap = now - (lastKeyTime || now);

      // --- TRANSISI ke mode scan: karakter ke-2 (gap cepat) ---
      if (!ADD_SCANNING && gap <= SCAN_ADD.MAX_INTERVAL) {
        ADD_SCANNING = true;

        // Mulai buffer dengan (karakter pertama + karakter sekarang)
        addBuf = (lastKeyChar || '') + e.key;

        // OPSIONAL: rollback 1 char yang sudah terlanjur ngetik ke input fokus
        if (el && 'value' in el && typeof el.value === 'string' &&
          el.selectionStart === el.value.length && el.selectionEnd === el.value.length &&
          el.value.length > 0) {
          el.value = el.value.slice(0, -1);
        }

        e.preventDefault();
        e.stopPropagation();
        if (addTimer) clearTimeout(addTimer);
        addTimer = setTimeout(() => {
          (addBuf.length >= SCAN_ADD.MIN_LEN) ? addFinishScan(addBuf): addResetScan();
        }, SCAN_ADD.END_WAIT);

      } else if (ADD_SCANNING) {
        // Sudah di mode scan → cegah input normal & buffer char
        e.preventDefault();
        e.stopPropagation();
        addBuf += e.key;
        if (addTimer) clearTimeout(addTimer);
        addTimer = setTimeout(() => {
          (addBuf.length >= SCAN_ADD.MIN_LEN) ? addFinishScan(addBuf): addResetScan();
        }, SCAN_ADD.END_WAIT);
      }

      // Simpan karakter & waktu untuk deteksi next key
      lastKeyChar = e.key;
      lastKeyTime = now;
    }, true);

    // BONUS: kalau aplikasi scanner “paste all at once”
    document.addEventListener('paste', (e) => {
      const txt = e.clipboardData?.getData('text') ?? '';
      if (txt && txt.length >= SCAN_ADD.MIN_LEN) {
        e.preventDefault();
        e.stopPropagation();
        addFinishScan(txt);
      }
    }, true);

    // Enter di #kodeProduk untuk input manual (opsional)
    document.getElementById('kodeProduk')?.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        if (ADD_SCANNING) return; // kalau sesi scan, abaikan
        e.preventDefault();
        document.getElementById('stok')?.focus(); // atau biarkan tetap di kode
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

    // helper tombol cetak yang taat setting
    function buildCetakBtn(b) {
      const labelCfg = (window.APP?.settings?.label) || {};
      const tipe = (labelCfg.tipe_kode || window.APP.TIPE_KODE || 'barcode').toLowerCase();

      if (tipe === 'barcode') {
        const widthPx = labelCfg.barcode?.width_px ?? 114;
        const perRow = labelCfg.barcode?.per_row ?? 6;
        return `
      <button onclick="printAsBarcode(${b.id}, ${widthPx}, ${perRow})"
        class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-400 text-sm flex items-center justify-center">
        <i class="fas fa-barcode mr-1"></i> Barcode
      </button>`;
      } else {
        const sizePx = labelCfg.qr?.size_px ?? 72;
        const perRow = labelCfg.qr?.per_row ?? 6;
        return `
      <button onclick="printAsQr(${b.id}, ${sizePx}, ${perRow})"
        class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-400 text-sm flex items-center justify-center">
        <i class="fas fa-qrcode mr-1"></i> QR Code
      </button>`;
      }
    }


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

    function setupCetakSemuaButton() {
      const tipe = (window.APP?.settings?.label?.tipe_kode || window.APP?.TIPE_KODE || 'barcode').toLowerCase();
      const btn = document.getElementById('btnCetakSemua');

      const icon = (tipe === 'qr') ? 'fa-qrcode' : 'fa-barcode';
      const label = (tipe === 'qr') ? 'Cetak Semua QR' : 'Cetak Semua Barcode';
      btn.innerHTML = `<i class="fas ${icon} mr-2"></i> ${label}`;

      btn.onclick = function() {
        window.open(`print_all_labels.php?tipe_kode=${encodeURIComponent(tipe)}`, '_blank');
      };
    }
  </script>

</body>

</html>