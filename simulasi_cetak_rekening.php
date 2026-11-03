<?php
require __DIR__ . '/config.php';

function parseTanggal($tanggal) {
    if (empty($tanggal)) return [date('d'), date('m'), date('Y')];
    if (strpos($tanggal, '/') !== false) {
        $b = explode('/', $tanggal);
        if (count($b) >= 3) return [$b[0], $b[1], $b[2]];
    }
    if (strpos($tanggal, '-') !== false && strlen($tanggal) === 10) {
        $b = explode('-', $tanggal);
        if (count($b) === 3) return [$b[2], $b[1], $b[0]];
    }
    if (strpos($tanggal, '-') !== false) {
        $b = explode('-', $tanggal);
        if (count($b) >= 3) return [$b[0], $b[1], $b[2]];
    }
    return [date('d'), date('m'), date('Y')];
}

function wrapLunas($label, $value, $max = 35) {
    return substr($label . $value, 0, $max);
}

function buatStruk($data, $bold_on = '', $bold_off = '') {
    $tanggal    = $data['tanggal'] ?? date('d/m/Y');
    $idpel      = $data['idpel'] ?? 'IDPEL';
    $nama       = $data['nama'] ?? 'NAMA';
    $petugas    = $data['petugas'] ?? 'PETUGAS';
    $no_ref     = $data['no_ref'] ?? '';
    $admin_bank = $data['beban'] ?? '0';
    $tagihan_baru = (int) str_replace('.', '', $data['rp_non_beban'] ?? $data['rptag'] ?? '0');
    $tagihan = number_format($tagihan_baru, 0, ',', '.');
    $total_baru = (int) str_replace('.', '', $data['rptag'] ?? $data['rptag'] ?? '0');
    $total = number_format($total_baru, 0, ',', '.');

    list($hari, $bulan, $tahun) = parseTanggal($tanggal);
    $tahun = substr($tahun, 0, 4);
    $nama_bulan = [
        '01' => 'JAN', '02' => 'FEB', '03' => 'MAR', '04' => 'APR', '05' => 'MEI', '06' => 'JUN',
        '07' => 'JUL', '08' => 'AGU', '09' => 'SEP', '10' => 'OKT', '11' => 'NOV', '12' => 'DES'
    ];
    $bulan_str = $nama_bulan[str_pad($bulan, 2, '0', STR_PAD_LEFT)] ?? $bulan;

    $lunas  = "\n" . $bold_on . "SEGI LUNAS\n" . $bold_off;
    $lunas .= "\n" . wrapLunas("IDPEL     :", $idpel);
    $lunas .= "\n" . wrapLunas("NAMA      :", $nama);
    $lunas .= "\n" . wrapLunas("TOTAL     :", "Rp. $total");
    $lunas .= "\n" . wrapLunas("BL/TH     :", "$bulan_str-$tahun");
    $lunas .= "\n" . wrapLunas("TGL       :", $tanggal);
    $lunas .= "\n" . wrapLunas("USER      :", $petugas);
    $lunas .= "\n" . wrapLunas("REF       :", $no_ref) . "\n";
    $lunas .= "\nSegi Lunas ";
    $lunas .= "\nTerima Kasih";

    $utama  = "\n";
    $utama .= str_pad("   TGL BAYAR:$tanggal", 63, " ", STR_PAD_LEFT) . "\n";
    $utama .= str_pad("STRUK PEMBAYARAN TAGIHAN PAMSIMAS SADEYAN", 65, " ", STR_PAD_BOTH) . "\n";
    $utama .= str_pad("LOKET SUMBERLAWANG/GRSFIND/001", 65, " ", STR_PAD_BOTH) . "\n";
    $utama .= str_pad("IDPEL      : $idpel", 25) . str_pad("BL/TH : $bulan_str-$tahun", 35, " ", STR_PAD_LEFT) . "\n";
    $utama .= "NAMA       : $nama\n";
    $utama .= "RP TAG PAMSIMAS : Rp. $tagihan\n";
    $utama .= "REF        : $no_ref\n\n";
    $utama .= str_pad("   PAMSIMAS menyatakan struk ini sebagai bukti pembayaran yang sah.", 35, " ", STR_PAD_BOTH) . "\n";
    $utama .= str_pad("BEBAN : Rp. $admin_bank\n", 34, " ", STR_PAD_LEFT);
    $utama .= str_pad("TOTAL BAYAR: Rp. $total\n", 35, " ", STR_PAD_LEFT);
    $utama .= str_pad("Terima Kasih", 45, " ", STR_PAD_BOTH) . "\n";

    $ll = explode("\n", $lunas);
    $lu = explode("\n", $utama);
    $mx = max(count($ll), count($lu));
    $out = "";
    for ($i = 0; $i < $mx; $i++) {
        $out .= str_pad($ll[$i] ?? "", 35) . ($lu[$i] ?? "") . "\n";
    }
    return $out;
}

$rawDate = $_GET['tanggal'] ?? date('d/m/Y');
$idpel = $_GET['idpel'] ?? '';
$nama = $_GET['nama'] ?? '';
$alamat = $_GET['alamat'] ?? '';
$bulan = $_GET['bulan'] ?? '';
$standLalu = isset($_GET['stand_lalu']) ? (float) $_GET['stand_lalu'] : 0;
$standSkrg = isset($_GET['stand_skrg']) ? (float) $_GET['stand_skrg'] : 0;
$tarif = isset($_GET['tarif']) ? (float) $_GET['tarif'] : 0;
$biayaAdmin = isset($_GET['biaya_admin']) ? (float) $_GET['biaya_admin'] : 0;
$ref = $_GET['ref'] ?? 'PPN-2026-001';
$petugas = $_GET['petugas'] ?? 'ADMIN';
$autoPrint = $_GET['auto'] ?? '0';
$printerInfo = $_GET['printer'] ?? 'Printer Lokal Windows (Dot Matrix / Epson / LQ-2190)';

if ($idpel !== '') {
    try {
        $stmt = $conn->prepare('SELECT p.idpelanggan, p.nama, p.alamat, d.* FROM data_pelanggan p LEFT JOIN data_pembacaan d ON d.idpelanggan = p.idpelanggan WHERE p.idpelanggan = ? ORDER BY d.tahun DESC, d.bulan DESC LIMIT 1');
        $stmt->execute([$idpel]);
        $row = $stmt->fetch();
        if ($row) {
            $nama = $row['nama'] ?? $nama;
            $alamat = $row['alamat'] ?? $alamat;
            $rawDate = $row['created_at'] ? date('d/m/Y', strtotime($row['created_at'])) : $rawDate;
            $bulan = (($row['bulan'] ?? date('n')) . ' ' . ($row['tahun'] ?? date('Y')));
            $standLalu = (float) ($row['standll'] ?? 0);
            $standSkrg = (float) ($row['standini'] ?? 0);
            $tarif = (float) ($row['rp_per_meter'] ?? 0);
            $biayaAdmin = (float) ($row['biaya_admin'] ?? 0);
            $ref = 'REF-' . ($row['id'] ?? $idpel);
            $petugas = 'ADMIN';
        }
    } catch (Throwable $e) {
        $nama = $nama !== '' ? $nama : 'SUGIANTO';
        $alamat = $alamat !== '' ? $alamat : 'RT 02 / RW 04, Desa Sumberlawang';
    }
}

if ($standSkrg > 0 && $standLalu > 0 && $tarif === 0 && $biayaAdmin === 0) {
    $tarif = 1000;
    $biayaAdmin = 5000;
}

if ($standSkrg > 0 && $standLalu > 0) {
    $pemakaian = $standSkrg - $standLalu;
} else {
    $pemakaian = 11;
    $standLalu = 120;
    $standSkrg = 131;
    $tarif = $tarif ?: 1000;
    $biayaAdmin = $biayaAdmin ?: 5000;
}

$tagihan = ($pemakaian * $tarif) + $biayaAdmin;
$nama = $nama !== '' ? $nama : 'SUGIANTO';
$alamat = $alamat !== '' ? $alamat : 'RT 02 / RW 04, Desa Sumberlawang';
$idpel = $idpel !== '' ? $idpel : '1234567890';
$bulan = $bulan !== '' ? $bulan : 'OKTOBER 2026';

$receiptData = [
    'tanggal' => $rawDate,
    'idpel' => $idpel,
    'nama' => $nama,
    'petugas' => $petugas,
    'no_ref' => $ref,
    'beban' => number_format($biayaAdmin, 0, ',', '.'),
    'rp_non_beban' => number_format(($pemakaian * $tarif), 0, ',', '.'),
    'rptag' => number_format($tagihan, 0, ',', '.'),
];
$fullDotMatrix = buatStruk($receiptData, chr(27) . chr(69), chr(27) . chr(70));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simulasi Cetak Rekening Dot Matrix</title>
    <style>
        :root {
            --bg: #f3f7fb;
            --panel: #ffffff;
            --ink: #1f2937;
            --muted: #64748b;
            --line: #dfe7ef;
            --primary: #0f766e;
            --primary-soft: #dcf5f2;
            --accent: #f59e0b;
            --dot: #111827;
            --dot-bg: #f8fafc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--ink);
        }
        .wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }
        .topbar {
            background: linear-gradient(135deg, #0f766e, #0ea5a4);
            color: white;
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 18px;
            box-shadow: 0 12px 28px rgba(15, 118, 110, 0.2);
        }
        .topbar h1 { margin: 0; font-size: 26px; }
        .topbar p { margin: 6px 0 0; opacity: 0.9; }
        .grid {
            display: grid;
            grid-template-columns: 1.1fr 1.1fr;
            gap: 18px;
        }
        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
        }
        .panel h2 {
            font-size: 20px;
            margin: 0 0 14px;
        }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            background: var(--primary-soft);
            color: var(--primary);
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .info { display: grid; gap: 12px; }
        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px dashed var(--line);
            padding-bottom: 8px;
        }
        .info-row label { color: var(--muted); }
        .info-row strong { text-align: right; }
        .note {
            margin-top: 14px;
            background: #fff7ed;
            border-left: 4px solid var(--accent);
            color: #8a5a00;
            padding: 12px 14px;
            border-radius: 8px;
            line-height: 1.6;
        }
        .dot-preview {
            background: var(--dot-bg);
            border: 2px solid #dfe3e8;
            border-radius: 12px;
            padding: 12px;
            overflow: auto;
        }
        .receipt {
            font-family: 'Courier New', monospace;
            background: white;
            color: var(--dot);
            border: 1px solid #dfe3e8;
            padding: 10px 12px;
            min-height: 500px;
            width: min(100%, 420px);
            margin: 0 auto;
            line-height: 1.25;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .raw-output {
            font-family: 'Courier New', monospace;
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            line-height: 1.3;
            font-size: 11px;
            white-space: pre-wrap;
            overflow: auto;
            min-height: 440px;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .btn {
            appearance: none;
            border: none;
            border-radius: 10px;
            min-height: 42px;
            padding: 0 16px;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .btn.secondary {
            background: #e5e7eb;
            color: var(--ink);
        }
        @media print {
            body { background: white; }
            .topbar, .actions, .note, .panel:not(.preview-panel) { display: none !important; }
            .grid { display: block; }
            .preview-panel { border: none; box-shadow: none; padding: 0; }
            .dot-preview { background: white; border: none; padding: 0; }
            .receipt { width: 100%; border: none; min-height: auto; }
            .raw-output { display: none; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <h1>Simulasi Cetak Rekening Dot Matrix</h1>
        <p>Menampilkan output yang sama seperti hasil dari cetak_rekening_5000_checkbox.php, lalu dicetak ke printer dot matrix lokal PC Windows.</p>
    </div>

    <div class="grid">
        <div class="panel">
            <span class="badge">Data Rekening</span>
            <div class="info">
                <div class="info-row"><label>ID Pelanggan</label><strong><?= htmlspecialchars($idpel) ?></strong></div>
                <div class="info-row"><label>Nama</label><strong><?= htmlspecialchars($nama) ?></strong></div>
                <div class="info-row"><label>Alamat</label><strong><?= htmlspecialchars($alamat) ?></strong></div>
                <div class="info-row"><label>Bulan</label><strong><?= htmlspecialchars($bulan) ?></strong></div>
                <div class="info-row"><label>Stand Awal</label><strong><?= number_format($standLalu, 2) ?> m³</strong></div>
                <div class="info-row"><label>Stand Sekarang</label><strong><?= number_format($standSkrg, 2) ?> m³</strong></div>
                <div class="info-row"><label>Pemakaian</label><strong><?= number_format($pemakaian, 2) ?> m³</strong></div>
                <div class="info-row"><label>Tarif / m³</label><strong>Rp <?= number_format($tarif, 0, ',', '.') ?></strong></div>
                <div class="info-row"><label>Biaya Admin</label><strong>Rp <?= number_format($biayaAdmin, 0, ',', '.') ?></strong></div>
                <div class="info-row"><label>Total</label><strong>Rp <?= number_format($tagihan, 0, ',', '.') ?></strong></div>
            </div>

            <div class="actions">
                <button type="button" class="btn" onclick="window.print()">Cetak ke printer lokal</button>
                <button type="button" class="btn secondary" onclick="location.reload()">Reset</button>
            </div>

            <div class="note">
                Pilih <strong>printer dot matrix / printer lokal Windows</strong> di dialog print. Ini adalah simulasi tanpa ke PDF. Browser Windows akan mengirim data langsung ke printer yang dipilih, bukan ke server Linux.
            </div>
        </div>

        <div class="panel preview-panel">
            <h2>Preview hasil cetak dot matrix</h2>
            <div class="dot-preview">
                <div class="receipt" aria-label="Struk dot matrix preview"><?= nl2br(htmlspecialchars($fullDotMatrix)) ?></div>
            </div>
            <div style="margin-top:16px;">
                <h3 style="margin:0 0 8px;font-size:16px;">Raw output (mirip hasil file cetak)</h3>
                <pre class="raw-output"><?= htmlspecialchars($fullDotMatrix) ?></pre>
            </div>
        </div>
    </div>
</div>

<script>
    const params = new URLSearchParams(window.location.search);
    if (params.get('auto') === '1') {
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 500);
        });
    }
</script>
</body>
</html>
