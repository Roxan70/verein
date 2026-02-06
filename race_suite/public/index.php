<?php
session_start();
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/view.php';
require_once __DIR__ . '/../lib/results.php';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function as_counted(string $status): int { return $status === 'OK' ? 1 : 0; }

function xls_table(string $filename, string $title, array $headers, array $rows): void {
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$filename.'.xls"');
  echo '<html><meta charset="utf-8"><body><h2>'.h($title).'</h2><table border="1"><tr>';
  foreach ($headers as $h) echo '<th>'.h($h).'</th>';
  echo '</tr>';
  foreach ($rows as $r) { echo '<tr>'; foreach ($r as $c) echo '<td>'.h((string)$c).'</td>'; echo '</tr>'; }
  echo '</table></body></html>';
}

function simple_pdf(string $title, array $lines): void {
  $content = "BT /F1 18 Tf 50 800 Td (" . addslashes($title) . ") Tj ";
  $y = 780;
  foreach ($lines as $line) {
    $safe = preg_replace('/[^\x20-\x7E]/', '?', $line);
    $content .= " /F1 10 Tf 50 {$y} Td (" . addslashes($safe) . ") Tj ";
    $y -= 14;
    if ($y < 50) break;
  }
  $content .= ' ET';
  $stream = $content;

  $pdf = "%PDF-1.4\n";
  $o1 = strlen($pdf); $pdf .= "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
  $o2 = strlen($pdf); $pdf .= "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n";
  $o3 = strlen($pdf); $pdf .= "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj\n";
  $o4 = strlen($pdf); $pdf .= "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";
  $o5 = strlen($pdf); $pdf .= "5 0 obj << /Length " . strlen($stream) . " >> stream\n{$stream}\nendstream endobj\n";
  $xref = strlen($pdf);
  $pdf .= "xref\n0 6\n0000000000 65535 f \n";
  foreach ([$o1,$o2,$o3,$o4,$o5] as $off) $pdf .= sprintf("%010d 00000 n \n", $off);
  $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="export.pdf"');
  echo $pdf;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'de';
$_SESSION['lang'] = $lang;
$dict = load_lang($lang);

if ($path === '/assets/styles.css') { header('Content-Type: text/css'); readfile(__DIR__ . '/assets/styles.css'); exit; }
if ($path === '/assets/app.js') { header('Content-Type: application/javascript'); readfile(__DIR__ . '/assets/app.js'); exit; }
if ($path === '/logout') { logout(); header('Location: /login'); exit; }

if ($path === '/login') {
  if ($method === 'POST') {
    if (login($_POST['username'] ?? '', $_POST['password'] ?? '')) { header('Location: /dashboard'); exit; }
    $err = 'Login fehlgeschlagen';
  }
  render_header('Login', $dict);
  echo '<h2>1) Login + Sprachwahl</h2>';
  if (isset($err)) echo '<p class="err">'.h($err).'</p>';
  echo '<form method="post"><label>Username <input name="username"></label><label>Password <input type="password" name="password"></label><label>Sprache<select name="lang"><option>de</option><option>en</option><option>hu</option><option>cs</option><option>sk</option></select></label><button>Login</button></form>';
  render_footer(); exit;
}

$user = require_login();
$pdo = db();

if ($path === '/' || $path === '/dashboard') {
  if ($method==='POST' && isset($_POST['new_event'])) {
    $stmt=$pdo->prepare('INSERT INTO events(title,race_type,event_lang,event_date,venue,final_size) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$_POST['title'],$_POST['race_type'],$_POST['event_lang'],$_POST['event_date'],$_POST['venue'],(int)($_POST['final_size']?:6)]);
  }
  $events = $pdo->query('SELECT * FROM events ORDER BY event_date DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Dashboard', $dict);
  echo '<h2>2) Dashboard (Eventliste)</h2>';
  echo '<form method="post"><input type="hidden" name="new_event" value="1"><input name="title" placeholder="Event" required><select name="race_type"><option>TRACK</option><option>COURSING</option><option>FUNRUN</option></select><select name="event_lang"><option>de</option><option>en</option><option>hu</option><option>cs</option><option>sk</option></select><input name="event_date" type="date"><input name="venue" placeholder="Ort"><input name="final_size" type="number" value="6"><button>Speichern</button></form>';
  echo '<table><tr><th>ID</th><th>Titel</th><th>Typ</th><th>Sprache</th><th>Aktion</th></tr>';
  foreach ($events as $e) echo '<tr><td>'.$e['id'].'</td><td>'.h($e['title']).'</td><td>'.$e['race_type'].'</td><td>'.$e['event_lang'].'</td><td><a href="/event?id='.$e['id'].'">Öffnen</a></td></tr>';
  echo '</table>'; render_footer(); exit;
}

if ($path === '/event') {
  $eventId=(int)($_GET['id']??0);
  $event=$pdo->query('SELECT * FROM events WHERE id='.$eventId)->fetch(PDO::FETCH_ASSOC);
  render_header('Event', $dict);
  echo '<h2>3) Event-Zentrale (Renntag-Menü)</h2><p>'.h($event['title'] ?? 'Unbekannt').'</p><div class="grid">';
  foreach ([
    '/owners'=>'4) Owners','/dogs'=>'5) Dogs','/entries?event_id='.$eventId=>'6) Entries','/vet?event_id='.$eventId=>'7) Vet-Check','/heats?event_id='.$eventId=>'8) Heats','/timing?event_id='.$eventId=>'9) Timing','/results/live?event_id='.$eventId=>'10) Results live','/results/final?event_id='.$eventId=>'10) Results final','/finals?event_id='.$eventId=>'11) Finals','/exports?event_id='.$eventId=>'12) Exports','/catalog?event_id='.$eventId=>'13) Catalog'
  ] as $url=>$label) echo '<a class="btn" href="'.$url.'">'.$label.'</a>';
  echo '</div>'; render_footer(); exit;
}

if ($path === '/owners') {
  require_role(['admin','organizer','clerk']);
  if ($method==='POST') {
    $reuse = isset($_POST['consent_reuse_future']) ? 1 : 0;
    $stmt=$pdo->prepare('INSERT INTO owners(name,street,zip,city,country,phone,email,consent_event_processing,consent_reuse_future,is_blocked) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$_POST['name'],$_POST['street'],$_POST['zip'],$_POST['city'],$_POST['country'],$_POST['phone'],$_POST['email'],isset($_POST['consent_event_processing'])?1:0,$reuse,$reuse?0:1]);
  }
  $rows=$pdo->query('SELECT * FROM owners ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Owners', $dict);
  echo '<h2>4) Owners</h2><form method="post" class="formgrid"><input required name="name" placeholder="Name"><input required name="street" placeholder="Straße"><input required name="zip" placeholder="PLZ"><input required name="city" placeholder="Ort"><input required name="country" placeholder="Land"><input name="phone" placeholder="Telefon"><input name="email" placeholder="E-Mail"><label><input type="checkbox" name="consent_event_processing">consent_event_processing</label><label><input type="checkbox" name="consent_reuse_future">consent_reuse_future</label><button>Speichern</button></form>';
  echo '<table><tr><th>Name</th><th>Adresse</th><th>Consent</th><th>Blocked</th></tr>';
  foreach($rows as $r) echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['street'].' '.$r['zip'].' '.$r['city'].' '.$r['country']).'</td><td>'.$r['consent_event_processing'].'/'.$r['consent_reuse_future'].'</td><td>'.$r['is_blocked'].'</td></tr>';
  echo '</table>'; render_footer(); exit;
}

if ($path === '/dogs') {
  require_role(['admin','organizer','clerk']);
  if ($method==='POST') {
    $stmt=$pdo->prepare('INSERT INTO dogs(name,breed,sex,dob,owner_id,registration_no) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$_POST['name'],$_POST['breed'],$_POST['sex'],$_POST['dob'],(int)$_POST['owner_id'],$_POST['registration_no']]);
  }
  $owners=$pdo->query('SELECT id,name FROM owners ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
  $rows=$pdo->query('SELECT d.*,o.name owner_name FROM dogs d JOIN owners o ON o.id=d.owner_id ORDER BY d.id DESC')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Dogs', $dict);
  echo '<h2>5) Dogs</h2><form method="post" class="formgrid"><input name="name" placeholder="Name"><input name="breed" placeholder="Rasse"><input name="sex" placeholder="Geschlecht"><input type="date" name="dob"><select name="owner_id">';
  foreach($owners as $o) echo '<option value="'.$o['id'].'">'.h($o['name']).'</option>'; echo '</select><input name="registration_no" placeholder="RegNo"><button>Speichern</button></form>';
  echo '<table><tr><th>Name</th><th>Owner</th></tr>'; foreach($rows as $r) echo '<tr><td>'.h($r['name']).'</td><td>'.h($r['owner_name']).'</td></tr>'; echo '</table>'; render_footer(); exit;
}

if ($path === '/entries') {
  $eventId=(int)($_GET['event_id']??0);
  if ($method==='POST') {
    $stmt=$pdo->prepare('INSERT OR IGNORE INTO entries(event_id,dog_id,category,distance_m,entry_no) VALUES (?,?,?,?,?)');
    $stmt->execute([$eventId,(int)$_POST['dog_id'],$_POST['category'],(int)$_POST['distance_m'],$_POST['entry_no']]);
  }
  $dogs=$pdo->query('SELECT id,name FROM dogs ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
  $rows=$pdo->query('SELECT e.*,d.name dog_name FROM entries e JOIN dogs d ON d.id=e.dog_id WHERE event_id='.$eventId)->fetchAll(PDO::FETCH_ASSOC);
  render_header('Entries', $dict);
  echo '<h2>6) Entries</h2><form method="post"><select name="dog_id">'; foreach($dogs as $d) echo '<option value="'.$d['id'].'">'.h($d['name']).'</option>'; echo '</select><input name="category" placeholder="Kategorie"><input name="distance_m" type="number" placeholder="Distanz"><input name="entry_no" placeholder="StartNr"><button>Speichern</button></form>';
  echo '<table><tr><th>Dog</th><th>Kategorie</th><th>Startnr</th></tr>'; foreach($rows as $r) echo '<tr><td>'.h($r['dog_name']).'</td><td>'.h($r['category']).'</td><td>'.h($r['entry_no']).'</td></tr>'; echo '</table>'; render_footer(); exit;
}

if ($path === '/vet') {
  require_role(['admin','organizer','vet']);
  $eventId=(int)($_GET['event_id']??0);
  if ($method==='POST') {
    $stmt = $pdo->prepare('INSERT INTO vet_checks(event_id,entry_id,vet_ok,vet_note,checked_by,checked_at,updated_at) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON CONFLICT(event_id,entry_id) DO UPDATE SET vet_ok=excluded.vet_ok, vet_note=excluded.vet_note, checked_by=excluded.checked_by, checked_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP');
    $stmt->execute([$eventId,(int)$_POST['entry_id'],isset($_POST['vet_ok'])?1:0,$_POST['vet_note'],$user['id']]);
  }
  $rows=$pdo->query('SELECT e.id entry_id,e.entry_no,d.name dog_name,o.name owner_name,COALESCE(v.vet_ok,0) vet_ok,COALESCE(v.vet_note,"") vet_note FROM entries e JOIN dogs d ON d.id=e.dog_id JOIN owners o ON o.id=d.owner_id LEFT JOIN vet_checks v ON v.entry_id=e.id AND v.event_id=e.event_id WHERE e.event_id='.$eventId.' ORDER BY e.id')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Vet', $dict);
  echo '<h2>7) Vet-Check</h2><table><tr><th>Startnr</th><th>Dog</th><th>Owner</th><th>vet_ok</th><th>vet_note</th><th>Save</th></tr>';
  foreach ($rows as $r) {
    echo '<tr><form method="post"><input type="hidden" name="entry_id" value="'.$r['entry_id'].'"><td>'.h($r['entry_no']).'</td><td>'.h($r['dog_name']).'</td><td>'.h($r['owner_name']).'</td><td><input type="checkbox" name="vet_ok" '.($r['vet_ok']?'checked':'').'></td><td><input name="vet_note" value="'.h($r['vet_note']).'"></td><td><button>Speichern</button></td></form></tr>';
  }
  echo '</table>'; render_footer(); exit;
}

if ($path === '/heats') {
  require_role(['admin','organizer']);
  $eventId=(int)($_GET['event_id']??0);
  if ($method==='POST') {
    if (isset($_POST['delete_id'])) {
      $stmt=$pdo->prepare('DELETE FROM heats WHERE id=? AND event_id=?');
      $stmt->execute([(int)$_POST['delete_id'],$eventId]);
    } else {
      $stmt=$pdo->prepare('INSERT INTO heats(event_id,code,heat_type,is_auto_final,ordering) VALUES (?,?,?,?,?)');
      $stmt->execute([$eventId,$_POST['code'],$_POST['heat_type'],0,(int)$_POST['ordering']]);
    }
  }
  $rows=$pdo->query('SELECT * FROM heats WHERE event_id='.$eventId.' ORDER BY ordering,id')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Heats', $dict);
  echo '<h2>8) Heats</h2><form method="post"><input name="code" required><select name="heat_type"><option>HEAT1</option><option>HEAT2</option><option>FINAL_A</option><option>FINAL_B</option><option>FINAL_C</option><option>COURSING_RUN1</option><option>COURSING_RUN2</option></select><input name="ordering" type="number" value="1"><button>Speichern</button></form><table><tr><th>Code</th><th>Typ</th><th>Auto</th><th>Delete</th></tr>';
  foreach($rows as $r) echo '<tr><td>'.h($r['code']).'</td><td>'.$r['heat_type'].'</td><td>'.$r['is_auto_final'].'</td><td><form method="post"><input type="hidden" name="delete_id" value="'.$r['id'].'"><button>Löschen</button></form></td></tr>';
  echo '</table>'; render_footer(); exit;
}

if ($path === '/timing') {
  require_role(['admin','organizer','timekeeper']);
  $eventId=(int)($_GET['event_id']??0);
  $heatId=(int)($_GET['heat_id']??0);
  $heat = $heatId ? $pdo->query('SELECT * FROM heats WHERE id='.$heatId.' AND event_id='.$eventId)->fetch(PDO::FETCH_ASSOC) : null;

  if ($method==='POST' && $heat) {
    $status = $_POST['status'];
    $isCounted = as_counted($status);
    $lock = $_POST['lock_updated_at'] ?? '';
    $existing = $pdo->prepare('SELECT updated_at FROM performances WHERE heat_id=? AND entry_id=?');
    $existing->execute([$heatId, (int)$_POST['entry_id']]);
    $current = $existing->fetchColumn();
    if ($current && $lock && $current !== $lock) {
      $_SESSION['timing_conflict'] = 'Konflikt: Datensatz wurde inzwischen aktualisiert.';
    } else {
      $stmt = $pdo->prepare('INSERT INTO performances(event_id,heat_id,entry_id,time_seconds,points_s,points_a,points_e,points_f,points_h,status,dq_reason,is_counted,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?) ON CONFLICT(heat_id,entry_id) DO UPDATE SET time_seconds=excluded.time_seconds, points_s=excluded.points_s, points_a=excluded.points_a, points_e=excluded.points_e, points_f=excluded.points_f, points_h=excluded.points_h, status=excluded.status, dq_reason=excluded.dq_reason, is_counted=excluded.is_counted, updated_at=CURRENT_TIMESTAMP, updated_by=excluded.updated_by');
      $stmt->execute([
        $eventId, $heatId, (int)$_POST['entry_id'],
        $_POST['time_seconds'] !== '' ? (float)$_POST['time_seconds'] : null,
        $_POST['points_s'] !== '' ? (int)$_POST['points_s'] : null,
        $_POST['points_a'] !== '' ? (int)$_POST['points_a'] : null,
        $_POST['points_e'] !== '' ? (int)$_POST['points_e'] : null,
        $_POST['points_f'] !== '' ? (int)$_POST['points_f'] : null,
        $_POST['points_h'] !== '' ? (int)$_POST['points_h'] : null,
        $status, $_POST['dq_reason'], $isCounted, $user['id']
      ]);
    }
    header('Location: /timing?event_id='.$eventId.'&heat_id='.$heatId);
    exit;
  }

  $heats = $pdo->query('SELECT id,code,heat_type FROM heats WHERE event_id='.$eventId.' ORDER BY ordering,id')->fetchAll(PDO::FETCH_ASSOC);
  $rows = [];
  if ($heat) {
    $rows=$pdo->query('SELECT e.id entry_id,e.entry_no,d.name dog_name,o.name owner_name,p.time_seconds,p.points_s,p.points_a,p.points_e,p.points_f,p.points_h,p.status,p.dq_reason,p.updated_at FROM entries e JOIN dogs d ON d.id=e.dog_id JOIN owners o ON o.id=d.owner_id LEFT JOIN performances p ON p.entry_id=e.id AND p.heat_id='.$heatId.' WHERE e.event_id='.$eventId.' ORDER BY e.id')->fetchAll(PDO::FETCH_ASSOC);
  }
  render_header('Timing', $dict);
  echo '<h2>9) Timing</h2><form method="get"><input type="hidden" name="event_id" value="'.$eventId.'"><select name="heat_id"><option value="">Heat wählen</option>';
  foreach ($heats as $h) echo '<option value="'.$h['id'].'" '.($heatId===$h['id']?'selected':'').'>'.h($h['code']).' '.$h['heat_type'].'</option>';
  echo '</select><button>Öffnen</button></form>';
  if (isset($_SESSION['timing_conflict'])) { echo '<p class="err">'.h($_SESSION['timing_conflict']).'</p>'; unset($_SESSION['timing_conflict']); }
  if ($heat) {
    $isCoursing = str_starts_with($heat['heat_type'], 'COURSING_');
    echo '<table><tr><th>Startnr</th><th>Dog</th><th>Owner Name</th><th>Daten</th><th>Status</th><th>DQ Grund</th><th>Save</th></tr>';
    foreach ($rows as $r) {
      echo '<tr><form method="post"><input type="hidden" name="entry_id" value="'.$r['entry_id'].'"><input type="hidden" name="lock_updated_at" value="'.h((string)$r['updated_at']).'"><td>'.h($r['entry_no']).'</td><td>'.h($r['dog_name']).'</td><td>'.h($r['owner_name']).'</td><td>';
      if ($isCoursing) {
        echo 'S <input type="number" name="points_s" value="'.h((string)$r['points_s']).'" class="tiny"> A <input type="number" name="points_a" value="'.h((string)$r['points_a']).'" class="tiny"> E <input type="number" name="points_e" value="'.h((string)$r['points_e']).'" class="tiny"> F <input type="number" name="points_f" value="'.h((string)$r['points_f']).'" class="tiny"> H <input type="number" name="points_h" value="'.h((string)$r['points_h']).'" class="tiny">';
      } else {
        echo '<input type="number" step="0.001" name="time_seconds" value="'.h((string)$r['time_seconds']).'">';
      }
      echo '</td><td><select name="status">';
      foreach (['OK','NS','NA','DIS','V','DQ'] as $s) echo '<option '.(($r['status']??'OK')===$s?'selected':'').'>'.$s.'</option>';
      echo '</select></td><td><input name="dq_reason" value="'.h((string)$r['dq_reason']).'"></td><td><button>Speichern</button></td></form></tr>';
    }
    echo '</table>';
  }
  render_footer(); exit;
}

if ($path === '/results/live' || $path === '/results/final') {
  $eventId=(int)($_GET['event_id']??0); $scores=entry_scores($eventId);
  render_header('Results', $dict); echo '<h2>10) '.($path==='/results/live'?'Results live':'Results final').'</h2>'; if ($path==='/results/live') echo '<meta http-equiv="refresh" content="12">';
  echo '<table><tr><th>Dog</th><th>Owner</th><th>Best</th><th>Sum</th><th>Points</th></tr>';
  foreach($scores as $s) echo '<tr><td>'.h($s['dog_name']).'</td><td>'.h($s['owner_name']).'</td><td>'.$s['best_time'].'</td><td>'.$s['sum_time'].'</td><td>'.$s['total_points'].'</td></tr>';
  echo '</table>'; render_footer(); exit;
}

if ($path === '/finals') {
  require_role(['admin','organizer']);
  $eventId=(int)($_GET['event_id']??0);
  if ($method==='POST' && isset($_POST['auto'])) auto_create_finals($eventId, isset($_POST['recreate']));
  $rows=$pdo->query('SELECT * FROM heats WHERE event_id='.$eventId.' AND heat_type LIKE "FINAL_%" ORDER BY ordering')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Finals', $dict); echo '<h2>11) Finals</h2><form method="post"><button name="auto" value="1">Finale automatisch erstellen</button><label><input type="checkbox" name="recreate">Neu erstellen</label></form><ul>'; foreach($rows as $r) echo '<li>'.h($r['code']).' auto='.$r['is_auto_final'].'</li>'; echo '</ul>'; render_footer(); exit;
}

if ($path === '/exports') {
  $eventId=(int)($_GET['event_id']??0);
  render_header('Exports', $dict); echo '<h2>12) Exports</h2><a class="btn" href="/export/pdf?event_id='.$eventId.'&mode=program">PDF Programm</a><a class="btn" href="/export/pdf?event_id='.$eventId.'&mode=results">PDF Ergebnisse</a><a class="btn" href="/export/xls?event_id='.$eventId.'&mode=program">XLS Programm</a><a class="btn" href="/export/xls?event_id='.$eventId.'&mode=results">XLS Ergebnisse</a>'; render_footer(); exit;
}

if ($path === '/export/xls') {
  $eventId=(int)($_GET['event_id']??0);
  $mode=$_GET['mode']??'program';
  if ($mode==='program') {
    $rows=$pdo->query('SELECT e.entry_no,d.name,o.name owner,e.category,e.distance_m FROM entries e JOIN dogs d ON d.id=e.dog_id JOIN owners o ON o.id=d.owner_id WHERE e.event_id='.$eventId.' ORDER BY e.entry_no,e.id')->fetchAll(PDO::FETCH_ASSOC);
    xls_table('program_'.$eventId,'Programm Export',['Startnr','Dog','Owner','Kategorie','Distanz'],array_map(fn($r)=>[$r['entry_no'],$r['name'],$r['owner'],$r['category'],$r['distance_m']],$rows));
  } else {
    $scores=entry_scores($eventId);
    xls_table('results_'.$eventId,'Ergebnis Export',['Dog','Owner','Best Time','Sum Time','Total Points'],array_map(fn($r)=>[$r['dog_name'],$r['owner_name'],$r['best_time'],$r['sum_time'],$r['total_points']],$scores));
  }
  exit;
}

if ($path === '/export/pdf') {
  $eventId=(int)($_GET['event_id']??0);
  $mode=$_GET['mode']??'program';
  if ($mode==='program') {
    $rows=$pdo->query('SELECT e.entry_no,d.name dog,o.name owner FROM entries e JOIN dogs d ON d.id=e.dog_id JOIN owners o ON o.id=d.owner_id WHERE e.event_id='.$eventId.' ORDER BY e.entry_no,e.id')->fetchAll(PDO::FETCH_ASSOC);
    $lines = array_map(fn($r)=>'Start '.$r['entry_no'].' | '.$r['dog'].' | '.$r['owner'],$rows);
    simple_pdf('Rennprogramm Event '.$eventId,$lines);
  } else {
    $scores=entry_scores($eventId);
    $lines = array_map(fn($r)=>$r['dog_name'].' | Best '.$r['best_time'].' | Sum '.$r['sum_time'].' | Points '.$r['total_points'],$scores);
    simple_pdf('Ergebnisse Event '.$eventId,$lines);
  }
  exit;
}

if ($path === '/catalog') {
  $eventId=(int)($_GET['event_id']??0);
  if ($method==='POST') {
    $count=(int)$pdo->query('SELECT COUNT(*) FROM catalog_snapshots WHERE event_id='.$eventId)->fetchColumn();
    if ($count===0) {
      $version=null; $label='Original';
    } else {
      $version=$count+1; $label='v'.$version;
    }
    $stmt=$pdo->prepare('INSERT INTO catalog_snapshots(event_id,version_no,footer_label,include_cover,include_officials,include_startlists,include_results,include_rules,include_sponsors,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$eventId,$version,$label,isset($_POST['cover'])?1:0,isset($_POST['officials'])?1:0,isset($_POST['startlists'])?1:0,isset($_POST['results'])?1:0,isset($_POST['rules'])?1:0,isset($_POST['sponsors'])?1:0,$user['id']]);
  }
  $rows=$pdo->query('SELECT * FROM catalog_snapshots WHERE event_id='.$eventId.' ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Catalog', $dict); echo '<h2>13) Catalog</h2><form method="post"><label><input type="checkbox" checked name="cover">Deckblatt</label><label><input type="checkbox" checked name="officials">Officials/Infos</label><label><input type="checkbox" checked name="startlists">Startlisten</label><label><input type="checkbox" checked name="results">Ergebnisse</label><label><input type="checkbox" checked name="rules">Regelauszug</label><label><input type="checkbox" checked name="sponsors">Sponsorenblock</label><button>Snapshot + PDF</button></form><ul>';
  foreach($rows as $r) echo '<li>'.h($r['footer_label']).' ('.$r['created_at'].')</li>'; echo '</ul><a class="btn" href="/catalog/pdf?event_id='.$eventId.'">PDF</a><a class="btn" href="/catalog/snapshot_and_pdf?event_id='.$eventId.'">snapshot_and_pdf</a>'; render_footer(); exit;
}

if ($path === '/catalog/pdf' || $path === '/catalog/snapshot_and_pdf') {
  $eventId=(int)($_GET['event_id']??0);
  $rows=$pdo->query('SELECT footer_label,created_at FROM catalog_snapshots WHERE event_id='.$eventId.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
  $lines=array_map(fn($r)=>$r['footer_label'].' '.$r['created_at'],$rows);
  simple_pdf('Catalog Event '.$eventId,$lines); exit;
}

http_response_code(404);
echo '404';
