<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';

function render_header(string $title, array $dict): void {
  $user = current_user();
  echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
  echo '<title>' . htmlspecialchars($title) . '</title>';
  echo '<link rel="stylesheet" href="/assets/styles.css">';
  echo '<script defer src="/assets/app.js"></script>';
  echo '</head><body><header><h1>EU Windhound Race Suite</h1>';
  if ($user) {
    echo '<div class="top-right">' . htmlspecialchars($user['full_name']) . ' (' . htmlspecialchars($user['role']) . ') <a href="/logout">Logout</a></div>';
  }
  echo '</header><nav><a href="/dashboard">Dashboard</a> <a href="/owners">Owners</a> <a href="/dogs">Dogs</a></nav><main>';
}

function render_footer(): void {
  echo '</main></body></html>';
}
