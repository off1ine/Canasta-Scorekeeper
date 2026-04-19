<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function start_app_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;

  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_name(APP_SESSION_NAME);

  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  session_start();
}

function current_user(): ?array {
  start_app_session();
  if (!isset($_SESSION['user_id'])) return null;
  return [
    'id' => (int)$_SESSION['user_id'],
    'username' => (string)($_SESSION['username'] ?? ''),
    'is_admin' => (bool)($_SESSION['is_admin'] ?? false),
  ];
}

function is_logged_in(): bool {
  return current_user() !== null;
}

function require_login_page(): void {
  if (is_logged_in()) return;
  $next = $_SERVER['REQUEST_URI'] ?? '/index.php';
  header('Location: login.php?next=' . urlencode($next));
  exit;
}

function require_login_api(): void {
  if (is_logged_in()) return;
  http_response_code(401);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'Not authenticated']);
  exit;
}

function require_admin_page(): void {
  $u = current_user();
  if ($u && $u['is_admin']) return;
  header('Location: index.php');
  exit;
}

function require_admin_api(): void {
  $u = current_user();
  if ($u && $u['is_admin']) return;
  http_response_code(403);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => 'Admin required']);
  exit;
}

function validate_pin(string $pin): bool {
  return (bool)preg_match('/^\d{4}$/', $pin);
}
