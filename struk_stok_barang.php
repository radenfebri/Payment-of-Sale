<?php
require __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('Asia/Jakarta');

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

// Ambil data dari JS
$input = json_decode(file_get_contents("php://input"), true);
$mode = $input['mode'] ?? "semua";

// Baca setting.json
$settingFile = __DIR__ . "/data/setting.json";
if (!file_exists($settingFile)) {
    echo json_encode(["status" => "error", "message" => "File setting.json tidak ditemukan"]);
    exit;
}
$settings = json_decode(file_get_contents($settingFile), true);
$printerName = $settings['printer_name'] ?? "POS-58";
$paperSize   = $settings['paper_size'] ?? "58mm";

// Ambil data barang dari file JSON
$barangFile = __DIR__ . "/data/barang.json";
if (!file_exists($barangFile)) {
    echo json_encode(["status" => "error", "message" => "File barang.json tidak ditemukan"]);
    exit;
}
$allBarang = json_decode(file_get_contents($barangFile), true);

// === CETAK LAPORAN ===
function cetakLaporanStok($barang, $printerName, $mode = "semua", $paperSize = "58mm")
{
    try {
        if ($mode === "habis" || $mode === "limit") {
            $barang = array_filter($barang, function ($b) {
                $stok = intval($b['stok']);
                $stokMin = intval($b['stokMin']);
                return ($stok <= $stokMin || $stok == 0);
            });
        }


        if (empty($barang)) {
            return ["status" => "error", "message" => "Tidak ada barang untuk dicetak"];
        }

        $connector = new WindowsPrintConnector($printerName);
        $printer   = new Printer($connector);

        if ($mode === "habis" || $mode === "limit") {
            $judul = "DATA BARANG HABIS / HAMPIR HABIS";
        } else {
            $judul = "DATA SEMUA BARANG";
        }

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text($judul . "\n");
        $printer->setEmphasis(false);
        $printer->text("Dicetak: " . date('d/m/Y H:i') . "\n");
        $printer->text(str_repeat("-", 32) . "\n");

        // Lebar maksimal karakter per baris tergantung ukuran kertas
        $maxChar = ($paperSize === "80mm") ? 42 : 32;

        foreach ($barang as $b) {
            $nama = substr($b['nama'], 0, $maxChar - 8); // sisakan space stok + satuan
            $stok = $b['stok'];     // tampilkan stok
            $printer->text(str_pad($nama, $maxChar - strlen($stok)));
            $printer->text($stok . "\n");
        }

        $printer->text(str_repeat("-", $maxChar) . "\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Total: " . count($barang) . " item\n");
        $printer->cut();
        $printer->close();

        return ["status" => "success", "message" => "Cetak laporan berhasil"];
    } catch (Exception $e) {
        return ["status" => "error", "message" => $e->getMessage()];
    }
}

$result = cetakLaporanStok($allBarang, $printerName, $mode, $paperSize);

// Balikan JSON ke JS
header("Content-Type: application/json");
echo json_encode($result);
