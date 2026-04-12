# CT-Orderlauf

Mobile, offlinefähige Web-App für Küchenbestellungen (PHP 8, MySQL, Alpine.js, IndexedDB, Service Worker).

## Lizenz

GNU AGPLv3 – siehe [LICENSE](LICENSE).

## Schnellstart mit Docker

1. Repository klonen.
2. Optional `.env` aus `.env.example` anlegen (Docker Compose setzt Variablen bereits).
3. Build & Start:

```bash
docker compose build
docker compose up -d
```

4. App: **http://localhost:8080**
5. Standard-Login: `admin` / `admin123` (in Produktion sofort ändern).

### Datenbank

MySQL wird mit [database/schema.sql](database/schema.sql) initialisiert (Admin-User, Tabellen).

## Lokale Entwicklung ohne Docker

- PHP 8.2+ mit `pdo_mysql`
- MySQL 8
- PDF: [lib/fpdf.php](lib/fpdf.php) (FPDF 1.86) ist im Repo enthalten.

Apache **Document Root** auf das Verzeichnis `public/` legen, `mod_rewrite` aktivieren.

`.env` anlegen (siehe `.env.example`) und `DB_*` auf die lokale Datenbank setzen, danach `database/schema.sql` importieren.

## Projektstruktur

- `public/` – Document Root (`index.php`, Assets, `sw.js`, `manifest.json`)
- `app/` – PHP (Controller, Repositories, Services, Views)
- `config/` – Bootstrap & Konfiguration
- `database/` – SQL-Schema & Beispiel-CSV unter `seeds/`

## CSV-Import

Formate siehe Pflichtenheft-Ergänzungen (UTF-8, Semikolon, definierte Kopfzeilen).

## Sicherheit

- Passwort des Standard-Users nach dem ersten Login ändern.
- Produktiv nur unter **HTTPS** betreiben.
