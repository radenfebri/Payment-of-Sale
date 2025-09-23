<?php
// satuan.php
$dataDir = __DIR__ . "/data";
$satuanFile = $dataDir . "/satuan.json";

// Pastikan direktori data ada
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Pastikan file JSON ada
if (!file_exists($satuanFile)) {
    file_put_contents($satuanFile, "[]");
}

if (isset($_GET['action'])) {
    // Baca data satuan
    $satuanData = file_get_contents($satuanFile);
    $satuan = json_decode($satuanData, true) ?? [];

    $input = json_decode(file_get_contents("php://input"), true);

    switch ($_GET['action']) {
        case 'get_satuan':
            header('Content-Type: application/json');
            echo json_encode($satuan);
            exit;

        case 'tambah_satuan':
            $nama = trim($input['nama'] ?? '');

            if (empty($nama)) {
                echo json_encode(['success' => false, 'message' => 'Nama satuan tidak boleh kosong']);
                exit;
            }

            // Cek apakah satuan sudah ada
            foreach ($satuan as $s) {
                if (strtolower($s['nama']) === strtolower($nama)) {
                    echo json_encode(['success' => false, 'message' => 'Satuan sudah ada']);
                    exit;
                }
            }

            $newSatuan = [
                'id' => time(),
                'nama' => $nama
            ];

            $satuan[] = $newSatuan;
            file_put_contents($satuanFile, json_encode($satuan, JSON_PRETTY_PRINT));

            echo json_encode(['success' => true, 'data' => $newSatuan]);
            exit;

        case 'edit_satuan':
            $id = $input['id'] ?? 0;
            $nama = trim($input['nama'] ?? '');

            if (empty($nama)) {
                echo json_encode(['success' => false, 'message' => 'Nama satuan tidak boleh kosong']);
                exit;
            }

            $found = false;
            foreach ($satuan as &$s) {
                if ($s['id'] == $id) {
                    $s['nama'] = $nama;
                    $found = true;
                    break;
                }
            }

            if ($found) {
                file_put_contents($satuanFile, json_encode($satuan, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Satuan tidak ditemukan']);
            }
            exit;

        case 'hapus_satuan':
            $id = $input['id'] ?? 0;

            $newSatuan = array_filter($satuan, function ($s) use ($id) {
                return $s['id'] != $id;
            });

            file_put_contents($satuanFile, json_encode(array_values($newSatuan), JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <title>Manajemen Satuan - POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="alert.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }

        .sticky-thead th {
            position: sticky;
            top: 0;
            background: #f3f4f6;
            z-index: 10;
        }

        /* Animasi untuk modal */
        .modal-transition {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .modal-enter {
            opacity: 0;
            transform: scale(0.95);
        }

        .modal-enter-active {
            opacity: 1;
            transform: scale(1);
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex">
    <!-- Sidebar -->
    <?php include "partials/sidebar.php"; ?>

    <main class="flex-1 p-6">
        <h2 class="text-2xl font-semibold mb-6">📏 Manajemen Satuan</h2>

        <!-- Form Tambah Satuan -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <h3 class="text-lg font-semibold mb-4">Tambah Satuan Baru</h3>
            <form id="formSatuan" class="flex gap-4">
                <div class="flex-1">
                    <input type="text" name="nama" placeholder="Nama satuan (contoh: pcs, box, kg)"
                        class="w-full border rounded-md p-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                        required>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-500 transition-colors">
                    <i class="fas fa-plus mr-1"></i> Tambah
                </button>
            </form>
        </div>

        <!-- Daftar Satuan -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="mb-4 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Daftar Satuan</h3>
                <input type="text" id="searchSatuan" placeholder="Cari satuan..."
                    class="border rounded-md p-2 w-1/3 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
            </div>

            <div class="table-container">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100 sticky-thead">
                            <th class="p-3 border-b font-semibold">No</th>
                            <th class="p-3 border-b font-semibold">Nama Satuan</th>
                            <th class="p-3 border-b font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="daftarSatuan">
                        <tr>
                            <td colspan="3" class="p-4 text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- Modal Edit Satuan -->
        <div id="modalEditSatuan" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50 modal-transition modal-enter">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Edit Satuan</h3>
                        <button onclick="tutupModalEditSatuan()" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <form id="formEditSatuan">
                        <input type="hidden" id="editId" name="id">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Satuan</label>
                            <input type="text" id="editNama" name="nama"
                                class="w-full border rounded-md p-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                                required>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="tutupModalEditSatuan()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition-colors">
                                Batal
                            </button>
                            <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-500 transition-colors">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Muat data satuan
        async function muatDaftarSatuan() {
            try {
                // console.log('Memuat data satuan...');
                const response = await fetch('satuan.php?action=get_satuan');

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const satuanData = await response.json();
                // console.log('Data diterima:', satuanData);
                tampilkanDaftarSatuan(satuanData);
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('daftarSatuan').innerHTML = `
                    <tr><td colspan="3" class="p-4 text-center text-red-500">Gagal memuat data satuan: ${error.message}</td></tr>
                `;
            }
        }

        // Tampilkan daftar satuan
        function tampilkanDaftarSatuan(satuan) {
            const container = document.getElementById('daftarSatuan');

            // Urut berdasarkan id descending (data terbaru di atas)
            satuan.sort((a, b) => b.id - a.id);

            // Filter berdasarkan search
            const searchValue = document.getElementById('searchSatuan')?.value.trim().toLowerCase();
            const filtered = searchValue ?
                satuan.filter(item => item.nama.toLowerCase().includes(searchValue)) :
                satuan;

            if (filtered.length === 0) {
                container.innerHTML = `
            <tr><td colspan="3" class="p-4 text-center text-gray-500">Tidak ada data satuan</td></tr>
        `;
                return;
            }

            let html = '';
            filtered.forEach((item, index) => {
                const escapedNama = item.nama.replace(/'/g, "\\'").replace(/"/g, '\\"');

                html += `
            <tr class="border-b hover:bg-gray-50 transition-colors">
                <td class="p-3">${index + 1}</td>
                <td class="p-3 font-medium">${item.nama}</td>
                <td class="p-3">
                    <div class="flex justify-center space-x-2">
                        <button onclick="bukaModalEditSatuan(${item.id}, '${escapedNama}')" 
                                class="text-blue-500 hover:text-blue-700 px-3 py-1 rounded hover:bg-blue-50 transition-colors" 
                                title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="hapusSatuan(${item.id})" 
                                class="text-red-500 hover:text-red-700 px-3 py-1 rounded hover:bg-red-50 transition-colors" 
                                title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
            });

            container.innerHTML = html;
        }


        document.getElementById('searchSatuan').addEventListener('input', () => {
            // Muat ulang tabel tanpa fetch lagi, cukup tampilkan yang sudah diambil
            fetch('satuan.php?action=get_satuan')
                .then(res => res.json())
                .then(data => tampilkanDaftarSatuan(data))
                .catch(err => console.error(err));
        });


        // Form tambah satuan
        document.getElementById('formSatuan').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {
                nama: formData.get('nama').trim()
            };

            if (!data.nama) {
                showToast('Nama satuan harus diisi!', 'error');
                return;
            }

            try {
                const response = await fetch('satuan.php?action=tambah_satuan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Satuan berhasil ditambahkan!', 'success');
                    this.reset();
                    muatDaftarSatuan();
                } else {
                    showToast('Gagal: ' + (result.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Terjadi kesalahan', 'error');
            }
        });


        // Modal edit satuan
        function bukaModalEditSatuan(id, nama) {
            document.getElementById('editId').value = id;
            document.getElementById('editNama').value = nama;

            const modal = document.getElementById('modalEditSatuan');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('modal-enter');
            }, 10);
        }

        function tutupModalEditSatuan() {
            const modal = document.getElementById('modalEditSatuan');
            modal.classList.add('modal-enter');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        // Form edit satuan
        document.getElementById('formEditSatuan').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {
                id: parseInt(formData.get('id')),
                nama: formData.get('nama').trim()
            };

            if (!data.nama) {
                showToast('Nama satuan harus diisi!', 'error');
                return;
            }

            try {
                const response = await fetch('satuan.php?action=edit_satuan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Satuan berhasil diupdate!', 'success');
                    tutupModalEditSatuan();
                    muatDaftarSatuan();
                } else {
                    showToast('Gagal: ' + (result.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Terjadi kesalahan', 'error');
            }
        });


        // Hapus satuan
        function hapusSatuan(id) {
            showConfirm(
                'Apakah Anda yakin ingin menghapus satuan ini?',
                async () => { // Callback OK
                        try {
                            const response = await fetch('satuan.php?action=hapus_satuan', {
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
                                showToast('Satuan berhasil dihapus!', 'success');
                                muatDaftarSatuan();
                            } else {
                                showToast('Gagal menghapus satuan: ' + (result.message || ''), 'error');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            showToast('Terjadi kesalahan: ' + error.message, 'error');
                        }
                    },
                    () => { // Callback Cancel
                        showToast('Aksi dibatalkan', 'info');
                    }
            );
        }


        // Muat data saat halaman dimuat
        document.addEventListener('DOMContentLoaded', muatDaftarSatuan);
    </script>
</body>

</html>