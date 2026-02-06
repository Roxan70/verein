<?php
require_once __DIR__ . '/db.php';

function current_user(): ?array {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (!isset($_SESSION['user_id'])) return null;
  $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  return $user ?: null;
}

function require_login(): array {
  $user = current_user();
  if (!$user) {
    header('Location: /login');
    exit;
  }
  return $user;
}

function require_role(array $roles): array {
  $user = require_login();
  if (!in_array($user['role'], $roles, true)) {
    http_response_code(403);
    echo '403 Forbidden';
    exit;
  }
  return $user;
}

function login(string $username, string $password): bool {
  $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
  $stmt->execute([$username]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user || !password_verify($password, $user['password_hash'])) return false;
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $_SESSION['user_id'] = (int)$user['id'];
  return true;
}

function logout(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  session_destroy();
}
