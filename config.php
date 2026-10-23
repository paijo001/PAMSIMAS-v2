<?php
// Konfigurasi database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'data_sblw');

// Koneksi ke database
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Info tambahan kalau diperlukan
define('APP_NAME', 'Aplikasi SBLW');
define('APP_VERSION', '1.0');

// MAYAR config (isi di environment atau ganti langsung di sini untuk testing)
define('MAYAR_API_KEY', getenv('MAYAR_API_KEY') ?: 'MASUKKAN_API_KEY_SANDBOX_DI_SINI');
define('MAYAR_BASE_URL', getenv('MAYAR_BASE_URL') ?: 'https://api.mayar.club/hl/v1');

// Webhook secret (jika diberikan Mayar)
define('MAYAR_WEBHOOK_SECRET', getenv('MAYAR_WEBHOOK_SECRET') ?: '9f6d8f6ee40871cd73b2c78aaf6ab726f271db52c2022021118845cef56738d17e9d305e78f092af833c6168d95d45ac029f5bb741701f1084d32c225973a15b');

?>
