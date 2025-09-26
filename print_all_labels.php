<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/print_helpers.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ====== Load data & setting ======
$barangFile  = __DIR__ . "/data/barang.json";
$settingFile = __DIR__ . "/data/setting.json";

$barangList = loadJson($barangFile, []);
$setting    = getSettings($settingFile);

if (empty($barangList)) {
  http_response_code(404);
  echo "Tidak ada barang untuk dicetak.";
  exit;
}

// ====== Ambil tipe dari query > setting.label.tipe_kode > setting.tipe_kode ======
$tipe = strtolower((string)($_GET['tipe_kode'] ?? ($setting['label']['tipe_kode'] ?? ($setting['tipe_kode'] ?? 'barcode'))));
$tipe = ($tipe === 'qr') ? 'qr' : 'barcode';

// ====== Ambil parameter layout dari setting ======
$cols = (int)($tipe === 'qr'
  ? ($setting['label']['qr']['per_row'] ?? 6)
  : ($setting['label']['barcode']['per_row'] ?? 6)
);

// NOTE: Kita gunakan angka ini sebagai JUMLAH LABEL per halaman (sesuai kesepakatan)
$labelsPerPage = (int)($tipe === 'qr'
  ? ($setting['label']['qr']['size_px'] ?? 72)
  : ($setting['label']['barcode']['width_px'] ?? 114)
);

// ====== Konstanta ukuran halaman (A4) & tile ======
$pageW  = 210;   // mm
$pageH  = 297;   // mm
$margin = 5;     // mm
$gap    = 0.8;   // mm

$usableW = $pageW - 2 * $margin;
$usableH = $pageH - 2 * $margin;

// ====== Helper untuk bangun 1 halaman (1 barang) ======
function buildOnePageTable(array $barang, string $tipe, int $cols, int $labelsPerPage, float $usableW, float $usableH, float $gap): string
{
  $kode  = $barang['kodeProduk'] ?? '';
  $harga = !empty($barang['satuanHarga'][0]['hargaEcer']) ? (int)$barang['satuanHarga'][0]['hargaEcer'] : null;

  $tileW = ($usableW - ($cols - 1) * $gap) / $cols;

  if ($tipe === 'qr') {
    $qrSide = 16.0;             // mm (kira-kira)
    $imgW   = $qrSide;
    $imgH   = $qrSide;
    $tileH  = $qrSide + 4.5;    // ruang harga
  } else {
    $imgW   = min(22.0, $tileW * 0.8);
    $imgH   = 7.0;
    $tileH  = $imgH + 5.0;
  }

  // Hitung baris maksimum yang muat dalam 1 halaman
  $autoRows = max(1, (int)floor(($usableH - 1.0) / ($tileH + $gap)));

  // Hitung baris supaya mendekati jumlah labels yang diminta, tapi tetap muat 1 halaman
  $needRows = (int)ceil($labelsPerPage / $cols);
  $rows     = min($needRows, $autoRows);

  $maxCells = min($labelsPerPage, $cols * $rows);
  $printed  = 0;

  $cellsHtml = '';
  for ($r = 0; $r < $rows; $r++) {
    $cellsHtml .= '<tr>';
    for ($c = 0; $c < $cols; $c++) {
      if ($printed >= $maxCells) {
        $cellsHtml .= '<td class="cell"><div class="tile"></div></td>';
        continue;
      }

      $img = renderCodeImage($kode, $tipe);
      // ganti class -> inline style untuk Dompdf
      $img = str_replace(
        'class="code-img ' . ($tipe === 'qr' ? 'qr' : 'barcode') . '"',
        'style="width:'.$imgW.'mm; height:'.$imgH.'mm; display:block; margin:0 auto;"',
        $img
      );

      $hargaHtml = $harga !== null
        ? '<div class="harga">Rp '.number_format($harga, 0, ',', '.').'</div>'
        : '';

      $cellsHtml .= '
        <td class="cell">
          <div class="tile" style="width:'.$tileW.'mm; height:'.$tileH.'mm;">
            <div class="code-wrap">'.$img.'</div>'.$hargaHtml.'
          </div>
        </td>';

      $printed++;
    }
    $cellsHtml .= '</tr>';
  }

  return '<table class="sheet" aria-hidden="true">'.$cellsHtml.'</table>';
}

// ====== CSS sekali di awal ======
$css = <<<CSS
<style>
  @page { size: A4 portrait; margin: {$margin}mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color:#111; }
  table.sheet { width:100%; border-collapse:separate; border-spacing: {$gap}mm; table-layout:fixed; }
  td.cell { padding:0; vertical-align:top; text-align:center; }
  tr { page-break-inside: avoid; }
  .tile { border:0.18mm dashed #bbb; padding:0.8mm 0.8mm; display:flex; flex-direction:column; justify-content:center; align-items:center; line-height:0; }
  .code-wrap { width:100%; text-align:center; margin:0; }
  .harga { margin:0.6mm 0 0 0; font-size:7pt; font-weight:700; line-height:1.1; text-align:center; }
  .page-break { page-break-after: always; }
  img { display:block; }
</style>
CSS;

// ====== Rakit HTML multi-halaman ======
$pages = [];
$total = count($barangList);
foreach ($barangList as $i => $b) {
  $pages[] = buildOnePageTable($b, $tipe, $cols, $labelsPerPage, $usableW, $usableH, $gap)
           . ($i < $total - 1 ? '<div class="page-break"></div>' : '');
}

$html = '<!doctype html><html><head><meta charset="utf-8" /><title>All Labels</title>'
      . $css
      . '</head><body>'
      . implode('', $pages)
      . '</body></html>';

// ====== Render PDF ======
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'All-Labels-' . strtoupper($tipe) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
