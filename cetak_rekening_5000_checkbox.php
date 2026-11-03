<?php
include '../config.php';
//session_start();

//if (!isset($_SESSION['user'])) {
//    echo "Silakan login terlebih dahulu.";
//    exit;
//}

//$user_id = $_SESSION['user'];

$stmt = $conn->prepare("SELECT computer_name, printer_name FROM users ");
//$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($computer_name, $printer_name);
$stmt->fetch();
$stmt->close();

if (!$computer_name || !$printer_name) {
    echo '<div style="padding:40px;text-align:center;">
        <h3>Pengaturan Printer Belum Lengkap</h3>
        <a href="setting_printer.php">Setting Printer</a>
    </div>';
    exit;
}

$petugas_result = $conn->query("SELECT DISTINCT petugas FROM pamsimas_sadeyan ORDER BY petugas");

$success_message = '';
$error_message   = '';
$preview_data    = null;
$list_data       = [];
$mode            = $_GET['mode'] ?? '';

// Ambil flash message dari session (hasil redirect setelah POST cetak)
if (!empty($_SESSION['flash_ok'])) {
    $success_message = $_SESSION['flash_ok'];
    unset($_SESSION['flash_ok']);
}
if (!empty($_SESSION['flash_err'])) {
    $error_message = $_SESSION['flash_err'];
    unset($_SESSION['flash_err']);
}

// ─── MODE: Tampilkan daftar berdasarkan Petugas ───────────────────────────────
if ($mode === 'tampil_petugas' && !empty($_GET['petugas'])) {
    $petugas_terpilih = $conn->real_escape_string($_GET['petugas']);
    $res = $conn->query("SELECT * FROM pamsimas_sadeyan WHERE petugas='$petugas_terpilih' ORDER BY idpel");
    while ($row = $res->fetch_assoc()) {
        $list_data[] = $row;
    }
    if (empty($list_data)) {
        $error_message = "Tidak ada data untuk petugas: <strong>" . htmlspecialchars($_GET['petugas']) . "</strong>";
    }
}

// ─── MODE: Tampilkan daftar berdasarkan IDPEL ────────────────────────────────
if ($mode === 'tampil_idpel' && !empty($_GET['idpel'])) {
    $idpel = $conn->real_escape_string($_GET['idpel']);
    $res   = $conn->query("SELECT * FROM pamsimas_sadeyan WHERE idpel='$idpel' LIMIT 1");
    while ($row = $res->fetch_assoc()) {
        $list_data[] = $row;
    }
    if (empty($list_data)) {
        $error_message = "Data tidak ditemukan untuk IDPEL: <strong>" . htmlspecialchars($_GET['idpel']) . "</strong>";
    }
}

// ─── MODE: Preview data IDPEL ────────────────────────────────────────────────
if ($mode === 'lihat' && !empty($_GET['idpel'])) {
    $idpel  = $conn->real_escape_string($_GET['idpel']);
    $res    = $conn->query("SELECT * FROM pamsimas_sadeyan WHERE idpel='$idpel' LIMIT 1");
    if ($res->num_rows > 0) {
        $preview_data = $res->fetch_assoc();
    } else {
        $error_message = "Data tidak ditemukan untuk IDPEL: <strong>" . htmlspecialchars($_GET['idpel']) . "</strong>";
    }
}

// ─── Fungsi helper ───────────────────────────────────────────────────────────
function parseTanggal($tanggal) {
    if (empty($tanggal)) return [date('d'), date('m'), date('Y')];
    if (strpos($tanggal, '/') !== false) {
        $b = explode("/", $tanggal);
        if (count($b) >= 3) return [$b[0], $b[1], $b[2]];
    }
    if (strpos($tanggal, '-') !== false && strlen($tanggal) == 10) {
        $b = explode("-", $tanggal);
        if (count($b) == 3) return [$b[2], $b[1], $b[0]];
    }
    if (strpos($tanggal, '-') !== false) {
        $b = explode("-", $tanggal);
        if (count($b) >= 3) return [$b[0], $b[1], $b[2]];
    }
    return [date('d'), date('m'), date('Y')];
}

function wrapLunas($label, $value, $max = 35) {
    return substr($label . $value, 0, $max);
}

function buatStruk($data, $bold_on, $bold_off) {
    $tanggal    = $data['tanggal'];
    $idpel      = $data['idpel'];
    $nama       = $data['nama'];
    $tarif_daya = $data['pemakaian'];
    $admin_bank = $data['beban'];
    $no_ref     = $data['no_ref'] ?? '';
    $petugas    = $data['petugas'] ?? 'PETUGAS';

    $tagihan_baru = (int)str_replace('.', '', $data['rp_non_beban']) ;
    $tagihan      = number_format($tagihan_baru, 0, ',', '.');

    $total_baru = (int)str_replace('.', '', $data['rptag']) ;
    $total      = number_format($total_baru, 0, ',', '.');

    list($hari, $bulan, $tahun) = parseTanggal($tanggal);
    $tahun = substr($tahun, 0, 4);
    $nama_bulan = [
        '01'=>'JAN','02'=>'FEB','03'=>'MAR','04'=>'APR','05'=>'MEI','06'=>'JUN',
        '07'=>'JUL','08'=>'AGU','09'=>'SEP','10'=>'OKT','11'=>'NOV','12'=>'DES'
    ];
    $bulan_str = $nama_bulan[str_pad($bulan, 2, '0', STR_PAD_LEFT)] ?? $bulan;

    $lunas  = "\n" . $bold_on . "SEGI LUNAS\n" . $bold_off;
    $lunas .= "\n" . wrapLunas("IDPEL     :", $idpel);
    $lunas .= "\n" . wrapLunas("NAMA      :", $nama);
   // $lunas .= "\n" . wrapLunas("Tarif/Daya:", $tarif_daya);
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
    //$utama .= "TARIF      : $tarif_daya\n";
    $utama .= "RP TAG PAMSIMAS : Rp. $tagihan\n";
    $utama .= "REF        : $no_ref\n\n";
    $utama .= str_pad("   PAMSIMAS menyatakan struk ini sebagai bukti pembayaran yang sah.", 35, " ", STR_PAD_BOTH) . "\n";
    $utama .= str_pad("BEBAN : Rp. $admin_bank\n", 34, " ", STR_PAD_LEFT);
    $utama .= str_pad("TOTAL BAYAR: Rp. $total\n", 35, " ", STR_PAD_LEFT);
    $utama .= str_pad("Terima Kasih", 45, " ", STR_PAD_BOTH) . "\n";
  //  $utama .= str_pad("Rincian Tagihan Dapat Diakses di www.pamsimas.co.id", 25, " ", STR_PAD_LEFT) . "\n\n\n";

    $ll = explode("\n", $lunas);
    $lu = explode("\n", $utama);
    $mx = max(count($ll), count($lu));
    $out = "";
    for ($i = 0; $i < $mx; $i++) {
        $out .= str_pad($ll[$i] ?? "", 35) . ($lu[$i] ?? "") . "\n";
    }
    return $out;
}

function cetakKePrinter($rows, $computer_name, $printer_name) {
    $esc    = chr(27);
    $init   = $esc . chr(64);
    $pitch  = $esc . "M";
    $lspace = $esc . "0";
    $plen   = $esc . "C" . chr(44);
    $bon    = $esc . chr(69);
    $boff   = $esc . chr(70);

    $output  = $init . $pitch . $lspace . $plen;
    $counter = 0;
    foreach ($rows as $row) {
        $output .= buatStruk($row, $bon, $boff);
        $counter++;
        if ($counter % 4 == 0) $output .= chr(12);
    }
    $output .= chr(13) . chr(10);

    $file = tempnam(sys_get_temp_dir(), 'ctk');
    file_put_contents($file, $output);
    $path = "//" . $computer_name . "/" . $printer_name;
    $ok   = @copy($file, $path);
    unlink($file);
    return [$ok, $counter, $path];
}

// ─── MODE: Cetak dari POST (checkbox terpilih) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cetak_terpilih'])) {
    $ids_safe = array_map(fn($id) => "'" . $conn->real_escape_string($id) . "'", $_POST['cetak_terpilih']);
    $ids_in   = implode(',', $ids_safe);
    $res      = $conn->query("SELECT * FROM pamsimas_sadeyan WHERE idpel IN ($ids_in) ORDER BY petugas, idpel");
    $rows     = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;

    $mode_asal    = $_POST['mode_asal']    ?? '';
    $petugas_asal = $_POST['petugas_asal'] ?? '';
    $idpel_asal   = $_POST['idpel_asal']   ?? '';

    if (!empty($rows)) {
        list($ok, $counter, $path) = cetakKePrinter($rows, $computer_name, $printer_name);

        // Simpan flash message ke session, lalu redirect (Post-Redirect-Get)
        if ($ok) {
            $_SESSION['flash_ok']  = "Berhasil mencetak <strong>$counter struk</strong> ke: " . htmlspecialchars($path);
        } else {
            $err = error_get_last();
            $_SESSION['flash_err'] = "Gagal cetak ke <strong>" . htmlspecialchars($path) . "</strong>";
            if ($err) $_SESSION['flash_err'] .= "<br>Detail: " . htmlspecialchars($err['message']);
        }
    } else {
        $_SESSION['flash_err'] = "Data tidak ditemukan untuk IDPEL yang dipilih.";
    }

    // Redirect kembali ke tampilan tabel (PRG pattern)
    if ($mode_asal === 'tampil_petugas' && $petugas_asal !== '') {
        header("Location: ?mode=tampil_petugas&petugas=" . urlencode($petugas_asal));
    } elseif ($mode_asal === 'tampil_idpel' && $idpel_asal !== '') {
        header("Location: ?mode=tampil_idpel&idpel=" . urlencode($idpel_asal));
    } else {
        header("Location: ?");
    }
    exit;
}

// ─── MODE: Cetak langsung IDPEL tunggal ──────────────────────────────────────
if ($mode === 'cetak_idpel' && !empty($_GET['idpel'])) {
    $idpel = $conn->real_escape_string($_GET['idpel']);
    $res   = $conn->query("SELECT * FROM pamsimas_sadeyan WHERE idpel='$idpel' LIMIT 1");
    $rows  = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;

    if (!empty($rows)) {
        list($ok, $counter, $path) = cetakKePrinter($rows, $computer_name, $printer_name);
        if ($ok) {
            $success_message = "Struk dicetak (IDPEL: " . htmlspecialchars($_GET['idpel']) . ")";
        } else {
            $err = error_get_last();
            $error_message = "Gagal cetak ke <strong>" . htmlspecialchars($path) . "</strong>";
            if ($err) $error_message .= "<br>" . htmlspecialchars($err['message']);
        }
    } else {
        $error_message = "Data tidak ditemukan.";
    }
}

$conn->close();

function nominalCetak($val) {
    return number_format((int)str_replace('.', '', $val) + 5000, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rekening Listrik</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);min-height:100vh;padding:20px}
        .container{max-width:1200px;margin:0 auto}
        .header{text-align:center;color:white;margin-bottom:28px}
        .header h1{font-size:2.1rem;text-shadow:2px 2px 4px rgba(0,0,0,.3);margin-bottom:5px}
        .header p{opacity:.9;font-size:1rem}

        .printer-bar{background:rgba(255,255,255,.18);backdrop-filter:blur(8px);padding:13px 20px;border-radius:12px;color:white;margin-bottom:18px;display:flex;align-items:center;gap:12px;font-size:.95rem}
        .printer-bar i{font-size:1.3rem}

        .info-badge{background:rgba(255,193,7,.25);color:#f57c00;padding:9px 16px;border-radius:9px;border-left:4px solid #f57c00;font-size:14px;font-weight:600;margin-bottom:18px}

        .msg{padding:13px 17px;border-radius:9px;margin-bottom:16px;font-weight:600;font-size:.95rem;line-height:1.6}
        .msg.ok{background:rgba(72,187,120,.2);color:#276749;border:2px solid rgba(72,187,120,.5)}
        .msg.err{background:rgba(245,101,101,.15);color:#c53030;border:2px solid rgba(245,101,101,.4)}

        .grid{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px}
        @media(max-width:768px){.grid{grid-template-columns:1fr}}

        .card{background:rgba(255,255,255,.97);border-radius:16px;padding:24px;box-shadow:0 14px 35px rgba(0,0,0,.1)}
        .card h3{font-size:1.15rem;color:#2d3748;margin-bottom:16px;display:flex;align-items:center;gap:7px}
        .card h3 i{color:#4facfe}

        label{display:block;font-weight:600;color:#2d3748;margin-bottom:6px;font-size:.9rem}
        select,input[type=text]{width:100%;padding:10px 13px;border:2px solid #e2e8f0;border-radius:8px;font-size:15px;background:#f8fafc;transition:border-color .2s;color:#2d3748}
        select:focus,input[type=text]:focus{outline:none;border-color:#4facfe;background:#fff}
        .fgroup{margin-bottom:16px}

        .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;color:white;text-decoration:none;white-space:nowrap}
        .btn:hover{transform:translateY(-2px);box-shadow:0 7px 18px rgba(0,0,0,.18)}
        .btn-blue{background:linear-gradient(45deg,#4facfe,#00f2fe)}
        .btn-purple{background:linear-gradient(45deg,#667eea,#764ba2)}
        .btn-green{background:linear-gradient(45deg,#56ab2f,#a8e6cf)}
        .btn-red{background:linear-gradient(45deg,#e53e3e,#fc8181)}
        .btn-orange{background:linear-gradient(45deg,#f6ad55,#f6d365);color:#7b4f00}
        .btn-sm{padding:7px 12px;font-size:13px}
        .btn:disabled{opacity:.45;cursor:not-allowed;transform:none !important;box-shadow:none !important}
        .brow{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px}

        .divider{border:none;border-top:2px dashed #e2e8f0;margin:18px 0}

        /* Tabel */
        .tbl-wrap{background:rgba(255,255,255,.97);border-radius:16px;padding:22px;box-shadow:0 14px 35px rgba(0,0,0,.1);margin-bottom:22px}
        .tbl-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px}
        .tbl-head h3{font-size:1.15rem;color:#2d3748;display:flex;align-items:center;gap:7px}
        .tbl-head h3 i{color:#4facfe}
        .badge{background:linear-gradient(45deg,#4facfe,#00f2fe);color:#fff;border-radius:20px;padding:2px 11px;font-size:12px;font-weight:700;margin-left:6px}

        .scroll-x{overflow-x:auto;max-height:450px;overflow-y:auto;border-radius:9px;border:1px solid #e2e8f0}
        table{width:100%;border-collapse:collapse;font-size:14px;min-width:680px}
        thead th{background:linear-gradient(135deg,#4facfe,#00f2fe);color:#fff;padding:10px 10px;text-align:left;font-weight:600;position:sticky;top:0;z-index:2}
        thead th:first-child{text-align:center;width:40px}
        tbody tr{border-bottom:1px solid #e8f0fe;transition:background .12s}
        tbody tr:hover{background:#f0f9ff}
        tbody tr.aktif{background:#dbeafe;border-left:3px solid #3b82f6}
        tbody td{padding:9px 10px;color:#2d3748;vertical-align:middle}
        tbody td:first-child{text-align:center}
        .nom{color:#f57c00;font-weight:700}

        input[type=checkbox]{width:17px;height:17px;cursor:pointer;accent-color:#4facfe}

        .counter-bar{margin-top:11px;padding:10px 15px;background:linear-gradient(135deg,#fff3cd,#ffeaa7);border-radius:8px;font-size:14px;color:#856404;font-weight:600;display:flex;align-items:center;gap:7px}

        .nav-links{display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:22px}
        .nav-link{background:rgba(255,255,255,.2);color:white;padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:600;display:flex;align-items:center;gap:7px;transition:all .2s}
        .nav-link:hover{background:rgba(255,255,255,.32);transform:translateY(-2px)}
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h1><i class="fas fa-receipt"></i> Cetak Rekening PAMSIMAS</h1>
        <p>Sistem Cetak Struk Pembayaran Tagihan PAMSIMAS</p>
    </div>

    <div class="printer-bar">
        <i class="fas fa-print"></i>
        <span><strong>Komputer:</strong> <?= htmlspecialchars($computer_name) ?> &nbsp;|&nbsp;
              <strong>Printer:</strong> <?= htmlspecialchars($printer_name) ?></span>
    </div>

    <div class="info-badge"><i class="fas fa-info-circle"></i> cetak ++</div>

    <?php if ($success_message): ?>
        <div class="msg ok"><i class="fas fa-check-circle"></i> <?= $success_message ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="msg err"><i class="fas fa-exclamation-circle"></i> <?= $error_message ?></div>
    <?php endif; ?>

    <div class="grid">

        <!-- ══ Migrasi ══ -->
        <div class="card">
            <h3><i class="fas fa-database"></i> Migrasi Data ke Rekening</h3>
            <form method="POST" action="migrate_exe20.php">
                <button type="submit" class="btn btn-blue" style="width:100%;margin-bottom:10px">
                    <i class="fas fa-download"></i> Masukan Data Exe 20
                </button>
            </form>
            <p style="color:#718096;font-size:13px;margin-bottom:11px">
                Mengosongkan tabel <strong>rekening</strong> lalu mengisi dari sumber. Field nominal otomatis diformat bertitik.
            </p>
            <form method="POST" action="migrate_cetak_akhir.php">
                <button type="submit" class="btn btn-blue" style="width:100%;margin-bottom:10px">
                    <i class="fas fa-download"></i> Masukan Data Cetak Akhir
                </button>
            </form>
            <form method="POST" action="migrate_griya_bayar.php">
                <button type="submit" class="btn btn-blue" style="width:100%;margin-bottom:10px">
                    <i class="fas fa-download"></i> Masukan Data Griya Bayar
                </button>
            </form>
            <form method="POST" action="migrate_teleanjar.php">
                <button type="submit" class="btn btn-blue" style="width:100%">
                    <i class="fas fa-download"></i> Masukan Data Teleanjar
                </button>
            </form>
        </div>

        <!-- ══ Form Cetak ══ -->
        <div class="card">

            <!-- Form Petugas — pakai GET biasa -->
            <h3><i class="fas fa-user-tie"></i> Tampilkan Data Petugas</h3>
            <form method="GET" action="">
                <input type="hidden" name="mode" value="tampil_petugas">
                <div class="fgroup">
                    <label>Pilih Petugas:</label>
                    <select name="petugas" required>
                        <option value="">-- Pilih Petugas --</option>
                        <?php
                        $petugas_result->data_seek(0);
                        while ($pr = $petugas_result->fetch_assoc()):
                            $sel = (isset($_GET['petugas']) && $_GET['petugas'] === $pr['petugas']) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($pr['petugas']) ?>" <?= $sel ?>>
                                <?= htmlspecialchars($pr['petugas']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="brow">
                    <button type="submit" class="btn btn-purple">
                        <i class="fas fa-list"></i> Tampilkan Daftar
                    </button>
                </div>
            </form>

            <hr class="divider">

            <!-- Form IDPEL — pakai JS redirect agar tidak bentrok -->
            <h3><i class="fas fa-id-card"></i> Cetak Berdasarkan IDPEL</h3>
            <div class="fgroup">
                <label>Masukkan IDPEL (12 digit):</label>
                <input type="text" id="inpIdpel"
                       placeholder="Contoh: 5205xxxxxxxx"
                       maxlength="12"
                       oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,12)">
            </div>
            <div class="brow">
                <button type="button" class="btn btn-purple" onclick="goIdpel('lihat')">
                    <i class="fas fa-eye"></i> Lihat Data
                </button>
                <button type="button" class="btn btn-blue" onclick="goIdpel('tampil_idpel')">
                    <i class="fas fa-list"></i> Tampilkan
                </button>
                <button type="button" class="btn btn-green" onclick="goIdpel('cetak_idpel')">
                    <i class="fas fa-print"></i> Cetak Langsung
                </button>
            </div>
        </div>

    </div><!-- /grid -->

    <!-- ══ Preview IDPEL ══ -->
    <?php if ($preview_data): ?>
    <div class="tbl-wrap">
        <div class="tbl-head">
            <h3><i class="fas fa-info-circle"></i> Detail Data IDPEL</h3>
        </div>
        <table>
            <thead><tr><th style="width:170px;text-align:left">Field</th><th>Nilai</th></tr></thead>
            <tbody>
                <tr><td><strong>IDPEL</strong></td><td><?= htmlspecialchars($preview_data['idpel']) ?></td></tr>
                <tr><td><strong>Nama</strong></td><td><?= htmlspecialchars($preview_data['nama']) ?></td></tr>
                <tr><td><strong>Alamat</strong></td><td><?= htmlspecialchars($preview_data['alamat'] ?? '-') ?></td></tr>
                <tr><td><strong>Tarif/Daya</strong></td><td><?= htmlspecialchars($preview_data['beban']) ?></td></tr>
                <tr><td><strong>Total Asli</strong></td><td>Rp. <?= htmlspecialchars($preview_data['rptag']) ?></td></tr>
                <tr><td><strong>Total Cetak</strong></td><td class="nom">Rp. <?= nominalCetak($preview_data['total']) ?></td></tr>
                <tr><td><strong>Petugas</strong></td><td><?= htmlspecialchars($preview_data['petugas']) ?></td></tr>
                <tr><td><strong>Tanggal</strong></td><td><?= htmlspecialchars($preview_data['tanggal']) ?></td></tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ══ Tabel Daftar + Checkbox ══ -->
    <?php if (!empty($list_data)): ?>
    <div class="tbl-wrap">
        <div class="tbl-head">
            <h3>
                <i class="fas fa-table"></i>
                Daftar Data Rekening
                <span class="badge"><?= count($list_data) ?> data</span>
            </h3>
            <div class="brow">
                <button type="button" class="btn btn-blue btn-sm" onclick="pilihSemua(true)">
                    <i class="fas fa-check-double"></i> Pilih Semua
                </button>
                <button type="button" class="btn btn-orange btn-sm" onclick="pilihSemua(false)">
                    <i class="fas fa-times"></i> Batal Semua
                </button>
            </div>
        </div>

        <form method="POST" action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?')) ?>" id="formCetak">
            <!-- Kirim info mode, petugas, idpel untuk redirect setelah cetak -->
            <input type="hidden" name="mode_asal" value="<?= htmlspecialchars($mode) ?>">
            <input type="hidden" name="petugas_asal" value="<?= htmlspecialchars($_GET['petugas'] ?? '') ?>">
            <input type="hidden" name="idpel_asal" value="<?= htmlspecialchars($_GET['idpel'] ?? '') ?>">

            <div class="scroll-x">
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="chkAll" onclick="toggleAll(this)" title="Pilih Semua"></th>
                            <th>No</th>
                            <th>IDPEL</th>
                            <th>Nama</th>
                            <th>Tarif/Daya</th>
                            <th>Tagihan (+5rb)</th>
                            <th>Total Bayar (+5rb)</th>
                            <th>Petugas</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($list_data as $i => $row): ?>
                        <tr id="tr<?= $i ?>">
                            <td>
                                <input type="checkbox"
                                       name="cetak_terpilih[]"
                                       value="<?= htmlspecialchars($row['idpel']) ?>"
                                       class="cbx"
                                       onchange="onCbx(this,<?= $i ?>)">
                            </td>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($row['idpel']) ?></strong></td>
                            <td><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['standini']) ?></td>
                            <td>Rp. <?= nominalCetak($row['rp_non_beban']) ?></td>
                            <td class="nom">Rp. <?= nominalCetak($row['rptag']) ?></td>
                            <td><?= htmlspecialchars($row['petugas']) ?></td>
                            <td><?= htmlspecialchars($row['tanggal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="counter-bar">
                <i class="fas fa-info-circle"></i>
                <span id="infoTerpilih">0 data dipilih — centang baris yang ingin dicetak</span>
            </div>

            <div class="brow" style="margin-top:13px">
                <button type="submit" class="btn btn-green" id="btnCetak" disabled>
                    <i class="fas fa-print"></i> Cetak Terpilih (<span id="jmlCetak">0</span>)
                </button>
                <button type="button" class="btn btn-red" onclick="pilihSemua(false)">
                    <i class="fas fa-times-circle"></i> Batal Pilihan
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="nav-links">
        <a href="../index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="setting_printer.php" class="nav-link"><i class="fas fa-cog"></i> Setting Printer</a>
    </div>

</div>

<script>
// ── Navigasi IDPEL ────────────────────────────────────────────────────────
function goIdpel(mode) {
    var v = document.getElementById('inpIdpel').value.trim();
    if (!v) { alert('Masukkan IDPEL terlebih dahulu!'); return; }
    if (v.length !== 12) { alert('IDPEL harus tepat 12 digit! (sekarang: ' + v.length + ' digit)'); return; }
    window.location.href = '?mode=' + mode + '&idpel=' + encodeURIComponent(v);
}

// ── Checkbox ──────────────────────────────────────────────────────────────
function toggleAll(master) {
    document.querySelectorAll('.cbx').forEach(function(cb, i) {
        cb.checked = master.checked;
        warnai(i, master.checked);
    });
    updateCounter();
}

function pilihSemua(flag) {
    document.querySelectorAll('.cbx').forEach(function(cb, i) {
        cb.checked = flag;
        warnai(i, flag);
    });
    var ca = document.getElementById('chkAll');
    if (ca) { ca.checked = flag; ca.indeterminate = false; }
    updateCounter();
}

function onCbx(cb, i) {
    warnai(i, cb.checked);
    updateCounter();
    syncMaster();
}

function warnai(i, aktif) {
    var tr = document.getElementById('tr' + i);
    if (!tr) return;
    if (aktif) tr.classList.add('aktif');
    else        tr.classList.remove('aktif');
}

function syncMaster() {
    var all     = document.querySelectorAll('.cbx').length;
    var checked = document.querySelectorAll('.cbx:checked').length;
    var ca = document.getElementById('chkAll');
    if (!ca) return;
    ca.indeterminate = (checked > 0 && checked < all);
    ca.checked       = (checked === all && all > 0);
}

function updateCounter() {
    var n   = document.querySelectorAll('.cbx:checked').length;
    var tot = document.querySelectorAll('.cbx').length;
    var info = document.getElementById('infoTerpilih');
    var jml  = document.getElementById('jmlCetak');
    var btn  = document.getElementById('btnCetak');
    if (info) info.textContent = n + ' dari ' + tot + ' data dipilih';
    if (jml)  jml.textContent  = n;
    if (btn)  btn.disabled     = (n === 0);
}

// ── Konfirmasi cetak ──────────────────────────────────────────────────────
var fc = document.getElementById('formCetak');
if (fc) {
    fc.addEventListener('submit', function(e) {
        var n = document.querySelectorAll('.cbx:checked').length;
        if (n === 0) { e.preventDefault(); alert('Pilih minimal 1 data!'); return; }
        if (!confirm('Cetak ' + n + ' struk ke printer?\nLanjutkan?')) { e.preventDefault(); }
    });
}

updateCounter();
</script>
</body>
</html>