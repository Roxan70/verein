# EU Windhound Race Suite (lokal, offline-fähig)

Lokales Renntagssystem auf Windows-Laptop mit SQLite als zentraler Datenbank. Clients greifen im lokalen WLAN per Browser zu.

## Enthalten
- Vollständiges SQLite Schema: `db/schema.sql`
- PHP-Server mit allen geforderten Screens/Routen: `public/index.php`
- Rollenmodell inkl. Datenschutzsicht für Timekeeper
- Vet-Check pro Entry mit `vet_ok` + `vet_note`
- Timing mit Track/Funrun-Zeit bzw. Coursing-Punkten, Status + `dq_reason`, Optimistic Locking per `updated_at`
- Exporte: echtes XLS (HTML-XLS) + echtes PDF (generiertes PDF)
- Katalog-Snapshots: `Original`, danach `v2`, `v3`, ...

## Windows Portable Runtime
Unter `runtime/php/` liegt die portable Runtime-Struktur (`php.exe`, `php.ini`, `ext/`).

> Hinweis: In dieser Entwicklungsumgebung konnte die offizielle Windows-PHP-ZIP nicht direkt geladen werden; deshalb sind Platzhalterdateien enthalten. Für Release-Build bitte durch echte PHP-Windows-x64-Binaries ersetzen.

## Installer (Setup.exe)
- Inno Setup Script: `installer/eu-windhound.iss`
- Startscript: `installer/start_server.bat` (mit PID-Datei, verhindert Mehrfachstart)
- Stopscript: `installer/stop_server.bat`

### Build
1. Inno Setup 6 installieren.
2. `installer/eu-windhound.iss` öffnen.
3. Build ausführen → `Setup.exe`.

### Verhalten
- Installation nach `%ProgramFiles%\EU Windhound Race Suite`
- Desktop-/Startmenü-Links: „EU Windhound Race Suite“
- Start über `start_server.bat` startet lokalen Server und öffnet `http://localhost:8080/login`
