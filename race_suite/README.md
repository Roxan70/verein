# EU Windhound Race Suite (lokal, offline-fähig)

Dieses Projekt liefert ein einzelnes, lokales Renntagssystem für einen Windows-Laptop mit SQLite als zentraler Datenbank. Tablets/Smartphones im selben WLAN nutzen den Browser als Client.

## Projektstruktur

- `config/config.php` – App-Konfiguration (SQLite-Pfad, Sprachen, Auto-Refresh).
- `db/schema.sql` – vollständiges SQLite-DDL.
- `lib/` – Datenbank, Auth, i18n, Berechnungslogik, Rendering-Helfer.
- `lang/{de,en,hu,cs,sk}.php` – Sprachdateien.
- `public/index.php` – kompletter Server inkl. aller geforderten Routen/Screens.
- `public/assets/` – UI-Styling und JS.
- `installer/` – Windows-Installer-Dateien.

## Routing

Erfüllt:

- `/login`, `/dashboard`
- `/event?id=`
- `/owners`, `/dogs`, `/entries?event_id=`
- `/vet?event_id=`
- `/heats?event_id=`
- `/timing?event_id=&heat_id=`
- `/results/live?event_id=`, `/results/final?event_id=`
- `/finals?event_id=`
- `/export/pdf?event_id=&mode=program|results`
- `/export/xls?event_id=&mode=program|results`
- `/catalog?event_id=`, `/catalog/pdf`, `/catalog/snapshot_and_pdf`

## Start lokal (Entwicklung)

```bash
cd race_suite/public
php -S 0.0.0.0:8080 index.php
```

Dann im WLAN aufrufen: `http://<LAPTOP-IP>:8080/login`

Default-Admin nach Erststart:
- User: `admin`
- Passwort: `admin123`

## Windows Installer (Setup.exe)

### 1) Voraussetzungen auf Build-Rechner
- Inno Setup 6 installiert.
- PHP Runtime in `runtime/php/` beilegen (embedded, ohne Endnutzerinstallation).

### 2) Installer bauen

- Datei: `installer/eu-windhound.iss` in Inno Setup öffnen.
- **Build** klicken → erzeugt `Setup.exe`.

### 3) Verhalten nach Installation

- Installer kopiert App nach `%ProgramFiles%\EU Windhound Race Suite`.
- Erstellt Desktop-Shortcut „EU Windhound Race Suite“.
- Shortcut startet `start_server.bat`.
- Batch startet den lokalen PHP-Webserver automatisch und öffnet den Browser auf `http://localhost:8080/login`.

## Datenschutz / Rollen / Logik

- Owner-Pflichtfelder und Consent-Felder in Schema + Owners-UI.
- `consent_reuse_future=false` setzt `is_blocked=1`.
- Timekeeper sieht im Result/Timing-Kontext nur Hund + Startnummer + Owner-Name.
- Rennen: TRACK / COURSING / FUNRUN.
- Berechnung:
  - TRACK FIELD: `best_time = MIN(HEAT1, HEAT2)`
  - TRACK SOLO/FUNRUN: `sum_time = HEAT1 + HEAT2`
  - COURSING: `S+A+E+F+H` je Run, beide Runs summiert
  - DQ/NS/NA/DIS/V über `is_counted=0` aus Wertung.
- Auto-Finale mit „Neu erstellen“ löscht alte Auto-Finale inkl. Assignments/Performance und erzeugt neu.
