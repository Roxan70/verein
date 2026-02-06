<?php
require_once __DIR__ . '/inc/db.php'; require_once __DIR__ . '/inc/functions.php'; require_once __DIR__ . '/inc/auth.php';
require_login();
$eventId=(int)($_GET['event_id']??0);$mode=$_GET['mode']??'program';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="event_'.$eventId.'_'.$mode.'.xls"');
echo '<html><meta charset="utf-8"><body>';
if($mode==='program'){echo '<table border="1"><tr><th>No</th><th>Type</th><th>Title</th></tr>';$st=$mysqli->prepare('SELECT heat_no,heat_type,title_cached FROM heats WHERE event_id=? ORDER BY heat_no');$st->bind_param('i',$eventId);$st->execute();$st->bind_result($no,$type,$title);while($st->fetch()){echo '<tr><td>'.(int)$no.'</td><td>'.e($type).'</td><td>'.e($title).'</td></tr>';}$st->close();echo '</table>';}
else{echo '<table border="1"><tr><th>Entry</th><th>Best</th><th>Solo</th><th>Coursing</th></tr>';$st=$mysqli->prepare('SELECT entry_id,best_track_time_ms,sum_solo_time_ms,coursing_total_points FROM results WHERE event_id=? ORDER BY is_counted DESC,best_track_time_ms ASC,sum_solo_time_ms ASC,coursing_total_points DESC');$st->bind_param('i',$eventId);$st->execute();$st->bind_result($entry,$best,$sum,$c);while($st->fetch()){echo '<tr><td>'.(int)$entry.'</td><td>'.e(ms_to_time($best)).'</td><td>'.e(ms_to_time($sum)).'</td><td>'.e((string)$c).'</td></tr>';}$st->close();echo '</table>';}
echo '</body></html>';
