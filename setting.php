<?php
require "struk_template.php";

date_default_timezone_set('Asia/Jakarta'); // pastikan tanggal struk sesuai GMT+7

$settingFile = __DIR__ . "/data/setting.json";
$saved = false; // inisialisasi agar tidak undefined

$raw = @file_get_contents($settingFile);
$settings = json_decode($raw, true);
if (!is_array($settings)) $settings = [];

$defaults = [
    'nama_toko'    => 'Toko Saya',
    'alamat'       => '',
    'telepon'      => '',
    'printer_name' => 'POS-58',
    'paper_size'   => '58mm',
    'auto_print'   => false,
    'footer'       => '',
    'tipe_kode'    => 'barcode',
    'label'        => [
        'tipe_kode' => 'barcode',
        'barcode'   => ['width_px' => 114, 'per_row' => 6],
        'qr'        => ['size_px'  =>  72, 'per_row' => 6],
    ],
];

// merge rekursif supaya key yang hilang terisi aman
$settings = array_replace_recursive($defaults, $settings);

// Simpan pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['tes_printer'])) {
    $settings['nama_toko'] = $_POST['nama_toko'] ?? $settings['nama_toko'];
    $settings['alamat'] = $_POST['alamat'] ?? $settings['alamat'];
    $settings['telepon'] = $_POST['telepon'] ?? $settings['telepon'];
    $settings['printer_name'] = $_POST['printer_name'] ?? $settings['printer_name'];
    $settings['paper_size'] = $_POST['paper_size'] ?? $settings['paper_size'];
    $settings['footer'] = $_POST['footer'] ?? $settings['footer'] ?? '';
    $settings['auto_print'] = isset($_POST['auto_print']);
    // Ambil dan validasi tipe_kode dari POST
    $tipe_kode_post = $_POST['tipe_kode'] ?? ($settings['tipe_kode'] ?? 'barcode');
    $tipe_kode = in_array($tipe_kode_post, ['barcode', 'qr'], true) ? $tipe_kode_post : 'barcode';
    $settings['tipe_kode'] = $tipe_kode;

    // ----- Label settings (ambil dari form; fallback ke nilai lama/default) -----
    $barcode_width_px = isset($_POST['barcode_width_px']) ? (int)$_POST['barcode_width_px'] : $settings['label']['barcode']['width_px'];
    $barcode_per_row  = isset($_POST['barcode_per_row'])  ? (int)$_POST['barcode_per_row']  : $settings['label']['barcode']['per_row'];
    $qr_size_px       = isset($_POST['qr_size_px'])       ? (int)$_POST['qr_size_px']       : $settings['label']['qr']['size_px'];
    $qr_per_row       = isset($_POST['qr_per_row'])       ? (int)$_POST['qr_per_row']       : $settings['label']['qr']['per_row'];

    // validasi sederhana
    $barcode_width_px = max(40, min($barcode_width_px, 800));
    $barcode_per_row  = max(1, min($barcode_per_row, 12));
    $qr_size_px       = max(40, min($qr_size_px, 800));
    $qr_per_row       = max(1, min($qr_per_row, 12));

    // set kembali ke settings
    $settings['label']['tipe_kode'] = $tipe_kode; // sinkron
    $settings['label']['barcode']['width_px'] = $barcode_width_px;
    $settings['label']['barcode']['per_row']  = $barcode_per_row;
    $settings['label']['qr']['size_px']       = $qr_size_px;
    $settings['label']['qr']['per_row']       = $qr_per_row;

    // bersihkan key typo jika ada
    unset($settings['tipe_kode  ']);

    $ok = @file_put_contents(
        $settingFile,
        json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    $saved = $ok !== false;
}

$tipe = $settings['tipe_kode'] ?? 'barcode';

// Tes print via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tes_printer'])) {
    header('Content-Type: application/json; charset=utf-8'); // ← tambahkan
    $items = [
        ["nama" => "Rinso Box", "qty" => 2, "harga" => 50000],
        ["nama" => "Teh Botol", "qty" => 3, "harga" => 7000],
        ["nama" => "Indomie Goreng", "qty" => 5, "harga" => 3500],
    ];
    echo json_encode(cetakStruk($items, $settings));
    exit;
}


if (isset($_GET['action'])) {
    $action = $_GET['action'];

    switch ($action) {
        case 'backup_data':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo "Method Not Allowed";
                exit;
            }
            backupData();
            break;

        case 'import_data':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
                exit;
            }
            importData();
            break;

        default:
            echo "Action tidak dikenali!";
            break;
    }
}

function backupData()
{
    $dataDir = __DIR__ . '/data';
    $files   = glob($dataDir . '/*.json');
    if (!$files) {
        http_response_code(404);
        exit('Tidak ada file JSON.');
    }

    $timestamp    = date('Ymd_His');
    $downloadName = "backup_json_{$timestamp}.tar.gz";
    $tmpTar       = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "backup_json_{$timestamp}.tar";

    try {
        $tar = new PharData($tmpTar);
        foreach ($files as $f) {
            $tar->addFile($f, basename($f));
        }
        $tar->compress(Phar::GZ);
        unset($tar);

        @unlink($tmpTar); // hapus file .tar, biar tinggal .tar.gz
        $tmpFile = $tmpTar . '.gz';
    } catch (Exception $e) {
        http_response_code(500);
        exit('Gagal membuat arsip: ' . $e->getMessage());
    }

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

function importData()
{
    header('Content-Type: application/json');

    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Upload file gagal.']);
        exit;
    }

    $file      = $_FILES['import_file'];
    $origName  = $file['name'];
    $tmpUpload = $file['tmp_name'];

    // Deteksi tipe berdasarkan ekstensi + mime
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $mime = mime_content_type($tmpUpload) ?: '';

    $dataDir = realpath(__DIR__ . '/data');
    if ($dataDir === false || !is_dir($dataDir)) {
        @mkdir(__DIR__ . '/data', 0777, true);
        $dataDir = realpath(__DIR__ . '/data');
        if ($dataDir === false) {
            echo json_encode(['status' => 'error', 'message' => 'Folder /data tidak tersedia dan gagal dibuat.']);
            exit;
        }
    }

    try {
        if ($ext === 'json' || strpos($mime, 'application/json') === 0 || $mime === 'text/plain') {
            // IMPORT JSON LANGSUNG → salin ke /data, timpa jika ada
            $target = $dataDir . DIRECTORY_SEPARATOR . basename($origName);
            if (!move_uploaded_file($tmpUpload, $target)) {
                // Jika move gagal (karena tmp sudah bukan upload?), coba copy stream
                if (!copy($tmpUpload, $target)) {
                    throw new RuntimeException('Gagal menyimpan file JSON.');
                }
            }
            echo json_encode(['status' => 'success', 'message' => "Berhasil mengimpor 1 file JSON: " . basename($origName)]);
            exit;
        } elseif ($ext === 'zip' || $mime === 'application/zip' || $mime === 'application/x-zip-compressed') {
            // IMPORT ZIP → extract ke temp → pindahkan *.json ke /data
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('File ZIP terdeteksi, namun ekstensi ZipArchive belum aktif di server.');
            }
            $count = extractZipJsonToData($tmpUpload, $dataDir);
            if ($count === 0) {
                throw new RuntimeException('Arsip ZIP tidak berisi file .json.');
            }
            echo json_encode(['status' => 'success', 'message' => "Berhasil mengimpor {$count} file JSON dari ZIP."]);
            exit;
        } elseif ($ext === 'gz' || $ext === 'tgz' || $ext === 'tar' || strpos($mime, 'application/x-gzip') === 0 || strpos($mime, 'application/gzip') === 0 || strpos($mime, 'application/x-tar') === 0) {
            // IMPORT TAR / TAR.GZ → extract ke temp → pindahkan *.json ke /data
            $count = extractTarJsonToData($tmpUpload, $origName, $dataDir);
            if ($count === 0) {
                throw new RuntimeException('Arsip TAR/TAR.GZ tidak berisi file .json.');
            }
            echo json_encode(['status' => 'success', 'message' => "Berhasil mengimpor {$count} file JSON dari arsip."]);
            exit;
        } else {
            throw new RuntimeException('Tipe file tidak didukung. Unggah .json, .zip, atau .tar/.tar.gz');
        }
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

function extractZipJsonToData(string $zipPath, string $dataDir): int
{
    $tmpDir = makeTempDir('import_zip_');
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Gagal membuka arsip ZIP.');
    }
    if (!$zip->extractTo($tmpDir)) {
        $zip->close();
        throw new RuntimeException('Gagal mengekstrak arsip ZIP.');
    }
    $zip->close();

    $files = collectJsonFiles($tmpDir);
    $count = moveJsonFiles($files, $dataDir);
    rrmdir($tmpDir);
    return $count;
}

function extractTarJsonToData(string $uploadPath, string $origName, string $dataDir): int
{
    $tmpDir = makeTempDir('import_tar_');

    // Simpan upload ke file sementara dengan ekstensi aslinya agar PharData bisa kenali
    $tmpArchive = $tmpDir . DIRECTORY_SEPARATOR . basename($origName);
    if (!move_uploaded_file($uploadPath, $tmpArchive) && !copy($uploadPath, $tmpArchive)) {
        rrmdir($tmpDir);
        throw new RuntimeException('Gagal menyiapkan arsip sementara.');
    }

    $lower = strtolower($tmpArchive);

    try {
        if (str_ends_with($lower, '.tar')) {
            // langsung extract
            $tar = new PharData($tmpArchive);
            $tar->extractTo($tmpDir . '/extracted', null, true);
        } elseif (str_ends_with($lower, '.tar.gz') || str_ends_with($lower, '.tgz') || preg_match('/\.t(ar\.)?gz$/', $lower)) {
            // decompress -> dapat .tar -> extract
            $p = new PharData($tmpArchive);
            $p->decompress(); // hasilkan .tar di lokasi yang sama
            unset($p);

            $tarPath = preg_replace('/\.gz$/i', '', $tmpArchive); // remove trailing .gz
            $tar = new PharData($tarPath);
            $tar->extractTo($tmpDir . '/extracted', null, true);

            @unlink($tarPath); // bersihkan .tar hasil decompress
        } else {
            // fallback: coba langsung dengan PharData (beberapa build bisa buka .tar.gz langsung)
            $p = new PharData($tmpArchive);
            // jika tidak throw, coba extract
            $p->extractTo($tmpDir . '/extracted', null, true);
            unset($p);
        }
    } catch (Exception $e) {
        rrmdir($tmpDir);
        throw new RuntimeException('Gagal mengekstrak arsip TAR/TAR.GZ: ' . $e->getMessage());
    }

    $files = collectJsonFiles($tmpDir . '/extracted');
    $count = moveJsonFiles($files, $dataDir);
    rrmdir($tmpDir);
    return $count;
}

function makeTempDir(string $prefix = 'import_'): string
{
    $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
    if (!mkdir($base, 0777, true)) {
        throw new RuntimeException('Gagal membuat direktori sementara.');
    }
    return $base;
}

function collectJsonFiles(string $root): array
{
    $result = [];
    if (!is_dir($root)) return $result;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($it as $fileInfo) {
        if ($fileInfo->isFile()) {
            $name = $fileInfo->getFilename();
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'json') {
                $result[] = $fileInfo->getPathname();
            }
        }
    }
    return $result;
}

function moveJsonFiles(array $files, string $dataDir): int
{
    $count = 0;
    foreach ($files as $src) {
        $dest = $dataDir . DIRECTORY_SEPARATOR . basename($src);
        // pakai copy agar robust di semua FS; overwrite
        if (!@copy($src, $dest)) {
            // kalau copy gagal, coba baca-tulis stream
            $in = @fopen($src, 'rb');
            $out = @fopen($dest, 'wb');
            if ($in && $out) {
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                $count++;
            } else {
                if ($in) fclose($in);
                if ($out) fclose($out);
                // skip file ini
            }
        } else {
            $count++;
        }
    }
    return $count;
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $path) {
        $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
    }
    @rmdir($dir);
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $len = strlen($needle);
        if ($len === 0) return true;
        return (substr($haystack, -$len) === $needle);
    }
}


?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Pengaturan Sistem</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="alert.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8fafc;
            --dark: #1e293b;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #f3f4f6 100%);
            min-height: 100vh;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .form-input {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            transition: all 0.2s;
            width: 100%;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-info {
            background: #8b5cf6;
            color: white;
        }

        .btn-info:hover {
            background: #7c3aed;
            transform: translateY(-1px);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: var(--primary);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background-color: var(--primary);
        }

        input:checked+.toggle-slider:before {
            transform: translateX(26px);
        }

        .modal-overlay {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            animation: modalAppear 0.3s ease-out;
        }

        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .preview-struk {
            font-family: 'Courier New', monospace;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 280px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>

<body class="min-h-screen flex">
    <?php include "partials/sidebar.php"; ?>

    <main class="flex-1 p-6">
        <!-- FULL WIDTH container -->
        <div class="w-full max-w-none mx-auto px-2 sm:px-4">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-cogs text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Pengaturan Sistem</h2>
                    <p class="text-gray-600">Kelola pengaturan toko dan sistem</p>
                </div>
            </div>

            <?php if (!empty($saved)): ?>
                <script>
                    document.addEventListener("DOMContentLoaded", () => {
                        showToast('Pengaturan berhasil disimpan!', 'success', 3000);
                    });
                </script>
            <?php endif; ?>

            <form method="post" class="card p-6 space-y-6 w-full">
                <!-- Informasi Toko -->
                <div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                        <div>
                            <label class="block font-medium mb-2 text-gray-700">Nama Toko</label>
                            <input
                                type="text"
                                name="nama_toko"
                                value="<?= htmlspecialchars($settings['nama_toko'], ENT_QUOTES, 'UTF-8') ?>"
                                class="form-input"
                                required />
                        </div>
                        <div>
                            <label class="block font-medium mb-2 text-gray-700">Nomor Telepon/WhatsApp</label>
                            <input
                                type="text"
                                name="telepon"
                                value="<?= htmlspecialchars($settings['telepon'], ENT_QUOTES, 'UTF-8') ?>"
                                class="form-input" />
                        </div>
                        <div>
                            <label class="block font-medium mb-2 text-gray-700">Alamat</label>
                            <textarea
                                name="alamat"
                                class="form-input"
                                rows="2"><?= htmlspecialchars($settings['alamat'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div>
                            <label class="block font-medium mb-2 text-gray-700">Footer Struk</label>
                            <textarea
                                name="footer"
                                class="form-input"
                                rows="2"
                                placeholder="Pesan tambahan di bagian bawah struk"><?= htmlspecialchars($settings['footer'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Pengaturan Printer -->
                <div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                        <div>
                            <label class="block font-medium mb-2 text-gray-700">Nama Printer</label>
                            <input
                                type="text"
                                name="printer_name"
                                value="<?= htmlspecialchars($settings['printer_name'], ENT_QUOTES, 'UTF-8') ?>"
                                class="form-input"
                                placeholder="Nama printer di komputer" />
                        </div>
                        <div>
                            <label class="block font-medium mb-2 text-gray-700">Ukuran Kertas</label>
                            <select name="paper_size" class="form-input">
                                <option value="58mm" <?= $settings['paper_size'] == '58mm' ? 'selected' : '' ?>>58mm</option>
                                <option value="80mm" <?= $settings['paper_size'] == '80mm' ? 'selected' : '' ?>>80mm</option>
                            </select>
                        </div>

                        <!-- Toggle Auto Print -->
                        <div class="flex items-center gap-3">
                            <label class="toggle-switch">
                                <input type="checkbox" name="auto_print" <?= $settings['auto_print'] ? 'checked' : '' ?> />
                                <span class="toggle-slider"></span>
                            </label>
                            <label class="font-medium text-gray-700">Cetak Otomatis setelah transaksi</label>
                        </div>

                        <!-- Tipe Kode Label -->
                        <div>
                            <label class="block font-medium mb-2 text-gray-700">Tipe Kode Label</label>
                            <div class="flex items-center gap-6">
                                <label class="inline-flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="tipe_kode"
                                        value="barcode"
                                        class="accent-blue-600"
                                        <?= ($tipe === 'barcode') ? 'checked' : '' ?> />
                                    <span>Barcode Panjang</span>
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input
                                        type="radio"
                                        name="tipe_kode"
                                        value="qr"
                                        class="accent-blue-600"
                                        <?= ($tipe === 'qr') ? 'checked' : '' ?> />
                                    <span>QR Code</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pengaturan Label -->
                <div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                        <!-- Barcode -->
                        <div>
                            <label class="block font-medium mb-2 text-gray-700">Lebar Gambar Barcode (px)</label>
                            <input
                                type="number"
                                min="40"
                                max="800"
                                name="barcode_width_px"
                                value="<?= (int)($settings['label']['barcode']['width_px'] ?? 114) ?>"
                                class="form-input mb-3" />

                            <label class="block font-medium mb-2 text-gray-700">Jumlah Barcode per Baris</label>
                            <input
                                type="number"
                                min="1"
                                max="12"
                                name="barcode_per_row"
                                value="<?= (int)($settings['label']['barcode']['per_row'] ?? 6) ?>"
                                class="form-input" />
                        </div>

                        <!-- QR Code -->
                        <div>
                            <label class="block font-medium mb-2 text-gray-700">Ukuran Sisi QR (px)</label>
                            <input
                                type="number"
                                min="40"
                                max="800"
                                name="qr_size_px"
                                value="<?= (int)($settings['label']['qr']['size_px'] ?? 72) ?>"
                                class="form-input mb-3" />

                            <label class="block font-medium mb-2 text-gray-700">Jumlah QR per Baris</label>
                            <input
                                type="number"
                                min="1"
                                max="12"
                                name="qr_per_row"
                                value="<?= (int)($settings['label']['qr']['per_row'] ?? 6) ?>"
                                class="form-input mb-3" />
                        </div>
                    </div>
                </div>


                <!-- Tombol Aksi -->
                <div class="action-buttons w-full flex flex-wrap gap-2 justify-between">
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Pengaturan
                        </button>
                        <button type="button" class="btn btn-secondary" id="btnPreviewPrinter">
                            <i class="fas fa-eye"></i>
                            Preview Struk
                        </button>
                        <button type="button" class="btn btn-success" id="btnCetakPrinter">
                            <i class="fas fa-print"></i>
                            Tes Printer
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-info" id="btnBackupData">
                            <i class="fas fa-download"></i>
                            Backup Data
                        </button>
                        <label class="btn btn-primary cursor-pointer m-0">
                            <i class="fas fa-upload"></i>
                            Import Data
                            <input type="file" id="importFile" accept=".json,.zip,.tar,.tar.gz" class="hidden" />
                        </label>
                    </div>
                </div>
            </form>

        </div>
    </main>


    <!-- Modal Preview -->
    <div id="modalPreview" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 modal-overlay">
        <div class="bg-white p-6 rounded-xl shadow-xl relative max-w-md w-full mx-4 modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Preview Struk</h3>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="previewStruk" class="preview-struk mx-auto"></div>
        </div>
    </div>

    <script>
        const settings = <?= json_encode($settings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const paperWidth = settings.paper_size === '80mm' ? 280 : 200;

        function generatePreview(items) {
            let total = 0;
            let itemsHtml = '';
            items.forEach(item => {
                total += item.qty * item.harga;
                itemsHtml += `<div style="display:flex;justify-content:space-between;margin:2px 0;font-size:12px">
                    <span style="text-align:left;width:70%">${item.nama} x${item.qty}</span>
                    <span style="text-align:right;width:30%">Rp ${(item.qty * item.harga).toLocaleString('id-ID')}</span>
                </div>`;
            });

            const footerText = settings.footer && settings.footer.trim() !== '' ? settings.footer : 'Terima kasih sudah berbelanja!';
            const now = new Date().toLocaleString('id-ID', {
                timeZone: 'Asia/Jakarta'
            });

            return `
<div style="width:${paperWidth}px;font-family:monospace;border:1px dashed #ccc;padding:10px;background:#fff;font-size:12px;line-height:1.4;">
    <h3 style="text-align:center;margin:0;font-weight:bold;font-size:14px;">${settings.nama_toko}</h3>
    <p style="text-align:center;margin:2px 0;font-size:11px;">${settings.alamat}</p>
    <p style="text-align:center;margin:2px 0;font-size:11px;">Telp/WA: ${settings.telepon}</p>
    <p style="text-align:center;margin:2px 0;font-size:10px;border-bottom:1px dashed #ccc;padding-bottom:5px;">${now}</p>
    ${itemsHtml}
    <div style="border-top:1px dashed #ccc;margin:5px 0;"></div>
    <div style="display:flex;justify-content:space-between;font-weight:bold;margin-top:5px;">
        <span style="text-align:left;">TOTAL:</span>
        <span style="text-align:right;">Rp ${total.toLocaleString('id-ID')}</span>
    </div>
    <div style="border-top:1px dashed #ccc;margin:5px 0;"></div>
    <p style="text-align:center;margin:5px 0;font-size:10px;">${footerText}</p>
</div>
`;
        }

        // Event Listener
        document.getElementById('btnPreviewPrinter').addEventListener('click', () => {
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
            document.getElementById('previewStruk').innerHTML = generatePreview(items);
            document.getElementById('modalPreview').classList.remove('hidden');
            document.getElementById('modalPreview').classList.add('flex'); // Tambahkan ini
        });

        document.getElementById('closeModal').addEventListener('click', () => {
            document.getElementById('modalPreview').classList.add('hidden');
            document.getElementById('modalPreview').classList.remove('flex'); // Tambahkan ini
        });

        document.getElementById('btnCetakPrinter').addEventListener('click', () => {
            const formData = new FormData();
            formData.append('tes_printer', '1');
            fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success')
                        showToast(data.message, 'success', 3000);
                    else
                        showToast(data.message, 'error', 5000); // <-- di sini menampilkan error printer
                })
                .catch(err => showToast('Terjadi kesalahan', 'error', 5000));

        });

        function ensureIframe() {
            let iframe = document.getElementById('download_iframe');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'download_iframe';
                iframe.name = 'download_iframe';
                iframe.style.display = 'none';
                document.body.appendChild(iframe);
            }
            return iframe;
        }

        // Backup data
        document.getElementById('btnBackupData').addEventListener('click', () => {
            window.showConfirm(
                'Yakin ingin melakukan backup data (.json) sekarang?',
                () => {
                    window.showToast('Menyiapkan backup…', 'success', 2000);

                    const iframe = ensureIframe();

                    iframe.onload = () => {
                        const text = iframe.contentDocument?.body?.innerText?.trim() || '';
                        if (text.length > 0) {
                            window.showToast('Backup gagal: ' + text, 'error', 5000);
                        } else {
                            window.showToast('Backup berhasil, unduhan dimulai.', 'success', 3000);
                        }
                    };

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'setting.php?action=backup_data';
                    form.target = 'download_iframe';
                    document.body.appendChild(form);
                    form.submit();
                    form.remove();
                },
                () => {
                    window.showToast('Backup dibatalkan.', 'info', 2000);
                }
            );
        });



        // Import data
        document.getElementById('importFile').addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('import_file', file);

            fetch('setting.php?action=import_data', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    showToast(data.message, data.status === 'success' ? 'success' : 'error', 5000);
                    e.target.value = '';
                })
                .catch(err => showToast('Terjadi error: ' + err.message, 'error', 5000));
        });
    </script>
</body>

</html>