<?php
function load_lang(string $lang): array {
  $config = require __DIR__ . '/../config/config.php';
  if (!in_array($lang, $config['supported_langs'], true)) $lang = $config['default_lang'];
  $file = __DIR__ . '/../lang/' . $lang . '.php';
  return file_exists($file) ? require $file : [];
}

function t(string $key, array $dict): string {
  return $dict[$key] ?? $key;
}
