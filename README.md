# CT-Orderlauf

Web-App für **Küchen-/Lagerbestellungen**: Rundgang nach Lagerorten, Kontrolle, Ausgabe per **E-Mail** (mailto oder SMTP) oder **PDF**. Läuft als **PWA** mit **offlinefähigem** Bestellrunden-Workflow im Browser (**IndexedDB** + **Service Worker**).

**Stack:** PHP **8.2+**, MySQL **8**, Vanilla Router, **Alpine.js** (ESM), kein Node-Build.

## Lizenz

**GNU AGPL v3** – siehe [LICENSE](LICENSE). Bei Weitergabe oder Hosting als Netzdienst gelten die AGPL-Pflichten (Quelltext zugänglich machen usw.).

---

## Funktionen (Kurz)

| Bereich | Inhalt |
|--------|--------|
| **Bestellrunde** | Vorbereiten (Datum, Lieferanten-Zieltag), Rundgang, Kontrolle, Ausgabe – Schritte 1–4 in der UI navigierbar. |
| **Offline** | Aktive Runde, Mengen und Notizen in **IndexedDB**; Stammdaten kommen vom Server und können bei Bedarf nachgeladen werden. |
| **Ausgabe** | Mail-Vorschau, optional **direkter SMTP-Versand**, CC für Bestellkopien, PDF-Anhang pro Lieferant, optional **Outlook-Export** (XML + PDFs, Makro unter `outlook-macro/`). |
| **Stammdaten** | Artikel, Lieferanten, Lagerorte, Import, Einstellungen (rollenbasiert). |

---

## Schnellstart: Docker

1. Repository klonen.
2. Im Projektroot:

```bash
docker compose build
docker compose up -d
```

3. Im Browser: **http://localhost:8080**
4. **Erster Login:** `admin` / `admin123` → Passwort **sofort** ändern (Profil / Benutzerverwaltung).

Die Compose-Datei mountet `database/schema.sql`, `database/seed.sql` und die SQL-Dateien unter `database/migrations/` nach `docker-entrypoint-initdb.d/` (Reihenfolge siehe [docker-compose.yml](docker-compose.yml)). **Nur beim ersten Start** eines **leeren** MySQL-Volumes werden diese Skripte ausgeführt.

**Datenbank zurücksetzen** (löscht alle Container-Daten der DB):

```bash
docker compose down -v && docker compose up -d
```

---

## Installation auf Webspace / Produktion

Die ausführliche Checkliste (PHP-Erweiterungen, Document Root, `.env`, `.htaccess`, HTTPS, Fehlerbilder) steht in:

**[WEBSPACE-INSTALL.md](WEBSPACE-INSTALL.md)**

Kurzfassung:

- **Document Root** muss auf **`public/`** zeigen.
- **`.env`** im Projektroot (nicht unter `public/`) – Vorlage: [.env.example](.env.example).
- **Composer auf dem Server** ist nicht nötig; Autoload über [config/bootstrap.php](config/bootstrap.php).
- **HTTPS** für Produktion und für den Service Worker sinnvoll/praktisch nötig.

---

## Lokale Entwicklung ohne Docker

- PHP **8.2+** mit **`pdo_mysql`**, empfohlen **`mbstring`**
- MySQL **8** (oder kompatible MariaDB-Version)
- Webserver mit Rewrite auf **`public/index.php`** (Apache: `public/.htaccess`, `mod_rewrite`)

`.env` anlegen, Datenbank mit **`database/schema.sql`** einrichten, optional **`database/seed.sql`**. Zusätzliche Änderungen: SQL-Dateien unter **`database/migrations/`** bei bestehenden Installationen **in sinnvoller Reihenfolge** ausführen (Dateinamen enthalten das Datum; bei Unsicherheit die Dateien kurz prüfen oder die Docker-Reihenfolge spiegeln).

**PDF:** [lib/fpdf.php](lib/fpdf.php) (FPDF) liegt im Repository.

---

## Projektstruktur

| Pfad | Rolle |
|------|--------|
| `public/` | Document Root: `index.php`, Assets, `sw.js`, `manifest.json` |
| `app/` | PHP: Router, Controller, Middleware, Repositories, Services, Views |
| `config/` | Bootstrap, App- und DB-Konfiguration |
| `database/` | `schema.sql`, `seed.sql`, `migrations/`, `seeds/` (CSV-Beispiele) |
| `outlook-macro/` | Optional: VBA-Makro + Anleitung für Outlook-Entwürfe |
| `project guidelines and definition/` | Pflichtenheft, UI-/Tech-Notizen (optional, nicht für den Lauf nötig) |

---

## Technische Hinweise

- **Bestellrunde vs. Server:** Mengen und die laufende Runde liegen **nur clientseitig** in IndexedDB. **„Bestellrunde laden“** auf der Startseite ersetzt die lokale Runde durch einen neuen Server-Snapshot – bei laufender Arbeit lieber **fortsetzen** (Startseite) als neu laden.
- **API:** JSON-Endpunkte unter `/api/…` (u. a. Order-Payload, PDF, optional Mail-Versand); CSRF-Token im Layout (`meta name="csrf-token"`).
- **Service Worker:** Cache-Version in `public/sw.js` (`CACHE = '…'`) bei **Änderungen an CSS/JS** anheben, damit Clients die neuen Assets ziehen.
- **CSV-Import:** Formate und Regeln in den Dokumenten unter `project guidelines and definition/` (z. B. UTF-8, Semikolon, Kopfzeilen).

---

## Sicherheit

- Standard-Admin-Passwort nach dem ersten Login **ändern**.
- Produktion nur unter **HTTPS** betreiben.
- **`.env`** niemals ins Git committen (steht in `.gitignore`).

---

## Weitere Dokumentation

- **Inventur (Planung, Stufe 1–5):** [project guidelines and definition/pflichtenheft - inventur v1.md](project%20guidelines%20and%20definition/pflichtenheft%20-%20inventur%20v1.md)
- **Anwender (Bedienung):** [docs/ANLEITUNG.md](docs/ANLEITUNG.md) (Bestellung + **Inventur**, Abschnitt 8)
- Deployment & Hosting: [WEBSPACE-INSTALL.md](WEBSPACE-INSTALL.md)
- Outlook-Workflow: [outlook-macro/README.md](outlook-macro/README.md)
