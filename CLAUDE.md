# CT-Orderlauf

Web-App für Küchen-/Lagerbestellungen und Inventur (PWA, offlinefähig). Überblick und Setup: [README.md](README.md).

## Stack

- PHP 8.2+ (strict_types), eigener Router (`app/Router.php`, Routen in `config/routes.php`), kein Composer-Autoload auf dem Server – Autoload über `config/bootstrap.php`
- MySQL 8, Zugriff über Repositories in `app/Repositories/`
- Frontend: Alpine.js (ESM), Vanilla JS unter `public/assets/js/`, ein CSS (`public/assets/css/app.css`) – **kein Node-Build**
- Offline: Bestellrunde/Inventur liegen nur clientseitig in IndexedDB; Service Worker `public/sw.js`
- PDF über `lib/fpdf.php`
- Keine automatisierten Tests vorhanden

## Lokal starten

```bash
docker compose up -d          # http://localhost:8080, Login admin / admin123
docker compose down -v && docker compose up -d   # DB komplett zurücksetzen
```

Syntaxcheck ohne Docker: `php -l <datei>` (PHP 8.4 lokal installiert).

## Rollen

`app/Helpers/UserRole.php`: `order` (Nur Bestellen), `editor` (Stammdaten), `admin`. Neue Seiten/Endpunkte immer mit passender Prüfung über `AuthMiddleware` / `UserRole` absichern.

## Datenbank-Änderungen

- `database/schema.sql` ist immer der **vollständige aktuelle Stand** – Strukturänderungen dort nachziehen.
- Zusätzlich eine Migration `database/migrations/YYYYMMDD_beschreibung.sql` für bestehende Installationen (Webspace).
- **Struktur-Migrationen nicht** in `docker-compose.yml` einbinden (würden mit schema.sql kollidieren). Nur reine Daten-Migrationen kommen dort mit fortlaufender Nummer rein.
- Bei neuen Migrationen ggf. [WEBSPACE-INSTALL.md](WEBSPACE-INSTALL.md) ergänzen.

## Frontend / Deploy

- `layout.php` hängt `?v=<filemtime>` an Asset-URLs – CSS/JS-Änderungen kommen per Reload an.
- Bei Änderungen an `public/sw.js` die `CACHE`-Version hochzählen (`ct-orderlauf-assets-vNN`); neue Assets ggf. in `PRECACHE` aufnehmen.
- API-Aufrufe (`/api/…`) laufen nicht über den Service Worker; Frontend prüft `navigator.onLine`. CSRF-Token kommt aus `meta[name="csrf-token"]`.

## Texte & Doku

- UI-Texte sind deutsch, kurz und handlungsorientiert (keine Dauer-Erklärtexte auf den Seiten).
- Anleitung existiert doppelt: `app/Data/ANLEITUNG.md` (wird von `HelpController` zuerst geladen) und `docs/ANLEITUNG.md`. Beide synchron halten.
- Fachliche Vorgaben (Pflichtenhefte, CSV-Formate, Beispieldaten): `project guidelines and definition/`
- Zurückgestellte Ideen stehen in [BACKLOG.md](BACKLOG.md) – nur umsetzen, wenn ausdrücklich gewünscht.

## Hilfe nach Rolle (später)

`/help` ist aktuell eine gemeinsame Anleitung für alle Rollen. Geplant sind später eigene Fassungen:

- **order**: Rundgang, Kontrolle, Versand
- **editor**: plus Artikel, Lieferanten, Import
- **admin**: plus Benutzer, Einstellungen, SMTP

Nicht anfangen, bis ausdrücklich danach gefragt wird. `/help` nicht nebenbei umbauen.

## Git

Commit-Messages auf Englisch, ein ganzer Satz im Imperativ mit Punkt (z. B. „Edit articles in a list dialog and restore free-item feedback.“).
