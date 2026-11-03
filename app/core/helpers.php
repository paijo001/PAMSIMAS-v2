<?php declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rows(string $sql, array $params = []): array
{
    global $conn;
    $statement = $conn->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function row(string $sql, array $params = []): ?array
{
    global $conn;
    $statement = $conn->prepare($sql);
    $statement->execute($params);
    $result = $statement->fetch();
    return $result ?: null;
}

function run(string $sql, array $params = []): int
{
    global $conn;
    $statement = $conn->prepare($sql);
    $statement->execute($params);
    return $statement->rowCount();
}

function e(string $key, string $default = ''): string
{
    return h($_POST[$key] ?? $default);
}

function money(mixed $value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('?page=login');
    }
}

function post_string(string $key, string $label, int $maxLength = 255): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '' || mb_strlen($value) > $maxLength) {
        throw new InvalidArgumentException($label . ' wajib diisi dan maksimal ' . $maxLength . ' karakter.');
    }
    return $value;
}

function post_amount(string $key, string $label): float
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_FLOAT);
    if ($value === false || $value < 0) {
        throw new InvalidArgumentException($label . ' harus berupa angka nol atau lebih.');
    }
    return (float) $value;
}

function normalize_photo_blob(string $blob): string
{
    if (strncmp($blob, "\xEF\xBB\xBF", 3) === 0) {
        return substr($blob, 3);
    }
    return $blob;
}

function normalize_wa_number(string $phone): string
{
    $digits = preg_replace('/\D+/', '', trim($phone));
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0')) {
        return '62' . substr($digits, 1);
    }
    if (str_starts_with($digits, '62')) {
        return $digits;
    }
    return '62' . $digits;
}

function whatsapp_link(string $phone, string $message): string
{
    $normalized = normalize_wa_number($phone);
    if ($normalized === '') {
        return '#';
    }
    return 'https://wa.me/' . rawurlencode($normalized) . '?text=' . rawurlencode($message);
}

function uploaded_photo(): ?array
{
    if (!isset($_FILES['foto_meter']) || $_FILES['foto_meter']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['foto_meter']['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Foto meter gagal diunggah. Silakan coba kembali.');
    }
    if ($_FILES['foto_meter']['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran foto maksimal 5 MB.');
    }
    $image = getimagesize($_FILES['foto_meter']['tmp_name']);
    $mime = $image['mime'] ?? '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new InvalidArgumentException('Foto harus berupa JPG, PNG, atau WebP.');
    }
    $contents = file_get_contents($_FILES['foto_meter']['tmp_name']);
    if ($contents === false) {
        throw new RuntimeException('Foto meter tidak dapat dibaca.');
    }
    return ['data' => normalize_photo_blob($contents), 'mime' => $mime];
}
