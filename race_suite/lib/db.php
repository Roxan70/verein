<?php
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $config = require __DIR__ . '/../config/config.php';
  $needInit = !file_exists($config['db_path']);
  $pdo = new PDO('sqlite:' . $config['db_path']);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('PRAGMA foreign_keys = ON');

  if ($needInit) {
    $schema = file_get_contents(__DIR__ . '/../db/schema.sql');
    $pdo->exec($schema);
    $stmt = $pdo->prepare('INSERT INTO users(username,password_hash,role,full_name,locale) VALUES (?,?,?,?,?)');
    $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 'Administrator', 'de']);
  }
  return $pdo;
}
