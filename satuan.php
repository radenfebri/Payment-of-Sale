<?php
// satuan.php
$satuanFile = __DIR__ . "/data/satuan.json";

// Pastikan file JSON ada
if (!file_exists($satuanFile)) {
    file_put_contents($satuanFile, "[]");
}

if (isset($_GET['action'])) {
    $satuan = json_decode(file_get_contents($satuanFile), true) ?? [];
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        class="w-full border rounded-md p-2" required>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-500">
                    <i class="fas fa-plus mr-1"></i> Tambah
                </button>
            </form>
        </div>

        <!-- Daftar Satuan -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="text-lg font-semibold mb-4">Daftar Satuan</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3 border-b">No</th>
                            <th class="p-3 border-b">Nama Satuan</th>
                            <th class="p-3 border-b">Aksi</th>
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
        <div id="modalEditSatuan" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                <div class="p-6">
                    <h3 class="text-xl font-semibold mb-4">Edit Satuan</h3>
                    <form id="formEditSatuan">
                        <input type="hidden" id="editId" name="id">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Satuan</label>
                            <input type="text" id="editNama" name="nama" class="w-full border rounded-md p-2" required>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="tutupModalEditSatuan()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
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
    </main>

    <script>
        // Muat data satuan
        async function muatDaftarSatuan() {
            try {
                const response = await fetch('satuan.php?action=get_satuan');
                const satuanData = await response.json();
                tampilkanDaftarSatuan(satuanData);
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('daftarSatuan').innerHTML = `
                    <tr><td colspan="3" class="p-4 text-center text-red-500">Gagal memuat data satuan</td></tr>
                `;
            }
        }

        // Tampilkan daftar satuan
        function tampilkanDaftarSatuan(satuan) {
            const container = document.getElementById('daftarSatuan');

            if (satuan.length === 0) {
                container.innerHTML = `
                    <tr><td colspan="3" class="p-4 text-center text-gray-500">Tidak ada data satuan</td></tr>
                `;
                return;
            }

            let html = '';
            satuan.forEach((item, index) => {
                html += `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3">${index + 1}</td>
                        <td class="p-3 font-medium">${item.nama}</td>
                        <td class="p-3">
                            <div class="flex space-x-2">
                                <button onclick="bukaModalEditSatuan(${item.id}, '${item.nama}')" 
                                        class="text-blue-500 hover:text-blue-700" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="hapusSatuan(${item.id})" 
                                        class="text-red-500 hover:text-red-700" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            container.innerHTML = html;
        }

        // Form tambah satuan
        document.getElementById('formSatuan').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {
                nama: formData.get('nama')
            };

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
                    alert('Satuan berhasil ditambahkan!');
                    this.reset();
                    muatDaftarSatuan();
                } else {
                    alert('Gagal: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan');
            }
        });

        // Modal edit satuan
        function bukaModalEditSatuan(id, nama) {
            document.getElementById('editId').value = id;
            document.getElementById('editNama').value = nama;
            document.getElementById('modalEditSatuan').classList.remove('hidden');
        }

        function tutupModalEditSatuan() {
            document.getElementById('modalEditSatuan').classList.add('hidden');
        }

        // Form edit satuan
        document.getElementById('formEditSatuan').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {
                id: parseInt(formData.get('id')),
                nama: formData.get('nama')
            };

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
                    alert('Satuan berhasil diupdate!');
                    tutupModalEditSatuan();
                    muatDaftarSatuan();
                } else {
                    alert('Gagal: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan');
            }
        });

        // Hapus satuan
        async function hapusSatuan(id) {
            if (!confirm('Apakah Anda yakin ingin menghapus satuan ini?')) {
                return;
            }

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
                    alert('Satuan berhasil dihapus!');
                    muatDaftarSatuan();
                } else {
                    alert('Gagal menghapus satuan');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan');
            }
        }

        // Muat data saat halaman dimuat
        document.addEventListener('DOMContentLoaded', muatDaftarSatuan);
    </script>
</body>

</html>