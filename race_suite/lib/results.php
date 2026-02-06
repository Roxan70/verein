<?php
require_once __DIR__ . '/db.php';

function entry_scores(int $eventId): array {
  $sql = "
  SELECT e.id as entry_id, d.name as dog_name, o.name as owner_name,
    MIN(CASE WHEN h.heat_type IN ('HEAT1','HEAT2') AND p.is_counted=1 THEN p.time_seconds END) as best_time,
    SUM(CASE WHEN h.heat_type IN ('HEAT1','HEAT2') AND p.is_counted=1 THEN p.time_seconds END) as sum_time,
    SUM(CASE WHEN h.heat_type IN ('COURSING_RUN1','COURSING_RUN2') AND p.is_counted=1 THEN COALESCE(p.points_s,0)+COALESCE(p.points_a,0)+COALESCE(p.points_e,0)+COALESCE(p.points_f,0)+COALESCE(p.points_h,0) ELSE 0 END) as total_points
  FROM entries e
  JOIN dogs d ON d.id=e.dog_id
  JOIN owners o ON o.id=d.owner_id
  LEFT JOIN performances p ON p.entry_id=e.id AND p.event_id=e.event_id
  LEFT JOIN heats h ON h.id=p.heat_id
  WHERE e.event_id=?
  GROUP BY e.id, d.name, o.name
  ";
  $stmt = db()->prepare($sql);
  $stmt->execute([$eventId]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function auto_create_finals(int $eventId, bool $recreate): void {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $event = $pdo->query('SELECT * FROM events WHERE id=' . (int)$eventId)->fetch(PDO::FETCH_ASSOC);
    if (!$event) throw new RuntimeException('Event not found');

    if ($recreate) {
      $pdo->exec("DELETE FROM performances WHERE heat_id IN (SELECT id FROM heats WHERE event_id={$eventId} AND is_auto_final=1)");
      $pdo->exec("DELETE FROM finals_assignments WHERE final_heat_id IN (SELECT id FROM heats WHERE event_id={$eventId} AND is_auto_final=1)");
      $pdo->exec("DELETE FROM heats WHERE event_id={$eventId} AND is_auto_final=1");
    }

    $scores = entry_scores($eventId);
    $eligible = [];
    foreach ($scores as $row) {
      if ($event['race_type'] === 'TRACK') {
        $metric = $row['best_time'];
      } elseif ($event['race_type'] === 'FUNRUN') {
        $metric = $row['sum_time'];
      } else {
        $metric = $row['total_points'];
      }
      if ($metric === null || $metric === '') continue;
      $eligible[] = ['entry_id' => $row['entry_id'], 'metric' => (float)$metric];
    }

    usort($eligible, function($a, $b) use ($event) {
      if ($event['race_type'] === 'COURSING') return $b['metric'] <=> $a['metric'];
      return $a['metric'] <=> $b['metric'];
    });

    $groups = array_chunk($eligible, (int)$event['final_size']);
    $finalCodes = ['FINAL_A','FINAL_B','FINAL_C'];
    $insertHeat = $pdo->prepare('INSERT INTO heats(event_id,code,heat_type,is_auto_final,ordering) VALUES (?,?,?,?,?)');
    $insertAssign = $pdo->prepare('INSERT INTO finals_assignments(event_id,final_heat_id,entry_id,seed_rank) VALUES (?,?,?,?)');

    foreach ($groups as $i => $group) {
      if ($i >= 3) break;
      $code = $finalCodes[$i];
      $insertHeat->execute([$eventId, $code, $code, 1, $i+1]);
      $heatId = (int)$pdo->lastInsertId();
      foreach ($group as $seed => $row) {
        $insertAssign->execute([$eventId, $heatId, $row['entry_id'], $seed + 1]);
      }
    }

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}
