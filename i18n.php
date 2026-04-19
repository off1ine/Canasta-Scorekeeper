<?php
declare(strict_types=1);

const I18N_LOCALES = ['en', 'de'];
const I18N_DEFAULT = 'en';

function i18n_init(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $locale = i18n_resolve_locale();
    $GLOBALS['__I18N_LOCALE'] = $locale;
    $GLOBALS['__I18N_STRINGS'] = i18n_load_strings($locale);
}

function i18n_resolve_locale(): string {
    if (session_status() === PHP_SESSION_ACTIVE
        && isset($_SESSION['locale'])
        && in_array($_SESSION['locale'], I18N_LOCALES, true)) {
        return (string)$_SESSION['locale'];
    }
    if (isset($_COOKIE['locale']) && in_array($_COOKIE['locale'], I18N_LOCALES, true)) {
        return (string)$_COOKIE['locale'];
    }
    $accept = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    foreach (explode(',', $accept) as $part) {
        $code = strtolower(trim((string)(explode(';', $part)[0] ?? '')));
        $code = substr($code, 0, 2);
        if ($code !== '' && in_array($code, I18N_LOCALES, true)) {
            return $code;
        }
    }
    return I18N_DEFAULT;
}

function i18n_load_strings(string $locale): array {
    $path = __DIR__ . '/lang/' . $locale . '.json';
    if (!is_readable($path)) return [];
    $json = (string)file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function t(string $key, array $params = []): string {
    $strings = $GLOBALS['__I18N_STRINGS'] ?? [];
    $str = $strings[$key] ?? null;
    if ($str === null) {
        $locale = $GLOBALS['__I18N_LOCALE'] ?? I18N_DEFAULT;
        if ($locale !== I18N_DEFAULT) {
            static $enCache = null;
            if ($enCache === null) $enCache = i18n_load_strings(I18N_DEFAULT);
            $str = $enCache[$key] ?? $key;
        } else {
            $str = $key;
        }
    }
    foreach ($params as $k => $v) {
        $str = str_replace('{' . $k . '}', (string)$v, (string)$str);
    }
    return (string)$str;
}

function current_locale(): string {
    return $GLOBALS['__I18N_LOCALE'] ?? I18N_DEFAULT;
}

function set_locale(string $locale): bool {
    if (!in_array($locale, I18N_LOCALES, true)) return false;
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['locale'] = $locale;
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('locale', $locale, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $GLOBALS['__I18N_LOCALE'] = $locale;
    $GLOBALS['__I18N_STRINGS'] = i18n_load_strings($locale);
    return true;
}

function i18n_strings_for_client(): array {
    return $GLOBALS['__I18N_STRINGS'] ?? [];
}
