<?php
require __DIR__ . '/config.php';

function moneyText($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

$rawDate = $_GET['tanggal'] ?? date('d/m/Y');
$idpel = $_GET['idpel'] ?? '1234567890';
$nama = $_GET['nama'] ?? 'SUGIANTO';
$alamat = $_GET['alamat'] ?? 'RT 02 / RW 04';
$bulan = $_GET['bulan'] ?? 'OKTOBER ' . date('Y');
$standLalu = isset($_GET['stand_lalu']) ? (float) $_GET['stand_lalu'] : 120;
$standSkrg = isset($_GET['stand_skrg']) ? (float) $_GET['stand_skrg'] : 131;
$tarif = isset($_GET['tarif']) ? (float) $_GET['tarif'] : 1000;
$biayaAdmin = isset($_GET['biaya_admin']) ? (float) $_GET['biaya_admin'] : 5000;
$ref = $_GET['ref'] ?? 'REF-2026-001';
$petugas = $_GET['petugas'] ?? 'ADMIN';

$pemakaian = max(0, $standSkrg - $standLalu);
$tagihan = ($pemakaian * $tarif) + $biayaAdmin;

$receipt = "";
$receipt .= "PAMSIMAS SADEYAN\n";
$receipt .= "REKENING AIR\n";
$receipt .= "------------------------------\n";
$receipt .= "IDPEL     : {$idpel}\n";
$receipt .= "NAMA      : {$nama}\n";
$receipt .= "ALAMAT    : {$alamat}\n";
$receipt .= "BL/TH     : {$bulan}\n";
$receipt .= "TGL       : {$rawDate}\n";
$receipt .= "PETUGAS   : {$petugas}\n";
$receipt .= "REF       : {$ref}\n";
$receipt .= "STAND LALU: {$standLalu} m3\n";
$receipt .= "STAND NOW : {$standSkrg} m3\n";
$receipt .= "PEMAKAIAN : {$pemakaian} m3\n";
$receipt .= "TARIF     : " . moneyText($tarif) . "\n";
$receipt .= "ADMIN     : " . moneyText($biayaAdmin) . "\n";
$receipt .= "TOTAL     : " . moneyText($tagihan) . "\n";
$receipt .= "------------------------------\n";
$receipt .= "TERIMA KASIH\n";
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simulasi Cetak Bluetooth</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f7fb;
            color: #111827;
        }
        .wrap {
            max-width: 980px;
            margin: 0 auto;
            padding: 22px 16px 40px;
        }
        .topbar {
            background: linear-gradient(135deg, #0f766e, #2563eb);
            color: #fff;
            padding: 18px 20px;
            border-radius: 14px;
            box-shadow: 0 12px 28px rgba(15, 118, 110, 0.18);
            margin-bottom: 18px;
        }
        .topbar h1 {
            margin: 0;
            font-size: 26px;
        }
        .topbar p {
            margin: 6px 0 0;
            opacity: 0.9;
        }
        .grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 18px;
        }
        .panel {
            background: #fff;
            border: 1px solid #dfe7ef;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
        }
        .panel h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 20px;
        }
        .receipt {
            background: #f8fafc;
            border: 1px dashed #94a3b8;
            border-radius: 10px;
            padding: 16px;
            font-family: "Courier New", monospace;
            white-space: pre-wrap;
            line-height: 1.5;
            font-size: 13px;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .button {
            display: inline-block;
            border: none;
            background: #0f766e;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .button.secondary {
            background: #e2e8f0;
            color: #0f172a;
        }
        .hint {
            margin-top: 12px;
            color: #475569;
            font-size: 14px;
            line-height: 1.5;
        }
        @media (max-width: 760px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <h1>Simulasi Cetak Bluetooth</h1>
            <p>Format rekening untuk smartphone yang terhubung ke printer bluetooth / thermal mobile.</p>
        </div>

        <div class="grid">
            <section class="panel">
                <h2>Preview hasil</h2>
                <div class="receipt"><?= htmlspecialchars($receipt, ENT_QUOTES, 'UTF-8') ?></div>
            </section>

            <section class="panel">
                <h2>Langkah cetak</h2>
                <ol>
                    <li>Pastikan printer bluetooth sudah terhubung ke smartphone.</li>
                    <li>Buka halaman ini di browser Android / Chrome.</li>
                    <li>Pilih menu <strong>Print</strong> atau <strong>Cetak</strong> pada browser.</li>
                    <li>Pilih printer bluetooth yang tersedia.</li>
                    <li>Set format kertas ke thermal/receipt sesuai printer.</li>
                </ol>
                <div class="actions">
                    <button class="button" onclick="window.print()">Cetak sekarang</button>
                    <a class="button secondary" href="index.php?page=printing">Kembali ke menu</a>
                </div>
                <div class="hint">
                    Catatan: beberapa printer bluetooth hanya bisa dicetak dari browser yang mendukung sistem print native. Jika browser tidak menampilkan printer bluetooth, gunakan browser yang kompatibel dengan print system Android dan pastikan printer sudah pairing dari HP.
                </div>
            </section>
        </div>
    </div>
</body>
</html>
