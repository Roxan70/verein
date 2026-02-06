<?php
require_once __DIR__ . '/inc/db.php'; require_once __DIR__ . '/inc/functions.php'; require_once __DIR__ . '/inc/auth.php'; require_once __DIR__ . '/inc/config.php';
require_login();
$eventId=(int)($_GET['event_id']??0);
$st=$mysqli->prepare('SELECT title_main,event_lang,officials,sponsors_text FROM events WHERE event_id=?');$st->bind_param('i',$eventId);$st->execute();$st->bind_result($title,$elang,$offi,$spon);$st->fetch();$st->close();
$html='<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif}h1{margin:0 0 10px}</style></head><body>';
if(!empty($_GET['cover'])){$html.='<h1>'.e($title).'</h1>';} if(!empty($_GET['officials'])){$html.='<h2>Officials</h2><p>'.nl2br(e($offi)).'</p>';} if(!empty($_GET['sponsors'])){$html.='<h2>Sponsors</h2><p>'.nl2br(e($spon)).'</p>';} $html.='</body></html>';
if (file_exists(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; if (class_exists('Dompdf\\Dompdf')) { $dompdf = new Dompdf\Dompdf(); $dompdf->loadHtml($html); $dompdf->setPaper('A4', 'portrait'); $dompdf->render(); $dompdf->stream('katalog_'.$eventId.'.pdf', array('Attachment'=>1)); exit; }}
header('Content-Type: text/html; charset=utf-8');echo $html;
