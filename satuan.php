<?php
// satuan.php
$dataDir = __DIR__ . "/data";
$satuanFile = $dataDir . "/satuan.json";

// Pastikan direktori & file ada
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}
if (!file_exists($satuanFile)) {
    file_put_contents($satuanFile, "[]");
}

// Normalisasi agar kompatibel: pastikan ada is_variable (boolean), abaikan step lama jika ada
function normalize_units($arr)
{
    foreach ($arr as &$u) {
        if (!isset($u['is_variable'])) $u['is_variable'] = false;
        // buang step jika ada (tidak dipakai lagi)
        if (isset($u['step'])) unset($u['step']);
    }
    return $arr;
}

if (isset($_GET['action'])) {
    $satuan = json_decode(file_get_contents($satuanFile), true) ?? [];
    $satuan = normalize_units($satuan);

    // Baca JSON body bila ada
    $raw = file_get_contents("php://input");
    $input = $raw ? json_decode($raw, true) : [];

    header('Content-Type: application/json');

    switch ($_GET['action']) {
        case 'get_satuan':
            echo json_encode($satuan, JSON_UNESCAPED_UNICODE);
            exit;

        case 'tambah_satuan': {
                $nama  = trim($input['nama'] ?? '');
                $isVar = filter_var($input['is_variable'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($nama === '') {
                    echo json_encode(['success' => false, 'message' => 'Nama satuan tidak boleh kosong']);
                    exit;
                }

                // Cek duplikat nama (case-insensitive)
                foreach ($satuan as $s) {
                    if (mb_strtolower($s['nama']) === mb_strtolower($nama)) {
                        echo json_encode(['success' => false, 'message' => 'Satuan sudah ada']);
                        exit;
                    }
                }

                $new = [
                    'id'          => (int) round(microtime(true) * 1000), // id unik
                    'nama'        => $nama,
                    'is_variable' => $isVar
                ];

                $satuan[] = $new;
                file_put_contents($satuanFile, json_encode($satuan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                echo json_encode(['success' => true, 'data' => $new]);
                exit;
            }

        case 'edit_satuan': {
                $id    = $input['id'] ?? 0;
                $nama  = trim($input['nama'] ?? '');
                $isVar = filter_var($input['is_variable'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($nama === '') {
                    echo json_encode(['success' => false, 'message' => 'Nama satuan tidak boleh kosong']);
                    exit;
                }

                $found = false;
                foreach ($satuan as &$s) {
                    if ($s['id'] == $id) {
                        // Cek duplikat nama ke satuan lain
                        foreach ($satuan as $other) {
                            if ($other['id'] != $id && mb_strtolower($other['nama']) === mb_strtolower($nama)) {
                                echo json_encode(['success' => false, 'message' => 'Nama satuan sudah dipakai']);
                                exit;
                            }
                        }

                        $s['nama']        = $nama;
                        $s['is_variable'] = $isVar;
                        // pastikan tidak ada field step tertinggal
                        if (isset($s['step'])) unset($s['step']);

                        $found = true;
                        break;
                    }
                }

                if ($found) {
                    file_put_contents($satuanFile, json_encode($satuan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Satuan tidak ditemukan']);
                }
                exit;
            }

        case 'hapus_satuan': {
                $id  = $input['id'] ?? 0;
                $new = array_values(array_filter($satuan, fn($s) => $s['id'] != $id));
                file_put_contents($satuanFile, json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true]);
                exit;
            }
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

        /* Animasi modal */
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

    <main class="flex-1 md:ml-64 ml-0 p-6">
        <div class="mb-6 flex items-center justify-between gap-3 flex-wrap">
            <h2 class="text-2xl font-semibold">📏 Manajemen Satuan</h2>

            <button
                type="button"
                onclick="openKamusSatuan()"
                class="inline-flex items-center gap-2 bg-gray-700 text-white text-sm px-3 py-1.5 rounded-md hover:bg-gray-600">
                <i class="fas fa-question-circle"></i> Kamus Satuan
            </button>
        </div>


        <!-- KAMUS SATUAN — versi simpel & responsif -->
        <div id="kamusSatuanModal"
            class="fixed inset-0 z-50 hidden">
            <!-- backdrop -->
            <div class="absolute inset-0 bg-black/50" onclick="closeKamusSatuan()"></div>

            <!-- wrapper untuk centering -->
            <div class="relative w-full h-full flex items-center justify-center p-4">
                <!-- konten modal -->
                <div
                    class="bg-white w-full max-w-sm sm:max-w-lg md:max-w-xl lg:max-w-2xl rounded-xl shadow-2xl overflow-hidden
             max-h-[90vh] flex flex-col">

                    <!-- Header -->
                    <div class="px-5 py-3 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold flex items-center gap-2">
                            <i class="fas fa-book text-blue-600"></i> Kamus Satuan
                        </h3>
                        <button onclick="closeKamusSatuan()" class="text-gray-600 hover:text-gray-900">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Body (scrollable kalau tinggi melebihi viewport) -->
                    <div class="p-5 space-y-4 text-base leading-7 overflow-y-auto">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="font-semibold mb-1">Apa itu “Support Timbang”?</div>
                            <ul class="list-disc pl-5 space-y-1">
                                <li><b>YA</b> → qty bisa desimal (0,25 · 1,5 · 2,75).</li>
                                <li><b>TIDAK</b> → qty harus bulat (1 · 2 · 3).</li>
                            </ul>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="border rounded-lg p-3">
                                <div class="font-semibold mb-2">Cocok pakai desimal</div>
                                <ul class="list-disc pl-5 space-y-1">
                                    <li>kg (0,5 kg = 500 g)</li>
                                    <li>liter (0,5 L = 500 mL)</li>
                                    <li>meter (0,3 m = 30 cm)</li>
                                </ul>
                            </div>
                            <div class="border rounded-lg p-3">
                                <div class="font-semibold mb-2">Tidak pakai desimal</div>
                                <ul class="list-disc pl-5 space-y-1">
                                    <li>pcs/biji</li>
                                    <li>box/dus</li>
                                    <li>pak</li>
                                </ul>
                            </div>
                        </div>

                        <div class="border rounded-lg p-3">
                            <div class="font-semibold mb-2">Aturan singkat</div>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Desimal disimpan sampai <b>3 angka</b> di belakang koma (contoh: 1,234).</li>
                                <li>Non-desimal otomatis dibulatkan ke <b>angka bulat</b>.</li>
                                <li>Stok tidak boleh minus.</li>
                            </ul>
                        </div>

                        <div class="border rounded-lg p-3">
                            <div class="font-semibold mb-2">Contoh cepat</div>
                            <div class="grid grid-cols-1 gap-2 text-sm">
                                <div class="flex justify-between"><span>0,25 kg</span><span>= 250 gram</span></div>
                                <div class="flex justify-between"><span>1,5 L</span><span>= 1500 mL</span></div>
                                <div class="flex justify-between"><span>0,3 m</span><span>= 30 cm</span></div>
                                <div class="flex justify-between"><span>0,5 pcs</span><span>= dibulatkan ke 1</span></div>
                                <div class="flex justify-between"><span>0,4 pcs</span><span>= dibulatkan ke 0</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer tetap terlihat -->
                    <div class="px-5 py-3 border-t bg-gray-50 flex justify-end">
                        <button onclick="closeKamusSatuan()" class="px-4 py-2 rounded-md bg-gray-200 hover:bg-gray-300">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>




        <!-- Form Tambah Satuan -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <h3 class="text-lg font-semibold mb-4">Tambah Satuan Baru</h3>

            <form id="formSatuan" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <!-- Nama Satuan -->
                <div class="md:col-span-6 lg:col-span-4">
                    <label for="namaSatuan" class="block text-sm font-medium mb-1">Nama satuan</label>
                    <input
                        id="namaSatuan"
                        name="nama"
                        type="text"
                        placeholder="pcs, box, kg, liter"
                        autocomplete="off"
                        class="w-full h-10 border rounded-md px-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                        required />
                </div>

                <!-- Support Timbang -->
                <div class="md:col-span-6 lg:col-span-4">
                    <label class="block text-sm font-medium mb-1 invisible">Support Timbang</label>
                    <div class="h-10 border rounded-md px-3 flex items-center gap-2">
                        <input id="addIsVariable" type="checkbox" class="w-4 h-4" />
                        <label for="addIsVariable" class="text-sm select-none">Support Timbang (qty desimal)</label>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <div class="md:col-span-12 lg:col-span-4 flex items-end">
                    <button
                        type="submit"
                        class="inline-flex items-center bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-500 transition-colors">
                        <i class="fas fa-plus mr-2"></i> Tambah
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar Satuan -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="mb-4 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Daftar Satuan</h3>
                <input
                    type="text"
                    id="searchSatuan"
                    placeholder="Cari satuan..."
                    class="border rounded-md p-2 w-1/3 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
            </div>

            <div class="table-container">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100 sticky-thead">
                            <th class="p-3 border-b font-semibold">No</th>
                            <th class="p-3 border-b font-semibold">Nama Satuan</th>
                            <th class="p-3 border-b font-semibold">Timbang?</th>
                            <th class="p-3 border-b font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="daftarSatuan">
                        <tr>
                            <td colspan="4" class="p-4 text-center text-gray-500">
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
                            <input
                                type="text"
                                id="editNama"
                                name="nama"
                                class="w-full border rounded-md p-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                                required>
                        </div>
                        <div class="mb-6 flex items-center gap-2">
                            <input type="checkbox" id="editIsVariable" class="w-4 h-4">
                            <label for="editIsVariable" class="text-sm select-none">Support Timbang (qty desimal)</label>
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
        // Expose ke global supaya bisa dipanggil dari onclick inline
        window.openKamusSatuan = function() {
            const m = document.getElementById('kamusSatuanModal');
            if (m) m.classList.remove('hidden');
        };

        window.closeKamusSatuan = function() {
            const m = document.getElementById('kamusSatuanModal');
            if (m) m.classList.add('hidden');
        };

        // Tutup jika klik backdrop
        document.addEventListener('click', function(e) {
            const m = document.getElementById('kamusSatuanModal');
            if (!m || m.classList.contains('hidden')) return;
            if (e.target === m) window.closeKamusSatuan();
        });

        // Tutup dengan ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') window.closeKamusSatuan();
        });


        // Muat data satuan
        async function muatDaftarSatuan() {
            try {
                const response = await fetch('satuan.php?action=get_satuan');
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const satuanData = await response.json();
                tampilkanDaftarSatuan(satuanData);
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('daftarSatuan').innerHTML = `
                    <tr><td colspan="4" class="p-4 text-center text-red-500">
                        Gagal memuat data satuan: ${error.message}
                    </td></tr>`;
            }
        }

        // Tampilkan daftar satuan
        function tampilkanDaftarSatuan(satuan) {
            const container = document.getElementById('daftarSatuan');

            // Urut terbaru di atas
            satuan.sort((a, b) => b.id - a.id);

            const searchValue = document.getElementById('searchSatuan')?.value.trim().toLowerCase();
            const filtered = searchValue ?
                satuan.filter(item => item.nama.toLowerCase().includes(searchValue)) :
                satuan;

            if (filtered.length === 0) {
                container.innerHTML = `
                    <tr><td colspan="4" class="p-4 text-center text-gray-500">Tidak ada data satuan</td></tr>`;
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
                            ${item.is_variable
                                ? '<span class="text-green-700 bg-green-100 text-xs px-2 py-1 rounded">Ya</span>'
                                : '<span class="text-gray-700 bg-gray-100 text-xs px-2 py-1 rounded">Tidak</span>'}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-center space-x-2">
                                <button
                                    onclick="bukaModalEditSatuan(${item.id}, '${escapedNama}', ${item.is_variable ? 'true' : 'false'})"
                                    class="text-blue-500 hover:text-blue-700 px-3 py-1 rounded hover:bg-blue-50 transition-colors"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button
                                    onclick="hapusSatuan(${item.id})"
                                    class="text-red-500 hover:text-red-700 px-3 py-1 rounded hover:bg-red-50 transition-colors"
                                    title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
            });

            container.innerHTML = html;
        }

        document.getElementById('searchSatuan').addEventListener('input', () => {
            fetch('satuan.php?action=get_satuan')
                .then(res => res.json())
                .then(data => tampilkanDaftarSatuan(data))
                .catch(err => console.error(err));
        });

        // Form tambah satuan (tanpa step)
        document.getElementById('formSatuan').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const nama = (formData.get('nama') || '').trim();
            const is_variable = document.getElementById('addIsVariable').checked;

            if (!nama) return showToast('Nama satuan harus diisi!', 'error');

            try {
                const res = await fetch('satuan.php?action=tambah_satuan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        nama,
                        is_variable
                    })
                });
                const result = await res.json();
                if (result.success) {
                    showToast('Satuan berhasil ditambahkan!', 'success');
                    this.reset();
                    document.getElementById('addIsVariable').checked = false;
                    muatDaftarSatuan();
                } else {
                    showToast('Gagal: ' + (result.message || ''), 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('Terjadi kesalahan', 'error');
            }
        });

        // Modal edit satuan (tanpa step)
        function bukaModalEditSatuan(id, nama, isVar = false) {
            document.getElementById('editId').value = id;
            document.getElementById('editNama').value = nama;

            const chk = document.getElementById('editIsVariable');
            chk.checked = !!isVar;

            const modal = document.getElementById('modalEditSatuan');
            modal.classList.remove('hidden');
            setTimeout(() => modal.classList.remove('modal-enter'), 10);
        }

        function tutupModalEditSatuan() {
            const modal = document.getElementById('modalEditSatuan');
            modal.classList.add('modal-enter');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        // Submit edit
        document.getElementById('formEditSatuan').addEventListener('submit', async function(e) {
            e.preventDefault();
            const id = parseInt(document.getElementById('editId').value, 10);
            const nama = (document.getElementById('editNama').value || '').trim();
            const is_variable = document.getElementById('editIsVariable').checked;

            if (!nama) return showToast('Nama satuan harus diisi!', 'error');

            try {
                const res = await fetch('satuan.php?action=edit_satuan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id,
                        nama,
                        is_variable
                    })
                });
                const result = await res.json();
                if (result.success) {
                    showToast('Satuan berhasil diupdate!', 'success');
                    tutupModalEditSatuan();
                    muatDaftarSatuan();
                } else {
                    showToast('Gagal: ' + (result.message || ''), 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('Terjadi kesalahan', 'error');
            }
        });

        // Hapus satuan
        function hapusSatuan(id) {
            showConfirm(
                'Apakah Anda yakin ingin menghapus satuan ini?',
                async () => {
                        try {
                            const response = await fetch('satuan.php?action=hapus_satuan', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    id
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
                    () => {
                        showToast('Aksi dibatalkan', 'info');
                    }
            );
        }

        // Init
        document.addEventListener('DOMContentLoaded', muatDaftarSatuan);
    </script>
</body>

</html>