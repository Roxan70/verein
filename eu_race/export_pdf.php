<?php
require_once __DIR__ . '/inc/db.php'; require_once __DIR__ . '/inc/functions.php'; require_once __DIR__ . '/inc/auth.php';
require_login();
$eventId=(int)($_GET['event_id']??0);$mode=$_GET['mode']??'program';
$html='<!doctype html><html><head><meta charset="utf-8"><style>table{width:100%;border-collapse:collapse}td,th{border:1px solid #999;padding:4px}</style></head><body>';
if($mode==='program'){$st=$mysqli->prepare('SELECT heat_no,title_cached FROM heats WHERE event_id=? ORDER BY heat_no');$st->bind_param('i',$eventId);$st->execute();$st->bind_result($no,$title);$html.='<h1>Program</h1><table><tr><th>No</th><th>Title</th></tr>';while($st->fetch()){$html.='<tr><td>'.(int)$no.'</td><td>'.e($title).'</td></tr>';}$html.='</table>'; $st->close();}
else{$st=$mysqli->prepare('SELECT entry_id,best_track_time_ms,sum_solo_time_ms,coursing_total_points FROM results WHERE event_id=? ORDER BY is_counted DESC,best_track_time_ms ASC,sum_solo_time_ms ASC,coursing_total_points DESC');$st->bind_param('i',$eventId);$st->execute();$st->bind_result($entry,$best,$sum,$c);$html.='<h1>Results</h1><table><tr><th>Entry</th><th>Best</th><th>Sum</th><th>Pts</th></tr>';while($st->fetch()){$html.='<tr><td>'.(int)$entry.'</td><td>'.e(ms_to_time($best)).'</td><td>'.e(ms_to_time($sum)).'</td><td>'.e((string)$c).'</td></tr>';}$html.='</table>'; $st->close();}
$html.='</body></html>';
if (file_exists(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; if (class_exists('Dompdf\\Dompdf')) { $dompdf = new Dompdf\Dompdf(); $dompdf->loadHtml($html); $dompdf->setPaper('A4','portrait'); $dompdf->render(); $dompdf->stream('event_'.$eventId.'_'.$mode.'.pdf',array('Attachment'=>1)); exit; }}
header('Content-Type:text/html; charset=utf-8'); echo $html;
