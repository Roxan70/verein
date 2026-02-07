<?php
session_start();
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/view.php';
require_once __DIR__ . '/../lib/results.php';

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
  if (isset($err)) echo '<p class="err">'.htmlspecialchars($err).'</p>';
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
  echo '<form method="post"><input type="hidden" name="new_event" value="1"><input name="title" placeholder="Event"><select name="race_type"><option>TRACK</option><option>COURSING</option><option>FUNRUN</option></select><select name="event_lang"><option>de</option><option>en</option><option>hu</option><option>cs</option><option>sk</option></select><input name="event_date" type="date"><input name="venue" placeholder="Ort"><input name="final_size" type="number" value="6"><button>Speichern</button></form>';
  echo '<table><tr><th>ID</th><th>Titel</th><th>Typ</th><th>Sprache</th><th>Aktion</th></tr>';
  foreach ($events as $e) echo '<tr><td>'.$e['id'].'</td><td>'.htmlspecialchars($e['title']).'</td><td>'.$e['race_type'].'</td><td>'.$e['event_lang'].'</td><td><a href="/event?id='.$e['id'].'">Öffnen</a></td></tr>';
  echo '</table>';
  render_footer(); exit;
}

if ($path === '/event') {
  $eventId=(int)($_GET['id']??0);
  $event=$pdo->query('SELECT * FROM events WHERE id='.$eventId)->fetch(PDO::FETCH_ASSOC);
  render_header('Event', $dict);
  echo '<h2>3) Event-Zentrale (Renntag-Menü)</h2><p>'.htmlspecialchars($event['title'] ?? 'Unbekannt').'</p>';
  echo '<div class="grid">';
  foreach ([
    '/owners'=>'4) Owners','/dogs'=>'5) Dogs','/entries?event_id='.$eventId=>'6) Entries','/vet?event_id='.$eventId=>'7) Vet-Check','/heats?event_id='.$eventId=>'8) Heats','/timing?event_id='.$eventId=>'9) Timing','/results/live?event_id='.$eventId=>'10) Results live','/results/final?event_id='.$eventId=>'10) Results final','/finals?event_id='.$eventId=>'11) Finals','/exports?event_id='.$eventId=>'12) Exports','/catalog?event_id='.$eventId=>'13) Catalog'
  ] as $url=>$label) echo '<a class="btn" href="'.$url.'">'.$label.'</a>';
  echo '</div>';
  render_footer(); exit;
}

if ($path === '/owners') {
  require_role(['admin','organizer','clerk']);
  if ($method==='POST') {
    $reuse = isset($_POST['consent_reuse_future']) ? 1 : 0;
    $blocked = $reuse ? 0 : 1;
    $stmt=$pdo->prepare('INSERT INTO owners(name,street,zip,city,country,phone,email,consent_event_processing,consent_reuse_future,is_blocked) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$_POST['name'],$_POST['street'],$_POST['zip'],$_POST['city'],$_POST['country'],$_POST['phone'],$_POST['email'],isset($_POST['consent_event_processing'])?1:0,$reuse,$blocked]);
  }
  $rows=$pdo->query('SELECT * FROM owners ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Owners', $dict);
  echo '<h2>4) Owners</h2><form method="post" class="formgrid"><input required name="name" placeholder="Name"><input required name="street" placeholder="Straße"><input required name="zip" placeholder="PLZ"><input required name="city" placeholder="Ort"><input required name="country" placeholder="Land"><input name="phone" placeholder="Telefon"><input name="email" placeholder="E-Mail"><label><input type="checkbox" name="consent_event_processing">consent_event_processing</label><label><input type="checkbox" name="consent_reuse_future">consent_reuse_future</label><button>Speichern</button></form>';
  echo '<table><tr><th>Name</th><th>Adresse</th><th>Consent</th><th>Blocked</th></tr>';
  foreach($rows as $r) echo '<tr><td>'.htmlspecialchars($r['name']).'</td><td>'.htmlspecialchars($r['street'].' '.$r['zip'].' '.$r['city'].' '.$r['country']).'</td><td>'.$r['consent_event_processing'].'/'.$r['consent_reuse_future'].'</td><td>'.$r['is_blocked'].'</td></tr>';
  echo '</table>';
  render_footer(); exit;
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
  foreach($owners as $o) echo '<option value="'.$o['id'].'">'.htmlspecialchars($o['name']).'</option>'; echo '</select><input name="registration_no" placeholder="RegNo"><button>Speichern</button></form>';
  echo '<table><tr><th>Name</th><th>Owner</th></tr>';
  foreach($rows as $r) echo '<tr><td>'.htmlspecialchars($r['name']).'</td><td>'.htmlspecialchars($r['owner_name']).'</td></tr>';
  echo '</table>';
  render_footer(); exit;
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
  echo '<h2>6) Entries</h2><form method="post"><select name="dog_id">'; foreach($dogs as $d) echo '<option value="'.$d['id'].'">'.htmlspecialchars($d['name']).'</option>'; echo '</select><input name="category" placeholder="Kategorie"><input name="distance_m" type="number" placeholder="Distanz"><input name="entry_no" placeholder="StartNr"><button>Speichern</button></form>';
  echo '<table><tr><th>Dog</th><th>Kategorie</th><th>Startnr</th></tr>'; foreach($rows as $r) echo '<tr><td>'.htmlspecialchars($r['dog_name']).'</td><td>'.htmlspecialchars($r['category']).'</td><td>'.htmlspecialchars($r['entry_no']).'</td></tr>'; echo '</table>';
  render_footer(); exit;
}

if ($path === '/vet') {
  require_role(['admin','organizer','vet']);
  $eventId=(int)($_GET['event_id']??0);
  render_header('Vet', $dict); echo '<h2>7) Vet-Check</h2><p>Status/Notizen pro Hund (CRUD im Tabellenmodus).</p>'; render_footer(); exit;
}
if ($path === '/heats') {
  require_role(['admin','organizer','timekeeper']);
  $eventId=(int)($_GET['event_id']??0);
  if ($method==='POST') {
    $stmt=$pdo->prepare('INSERT INTO heats(event_id,code,heat_type,is_auto_final,ordering) VALUES (?,?,?,?,?)');
    $stmt->execute([$eventId,$_POST['code'],$_POST['heat_type'],0,(int)$_POST['ordering']]);
  }
  $rows=$pdo->query('SELECT * FROM heats WHERE event_id='.$eventId.' ORDER BY ordering,id')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Heats', $dict); echo '<h2>8) Heats</h2><form method="post"><input name="code"><select name="heat_type"><option>HEAT1</option><option>HEAT2</option><option>FINAL_A</option><option>FINAL_B</option><option>FINAL_C</option><option>COURSING_RUN1</option><option>COURSING_RUN2</option></select><input name="ordering" type="number" value="1"><button>Speichern</button></form><ul>';
  foreach($rows as $r) echo '<li>'.htmlspecialchars($r['code']).' '.$r['heat_type'].'</li>'; echo '</ul>';
  render_footer(); exit;
}
if ($path === '/timing') {
  require_role(['admin','organizer','timekeeper']);
  render_header('Timing', $dict); echo '<h2>9) Timing</h2><p>Track: Zeit+Status / Coursing: Punkte+Status, optimistic locking via updated_at.</p>'; render_footer(); exit;
}
if ($path === '/results/live') {
  $eventId=(int)($_GET['event_id']??0); $scores=entry_scores($eventId);
  render_header('Results live', $dict); echo '<h2>10) Results live</h2><meta http-equiv="refresh" content="12"><table><tr><th>Dog</th><th>Owner</th><th>Best</th><th>Sum</th><th>Points</th></tr>';
  foreach($scores as $s) echo '<tr><td>'.htmlspecialchars($s['dog_name']).'</td><td>'.htmlspecialchars($s['owner_name']).'</td><td>'.$s['best_time'].'</td><td>'.$s['sum_time'].'</td><td>'.$s['total_points'].'</td></tr>';
  echo '</table>'; render_footer(); exit;
}
if ($path === '/results/final') {
  $eventId=(int)($_GET['event_id']??0); $scores=entry_scores($eventId);
  render_header('Results final', $dict); echo '<h2>10) Results final</h2><table><tr><th>Dog</th><th>Best</th><th>Sum</th><th>Points</th></tr>';
  foreach($scores as $s) echo '<tr><td>'.htmlspecialchars($s['dog_name']).'</td><td>'.$s['best_time'].'</td><td>'.$s['sum_time'].'</td><td>'.$s['total_points'].'</td></tr>';
  echo '</table>'; render_footer(); exit;
}
if ($path === '/finals') {
  require_role(['admin','organizer']);
  $eventId=(int)($_GET['event_id']??0);
  if ($method==='POST' && isset($_POST['auto'])) auto_create_finals($eventId, isset($_POST['recreate']));
  $rows=$pdo->query('SELECT * FROM heats WHERE event_id='.$eventId.' AND heat_type LIKE "FINAL_%" ORDER BY ordering')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Finals', $dict); echo '<h2>11) Finals</h2><form method="post"><button name="auto" value="1">Finale automatisch erstellen</button><label><input type="checkbox" name="recreate">Neu erstellen</label></form><ul>'; foreach($rows as $r) echo '<li>'.htmlspecialchars($r['code']).' auto='.$r['is_auto_final'].'</li>'; echo '</ul>'; render_footer(); exit;
}
if ($path === '/exports') {
  $eventId=(int)($_GET['event_id']??0);
  render_header('Exports', $dict); echo '<h2>12) Exports</h2><a class="btn" href="/export/pdf?event_id='.$eventId.'&mode=program">PDF Programm</a><a class="btn" href="/export/pdf?event_id='.$eventId.'&mode=results">PDF Ergebnisse</a><a class="btn" href="/export/xls?event_id='.$eventId.'&mode=program">XLS Programm</a><a class="btn" href="/export/xls?event_id='.$eventId.'&mode=results">XLS Ergebnisse</a>'; render_footer(); exit;
}
if ($path === '/export/pdf') {
  header('Content-Type: text/html; charset=utf-8');
  echo '<html><body><h1>PDF Placeholder</h1><p>In Produktion via Dompdf/mPDF eingebettet.</p></body></html>'; exit;
}
if ($path === '/export/xls') {
  header('Content-Type: application/vnd.ms-excel');
  header('Content-Disposition: attachment; filename="export.xls"');
  echo '<table><tr><th>Mode</th><th>Event</th></tr><tr><td>'.htmlspecialchars($_GET['mode']??'').'</td><td>'.(int)($_GET['event_id']??0).'</td></tr></table>'; exit;
}
if ($path === '/catalog') {
  $eventId=(int)($_GET['event_id']??0);
  if ($method==='POST') {
    $last=(int)$pdo->query('SELECT COALESCE(MAX(version_no),1) FROM catalog_snapshots WHERE event_id='.$eventId)->fetchColumn();
    $isFirst=$pdo->query('SELECT COUNT(*) FROM catalog_snapshots WHERE event_id='.$eventId)->fetchColumn()==0;
    $version=$isFirst?1:$last+1;
    $label=$isFirst?'Original':'v'.$version;
    $stmt=$pdo->prepare('INSERT INTO catalog_snapshots(event_id,version_no,footer_label,include_cover,include_officials,include_startlists,include_results,include_rules,include_sponsors) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$eventId,$version,$label,isset($_POST['cover'])?1:0,isset($_POST['officials'])?1:0,isset($_POST['startlists'])?1:0,isset($_POST['results'])?1:0,isset($_POST['rules'])?1:0,isset($_POST['sponsors'])?1:0]);
  }
  $rows=$pdo->query('SELECT * FROM catalog_snapshots WHERE event_id='.$eventId.' ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
  render_header('Catalog', $dict); echo '<h2>13) Catalog</h2><form method="post"><label><input type="checkbox" checked name="cover">Deckblatt</label><label><input type="checkbox" checked name="officials">Officials/Infos</label><label><input type="checkbox" checked name="startlists">Startlisten</label><label><input type="checkbox" checked name="results">Ergebnisse</label><label><input type="checkbox" checked name="rules">Regelauszug</label><label><input type="checkbox" checked name="sponsors">Sponsorenblock</label><button>Snapshot + PDF</button></form><ul>';
  foreach($rows as $r) echo '<li>'.$r['footer_label'].' ('.$r['created_at'].')</li>'; echo '</ul><a class="btn" href="/catalog/pdf?event_id='.$eventId.'">PDF</a><a class="btn" href="/catalog/snapshot_and_pdf?event_id='.$eventId.'">snapshot_and_pdf</a>'; render_footer(); exit;
}
if ($path === '/catalog/pdf' || $path === '/catalog/snapshot_and_pdf') {
  echo '<html><body><h1>Catalog PDF Placeholder</h1></body></html>'; exit;
}

http_response_code(404);
echo '404';
