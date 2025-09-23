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
        $nama_pelanggan = $nama_pelanggan ?: "Pelanggan" . rand(100, 999);
        $waktu = $waktu ?: date('Y-m-d H:i:s');

        $total = 0;
        foreach ($items as $item) {
            $total += $item['qty'] * $item['harga'];
        }

        $kembalian = ($bayar !== null) ? max($bayar - $total, 0) : 0;

        $connector = new Mike42\Escpos\PrintConnectors\WindowsPrintConnector($settings['printer_name']);
        $printer = new Mike42\Escpos\Printer($connector);

        // Header
        $printer->setJustification(Mike42\Escpos\Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text($settings['nama_toko'] . "\n");
        $printer->setEmphasis(false);
        $printer->text($settings['alamat'] . "\n");
        $printer->text("Telp/WA: " . $settings['telepon'] . "\n");
        $printer->text(str_repeat("-", 32) . "\n");

        // Info pelanggan & tanggal
        $printer->setJustification(Mike42\Escpos\Printer::JUSTIFY_LEFT);
        $printer->text("Nama: " . $nama_pelanggan . "\n");
        $printer->text("Tanggal : " . $waktu . "\n");
        $printer->text(str_repeat("-", 32) . "\n");

        // Items
        foreach ($items as $item) {
            $printer->text(printItemLine("{$item['nama']} x{$item['qty']}", "Rp " . number_format($item['qty'] * $item['harga'])));
        }

        $printer->text(str_repeat("-", 32) . "\n");

        // Total
        $printer->setEmphasis(true);
        $printer->text(printLine("TOTAL", "Rp " . number_format($total)));

        // Tambahkan hutang jika > 0
        if ($hutang > 0) {
            $printer->text(printLine("HUTANG", "Rp " . number_format($hutang)));
        }

        // Kembalian
        $printer->text(printLine("KEMBALIAN", "Rp " . number_format($kembalian)));
        $printer->setEmphasis(false);
        $printer->text(str_repeat("-", 32) . "\n");

        // Footer
        $printer->setJustification(Mike42\Escpos\Printer::JUSTIFY_CENTER);
        $printer->text(!empty($settings['footer']) ? $settings['footer'] . "\n" : "Terima kasih sudah berbelanja!\n");

        $printer->cut();
        $printer->close();

        return ["status" => "success", "message" => "Cetak berhasil!"];
    } catch (Exception $e) {
        return ["status" => "error", "message" => "Gagal mencetak: " . $e->getMessage()];
    }
}
