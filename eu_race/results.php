<?php
require_once __DIR__ . '/inc/header.php'; require_login(); require_role(array('admin','organizer','viewer'));
$eventId=(int)($_GET['event_id']??0);
if($eventId>0 && isset($_GET['recalc']) && in_array($_SESSION['role'],array('admin','organizer'),true)){
$st=$mysqli->prepare('SELECT e.entry_id,e.group_code,e.status FROM entries e WHERE e.event_id=?');$st->bind_param('i',$eventId);$st->execute();$st->bind_result($entry,$group,$estatus);
while($st->fetch()){
$best=null;$sum=null;$cPts=null;$counted=($estatus==='OK')?1:0;
if($group==='FIELD'){$q=$mysqli->prepare('SELECT MIN(p.time_ms) FROM performance p JOIN heats h ON h.heat_id=p.heat_id WHERE h.event_id=? AND p.entry_id=? AND h.heat_type IN ("HEAT1","HEAT2") AND p.status="OK"');$q->bind_param('ii',$eventId,$entry);$q->execute();$q->bind_result($best);$q->fetch();$q->close();if($best===null){$counted=0;}}
else {$q=$mysqli->prepare('SELECT SUM(p.time_ms),COUNT(*) FROM performance p JOIN heats h ON h.heat_id=p.heat_id WHERE h.event_id=? AND p.entry_id=? AND h.heat_type IN ("HEAT1","HEAT2") AND p.status="OK"');$q->bind_param('ii',$eventId,$entry);$q->execute();$q->bind_result($sum,$cnt);$q->fetch();$q->close();if((int)$cnt<2){$sum=null;$counted=0;}}
$q2=$mysqli->prepare('SELECT SUM(total_points) FROM performance p JOIN heats h ON h.heat_id=p.heat_id WHERE h.event_id=? AND p.entry_id=? AND h.heat_type IN ("COURSING_RUN1","COURSING_RUN2") AND p.status="OK"');$q2->bind_param('ii',$eventId,$entry);$q2->execute();$q2->bind_result($cPts);$q2->fetch();$q2->close();
$chk=$mysqli->prepare('SELECT result_id FROM results WHERE event_id=? AND entry_id=? LIMIT 1');$chk->bind_param('ii',$eventId,$entry);$chk->execute();$chk->bind_result($rid);
if($chk->fetch()){$chk->close();$u=$mysqli->prepare('UPDATE results SET best_track_time_ms=?,sum_solo_time_ms=?,coursing_total_points=?,is_counted=? WHERE result_id=?');$u->bind_param('iiiii',$best,$sum,$cPts,$counted,$rid);$u->execute();$u->close();}
else{$chk->close();$i=$mysqli->prepare('INSERT INTO results (event_id,entry_id,best_track_time_ms,sum_solo_time_ms,coursing_total_points,is_counted) VALUES (?,?,?,?,?,?)');$i->bind_param('iiiiii',$eventId,$entry,$best,$sum,$cPts,$counted);$i->execute();$i->close();}
}
$st->close();
}
$events=array();$s1=$mysqli->prepare('SELECT event_id,title_main FROM events ORDER BY event_date DESC LIMIT 200');$s1->execute();$s1->bind_result($eid,$et);while($s1->fetch()){$events[$eid]=$et;} $s1->close();
$st2=null;if($eventId>0){$st2=$mysqli->prepare('SELECT r.entry_id,d.name,e.class_code,e.group_code,r.best_track_time_ms,r.sum_solo_time_ms,r.coursing_total_points,r.is_counted FROM results r JOIN entries e ON e.entry_id=r.entry_id JOIN dogs d ON d.dog_id=e.dog_id WHERE r.event_id=? ORDER BY r.is_counted DESC,r.best_track_time_ms ASC,r.sum_solo_time_ms ASC,r.coursing_total_points DESC LIMIT 500');$st2->bind_param('i',$eventId);$st2->execute();$st2->bind_result($entry,$dog,$class,$group,$best,$sum,$cpt,$cntd);}?>
<div class="card"><form method="get"><label>Event<select name="event_id"><option value="0">--</option><?php foreach($events as $k=>$v):?><option value="<?php echo(int)$k;?>" <?php if($eventId===$k)echo'selected';?>><?php echo e($v);?></option><?php endforeach;?></select></label><button>Show</button><?php if($eventId>0):?><a href="results.php?event_id=<?php echo(int)$eventId;?>&recalc=1">Recalculate</a><?php endif;?></form></div>
<?php if($st2):?><table><tr><th>Entry</th><th>Dog</th><th>Class</th><th>Group</th><th>Best</th><th>Solo Sum</th><th>Coursing</th><th>Counted</th></tr><?php while($st2->fetch()):?><tr><td><?php echo(int)$entry;?></td><td><?php echo e($dog);?></td><td><?php echo e($class);?></td><td><?php echo e($group);?></td><td><?php echo e(ms_to_time($best));?></td><td><?php echo e(ms_to_time($sum));?></td><td><?php echo e((string)$cpt);?></td><td><?php echo(int)$cntd;?></td></tr><?php endwhile;?></table><?php $st2->close(); endif;?>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
