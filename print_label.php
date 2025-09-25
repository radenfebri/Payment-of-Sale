<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/print_helpers.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$barangFile  = __DIR__ . "/data/barang.json";
$settingFile = __DIR__ . "/data/setting.json";

// Load data
$barangList = loadJson($barangFile, []);
$setting    = getSettings($settingFile);

// Params
$id   = $_GET['id']   ?? null;
$cols = max(1, (int)($_GET['cols'] ?? 5));
$rows = max(1, (int)($_GET['rows'] ?? 6));

// Cari barang
$barangItem = null;
foreach ($barangList as $b) {
    if ((string)($b['id'] ?? '') === (string)$id) {
        $barangItem = $b;
        break;
    }
}
if (!$barangItem) {
    http_response_code(404);
    echo "Barang tidak ditemukan untuk id: " . htmlspecialchars((string)$id);
    exit;
}

// Tipe ikut setting, bisa override via ?tipe_kode=qr|barcode
$tipe = $_GET['tipe_kode'] ?? ($setting['tipe_kode'] ?? 'barcode');
$tipe = ($tipe === 'qr') ? 'qr' : 'barcode';

// Build HTML
$html = buildLabelHtml($barangItem, $setting, $tipe, $cols, $rows);

// Render PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Label-' . ($barangItem['kodeProduk'] ?? 'Barang') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
