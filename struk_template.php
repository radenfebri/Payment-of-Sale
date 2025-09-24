<?php

require __DIR__ . '/vendor/autoload.php';

function renderStrukHTML($items, $settings, $nama_pelanggan = null, $waktu = null, $kembalian = 0)
{
    // Auto-generate jika kosong
    $nama_pelanggan = $nama_pelanggan ?: "Pelanggan" . rand(100, 999);
    $waktu = $waktu ?: date('Y-m-d H:i:s');
    $paperWidth = $settings['paper_size'] === '80mm' ? 280 : 200;

    $paperWidth = $settings['paper_size'] === '80mm' ? 280 : 200;
    $total = 0;
    $itemsHtml = '';

    foreach ($items as $item) {
        $subtotal = $item['qty'] * $item['harga'];
        $total += $subtotal;
        $itemsHtml .= "<div style='display:flex;justify-content:space-between;margin:2px 0'>
            <span>{$item['nama']} x{$item['qty']}</span>
            <span>Rp " . number_format($subtotal) . "</span>
        </div>";
    }

    return "
<div style='width:{$paperWidth}px;font-family:monospace;border:1px solid #333;padding:10px;background:#fff;'>
    <h3 style='text-align:center;margin:0'>{$settings['nama_toko']}</h3>
    <p style='text-align:center;margin:0'>{$settings['alamat']}</p>
    <p style='text-align:center;margin:0'>Telp/WA: {$settings['telepon']}</p>
    <hr>
    <p><strong>Nama Pelanggan:</strong> {$nama_pelanggan}</p>
    <p><strong>Tanggal:</strong> {$waktu}</p>
    <hr>
    {$itemsHtml}
    <hr>
    <div style='display:flex;justify-content:space-between;font-weight:bold'>
        <span>TOTAL:</span>
        <span>Rp " . number_format($total) . "</span>
    </div>
    <div style='display:flex;justify-content:space-between;font-weight:bold'>
        <span>KEMBALIAN:</span>
        <span>Rp " . number_format($kembalian) . "</span>
    </div>
    <hr>
    <p style='text-align:center;margin:0;'>Terima kasih sudah berbelanja!</p>
    <p style='text-align:center;margin:0;'>Simpan struk ini untuk referensi.</p>
</div>
";
}


function printLine($left, $right, $width = 32)
{
    $left = substr($left, 0, $width - 1);
    $right = substr($right, 0, $width - 1);
    $spaces = $width - strlen($left) - strlen($right);
    if ($spaces < 1) $spaces = 1;
    return $left . str_repeat(" ", $spaces) . $right . "\n";
}

function printItemLine($nameQty, $subtotal, $width = 32)
{
    $spaces = $width - strlen($nameQty) - strlen($subtotal);
    if ($spaces < 1) $spaces = 1;
    return $nameQty . str_repeat(" ", $spaces) . $subtotal . "\n";
}


function cetakStruk($items, $settings, $nama_pelanggan = null, $waktu = null, $bayar = null, $hutang = 0)
{
    try {
        // Validasi input
        if (empty($items)) {
            return ["status" => "error", "message" => "Tidak ada item untuk dicetak"];
        }

        if (empty($settings['printer_name'])) {
            return ["status" => "error", "message" => "Nama printer belum diatur"];
        }

        // Cek koneksi printer
        try {
            $connector = new Mike42\Escpos\PrintConnectors\WindowsPrintConnector($settings['printer_name']);
            $printer = new Mike42\Escpos\Printer($connector);
        } catch (Exception $e) {
            return ["status" => "error", "message" => "Printer '{$settings['printer_name']}' tidak ditemukan. Pastikan printer sudah terhubung dan nama sesuai."];
        }

        $nama_pelanggan = $nama_pelanggan ?: "Pelanggan" . rand(100, 999);
        $waktu = $waktu ?: date('d/m/Y H:i:s'); // Format lebih singkat

        $total = 0;
        foreach ($items as $item) {
            // Validasi item
            if (!isset($item['nama']) || !isset($item['qty']) || !isset($item['harga'])) {
                return ["status" => "error", "message" => "Format item tidak valid"];
            }
            $total += $item['qty'] * $item['harga'];
        }

        $kembalian = ($bayar !== null) ? max($bayar - $total, 0) : 0;

        // Header
        $printer->setJustification(Mike42\Escpos\Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text($settings['nama_toko'] . "\n");
        $printer->setEmphasis(false);
        $printer->text(wordwrap($settings['alamat'], 32, "\n") . "\n");
        $printer->text("Telp/WA: " . $settings['telepon'] . "\n");
        $printer->text(str_repeat("-", 32) . "\n");

        // Info pelanggan & tanggal
        $printer->setJustification(Mike42\Escpos\Printer::JUSTIFY_LEFT);
        $printer->text("Pelanggan: " . substr($nama_pelanggan, 0, 20) . "\n");
        $printer->text("Waktu    : " . $waktu . "\n");
        $printer->text(str_repeat("-", 32) . "\n");

        // Items dengan format yang lebih rapi
        foreach ($items as $item) {
            $namaItem = substr($item['nama'], 0, 20); // Batasi panjang nama
            $qtyHarga = "x" . $item['qty'] . " @Rp " . number_format($item['harga']);
            $subtotal = "Rp " . number_format($item['qty'] * $item['harga']);

            $printer->text($namaItem . "\n");
            $printer->text(str_pad($qtyHarga, 20) . str_pad($subtotal, 12, " ", STR_PAD_LEFT) . "\n");
        }

        $printer->text(str_repeat("-", 32) . "\n");

        // Total dan pembayaran
        $printer->setEmphasis(true);
        $printer->text(str_pad("TOTAL:", 20) . str_pad("Rp " . number_format($total), 12, " ", STR_PAD_LEFT) . "\n");

        if ($bayar !== null) {
            $printer->text(str_pad("BAYAR:", 20) . str_pad("Rp " . number_format($bayar), 12, " ", STR_PAD_LEFT) . "\n");
            $printer->text(str_pad("KEMBALI:", 20) . str_pad("Rp " . number_format($kembalian), 12, " ", STR_PAD_LEFT) . "\n");
        }

        // Hutang
        if ($hutang > 0) {
            $printer->text(str_pad("HUTANG:", 20) . str_pad("Rp " . number_format($hutang), 12, " ", STR_PAD_LEFT) . "\n");
        }

        $printer->setEmphasis(false);
        $printer->text(str_repeat("-", 32) . "\n");

        // Footer dengan wordwrap
        $printer->setJustification(Mike42\Escpos\Printer::JUSTIFY_CENTER);
        $footerText = !empty($settings['footer']) ? $settings['footer'] : "Terima kasih sudah berbelanja!";
        $printer->text(wordwrap($footerText, 32) . "\n");

        // Tambahkan informasi penting
        $printer->text("Simpan struk ini\n");
        $printer->text("untuk bukti transaksi\n");

        $printer->cut();
        $printer->close();

        return [
            "status" => "success",
            "message" => "Struk berhasil dicetak ke printer: {$settings['printer_name']}",
            "detail" => [
                "pelanggan" => $nama_pelanggan,
                "total" => $total,
                "items" => count($items)
            ]
        ];
    } catch (Exception $e) {
        // Pesan error yang lebih informatif
        $errorMsg = "Gagal mencetak struk: ";

        if (strpos($e->getMessage(), 'cannot be found') !== false) {
            $errorMsg .= "Printer '{$settings['printer_name']}' tidak ditemukan. ";
            $errorMsg .= "Periksa koneksi dan nama printer.";
        } elseif (strpos($e->getMessage(), 'access denied') !== false) {
            $errorMsg .= "Akses printer ditolak. Pastikan aplikasi memiliki izin akses printer.";
        } else {
            $errorMsg .= $e->getMessage();
        }

        return ["status" => "error", "message" => $errorMsg];
    }
}
