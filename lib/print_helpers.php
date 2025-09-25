<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Endroid\QrCode\QrCode;


/** Baca JSON aman */
function loadJson(string $path, $default = [])
{
    if (!file_exists($path)) return $default;
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

/** Get settings: buat default jika belum ada, merge dengan defaults */
function getSettings(string $path): array
{
    $defaults = [
        'nama_toko'    => 'Toko Saya',
        'alamat'       => 'Alamat toko',
        'telepon'      => '08123456789',
        'printer_name' => 'POS-58',
        'paper_size'   => '58mm',
        'auto_print'   => false,
        'footer'       => 'Terima kasih sudah berbelanja di toko kami!',
        'tipe_kode'    => 'barcode',
    ];

    if (!file_exists(dirname($path))) {
        @mkdir(dirname($path), 0777, true);
    }

    if (!file_exists($path)) {
        file_put_contents($path, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaults;
    }

    $current = loadJson($path, []);
    if (!is_array($current)) $current = [];

    // merge default ← current (current override)
    $merged = array_merge($defaults, $current);

    // tulis balik jika ada key baru dari defaults
    if ($merged !== $current) {
        file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    return $merged;
}

/** Render QR atau Barcode jadi <img src="data:..."> */
function renderCodeImage(string $value, string $tipe): string
{
    $value = (string)$value;

    if ($tipe === 'qr') {
        // v6: langsung pakai QrCode + PngWriter
        $qr = new QrCode($value);
        $writer = new PngWriter();
        $result = $writer->write($qr);      // <- menghasilkan Result object
        $png = $result->getString();        // <- ambil PNG binary

        return '<img class="code-img qr" src="data:image/png;base64,' . base64_encode($png) . '" alt="QR" />';
    }

    // BARCODE (Picqer)
    $generator   = new BarcodeGeneratorPNG();
    $widthFactor = 1;
    $totalHeight = 18;

    $png = $generator->getBarcode(
        $value,
        $generator::TYPE_CODE_128,
        $widthFactor,
        $totalHeight
    );

    return '<img class="code-img barcode" src="data:image/png;base64,' . base64_encode($png) . '" alt="Barcode" />';
}


/** Bangun HTML A4 (grid label) untuk 1 barang per halaman */
function buildLabelHtml(array $barang, array $setting, string $tipe, int $cols = 6, int $rows = 0): string
{
    $kode  = $barang['kodeProduk'] ?? '';
    $harga = !empty($barang['satuanHarga'][0]['hargaEcer'])
        ? (int)$barang['satuanHarga'][0]['hargaEcer']
        : null;

    // A4 portrait (mm)
    $pageW  = 210;
    $pageH  = 297;
    $margin = 5;     // margin kecil biar muat banyak
    $gap    = 0.8;   // jarak antar tile

    // Area efektif
    $usableW = $pageW - 2 * $margin;
    $usableH = $pageH - 2 * $margin;

    // Override cols via ?cols=...
    $cols = isset($_GET['cols']) ? max(1, (int)$_GET['cols']) : $cols;

    // Lebar tile otomatis
    $tileW = ($usableW - ($cols - 1) * $gap) / $cols;

    // Ukuran tile & gambar
    if ($tipe === 'qr') {
        $qrSide = 16;             // ukuran QR mm
        $imgW   = $qrSide;
        $imgH   = $qrSide;
        $tileH  = $qrSide + 4.5;  // ruang harga
    } else {
        $imgW   = min(22, $tileW * 0.8);
        $imgH   = 7.0;
        $tileH  = $imgH + 5.0;
    }

    // Hitung baris otomatis
    $autoRows   = max(1, (int)floor(($usableH - 1.0) / ($tileH + $gap)));
    $rowsParam   = isset($_GET['rows'])   ? max(1, (int)$_GET['rows'])   : null;
    $labelsParam = isset($_GET['labels']) ? max(1, (int)$_GET['labels']) : null;

    if ($labelsParam !== null) {
        $needRows = (int)ceil($labelsParam / $cols);
        $rows = min($needRows, $autoRows);
    } elseif ($rowsParam !== null) {
        $rows = min($rowsParam, $autoRows);
    } elseif ($rows <= 0) {
        $rows = $autoRows;
    }

    // Batasi jumlah label yang benar-benar dicetak
    $maxCells = ($labelsParam !== null)
        ? min($labelsParam, $cols * $rows)
        : ($cols * $rows);

    $printed  = 0;
    $cellsHtml = '';

    // Bangun grid
    for ($r = 0; $r < $rows; $r++) {
        $cellsHtml .= '<tr>';
        for ($c = 0; $c < $cols; $c++) {
            if ($printed >= $maxCells) {
                // Isi kosong agar grid rapih
                $cellsHtml .= '<td class="cell"><div class="tile"></div></td>';
                continue;
            }

            $img = renderCodeImage($kode, $tipe);

            // Override style Dompdf
            $img = str_replace(
                'class="code-img ' . ($tipe === 'qr' ? 'qr' : 'barcode') . '"',
                'style="width:' . $imgW . 'mm; height:' . $imgH . 'mm; display:block; margin:0 auto;"',
                $img
            );

            $hargaHtml = $harga !== null
                ? '<div class="harga">Rp ' . number_format($harga, 0, ',', '.') . '</div>'
                : '';

            $cellsHtml .= '
              <td class="cell">
                <div class="tile" style="width:' . $tileW . 'mm; height:' . $tileH . 'mm;">
                  <div class="code-wrap">' . $img . '</div>
                  ' . $hargaHtml . '
                </div>
              </td>';

            $printed++;
        }
        $cellsHtml .= '</tr>';
    }

    // CSS
    $css = <<<CSS
<style>
  @page { size: A4 portrait; margin: {$margin}mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color:#111; }

  table.sheet { width:100%; border-collapse:separate; border-spacing: {$gap}mm; table-layout:fixed; }
  td.cell { padding:0; vertical-align:top; text-align:center; }
  tr { page-break-inside: avoid; }

  .tile {
    border:0.18mm dashed #bbb;
    padding:0.8mm 0.8mm;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    line-height:0;
  }

  .code-wrap { width:100%; text-align:center; margin:0; }
  .harga {
    margin:0.6mm 0 0 0;
    font-size:7pt;
    font-weight:700;
    line-height:1.1;
    text-align:center;
  }

  img { display:block; }
</style>
CSS;

    return <<<HTML
<!doctype html>
<html>
<head><meta charset="utf-8" /><title>Label — {$kode}</title>{$css}</head>
<body>
  <table class="sheet" aria-hidden="true">{$cellsHtml}</table>
</body>
</html>
HTML;
}
