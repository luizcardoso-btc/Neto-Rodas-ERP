<?php
declare(strict_types=1);

function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** "1.234,56" ou "1234.56" -> 1234.56 */
function parse_money(string|int|float|null $v): float
{
    if (is_int($v) || is_float($v)) {
        return round((float) $v, 2);
    }
    $v = trim((string) $v);
    if ($v === '') {
        return 0.0;
    }
    $v = preg_replace('/[^\d,.\-]/', '', $v) ?? '';
    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return round((float) $v, 2);
}

function money(float|int|string|null $v): string
{
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
}

function digits(?string $v): string
{
    return preg_replace('/\D+/', '', (string) $v) ?? '';
}

function format_phone(?string $v): string
{
    $d = digits($v);
    if (strlen($d) === 11) {
        return sprintf('%s %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('%s %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return (string) $v;
}

function whatsapp_link(?string $phone): string
{
    $d = digits($phone);
    if ($d === '') {
        return '';
    }
    if (strlen($d) <= 11) {
        $d = '55' . $d;
    }
    return 'https://wa.me/' . $d;
}

function fmt_date(?string $v, bool $time = false): string
{
    if (!$v) {
        return '-';
    }
    $t = strtotime($v);
    return $t ? date($time ? 'd/m/Y H:i' : 'd/m/Y', $t) : '-';
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function url(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> */
function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function old(string $key, string $default = ''): string
{
    return e((string) ($_POST[$key] ?? $default));
}

function is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Padrao do sistema: texto sempre em CAIXA ALTA, sem espacos nas pontas. */
function upper(?string $v): string
{
    return mb_strtoupper(trim((string) $v), 'UTF-8');
}

/** @param array<mixed> $a  Aplica upper() em todos os textos (recursivo). */
function upper_all(array $a): array
{
    foreach ($a as $k => $v) {
        $a[$k] = is_array($v) ? upper_all($v) : (is_string($v) ? upper($v) : $v);
    }
    return $a;
}

/** URL de arquivo em /assets com versao pela data de modificacao: o navegador nunca usa CSS/JS antigo em cache. */
function asset(string $file): string
{
    $path = dirname(__DIR__) . '/public/assets/' . $file;
    return 'assets/' . $file . '?v=' . (is_file($path) ? filemtime($path) : 1);
}
