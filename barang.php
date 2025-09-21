<?php
// ======== BAGIAN PHP: Handle aksi CRUD ========== 
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

$barangFile = __DIR__ . "/data/barang.json";
$satuanFile = __DIR__ . "/data/satuan.json";

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
</head>

<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include "partials/sidebar.php"; ?>

  <main class="flex-1 p-6">
    <h2 class="text-2xl font-semibold mb-4">📦 Manajemen Barang</h2>

    <!-- Form -->
    <div class="bg-white p-6 rounded-lg shadow-lg mb-6 max-w-4xl mx-auto">
      <form id="form" class="space-y-4">
        <input type="hidden" id="id" />

        <!-- Kode Produk & Nama Barang -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="kodeProduk" class="block text-sm font-medium text-gray-700">Kode Produk</label>
            <input id="kodeProduk" placeholder="Kode / Barcode" class="border rounded-md p-2 w-full bg-gray-100 text-gray-500 cursor-not-allowed" readonly />
          </div>
          <div>
            <label for="nama" class="block text-sm font-medium text-gray-700">Nama Barang</label>
            <input id="nama" placeholder="Nama Barang" class="border rounded-md p-2 w-full" required />
          </div>
        </div>

        <!-- Stok & Stok Minimal -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="stok" class="block text-sm font-medium text-gray-700">Stok</label>
            <input id="stok" type="number" placeholder="Jumlah Barang" class="border rounded-md p-2 w-full" required />
          </div>
          <div>
            <label for="stokMin" class="block text-sm font-medium text-gray-700">Stok Minimal</label>
            <input id="stokMin" type="number" placeholder="Jumlah Minimal" class="border rounded-md p-2 w-full" />
          </div>
        </div>

        <!-- Dropdown Satuan -->
        <div class="flex flex-col md:flex-row items-center gap-4">
          <div class="flex-1">
            <label for="satuan" class="block text-sm font-medium text-gray-700">Satuan</label>
            <select id="satuan" class="border rounded-md p-2 w-full bg-gray-50 focus:ring-2 focus:ring-blue-600">
              <option value="" disabled selected>Pilih Satuan</option>
            </select>
          </div>
          <button type="button" onclick="tambahSatuan()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-500 transition duration-300">+</button>
        </div>

        <!-- Harga -->
        <div>
          <h3 class="font-semibold text-lg text-gray-700 mt-4">Harga</h3>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
            <input id="hargaModal" type="number" placeholder="Harga Modal" class="border rounded-md p-2 w-full" required />
            <input id="hargaEcer" type="number" placeholder="Harga Ecer" class="border rounded-md p-2 w-full" required />
            <input id="hargaGrosir" type="number" placeholder="Harga Jual Ulang" class="border rounded-md p-2 w-full" required />
          </div>
        </div>

        <!-- Tombol Simpan dan Reset -->
        <div class="mt-6 flex gap-4 flex-col md:flex-row">
          <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-500 transition duration-300 w-full md:w-auto">Simpan</button>
          <button type="button" id="reset" class="bg-gray-500 text-white px-6 py-3 rounded-md hover:bg-gray-400 transition duration-300 w-full md:w-auto">Reset</button>
        </div>
      </form>
    </div>


    <!-- Table and Search -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6 overflow-x-auto">
      <!-- Pencarian dan Filter -->
      <div class="flex justify-between items-center mb-4">
        <div class="flex gap-3">
          <input id="search" type="text" class="border rounded-md p-2" placeholder="Cari Barang..." oninput="filterTable()">
          <select id="filterSatuan" class="border rounded-md p-2" onchange="filterTable()">
            <option value="">Filter Berdasarkan Satuan</option>
            <!-- Opsi satuan akan diisi oleh JavaScript -->
          </select>
          <button onclick="clearFilters()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-500">Reset Filter</button>
        </div>
        <span id="stokWarning" class="text-sm text-red-500"></span>
      </div>

      <!-- Tabel Barang -->
      <table class="w-full text-left border border-gray-300">
        <thead>
          <tr class="bg-gray-200">
            <th class="p-3 border">Kode</th>
            <th class="p-3 border">Nama</th>
            <th class="p-3 border">Stok</th>
            <th class="p-3 border">Satuan</th>
            <th class="p-3 border">Harga Modal</th>
            <th class="p-3 border">Harga Ecer</th>
            <th class="p-3 border">Harga Jual Ulang</th>
            <th class="p-3 border">Aksi</th>
          </tr>
        </thead>
        <tbody id="tabelBarang"></tbody>
      </table>
    </div>

  </main>

  <script>
    // Variabel global untuk menyimpan data barang
    let allBarang = [];
    let filteredBarang = [];

    // Fungsi untuk generate kode produk otomatis berdasarkan timestamp
    function generateKodeProduk() {
      const timestamp = Date.now().toString().slice(-6);
      document.getElementById("kodeProduk").value = "TMY" + timestamp;
    }

    // Event onLoad
    window.onload = function() {
      generateKodeProduk();
      loadSatuan();
      loadBarang();
      loadSatuanOptions(); // Memuat opsi satuan untuk filter
    };

    // Memuat opsi satuan untuk dropdown filter
    async function loadSatuanOptions() {
      try {
        let res = await fetch("?action=satuan_list");
        if (!res.ok) throw new Error("Gagal mengambil data satuan");

        let data = await res.json();
        let filterSatuanSelect = document.getElementById("filterSatuan");

        // Kosongkan dulu opsi yang ada (kecuali opsi default)
        filterSatuanSelect.innerHTML = '<option value="">Filter Berdasarkan Satuan</option>';

        // Tambahkan opsi satuan
        data.forEach(s => {
          filterSatuanSelect.innerHTML += `<option value="${s.nama}">${s.nama}</option>`;
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

        allBarang = await res.json();
        filteredBarang = [...allBarang];
        displayBarang(filteredBarang);
      } catch (error) {
        console.error("Error loading barang:", error);
        alert("Gagal memuat data barang: " + error.message);
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
        const isStokMinimum = b.stok <= b.stokMin;

        tbody.innerHTML += `
          <tr class="border-b hover:bg-gray-100 ${isStokMinimum ? 'bg-yellow-100' : ''}">
            <td class="p-3 border">${b.kodeProduk || '-'}</td>
            <td class="p-3 border">${b.nama || '-'}</td>
            <td class="p-3 border">${b.stok || 0}</td>
            <td class="p-3 border">${b.satuan || '-'}</td>
            <td class="p-3 border">Rp ${parseInt(harga.hargaModal || 0).toLocaleString('id-ID')}</td>
            <td class="p-3 border">Rp ${parseInt(harga.hargaEcer || 0).toLocaleString('id-ID')}</td>
            <td class="p-3 border">Rp ${parseInt(harga.hargaGrosir || 0).toLocaleString('id-ID')}</td>
            <td class="p-3 border">
              <button onclick="editBarang(${b.id})" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-400 text-sm">Edit</button>
              <button onclick='hapusBarang(${b.id})' class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-500 text-sm">Hapus</button>
            </td>
          </tr>
        `;
      });
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

        // Scroll ke form
        document.getElementById("form").scrollIntoView({
          behavior: 'smooth'
        });

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
          alert(isEdit ? "Barang berhasil diupdate!" : "Barang berhasil ditambahkan!");
          resetForm();
          loadBarang(); // Memuat ulang data barang
        } else {
          alert("Gagal menyimpan data: " + (result.error || "Unknown error"));
        }
      } catch (error) {
        console.error("Error saving barang:", error);
        alert("Error: " + error.message);
      }
    });

    // Reset form
    function resetForm() {
      document.getElementById("id").value = "";
      document.getElementById("nama").value = "";
      document.getElementById("stok").value = "";
      document.getElementById("stokMin").value = "";
      document.getElementById("satuan").value = "";
      document.getElementById("hargaModal").value = "";
      document.getElementById("hargaEcer").value = "";
      document.getElementById("hargaGrosir").value = "";

      generateKodeProduk();
    }

    // Menangani penghapusan barang
    async function hapusBarang(id) {
      if (!confirm("Yakin hapus barang ini?")) return;

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
          alert("Barang berhasil dihapus");
          loadBarang(); // Memuat ulang data barang
        } else {
          alert("Gagal menghapus barang: " + (result.error || "Unknown error"));
        }
      } catch (error) {
        console.error("Error deleting barang:", error);
        alert("Error: " + error.message);
      }
    }

    // Event listener untuk tombol reset
    document.getElementById("reset").addEventListener("click", resetForm);

    // Event listener untuk perubahan satuan
    document.getElementById("satuan").addEventListener("change", function() {
      const satuan = this.value;
      if (satuan) {
        document.getElementById("hargaModal").placeholder = "Harga Modal " + satuan;
        document.getElementById("hargaEcer").placeholder = "Harga Ecer " + satuan;
        document.getElementById("hargaGrosir").placeholder = "Harga Jual Ulang " + satuan;
      } else {
        document.getElementById("hargaModal").placeholder = "Harga Modal";
        document.getElementById("hargaEcer").placeholder = "Harga Ecer";
        document.getElementById("hargaGrosir").placeholder = "Harga Jual Ulang";
      }
    });
  </script>

</body>

</html>