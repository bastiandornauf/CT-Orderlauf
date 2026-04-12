# CT-Orderlauf auf dem Webspace installieren (Kurzanleitung)

Diese Anleitung ist bewusst generisch: Bei deinem Hoster heißen die Menüs oft „MySQL“, „Subdomain“ oder „Document Root“ ähnlich.

## Fact Sheet: Was der Webspace können muss

Zum Abhaken beim Hoster oder im Paketvergleich.

| Kategorie | Anforderung | Details |
|-----------|-------------|---------|
| **PHP** | **8.2+** (empfohlen: aktuelle 8.3/8.4) | Älter als 8.2 wird nicht unterstützt. |
| **Erweiterung** | **`pdo_mysql`** (PDO + MySQL-Treiber) | Ohne diese Extension keine Datenbankanbindung. Zuerst im Panel aktivieren; siehe auch README-Hinweis zu fehlender Extension. |
| **Erweiterung** | **`mbstring`** | Für PDF- und Textverarbeitung mit Umlauten (`PdfService`, Zeichenketten); fehlt sie, können u. a. PDFs oder Sonderzeichen Probleme machen. |
| **Erweiterung** | **`json`** | In PHP 8 standardmäßig dabei. |
| **Erweiterung** | **`session`** | Für Login; in PHP üblich eingebaut. |
| **Datenbank** | **MySQL 8** oder **MariaDB ≥ 10.6** | Eine leere Datenbank + Benutzer mit Rechten auf diese DB; Host oft `localhost` oder ein Hoster-Hostname. |
| **Webserver** | **URL-Rewrite** | **Apache:** `mod_rewrite` + `.htaccess` erlaubt (`AllowOverride` mind. `FileInfo`). **Nginx:** eigene `try_files`/Rewrite-Regel auf `public/index.php` (nicht im Repo dokumentiert – beim Hoster oder Admin nach Vorlage fragen). |
| **Document Root** | Zeigt auf **`public/`** | Nicht auf das Projektroot; sonst Pfad- und Asset-Fehler. |
| **HTTPS** | **Empfohlen / faktisch nötig für PWA** | Service Worker und moderne Cookies: Produktion unter TLS. |
| **Composer** | **Nicht nötig** auf dem Server | Autoload über `app/` (siehe `config/bootstrap.php`). |
| **SSH / Cron** | **Nicht nötig** | Reiner FTP/SFTP-Upload reicht; Cronjobs werden nicht verwendet. |
| **Schreibrechte** | **Kein uploads-Ordner nötig** | App schreibt standardmäßig nicht ins Dateisystem (außer du erweiterst sie). |
| **Speicher / Limits** | **Übliche Shared-Hosting-Werte** | Sehr große CSV-Imports können an `upload_max_filesize` / `post_max_size` / `max_execution_time` stoßen – bei Bedarf im Panel erhöhen. |

**Kurz in einem Satz:** PHP **8.2+** mit **`pdo_mysql`** und **`mbstring`**, **MySQL/MariaDB**, Webserver mit **Rewrite** und **Document Root auf `public/`**, idealerweise **HTTPS**.

---

## Voraussetzungen

- **PHP 8.2 oder höher** mit **PDO MySQL** (`pdo_mysql`) und **`mbstring`**
- **MySQL 8** (oder kompatibel, z. B. MariaDB 10.6+)
- **Apache** mit `mod_rewrite` **oder** Nginx mit eigener Rewrite-Regel auf `public/index.php`
- **HTTPS** für Produktion empfohlen (für PWA praktisch erforderlich)

Es wird **kein Composer** auf dem Server benötigt, wenn du das Projekt lokal oder in CI ohne `vendor/` auslieferst (Autoload nutzt die PHP-Dateien unter `app/`).

---

## 1. Datenbank anlegen

1. Im Hoster-Control-Panel eine **neue MySQL-Datenbank** anlegen (Name merken, z. B. `usr_xyz_ctorder`).
2. Einen **DB-Benutzer** anlegen oder verwenden und dem Benutzer **alle Rechte auf genau diese Datenbank** geben.
3. **Host** notieren – oft `localhost`, bei manchen Anbietern ein eigener Hostname wie `mysql12.anbieter.de`.
4. **Port** meist `3306` (nur ändern, wenn der Hoster etwas anderes vorgibt).

---

## 2. Dateien hochladen

1. Das **komplette Projektverzeichnis** auf den Webspace legen (FTP, SFTP, Git Deploy, ZIP – wie du magst).
2. Wichtig: Die App erwartet, dass der **Document Root (Webroot)** auf den Ordner **`public/`** zeigt.

**Variante A (empfohlen):** Document Root im Panel auf `…/CT-Orderlauf/public` stellen.

**Variante B:** Projekt liegt unter `htdocs/ct/` und du kannst den Root nicht ändern – dann entweder Subdomain mit Root auf `public/` oder eine **Rewrite-Weiterleitung** von `public/` aus dokumentieren ( beim Hoster nachfragen).

3. **Nicht** unbedingt nötig auf dem Server: `.git/`, `docker/`, lokale `.venv`, große Excel-Dateien aus `project guidelines` – können weggelassen werden, schaden aber meist nicht.

---

## 3. Konfiguration: `.env` anlegen

Im **Projektroot** (eine Ebene **über** `public/`, dort wo auch `app/`, `config/` liegen) eine Datei **`.env`** anlegen – **nicht** unter `public/`, damit sie nicht aus dem Web erreichbar ist.

Inhalt (Werte durch deine echten Zugangsdaten ersetzen):

```env
APP_ENV=production
APP_DEBUG=0

DB_HOST=localhost
DB_PORT=3306
DB_NAME=deine_datenbank
DB_USER=dein_db_user
DB_PASS=dein_sicheres_passwort

SESSION_NAME=ct_orderlauf_session
```

- **`DB_HOST`:** wie vom Hoster angegeben (`localhost` oder Remote-Host).
- **`APP_DEBUG=0`** in Produktion lassen.
- **`SESSION_NAME`:** nur ändern, wenn du mehrere Apps auf derselben Domain betreibst und Kollisionen vermeiden willst.

Alternativ kann der Hoster Umgebungsvariablen setzen – dieselben Namen wie oben (`DB_HOST`, `DB_NAME`, …) werden in `config/bootstrap.php` ausgewertet.

---

## 4. Datenbank-Schema (und optional Daten) importieren

Per **phpMyAdmin**, **Adminer** oder SSH:

1. **`database/schema.sql`** in die angelegte Datenbank importieren (legt Tabellen und den Standard-User an).
2. Optional: **`database/seed.sql`** importieren, falls du die Beispiel-/Excel-Daten nutzen willst.
3. Falls du schon einmal lokal migriert hast: die Dateien unter **`database/migrations/`** in sinnvoller Reihenfolge ausführen (nur nötig, wenn du bestehende Daten anpassen willst; frische Installation reicht meist mit `schema.sql` + optional `seed.sql`).

**Erster Login (Standard aus Schema):**

- Benutzer: `admin`
- Passwort: `admin123`

→ **Sofort nach dem ersten Login** unter einer sicheren Verbindung das Passwort ändern (Profil/Passwort ändern, falls vorhanden, oder direkt in der DB – je nachdem, was die App anbietet).

---

## 5. Berechtigkeiten (chmod)

Typisch auf Linux-Webspaces:

- Verzeichnisse: **`755`**
- Dateien: **`644`**
- **`.env`:** wenn möglich **`600`** (nur dein FTP/SFTP-User darf lesen)

Die App schreibt standardmäßig **nichts** ins Projektverzeichnis; ein beschreibbares `uploads/`-Verzeichnis ist für den Basis-Betrieb nicht nötig.

---

## 6. Apache: `public/.htaccess`

Im Ordner **`public/`** liegt eine **`.htaccess`** mit URL-Rewrite. Dafür muss **`AllowOverride`** mindestens **`FileInfo`** (oder `All`) sein, damit die Regeln greifen.

Liegt die App in einem **Unterordner** (z. B. `https://domain.de/orderlauf/`), muss in **`public/.htaccess`** die Zeile **`RewriteBase`** angepasst werden, z. B.:

```apache
RewriteBase /orderlauf/
```

(Exakten Pfad vom Webroot bis zu `public/` klären – oft nur ein Slash `/` wenn der vHost direkt auf `public/` zeigt.)

---

## 7. Kurz-Checkliste nach dem Deploy

- [ ] Aufruf der Startseite lädt ohne PHP-Fehler.
- [ ] Login funktioniert.
- [ ] Admin-Passwort geändert.
- [ ] Einstellungen (App-Name, ggf. Firmendaten für PDF, Mail-CC, Testbetrieb) geprüft.
- [ ] **HTTPS** aktiv.
- [ ] Optional: PWA/Service Worker testen (HTTPS erforderlich).

---

## 8. Häufige Probleme

| Symptom | Mögliche Ursache |
|--------|-------------------|
| 500 / weiße Seite | PHP-Version zu alt; `pdo_mysql` fehlt; falscher Document Root |
| PDF-Fehler / Encoding | `mbstring` fehlt oder ist deaktiviert |
| DB-Verbindungsfehler | Falsche `DB_HOST` / `DB_NAME` / User / Passwort in `.env` |
| CSS/JS 404 | Document Root zeigt nicht auf `public/` |
| Schöne URLs gehen nicht | `mod_rewrite` aus; `.htaccess` ignoriert; `RewriteBase` falsch |

---

## 9. Backup

Regelmäßig **MySQL-Dump** der Datenbank und eine Kopie von **`.env`** (ohne im öffentlichen Git zu committen) aufbewahren.

---

*Stand: Projekt CT-Orderlauf – bei Abweichungen in `config/` oder Router bitte diese Datei anpassen.*
