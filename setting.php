<?php
require __DIR__ . '/vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

$settingFile = __DIR__ . "/data/setting.json";
$settings = json_decode(file_get_contents($settingFile), true);

// Simpan pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['tes_printer'])) {
    $settings['nama_toko'] = $_POST['nama_toko'] ?? $settings['nama_toko'];
    $settings['alamat'] = $_POST['alamat'] ?? $settings['alamat'];
    $settings['telepon'] = $_POST['telepon'] ?? $settings['telepon'];
    $settings['printer_name'] = $_POST['printer_name'] ?? $settings['printer_name'];
    $settings['paper_size'] = $_POST['paper_size'] ?? $settings['paper_size'];
    $settings['auto_print'] = isset($_POST['auto_print']);
    file_put_contents($settingFile, json_encode($settings, JSON_PRETTY_PRINT));
    $saved = true;
}

// Tes printer via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tes_printer'])) {
    $items = [
        ["nama" => "Rinso Box", "qty" => 2, "harga" => 50000],
        ["nama" => "Teh Botol", "qty" => 3, "harga" => 7000],
        ["nama" => "Indomie Goreng", "qty" => 5, "harga" => 3500],
    ];
    $total = 0;
    foreach ($items as $item) $total += $item['qty'] * $item['harga'];

    try {
        $connector = new WindowsPrintConnector($settings['printer_name']);
        $printer = new Printer($connector);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text($settings['nama_toko'] . "\n");
        $printer->setEmphasis(false);
        $printer->text($settings['alamat'] . "\n");
        $printer->text("Telp/WA: " . $settings['telepon'] . "\n");
        $printer->text("-----------------------------\n");

        foreach ($items as $item) {
            $printer->text($item['nama'] . " x" . $item['qty'] . "  Rp " . number_format($item['qty'] * $item['harga']) . "\n");
        }

        $printer->text("-----------------------------\n");
        $printer->setEmphasis(true);
        $printer->text("TOTAL: Rp " . number_format($total) . "\n");
        $printer->setEmphasis(false);
        $printer->text("-----------------------------\n");
        $printer->text("Terima kasih sudah berbelanja!\n");
        $printer->cut();
        $printer->close();

        echo json_encode(["status" => "success", "message" => "Cetak berhasil!"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Gagal mencetak: Printer tidak terhubung atau nama printer salah!"]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Pengaturan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="alert.js"></script>
</head>

<body class="bg-gray-100 min-h-screen flex">
    <?php include "partials/sidebar.php"; ?>

    <main class="flex-1 p-6">
        <h2 class="text-2xl font-bold mb-6">Pengaturan Sistem</h2>

        <?php if (!empty($saved)): ?>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    showToast('Pengaturan berhasil disimpan!', 'success', 3000);
                });
            </script>
        <?php endif; ?>




        <form method="post" class="bg-white p-6 rounded-lg shadow-md space-y-4 max-w-xl" id="settingsForm">
            <div>
                <label class="block font-medium mb-1">Nama Toko</label>
                <input type="text" name="nama_toko" value="<?= htmlspecialchars($settings['nama_toko']) ?>"
                    class="w-full border rounded p-2" required>
            </div>
            <div>
                <label class="block font-medium mb-1">Alamat</label>
                <textarea name="alamat" class="w-full border rounded p-2" rows="3"><?= htmlspecialchars($settings['alamat']) ?></textarea>
            </div>
            <div>
                <label class="block font-medium mb-1">Nomor Telepon/WhatsApp</label>
                <input type="text" name="telepon" value="<?= htmlspecialchars($settings['telepon']) ?>"
                    class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block font-medium mb-1">Nama Printer (opsional)</label>
                <input type="text" name="printer_name" value="<?= htmlspecialchars($settings['printer_name']) ?>"
                    class="w-full border rounded p-2" placeholder="Nama printer di komputer">
            </div>
            <div>
                <label class="block font-medium mb-1">Ukuran Kertas</label>
                <select name="paper_size" class="w-full border rounded p-2">
                    <option value="58mm" <?= $settings['paper_size'] == '58mm' ? 'selected' : '' ?>>58mm</option>
                    <option value="80mm" <?= $settings['paper_size'] == '80mm' ? 'selected' : '' ?>>80mm</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="auto_print" id="auto_print" <?= $settings['auto_print'] ? 'checked' : '' ?>>
                <label for="auto_print" class="font-medium">Cetak Otomatis setelah transaksi</label>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Simpan Pengaturan
                </button>
                <button type="button" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600"
                    id="btnPreviewPrinter">
                    Preview Struk
                </button>
                <button type="button" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
                    id="btnCetakPrinter">
                    Cetak ke Printer
                </button>
            </div>
        </form>
    </main>


    <!-- Modal Preview Struk -->
    <div id="modalPreview" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white p-4 rounded shadow-lg relative">
            <button id="closeModal" class="absolute top-2 right-2 px-2 py-1 bg-red-500 text-white rounded">Close</button>
            <div id="previewStruk" class="font-mono mx-auto"></div>
        </div>
    </div>

    <script>
        const settings = <?= json_encode($settings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        // Konversi ukuran kertas ke pixel (estimasi)
        const paperWidth = settings.paper_size === '80mm' ? 280 : 200;

        // Tombol preview struk
        document.getElementById('btnPreviewPrinter').addEventListener('click', function() {
            const items = [{
                    nama: "Rinso Box",
                    qty: 2,
                    harga: 50000
                },
                {
                    nama: "Teh Botol",
                    qty: 3,
                    harga: 7000
                },
                {
                    nama: "Indomie Goreng",
                    qty: 5,
                    harga: 3500
                }
            ];

            let total = 0;
            let itemsHtml = '';
            items.forEach(item => {
                total += item.qty * item.harga;
                itemsHtml += `<div style="display:flex;justify-content:space-between;margin:2px 0">
        <span style="text-align:left;">${item.nama} x${item.qty}</span>
        <span style="text-align:right;">Rp ${item.qty * item.harga}</span>
    </div>`;
            });

            const content = `
<div style="width:${paperWidth}px;font-family:monospace;border:1px solid #333;padding:10px;background:#fff;">
    <h3 style="text-align:center;margin:0">${settings.nama_toko}</h3>
    <p style="text-align:center;margin:0">${settings.alamat}</p>
    <p style="text-align:center;margin:0">Telp/WA: ${settings.telepon}</p>
    <hr>
    ${itemsHtml}
    <hr>
    <div style="display:flex;justify-content:space-between;font-weight:bold">
        <span style="text-align:left;">TOTAL:</span>
        <span style="text-align:right;">Rp ${total}</span>
    </div>
    <hr>
    <p style="text-align:center;margin:0;">Terima kasih sudah berbelanja!</p>
</div>
`;


            const previewDiv = document.getElementById('previewStruk');
            previewDiv.innerHTML = content;

            // Tampilkan modal
            const modal = document.getElementById('modalPreview');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });

        // Tombol tutup modal
        document.getElementById('closeModal').addEventListener('click', function() {
            const modal = document.getElementById('modalPreview');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });

        // Tombol cetak ke printer
        document.getElementById('btnCetakPrinter').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('tes_printer', '1');

            fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast(data.message, 'success', 3000);
                    } else {
                        showToast(data.message + "\nPastikan printer sudah terhubung via Bluetooth/USB dan nama printer benar.", 'error', 5000); // <-- ganti alert
                    }
                })
                .catch(err => {
                    showToast('Terjadi kesalahan saat koneksi ke printer', 'error', 5000);
                    console.error(err);
                });
        });
    </script>
</body>

</html>