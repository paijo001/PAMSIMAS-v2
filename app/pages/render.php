<?php
require __DIR__ . '/../core/bootstrap.php';

$userCount = (int) row('SELECT COUNT(*) AS total FROM `user`')['total'];
if ($userCount === 0) {
    run('INSERT INTO `user` (username, password) VALUES (?, ?)', [
        'admin',
        password_hash('admin', PASSWORD_DEFAULT),
    ]);
}

$page = (string) ($_GET['page'] ?? (isset($_SESSION['user_id']) ? 'dashboard' : 'login'));
$allowedPages = ['login', 'dashboard', 'users', 'settings', 'conditions', 'customers', 'readings', 'history', 'printing'];
if (!in_array($page, $allowedPages, true) && !isset($_GET['invoice'], $_GET['photo'])) {
    $page = 'dashboard';
}

if (isset($_GET['photo'])) {
    require_login();
    $photo = row('SELECT foto_meter, foto_mime FROM data_pembacaan WHERE id = ?', [(int) $_GET['photo']]);
    if (!$photo || $photo['foto_meter'] === null) {
        http_response_code(404);
        exit('Foto tidak ditemukan.');
    }
    $imageBlob = normalize_photo_blob((string) $photo['foto_meter']);
    $imageMime = $photo['foto_mime'] ?: 'image/jpeg';
    header('Content-Type: ' . $imageMime);
    header('X-Content-Type-Options: nosniff');
    echo $imageBlob;
    exit;
}

if (isset($_GET['invoice'])) {
    require_login();
    $invoice = row(
        'SELECT * FROM data_pembacaan WHERE id = ?',
        [(int) $_GET['invoice']]
    );
    if (!$invoice) {
        http_response_code(404);
        exit('Rekening tidak ditemukan.');
    }
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $settingNow = row('SELECT biaya_admin, rp_per_meter FROM setting WHERE id = 1') ?? ['biaya_admin' => 0, 'rp_per_meter' => 0];
    $effectiveRate = (float) (($invoice['rp_per_meter'] ?? 0) > 0 ? $invoice['rp_per_meter'] : ($settingNow['rp_per_meter'] ?? 0));
    $effectiveAdmin = (float) (($invoice['biaya_admin'] ?? 0) > 0 ? $invoice['biaya_admin'] : ($settingNow['biaya_admin'] ?? 0));
    $effectiveUsage = max(0, (float) ($invoice['standini'] ?? 0) - (float) ($invoice['standll'] ?? 0));
    $effectiveBill = $effectiveUsage * $effectiveRate + $effectiveAdmin;
    $displayBill = (float) ($invoice['tagihan'] ?? 0) > 0 ? (float) $invoice['tagihan'] : $effectiveBill;
    $waMessage = "Halo Bapak/Ibu " . $invoice['nama'] . ",\n" .
        "Rekening air PAMSIMAS\n" .
        "Bulan: " . ($monthNames[(int) $invoice['bulan']] ?? 'Bulan') . ' ' . $invoice['tahun'] . "\n" .
        "ID Pelanggan: " . $invoice['idpelanggan'] . "\n" .
        "Stand sebelumnya: " . ($invoice['standll'] ?? 0) . " m3\n" .
        "Stand sekarang: " . ($invoice['standini'] ?? 0) . " m3\n" .
        "Pemakaian: " . ($invoice['pemakaian'] ?? $effectiveUsage) . " m3\n" .
        "Tarif: " . money($effectiveRate) . "\n" .
        "Biaya admin: " . money($effectiveAdmin) . "\n" .
        "Total tagihan: " . money($displayBill);
    $waLink = whatsapp_link((string) ($invoice['no_hp'] ?? ''), $waMessage);
    $simPrintUrl = 'simulasi_cetak_rekening.php?'
        . 'idpel=' . rawurlencode((string) $invoice['idpelanggan'])
        . '&nama=' . rawurlencode((string) $invoice['nama'])
        . '&alamat=' . rawurlencode((string) $invoice['alamat'])
        . '&bulan=' . rawurlencode((string) $monthNames[(int) $invoice['bulan']] . ' ' . $invoice['tahun'])
        . '&stand_lalu=' . rawurlencode((string) ($invoice['standll'] ?? 0))
        . '&stand_skrg=' . rawurlencode((string) ($invoice['standini'] ?? 0))
        . '&tarif=' . rawurlencode((string) $effectiveRate)
        . '&biaya_admin=' . rawurlencode((string) $effectiveAdmin)
        . '&ref=' . rawurlencode('REF-' . (string) $invoice['id'])
        . '&petugas=' . rawurlencode('ADMIN')
        . '&printer=' . rawurlencode('Printer Lokal Windows Dot Matrix')
        . '&auto=1';
    $simBluetoothUrl = 'simulasi_cetak_bluetooth.php?'
        . 'idpel=' . rawurlencode((string) $invoice['idpelanggan'])
        . '&nama=' . rawurlencode((string) $invoice['nama'])
        . '&alamat=' . rawurlencode((string) $invoice['alamat'])
        . '&bulan=' . rawurlencode((string) $monthNames[(int) $invoice['bulan']] . ' ' . $invoice['tahun'])
        . '&stand_lalu=' . rawurlencode((string) ($invoice['standll'] ?? 0))
        . '&stand_skrg=' . rawurlencode((string) ($invoice['standini'] ?? 0))
        . '&tarif=' . rawurlencode((string) $effectiveRate)
        . '&biaya_admin=' . rawurlencode((string) $effectiveAdmin)
        . '&ref=' . rawurlencode('REF-' . (string) $invoice['id'])
        . '&petugas=' . rawurlencode('ADMIN')
        . '&auto=1';
    ?>
    <!doctype html>
    <html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekening Air <?= h($invoice['idpelanggan']) ?></title><link rel="stylesheet" href="assets/style.css"></head>
    <body class="invoice-page"><main class="invoice">
        <div class="invoice-brand"><strong>PAMSIMAS</strong><span>REKENING PEMAKAIAN AIR</span></div>
        <h1>Tagihan Air</h1>
        <p class="muted"><?= h($monthNames[(int) $invoice['bulan']] . ' ' . $invoice['tahun']) ?></p>
        <hr>
        <dl>
            <dt>ID pelanggan</dt><dd><?= h($invoice['idpelanggan']) ?></dd>
            <dt>Nama</dt><dd><?= h($invoice['nama']) ?></dd>
            <dt>Alamat</dt><dd><?= h($invoice['alamat']) ?></dd>
            <dt>Stand sebelumnya</dt><dd><?= h($invoice['standll']) ?> m³</dd>
            <dt>Stand sekarang</dt><dd><?= h($invoice['standini']) ?> m³</dd>
            <dt>Pemakaian</dt><dd><?= h($invoice['pemakaian']) ?> m³</dd>
            <dt>Tarif per m³</dt><dd><?= money($effectiveRate) ?></dd>
            <dt>Biaya admin</dt><dd><?= money($effectiveAdmin) ?></dd>
        </dl>
        <div class="invoice-total"><span>Total tagihan</span><strong><?= money($displayBill) ?></strong></div>
        <p class="muted invoice-note">Simpan bukti pembayaran ini. Terima kasih.</p>
        <div class="actions no-print" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:stretch;">
            <a class="button secondary" href="javascript:history.back()">Kembali ke menu sebelumnya</a>
            <button class="button" onclick="window.print()">Cetak / Print</button>
            <a class="button secondary" href="<?= h($simPrintUrl) ?>" target="_blank" rel="noopener">Cetak Dot Matrix</a>
            <a class="button secondary" href="<?= h($simBluetoothUrl) ?>" target="_blank" rel="noopener">Cetak Bluetooth</a>
            <?php if ($waLink !== '#'): ?>
                <a class="button success" href="<?= h($waLink) ?>" target="_blank" rel="noopener">Kirim WhatsApp</a>
            <?php endif; ?>
        </div>
    </main></body></html>
    <?php
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman dan coba kembali.');
        }
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'login') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $account = row('SELECT id, username, password FROM `user` WHERE username = ?', [$username]);
            if (!$account || !password_verify((string) ($_POST['password'] ?? ''), $account['password'])) {
                throw new InvalidArgumentException('Username atau password salah.');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            $_SESSION['username'] = $account['username'];
            flash('Selamat datang, ' . $account['username'] . '.');
            redirect('?page=dashboard');
        }

        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            redirect('?page=login');
        }

        require_login();

        if ($action === 'save_user') {
            $id = (int) ($_POST['id'] ?? 0);
            $username = post_string('username', 'Username', 80);
            $password = (string) ($_POST['password'] ?? '');
            if ($id === 0 && strlen($password) < 6) {
                throw new InvalidArgumentException('Password user baru minimal 6 karakter.');
            }
            if ($password !== '' && strlen($password) < 6) {
                throw new InvalidArgumentException('Password minimal 6 karakter.');
            }
            $duplicate = row('SELECT id FROM `user` WHERE username = ? AND id <> ?', [$username, $id]);
            if ($duplicate) {
                throw new InvalidArgumentException('Username sudah digunakan.');
            }
            if ($id > 0) {
                if ($password !== '') {
                    run('UPDATE `user` SET username = ?, password = ? WHERE id = ?', [
                        $username, password_hash($password, PASSWORD_DEFAULT), $id,
                    ]);
                } else {
                    run('UPDATE `user` SET username = ? WHERE id = ?', [$username, $id]);
                }
                if ($id === (int) $_SESSION['user_id']) {
                    $_SESSION['username'] = $username;
                }
            } else {
                run('INSERT INTO `user` (username, password) VALUES (?, ?)', [
                    $username, password_hash($password, PASSWORD_DEFAULT),
                ]);
            }
            flash('Data user berhasil disimpan.');
            redirect('?page=users');
        }

        if ($action === 'delete_user') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $_SESSION['user_id']) {
                throw new InvalidArgumentException('Akun yang sedang digunakan tidak dapat dihapus.');
            }
            if ((int) row('SELECT COUNT(*) AS total FROM `user`')['total'] <= 1) {
                throw new InvalidArgumentException('User terakhir tidak dapat dihapus.');
            }
            run('DELETE FROM `user` WHERE id = ?', [$id]);
            flash('User berhasil dihapus.');
            redirect('?page=users');
        }

        if ($action === 'save_setting') {
            run('UPDATE setting SET biaya_admin = ?, rp_per_meter = ? WHERE id = 1', [
                post_amount('biaya_admin', 'Biaya admin'),
                post_amount('rp_per_meter', 'Tarif per meter'),
            ]);
            flash('Pengaturan tarif berhasil disimpan.');
            redirect('?page=settings');
        }

        if ($action === 'save_condition') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_string('nama', 'Nama kondisi meter', 60);
            $duplicate = row('SELECT id FROM kondisi_meter WHERE nama = ? AND id <> ?', [$name, $id]);
            if ($duplicate) {
                throw new InvalidArgumentException('Nama kondisi meter sudah ada.');
            }
            if ($id > 0) {
                run('UPDATE kondisi_meter SET nama = ? WHERE id = ?', [$name, $id]);
            } else {
                run('INSERT INTO kondisi_meter (nama) VALUES (?)', [$name]);
            }
            flash('Kondisi meter berhasil disimpan.');
            redirect('?page=conditions');
        }

        if ($action === 'delete_condition') {
            $id = (int) ($_POST['id'] ?? 0);
            if ((int) row('SELECT COUNT(*) AS total FROM kondisi_meter')['total'] <= 1) {
                throw new InvalidArgumentException('Kondisi meter terakhir tidak dapat dihapus.');
            }
            if (row('SELECT id FROM data_pembacaan WHERE kondisi_meter_id = ? LIMIT 1', [$id])) {
                throw new InvalidArgumentException('Kondisi yang sudah dipakai pada pembacaan tidak dapat dihapus.');
            }
            run('DELETE FROM kondisi_meter WHERE id = ?', [$id]);
            flash('Kondisi meter berhasil dihapus.');
            redirect('?page=conditions');
        }

        if ($action === 'save_customer') {
            $id = post_string('idpelanggan', 'ID pelanggan', 40);
            $name = post_string('nama', 'Nama pelanggan', 150);
            $address = post_string('alamat', 'Alamat', 2000);
            $phone = trim((string) ($_POST['no_hp'] ?? ''));
            $location = trim((string) ($_POST['kode_lokasi'] ?? ''));
            $readingCode = post_string('kode_baca', 'Kode baca', 60);
            $order = filter_var($_POST['urut_baca'] ?? 0, FILTER_VALIDATE_INT);
            if ($order === false) {
                throw new InvalidArgumentException('Urutan baca harus berupa bilangan bulat.');
            }
            $originalId = (string) ($_POST['original_id'] ?? '');
            if ($originalId !== '' && $originalId !== $id) {
                throw new InvalidArgumentException('ID pelanggan tidak dapat diubah setelah dibuat.');
            }
            $duplicate = row('SELECT idpelanggan FROM data_pelanggan WHERE idpelanggan = ?', [$id]);
            if ($originalId === '' && $duplicate) {
                throw new InvalidArgumentException('ID pelanggan sudah digunakan.');
            }
            if ($originalId !== '') {
                run(
                    'UPDATE data_pelanggan SET nama = ?, alamat = ?, no_hp = ?, kode_lokasi = ?, kode_baca = ?, urut_baca = ? WHERE idpelanggan = ?',
                    [$name, $address, $phone, $location, $readingCode, $order, $originalId]
                );
            } else {
                run(
                    'INSERT INTO data_pelanggan (idpelanggan, nama, alamat, no_hp, kode_lokasi, kode_baca, urut_baca) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$id, $name, $address, $phone, $location, $readingCode, $order]
                );
            }
            flash('Data pelanggan berhasil disimpan.');
            redirect('?page=customers');
        }

        if ($action === 'delete_customer') {
            $id = (string) ($_POST['idpelanggan'] ?? '');
            if (row('SELECT id FROM data_pembacaan WHERE idpelanggan = ? LIMIT 1', [$id])
                || row('SELECT id FROM historis WHERE idpelanggan = ? LIMIT 1', [$id])) {
                throw new InvalidArgumentException('Pelanggan yang sudah memiliki riwayat pembacaan tidak dapat dihapus.');
            }
            run('DELETE FROM data_pelanggan WHERE idpelanggan = ?', [$id]);
            flash('Data pelanggan berhasil dihapus.');
            redirect('?page=customers');
        }

        if ($action === 'save_reading') {
            $customerId = post_string('idpelanggan', 'ID pelanggan', 40);
            $customer = row('SELECT * FROM data_pelanggan WHERE idpelanggan = ?', [$customerId]);
            if (!$customer) {
                throw new InvalidArgumentException('Data pelanggan tidak ditemukan.');
            }
            $reading = post_amount('standini', 'Stand sekarang');
            $conditionId = (int) ($_POST['kondisi_meter_id'] ?? 0);
            if ($conditionId <= 0) {
                $defaultCondition = row('SELECT id FROM kondisi_meter WHERE LOWER(nama) = ? LIMIT 1', ['normal']);
                $conditionId = (int) ($defaultCondition['id'] ?? 1);
            }
            if (!row('SELECT id FROM kondisi_meter WHERE id = ?', [$conditionId])) {
                throw new InvalidArgumentException('Pilih kondisi meter yang tersedia.');
            }
            $year = (int) date('Y');
            $month = (int) date('n');
            $previous = row(
                'SELECT standini, standinill FROM data_pembacaan
                 WHERE idpelanggan = ? AND (tahun < ? OR (tahun = ? AND bulan < ?))
                 ORDER BY tahun DESC, bulan DESC LIMIT 1',
                [$customerId, $year, $year, $month]
            );
            $lastStand = $previous ? (float) $previous['standini'] : 0.0;
            if ($reading < $lastStand) {
                throw new InvalidArgumentException('Stand sekarang tidak boleh lebih kecil dari stand sebelumnya (' . $lastStand . ').');
            }
            $oldReading = row(
                'SELECT id, foto_meter, foto_mime FROM data_pembacaan WHERE idpelanggan = ? AND tahun = ? AND bulan = ?',
                [$customerId, $year, $month]
            );
            $photo = uploaded_photo();
            $settings = row('SELECT biaya_admin, rp_per_meter FROM setting WHERE id = 1');
            $adminFee = (float) ($settings['biaya_admin'] ?? 0);
            $rate = (float) ($settings['rp_per_meter'] ?? 0);
            $usage = $reading - $lastStand;
            $bill = $usage * $rate + $adminFee;
            $initialStand = $previous ? (float) $previous['standinill'] : $lastStand;
            if ($oldReading && !$photo) {
                $photo = [
                    'data' => $oldReading['foto_meter'],
                    'mime' => $oldReading['foto_mime'],
                ];
            }
            $conn->beginTransaction();
            $statement = $conn->prepare(
                'INSERT INTO data_pembacaan
                    (idpelanggan, nama, alamat, no_hp, kode_lokasi, kode_baca, urut_baca,
                     standll, standinill, standini, pemakaian, kondisi_meter_id, foto_meter, foto_mime,
                     tahun, bulan, biaya_admin, rp_per_meter, tagihan)
                 VALUES
                    (:idpelanggan, :nama, :alamat, :no_hp, :kode_lokasi, :kode_baca, :urut_baca,
                     :standll, :standinill, :standini, :pemakaian, :kondisi, :foto, :mime,
                     :tahun, :bulan, :admin, :tarif, :tagihan)
                 ON DUPLICATE KEY UPDATE
                    nama = VALUES(nama), alamat = VALUES(alamat), no_hp = VALUES(no_hp),
                    kode_lokasi = VALUES(kode_lokasi), kode_baca = VALUES(kode_baca),
                    urut_baca = VALUES(urut_baca), standll = VALUES(standll),
                    standinill = VALUES(standinill), standini = VALUES(standini),
                    pemakaian = VALUES(pemakaian), kondisi_meter_id = VALUES(kondisi_meter_id),
                    foto_meter = VALUES(foto_meter), foto_mime = VALUES(foto_mime),
                    biaya_admin = VALUES(biaya_admin), rp_per_meter = VALUES(rp_per_meter),
                    tagihan = VALUES(tagihan)'
            );
            $statement->execute([
                'idpelanggan' => $customerId,
                'nama' => $customer['nama'],
                'alamat' => $customer['alamat'],
                'no_hp' => $customer['no_hp'],
                'kode_lokasi' => $customer['kode_lokasi'],
                'kode_baca' => $customer['kode_baca'],
                'urut_baca' => $customer['urut_baca'],
                'standll' => $lastStand,
                'standinill' => $initialStand,
                'standini' => $reading,
                'pemakaian' => $usage,
                'kondisi' => $conditionId,
                'foto' => $photo['data'] ?? null,
                'mime' => $photo['mime'] ?? null,
                'tahun' => $year,
                'bulan' => $month,
                'admin' => $adminFee,
                'tarif' => $rate,
                'tagihan' => $bill,
            ]);
            $monthColumn = sprintf('stand%02d', $month);
            $historic = row('SELECT id FROM historis WHERE idpelanggan = ? AND tahun = ?', [$customerId, $year]);
            if ($historic) {
                run(
                    "UPDATE historis SET nama = ?, alamat = ?, nohp = ?, kode_lokasi = ?, `$monthColumn` = ? WHERE idpelanggan = ? AND tahun = ?",
                    [$customer['nama'], $customer['alamat'], $customer['no_hp'], $customer['kode_lokasi'], $reading, $customerId, $year]
                );
            } else {
                run(
                    "INSERT INTO historis (idpelanggan, nama, alamat, nohp, kode_lokasi, standll, standini, tahun, `$monthColumn`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$customerId, $customer['nama'], $customer['alamat'], $customer['no_hp'], $customer['kode_lokasi'], $lastStand, $lastStand, $year, $reading]
                );
            }
            $conn->commit();
            flash($oldReading ? 'Pembacaan berhasil diperbarui.' : 'Pembacaan berhasil disimpan.');
            redirect('?page=readings&kode_baca=' . rawurlencode($customer['kode_baca']));
        }

        throw new InvalidArgumentException('Aksi tidak dikenal.');
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}

if ($page !== 'login') {
    require_login();
}

$flashMessage = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$monthLabels = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07877e">
    <title><?= h(APP_NAME) ?><?= $page === 'login' ? ' - Masuk' : ' - ' . h(ucfirst($page)) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if ($page === 'login'): ?>
    <main class="login-wrap">
        <section class="login-card">
            <div class="brand-mark">P</div>
            <p class="eyebrow">PENGELOLAAN AIR BERSIH</p>
            <h1>Masuk PAMSIMAS</h1>
            <p class="muted">Kelola pelanggan, pembacaan meter, dan tagihan bulanan.</p>
            <?php if ($error !== ''): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
            <?php if ($flashMessage): ?><div class="alert <?= h($flashMessage['type']) ?>"><?= h($flashMessage['message']) ?></div><?php endif; ?>
            <form method="post" class="stack">
                <?= csrf_field() ?><input type="hidden" name="action" value="login">
                <label>Username<input name="username" autocomplete="username" required autofocus></label>
                <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
                <button class="button primary full">Masuk</button>
            </form>
            <p class="login-hint">Login awal: <strong>admin</strong> / <strong>admin</strong>. Segera ubah password setelah masuk.</p>
        </section>
    </main>
<?php else: ?>
    <header class="topbar">
        <a class="brand" href="?page=dashboard"><span class="brand-mark small">P</span><span><strong>PAMSIMAS</strong><small>Manajemen Air Bersih</small></span></a>
        <div class="top-user"><span><?= h($_SESSION['username'] ?? '') ?></span>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="link-button">Keluar</button></form>
        </div>
    </header>
    <div class="layout">
        <nav class="sidebar">
            <span class="nav-label">MENU UTAMA</span>
            <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">Ringkasan</a>
            <a class="<?= $page === 'customers' ? 'active' : '' ?>" href="?page=customers">Data pelanggan</a>
            <a class="<?= $page === 'readings' ? 'active' : '' ?>" href="?page=readings">Pembacaan meter</a>
            <a class="<?= $page === 'history' ? 'active' : '' ?>" href="?page=history">Historis pembacaan</a>
            <a class="<?= $page === 'printing' ? 'active' : '' ?>" href="?page=printing">Cetak rekening</a>
            <span class="nav-label management-label">PENGELOLAAN</span>
            <a class="<?= $page === 'conditions' ? 'active' : '' ?>" href="?page=conditions">Kondisi meter</a>
            <a class="<?= $page === 'settings' ? 'active' : '' ?>" href="?page=settings">Pengaturan tarif</a>
            <a class="<?= $page === 'users' ? 'active' : '' ?>" href="?page=users">User</a>
        </nav>
        <main class="content">
            <?php if ($error !== ''): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
            <?php if ($flashMessage): ?><div class="alert <?= h($flashMessage['type']) ?>"><?= h($flashMessage['message']) ?></div><?php endif; ?>
            <?php if ($page === 'dashboard'):
                $counts = row(
                    'SELECT (SELECT COUNT(*) FROM data_pelanggan) AS pelanggan,
                            (SELECT COUNT(*) FROM data_pembacaan WHERE tahun = ? AND bulan = ?) AS terbaca,
                            (SELECT COALESCE(SUM(tagihan), 0) FROM data_pembacaan WHERE tahun = ? AND bulan = ?) AS tagihan',
                    [(int) date('Y'), (int) date('n'), (int) date('Y'), (int) date('n')]
                );
                $recent = rows(
                    'SELECT id, idpelanggan, nama, kode_baca, standini, pemakaian, tagihan
                     FROM data_pembacaan WHERE tahun = ? AND bulan = ?
                     ORDER BY updated_at DESC LIMIT 8',
                    [(int) date('Y'), (int) date('n')]
                );
            ?>
                <div class="page-heading"><div><p class="eyebrow">RINGKASAN</p><h1>Dashboard</h1><p class="muted">Kondisi pencatatan bulan <?= h($monthLabels[(int) date('n')] . ' ' . date('Y')) ?>.</p></div></div>
                <section class="stats">
                    <article class="stat-card"><span>Total pelanggan</span><strong><?= number_format((int) $counts['pelanggan']) ?></strong><small>Terdaftar</small></article>
                    <article class="stat-card"><span>Sudah dibaca</span><strong><?= number_format((int) $counts['terbaca']) ?></strong><small>Bulan ini</small></article>
                    <article class="stat-card"><span>Total tagihan</span><strong><?= money($counts['tagihan']) ?></strong><small>Bulan ini</small></article>
                </section>
                <section class="panel"><div class="panel-heading"><div><h2>Pembacaan terbaru</h2><p class="muted">Data bulan berjalan</p></div><a class="button secondary" href="?page=readings">Mulai membaca</a></div>
                    <?php if (!$recent): ?><p class="empty">Belum ada pembacaan pada bulan ini.</p><?php else: ?>
                    <div class="table-wrap"><table><thead><tr><th>Pelanggan</th><th>Kelompok</th><th>Stand sekarang</th><th>Pemakaian</th><th>Tagihan</th><th></th></tr></thead><tbody>
                    <?php foreach ($recent as $item): ?><tr><td><strong><?= h($item['nama']) ?></strong><small class="cell-sub"><?= h($item['idpelanggan']) ?></small></td><td><?= h($item['kode_baca']) ?></td><td><?= h($item['standini']) ?> m³</td><td><?= h($item['pemakaian']) ?> m³</td><td><?= money($item['tagihan']) ?></td><td><a class="table-link" href="?invoice=<?= (int) $item['id'] ?>">Invoice</a></td></tr><?php endforeach; ?>
                    </tbody></table></div><?php endif; ?>
                </section>
            <?php elseif ($page === 'users'):
                $edit = isset($_GET['edit']) ? row('SELECT id, username FROM `user` WHERE id = ?', [(int) $_GET['edit']]) : null;
                $users = rows('SELECT id, username, created_at FROM `user` ORDER BY username');
            ?>
                <div class="page-heading"><div><p class="eyebrow">PENGELOLAAN AKUN</p><h1>User</h1><p class="muted">Buat akun dan ubah password petugas.</p></div></div>
                <div class="two-column">
                    <section class="panel"><h2><?= $edit ? 'Edit user' : 'Tambah user' ?></h2>
                        <form method="post" class="stack">
                            <?= csrf_field() ?><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
                            <label>Username<input name="username" maxlength="80" value="<?= h($edit['username'] ?? '') ?>" required></label>
                            <label>Password<input type="password" name="password" minlength="6" <?= $edit ? '' : 'required' ?> autocomplete="new-password"><small><?= $edit ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 6 karakter.' ?></small></label>
                            <div class="actions"><button class="button primary">Simpan user</button><?php if ($edit): ?><a class="button secondary" href="?page=users">Batal</a><?php endif; ?></div>
                        </form>
                    </section>
                    <section class="panel"><h2>Daftar user</h2><div class="table-wrap"><table><thead><tr><th>Username</th><th>Dibuat</th><th></th></tr></thead><tbody>
                        <?php foreach ($users as $item): ?><tr><td><?= h($item['username']) ?><?= (int) $item['id'] === (int) $_SESSION['user_id'] ? ' (Anda)' : '' ?></td><td><?= h(date('d/m/Y', strtotime($item['created_at']))) ?></td><td class="row-actions"><a class="table-link" href="?page=users&edit=<?= (int) $item['id'] ?>">Edit</a><?php if ((int) $item['id'] !== (int) $_SESSION['user_id']): ?><form method="post" onsubmit="return confirm('Hapus user ini?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="danger-link">Hapus</button></form><?php endif; ?></td></tr><?php endforeach; ?>
                    </tbody></table></div></section>
                </div>
            <?php elseif ($page === 'settings'):
                $setting = row('SELECT biaya_admin, rp_per_meter FROM setting WHERE id = 1') ?? ['biaya_admin' => 0, 'rp_per_meter' => 0];
            ?>
                <div class="page-heading"><div><p class="eyebrow">PENGELOLAAN</p><h1>Pengaturan tarif</h1><p class="muted">Perubahan tarif berlaku untuk pembacaan yang disimpan berikutnya.</p></div></div>
                <section class="panel narrow"><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="save_setting">
                    <label>Biaya admin (Rp)<input type="number" name="biaya_admin" min="0" step="0.01" value="<?= h($setting['biaya_admin']) ?>" required></label>
                    <label>Tarif per meter (Rp/m³)<input type="number" name="rp_per_meter" min="0" step="0.01" value="<?= h($setting['rp_per_meter']) ?>" required></label>
                    <div class="formula-note">Tagihan = (stand sekarang − stand sebelumnya) × tarif per meter + biaya admin.</div>
                    <button class="button primary">Simpan pengaturan</button>
                </form></section>
            <?php elseif ($page === 'conditions'):
                $edit = isset($_GET['edit']) ? row('SELECT id, nama FROM kondisi_meter WHERE id = ?', [(int) $_GET['edit']]) : null;
                $conditions = rows('SELECT id, nama FROM kondisi_meter ORDER BY nama');
            ?>
                <div class="page-heading"><div><p class="eyebrow">DATA REFERENSI</p><h1>Kondisi meter</h1><p class="muted">Kondisi yang tersedia saat pencatatan meter.</p></div></div>
                <div class="two-column">
                    <section class="panel"><h2><?= $edit ? 'Edit kondisi' : 'Tambah kondisi' ?></h2><form method="post" class="stack">
                        <?= csrf_field() ?><input type="hidden" name="action" value="save_condition"><input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
                        <label>Nama kondisi<input name="nama" maxlength="60" value="<?= h($edit['nama'] ?? '') ?>" required></label>
                        <div class="actions"><button class="button primary">Simpan kondisi</button><?php if ($edit): ?><a class="button secondary" href="?page=conditions">Batal</a><?php endif; ?></div>
                    </form></section>
                    <section class="panel"><h2>Daftar kondisi</h2><div class="simple-list"><?php foreach ($conditions as $item): ?><div class="simple-row"><span><?= h($item['nama']) ?></span><div class="row-actions"><a class="table-link" href="?page=conditions&edit=<?= (int) $item['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus kondisi ini?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_condition"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="danger-link">Hapus</button></form></div></div><?php endforeach; ?></div></section>
                </div>
            <?php elseif ($page === 'customers'):
                $edit = isset($_GET['edit']) ? row('SELECT * FROM data_pelanggan WHERE idpelanggan = ?', [(string) $_GET['edit']]) : null;
                $customers = rows('SELECT * FROM data_pelanggan ORDER BY kode_baca, urut_baca, nama');
            ?>
                <div class="page-heading"><div><p class="eyebrow">DATA PELANGGAN</p><h1>Pelanggan</h1><p class="muted">Atur data dan kelompok pembacaan setiap pelanggan.</p></div><a class="button primary" href="#form-pelanggan">Tambah pelanggan</a></div>
                <section class="panel" id="form-pelanggan"><h2><?= $edit ? 'Edit pelanggan' : 'Form pelanggan' ?></h2><form method="post" class="form-grid">
                    <?= csrf_field() ?><input type="hidden" name="action" value="save_customer"><input type="hidden" name="original_id" value="<?= h($edit['idpelanggan'] ?? '') ?>">
                    <label>ID pelanggan<input name="idpelanggan" maxlength="40" value="<?= h($edit['idpelanggan'] ?? '') ?>" <?= $edit ? 'readonly' : '' ?> required></label>
                    <label>Nama<input name="nama" maxlength="150" value="<?= e('nama', $edit['nama'] ?? '') ?>" required></label>
                    <label>No. HP<input name="no_hp" maxlength="30" value="<?= e('no_hp', $edit['no_hp'] ?? '') ?>"></label>
                    <label>Kode lokasi<input name="kode_lokasi" maxlength="60" value="<?= e('kode_lokasi', $edit['kode_lokasi'] ?? '') ?>"></label>
                    <label>Kode baca / kelompok<input name="kode_baca" maxlength="60" value="<?= e('kode_baca', $edit['kode_baca'] ?? '') ?>" required></label>
                    <label>Urut baca<input type="number" name="urut_baca" value="<?= e('urut_baca', $edit['urut_baca'] ?? '0') ?>" required></label>
                    <label class="span-all">Alamat<textarea name="alamat" rows="2" required><?= e('alamat', $edit['alamat'] ?? '') ?></textarea></label>
                    <div class="span-all actions"><button class="button primary">Simpan pelanggan</button><?php if ($edit): ?><a class="button secondary" href="?page=customers">Batal</a><?php endif; ?></div>
                </form></section>
                <section class="panel"><div class="panel-heading"><h2>Daftar pelanggan <span class="count"><?= count($customers) ?></span></h2></div>
                    <?php if (!$customers): ?><p class="empty">Belum ada pelanggan.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>ID / Nama</th><th>Kelompok</th><th>Urut</th><th>Kontak / Alamat</th><th></th></tr></thead><tbody>
                    <?php foreach ($customers as $item): ?><tr><td><strong><?= h($item['nama']) ?></strong><small class="cell-sub"><?= h($item['idpelanggan']) ?></small></td><td><?= h($item['kode_baca']) ?></td><td><?= h($item['urut_baca']) ?></td><td><?= h($item['no_hp']) ?><small class="cell-sub"><?= h($item['alamat']) ?></small></td><td class="row-actions"><a class="table-link" href="?page=customers&edit=<?= rawurlencode($item['idpelanggan']) ?>#form-pelanggan">Edit</a><form method="post" onsubmit="return confirm('Hapus pelanggan ini? Data dengan riwayat tidak dapat dihapus.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_customer"><input type="hidden" name="idpelanggan" value="<?= h($item['idpelanggan']) ?>"><button class="danger-link">Hapus</button></form></td></tr><?php endforeach; ?>
                    </tbody></table></div><?php endif; ?>
                </section>
            <?php elseif ($page === 'readings'):
                $groups = rows('SELECT kode_baca, COUNT(*) AS total FROM data_pelanggan GROUP BY kode_baca ORDER BY kode_baca');
                $selectedGroup = (string) ($_GET['kode_baca'] ?? '');
                $customers = $selectedGroup === '' ? [] : rows(
                    'SELECT p.*, r.id AS reading_id, r.standll, r.standinill, r.standini, r.pemakaian, r.tagihan,
                            r.kondisi_meter_id, r.foto_mime
                     FROM data_pelanggan p
                     LEFT JOIN data_pembacaan r ON r.idpelanggan = p.idpelanggan AND r.tahun = ? AND r.bulan = ?
                     WHERE p.kode_baca = ? ORDER BY p.urut_baca, p.nama',
                    [(int) date('Y'), (int) date('n'), $selectedGroup]
                );
                $conditions = rows('SELECT id, nama FROM kondisi_meter ORDER BY nama');
            ?>
                <div class="page-heading"><div><p class="eyebrow">PEMBACAAN METER</p><h1>Pembacaan meter</h1><p class="muted"><?= h($monthLabels[(int) date('n')] . ' ' . date('Y')) ?> · Pilih kelompok untuk memulai.</p></div></div>
                <?php if (!$groups): ?><section class="panel empty">Tambahkan data pelanggan terlebih dahulu agar kelompok pembacaan tersedia.</section><?php else: ?>
                    <div class="group-list"><?php foreach ($groups as $group): ?><a class="group-card <?= $selectedGroup === $group['kode_baca'] ? 'selected' : '' ?>" href="?page=readings&kode_baca=<?= rawurlencode($group['kode_baca']) ?>"><span class="group-icon">⌖</span><span><strong><?= h($group['kode_baca']) ?></strong><small><?= (int) $group['total'] ?> pelanggan</small></span><span class="arrow">›</span></a><?php endforeach; ?></div>
                <?php endif; ?>
                <?php if ($selectedGroup !== ''): ?><section class="reading-list"><div class="section-title"><div><h2>Kelompok <?= h($selectedGroup) ?></h2><p class="muted">Urutan pembacaan mengikuti nomor urut yang telah diatur.</p></div></div>
                    <?php foreach ($customers as $customer):
                        $previous = row('SELECT standini FROM data_pembacaan WHERE idpelanggan = ? AND (tahun < ? OR (tahun = ? AND bulan < ?)) ORDER BY tahun DESC, bulan DESC LIMIT 1', [$customer['idpelanggan'], (int) date('Y'), (int) date('Y'), (int) date('n')]);
                        $lastStand = (float) ($previous['standini'] ?? 0);
                        $isRead = $customer['reading_id'] !== null;
                        $defaultConditionId = (int) ($customer['kondisi_meter_id'] ?? 0);
                        if ($defaultConditionId <= 0) {
                            $defaultCondition = row('SELECT id FROM kondisi_meter WHERE LOWER(nama) = ? LIMIT 1', ['normal']);
                            $defaultConditionId = (int) ($defaultCondition['id'] ?? 1);
                        }
                        $dotMatrixUrl = 'simulasi_cetak_rekening.php?'
                            . 'idpel=' . rawurlencode((string) $customer['idpelanggan'])
                            . '&nama=' . rawurlencode((string) $customer['nama'])
                            . '&alamat=' . rawurlencode((string) $customer['alamat'])
                            . '&bulan=' . rawurlencode((string) $monthLabels[(int) date('n')] . ' ' . date('Y'))
                            . '&stand_lalu=' . rawurlencode((string) ($isRead ? ($customer['standll'] ?? $lastStand) : $lastStand))
                            . '&stand_skrg=' . rawurlencode((string) ($isRead ? ($customer['standini'] ?? $lastStand) : $lastStand))
                            . '&tarif=' . rawurlencode((string) ($isRead ? ($customer['rp_per_meter'] ?? 0) : 0))
                            . '&biaya_admin=' . rawurlencode((string) ($isRead ? ($customer['biaya_admin'] ?? 0) : 0))
                            . '&ref=' . rawurlencode('REF-' . (string) ($customer['reading_id'] ?? $customer['idpelanggan']))
                            . '&petugas=' . rawurlencode('ADMIN')
                            . '&printer=' . rawurlencode('Printer Lokal Windows Dot Matrix')
                            . '&auto=1';
                        $bluetoothPrintUrl = 'simulasi_cetak_bluetooth.php?'
                            . 'idpel=' . rawurlencode((string) $customer['idpelanggan'])
                            . '&nama=' . rawurlencode((string) $customer['nama'])
                            . '&alamat=' . rawurlencode((string) $customer['alamat'])
                            . '&bulan=' . rawurlencode((string) $monthLabels[(int) date('n')] . ' ' . date('Y'))
                            . '&stand_lalu=' . rawurlencode((string) ($isRead ? ($customer['standll'] ?? $lastStand) : $lastStand))
                            . '&stand_skrg=' . rawurlencode((string) ($isRead ? ($customer['standini'] ?? $lastStand) : $lastStand))
                            . '&tarif=' . rawurlencode((string) ($isRead ? ($customer['rp_per_meter'] ?? 0) : 0))
                            . '&biaya_admin=' . rawurlencode((string) ($isRead ? ($customer['biaya_admin'] ?? 0) : 0))
                            . '&ref=' . rawurlencode('REF-' . (string) ($customer['reading_id'] ?? $customer['idpelanggan']))
                            . '&petugas=' . rawurlencode('ADMIN')
                            . '&auto=1';
                        $currentBill = $isRead ? ((float) ($customer['tagihan'] ?? 0) > 0 ? (float) $customer['tagihan'] : (max(0, (float) ($customer['standini'] ?? $lastStand) - (float) ($customer['standll'] ?? $lastStand)) * (float) ($customer['rp_per_meter'] ?? 0) + (float) ($customer['biaya_admin'] ?? 0))) : (max(0, (float) ($customer['standini'] ?? $lastStand) - (float) ($lastStand)) * (float) 0 + 0);
                        $waReadingMessage = "Halo Bapak/Ibu " . $customer['nama'] . ",\n" .
                            "Hasil pembacaan meter PAMSIMAS\n" .
                            "ID Pelanggan: " . $customer['idpelanggan'] . "\n" .
                            "Alamat: " . $customer['alamat'] . "\n" .
                            "Stand sebelumnya: " . ($isRead ? ($customer['standll'] ?? $lastStand) : $lastStand) . " m3\n" .
                            "Stand sekarang: " . ($isRead ? ($customer['standini'] ?? $lastStand) : $lastStand) . " m3\n" .
                            "Pemakaian: " . ($isRead ? ($customer['pemakaian'] ?? max(0, $lastStand)) : 0) . " m3\n" .
                            "Tarif: " . money((float) ($customer['rp_per_meter'] ?? 0)) . "\n" .
                            "Biaya admin: " . money((float) ($customer['biaya_admin'] ?? 0)) . "\n" .
                            "Total tagihan: " . money($currentBill);
                        $waReadingLink = whatsapp_link((string) ($customer['no_hp'] ?? ''), $waReadingMessage);
                    ?>
                        <article class="reading-card <?= $isRead ? 'is-read' : '' ?>">
                            <div class="reading-customer"><span class="order-badge"><?= h($customer['urut_baca']) ?></span><div><h3><?= h($customer['nama']) ?><?= $isRead ? '<span class="read-badge">Sudah dibaca</span>' : '' ?></h3><p><?= h($customer['idpelanggan']) ?> · <?= h($customer['alamat']) ?></p><small>Stand sebelumnya: <strong><?= h($isRead ? $customer['standll'] : $lastStand) ?> m³</strong><?php if ($isRead): ?> · Pemakaian: <strong><?= h($customer['pemakaian']) ?> m³</strong> · Tagihan: <strong><?= money($customer['tagihan']) ?></strong><?php endif; ?></small></div></div>
                            <form method="post" enctype="multipart/form-data" class="reading-form">
                                <?= csrf_field() ?><input type="hidden" name="action" value="save_reading"><input type="hidden" name="idpelanggan" value="<?= h($customer['idpelanggan']) ?>">
                                <label>Stand sekarang (m³)<input type="number" name="standini" min="<?= h($lastStand) ?>" step="0.01" value="<?= h($customer['standini'] ?? $lastStand) ?>" required></label>
                                <label>Kondisi meter<select name="kondisi_meter_id" required><option value="">Pilih kondisi</option><?php foreach ($conditions as $condition): ?><option value="<?= (int) $condition['id'] ?>" <?= (int) ($customer['kondisi_meter_id'] ?? $defaultConditionId) === (int) $condition['id'] ? 'selected' : '' ?>><?= h($condition['nama']) ?></option><?php endforeach; ?></select></label>
                                <label class="photo-field">Foto meter<input type="file" name="foto_meter" accept="image/*" capture="environment"><small>Ambil foto dengan kamera atau pilih dari galeri (maks. 5 MB).</small></label>
                                <div class="reading-actions"><?php if ($isRead && $customer['foto_mime']): ?><a class="table-link" target="_blank" rel="noopener" href="?photo=<?= (int) $customer['reading_id'] ?>">Lihat foto</a><?php endif; ?><button class="button <?= $isRead ? 'secondary' : 'primary' ?>"><?= $isRead ? 'Edit pembacaan' : 'Simpan pembacaan' ?></button><?php if ($isRead): ?><a class="button outline" target="_blank" rel="noopener" href="?invoice=<?= (int) $customer['reading_id'] ?>">Invoice</a><?php endif; ?><a class="button secondary" target="_blank" rel="noopener" href="<?= h($dotMatrixUrl) ?>">Dot Matrix</a><a class="button secondary" target="_blank" rel="noopener" href="<?= h($bluetoothPrintUrl) ?>">Bluetooth</a><?php if ($waReadingLink !== '#'): ?><a class="button success" target="_blank" rel="noopener" href="<?= h($waReadingLink) ?>">WhatsApp</a><?php endif; ?></div>
                            </form>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$customers): ?><div class="panel empty">Tidak ada pelanggan pada kelompok ini.</div><?php endif; ?>
                </section><?php endif; ?>
            <?php elseif ($page === 'printing'):
                $monthNames = $monthLabels;
                $printQueue = rows(
                    'SELECT d.id, d.idpelanggan, d.nama, d.alamat, d.bulan, d.tahun,
                            d.standll, d.standini, d.pemakaian,
                            CASE WHEN d.rp_per_meter IS NULL OR d.rp_per_meter <= 0 THEN s.rp_per_meter ELSE d.rp_per_meter END AS rp_per_meter,
                            CASE WHEN d.biaya_admin IS NULL OR d.biaya_admin <= 0 THEN s.biaya_admin ELSE d.biaya_admin END AS biaya_admin,
                            CASE WHEN d.tagihan IS NULL OR d.tagihan <= 0 THEN ((d.standini - d.standll) * (CASE WHEN d.rp_per_meter IS NULL OR d.rp_per_meter <= 0 THEN s.rp_per_meter ELSE d.rp_per_meter END) + (CASE WHEN d.biaya_admin IS NULL OR d.biaya_admin <= 0 THEN s.biaya_admin ELSE d.biaya_admin END)) ELSE d.tagihan END AS tagihan
                     FROM data_pembacaan d
                     LEFT JOIN setting s ON s.id = 1
                     ORDER BY d.tahun DESC, d.bulan DESC, d.id DESC LIMIT 50'
                );
            ?>
                <div class="page-heading"><div><p class="eyebrow">CETAK</p><h1>Cetak rekening</h1><p class="muted">Pilih jenis printer dan rekening pelanggan yang ingin dicetak.</p></div></div>
                <section class="two-column">
                    <article class="panel">
                        <h2>Printer dot matrix</h2>
                        <p class="muted">Untuk PC Windows yang terhubung ke printer lokal dot matrix, Epson LQ, atau printer yang dipilih dari Windows.</p>
                        <div class="actions">
                            <a class="button primary" href="simulasi_cetak_rekening.php" target="_blank" rel="noopener">Buka simulasi dot matrix</a>
                        </div>
                    </article>
                    <article class="panel">
                        <h2>Printer bluetooth</h2>
                        <p class="muted">Untuk smartphone yang terkoneksi ke printer bluetooth atau printer thermal mobile.</p>
                        <div class="actions">
                            <a class="button secondary" href="simulasi_cetak_bluetooth.php" target="_blank" rel="noopener">Buka simulasi bluetooth</a>
                        </div>
                    </article>
                </section>
                <section class="panel" style="margin-top:24px;">
                    <div class="panel-heading">
                        <div><h2>Daftar rekening</h2><p class="muted">Pilih pelanggan untuk membuka hasil cetak sesuai jenis printer.</p></div>
                    </div>
                    <?php if (!$printQueue): ?><p class="empty">Belum ada data rekening untuk dicetak.</p><?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Pelanggan</th>
                                    <th>Bulan</th>
                                    <th>Stand</th>
                                    <th>Pemakaian</th>
                                    <th>Tagihan</th>
                                    <th>Cetak</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($printQueue as $item):
                                    $printMonth = $monthNames[(int) $item['bulan']] ?? (string) $item['bulan'];
                                    $effectiveRate = (float) ($item['rp_per_meter'] ?? 0);
                                    $effectiveAdmin = (float) ($item['biaya_admin'] ?? 0);
                                    $effectiveStandLalu = (float) ($item['standll'] ?? 0);
                                    $effectiveStandNow = (float) ($item['standini'] ?? 0);
                                    $effectiveUsage = max(0, $effectiveStandNow - $effectiveStandLalu);
                                    $effectiveBill = $effectiveUsage * $effectiveRate + $effectiveAdmin;
                                    $item['tagihan'] = $item['tagihan'] ?? $effectiveBill;
                                    $item['tagihan'] = (float) $item['tagihan'] ?: $effectiveBill;
                                    $displayBill = (float) $item['tagihan'] > 0 ? (float) $item['tagihan'] : $effectiveBill;
                                    $dotUrl = 'simulasi_cetak_rekening.php?'
                                        . 'idpel=' . rawurlencode((string) $item['idpelanggan'])
                                        . '&nama=' . rawurlencode((string) $item['nama'])
                                        . '&alamat=' . rawurlencode((string) $item['alamat'])
                                        . '&bulan=' . rawurlencode((string) $printMonth . ' ' . $item['tahun'])
                                        . '&stand_lalu=' . rawurlencode((string) ($item['standll'] ?? 0))
                                        . '&stand_skrg=' . rawurlencode((string) ($item['standini'] ?? 0))
                                        . '&tarif=' . rawurlencode((string) ($item['rp_per_meter'] ?? 0))
                                        . '&biaya_admin=' . rawurlencode((string) ($item['biaya_admin'] ?? 0))
                                        . '&ref=' . rawurlencode('REF-' . (string) $item['id'])
                                        . '&petugas=' . rawurlencode('ADMIN')
                                        . '&printer=' . rawurlencode('Printer Lokal Windows Dot Matrix')
                                        . '&auto=1';
                                    $btUrl = 'simulasi_cetak_bluetooth.php?'
                                        . 'idpel=' . rawurlencode((string) $item['idpelanggan'])
                                        . '&nama=' . rawurlencode((string) $item['nama'])
                                        . '&alamat=' . rawurlencode((string) $item['alamat'])
                                        . '&bulan=' . rawurlencode((string) $printMonth . ' ' . $item['tahun'])
                                        . '&stand_lalu=' . rawurlencode((string) ($item['standll'] ?? 0))
                                        . '&stand_skrg=' . rawurlencode((string) ($item['standini'] ?? 0))
                                        . '&tarif=' . rawurlencode((string) ($item['rp_per_meter'] ?? 0))
                                        . '&biaya_admin=' . rawurlencode((string) ($item['biaya_admin'] ?? 0))
                                        . '&ref=' . rawurlencode('REF-' . (string) $item['id'])
                                        . '&petugas=' . rawurlencode('ADMIN')
                                        . '&auto=1';
                                ?>
                                    <tr>
                                        <td><strong><?= h($item['nama']) ?></strong><small class="cell-sub"><?= h($item['idpelanggan']) ?></small></td>
                                        <td><?= h($printMonth . ' ' . $item['tahun']) ?></td>
                                        <td><?= h($item['standll']) ?> – <?= h($item['standini']) ?> m³</td>
                                        <td><?= h($item['pemakaian']) ?> m³</td>
                                        <td><?= money($displayBill) ?></td>
                                        <td class="row-actions">
                                            <a class="table-link" href="<?= h($dotUrl) ?>" target="_blank" rel="noopener">Dot matrix</a>
                                            <a class="table-link" href="<?= h($btUrl) ?>" target="_blank" rel="noopener">Bluetooth</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </section>
            <?php elseif ($page === 'history'):
                $history = rows(
                    'SELECT h.*, p.kode_baca FROM historis h
                     LEFT JOIN data_pelanggan p ON p.idpelanggan = h.idpelanggan
                     ORDER BY h.tahun DESC, h.kode_lokasi, h.idpelanggan'
                );
            ?>
                <div class="page-heading"><div><p class="eyebrow">ARSIP PEMBACAAN</p><h1>Historis pembacaan</h1><p class="muted">Rekap stand meter per pelanggan dan tahun.</p></div></div>
                <section class="panel"><?php if (!$history): ?><p class="empty">Belum ada historis pembacaan.</p><?php else: ?><div class="table-wrap"><table class="history-table"><thead><tr><th>Pelanggan</th><th>Tahun</th><th>Stand awal</th><?php foreach ($monthLabels as $label): ?><th><?= h(substr($label, 0, 3)) ?></th><?php endforeach; ?></tr></thead><tbody>
                    <?php foreach ($history as $item): ?><tr><td><strong><?= h($item['nama']) ?></strong><small class="cell-sub"><?= h($item['idpelanggan']) ?> · <?= h($item['kode_baca'] ?? '') ?></small></td><td><?= h($item['tahun']) ?></td><td><?= h($item['standini']) ?></td><?php for ($month = 1; $month <= 12; $month++): ?><td><?= h($item[sprintf('stand%02d', $month)] ?? '—') ?></td><?php endfor; ?></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?></section>
            <?php endif; ?>
            <footer class="footer"><?= h(APP_NAME) ?> <span>·</span> Pencatatan dan tagihan air</footer>
        </main>
    </div>
<?php endif; ?>
</body>
</html>


