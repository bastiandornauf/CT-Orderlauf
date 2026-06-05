# Pflichtenheft Teams V1

**CT-Orderlauf – Erweiterung „Mehrere Teams / eigene Lager“**

Dokumentstatus: V1 (Planung)  
Bezug: [pflichtenheft v1.md](pflichtenheft%20v1.md), [pflichtenheft - inventur v1.md](pflichtenheft%20-%20inventur%20v1.md)

⸻

## 1. Ziel

### Zweck

Die App soll **mehrere Küchen-Teams** (z. B. **Kochkultur / Service** und **Auszeit**) unterstützen, die:

- **eigene Lagerorte** haben (physisch getrennt),
- **eigene Bestellrunden** fahren,
- **eigene Inventuren** durchführen,
- ggf. **andere Artikel** nutzen (manche nur in einem Team aktiv / sichtbar),
- Lieferanten in E-Mails klar erkennen lassen, **von welchem Team** bestellt wird.

### Ausgangslage (Ist)

| Thema | Heute |
|--------|--------|
| Lagerorte | global, alle Nutzer sehen alles |
| Artikel | je **ein** `location_id`, globaler Katalog |
| Bestellrunde | eine Session pro Gerät, alle Artikel |
| Inventur | eine Session pro Gerät, alle Artikel |
| Nutzer | Rollen `admin` / `editor` / `order`, **kein Team** |
| E-Mail | `{{COMPANY}}` aus globalen Einstellungen (z. B. „Kochkultur“) |

### Ziele Stufe 1

1. Teams als **Arbeitskontext** (Filter), nicht als vollständige Mandanten-Trennung
2. Nutzer sind **fest einem Team** zugeordnet
3. Bestellung und Inventur laden nur **Team-eigene** Lagerorte und Artikel
4. Artikel können **pro Team** aktiv/inaktiv sein (andere Teams sehen sie nicht)
5. Bestell-E-Mails enthalten **Team-Bezeichnung** (Betreff/Body), Lieferant erkennt Herkunft
6. **Kein Team-Wechsel** in der UI (Workaround: mehrere Accounts für Küchenchef)

### Nicht-Ziel Stufe 1

- Kein Team-Umschalter in der App (→ Stufe 2, optional)
- Kein getrennter Lieferanten-Stamm pro Team (Lieferanten bleiben global)
- Kein zentraler Artikel-Katalog ohne Duplikate (→ Stufe 2 „Katalog + Platzierung“)
- Keine teamübergreifende Sammelbestellung

### Leitplanke

**Bestehende Einzel-Team-Nutzung darf nach Migration unverändert funktionieren.** Ein Default-Team übernimmt alle heutigen Daten.

⸻

## 2. Fachmodell (Vorschlag Stufe 1)

### 2.1 Entitäten

```
Team
 ├── Lagerorte (locations)
 ├── Artikel (items) — je Team eigener Datensatz
 ├── Nutzer (users.team_id)
 ├── Bestellrunde (lokal, team_id in Session)
 └── Inventur (lokal, team_id in Session)
```

**Lieferanten**, **Liefertage**, **item_supplier**-Zuordnungen: weiterhin **global** (gleiche Lieferanten für alle Teams).

### 2.2 Team-Stammdaten

| Feld | Beschreibung |
|------|----------------|
| `id` | PK |
| `name` | Anzeigename, z. B. „Kochkultur“, „Auszeit“ |
| `slug` | Kurzkennung für URLs/Export, z. B. `kochkultur` |
| `email_label` | Text in Mails, z. B. „Kochkultur“ / „Team Auszeit“ |
| `order_email_subject_template` | optional, überschreibt globales Subject |
| `order_email_body_template` | optional, überschreibt globales Body-Template |
| `active` | Team ein/aus |
| `sort_order` | Reihenfolge in Admin-UI |

**E-Mail-Logik:** Wenn Team-Templates leer → globale Einstellungen. Sonst Team-Vorlage.  
Neuer Platzhalter **`{{TEAM}}`** = `email_label` (Fallback: `name`).

Beispiel Betreff global: `Bestellung {{TEAM}} {{TARGET_DATE}}`  
Ergebnis Auszeit: `Bestellung Team Auszeit 12.04.2026`

### 2.3 Lagerorte

- `locations.team_id` NOT NULL (FK → teams)
- Tabs im Rundgang (Bestellung/Inventur): nur Orte des Nutzer-Teams
- Namen dürfen pro Team gleich sein (beide haben „Kühlhaus 1“)

### 2.4 Artikel

**Stufe 1 (einfach, umsetzbar):**

- `items.team_id` NOT NULL
- Eindeutigkeit: **`UNIQUE (team_id, name)`** statt global nur nach Name
- `active` pro Team-Instanz → Team B kann Artikel ausblenden, Team A nutzt ihn weiter
- Lieferanten-Zuordnung (`item_supplier`) bleibt am Artikel — bei Duplikat pro Team ggf. doppelt pflegen

**Stufe 2 (später, wenn Duplikat-Pflege nervt):**

- Gemeinsamer **Katalog** (`catalog_items`: Name, Einheit, Lieferanten)
- **Platzierung** (`item_placements`: team_id, location_id, min/max, active, sort_order)

→ Stufe 1 bewusst wählen, Stufe 2 als Upgrade dokumentieren.

### 2.5 Nutzer

| Rolle | Team-Zuordnung |
|--------|----------------|
| `order` | **Pflicht** `team_id` |
| `editor` | **Pflicht** `team_id` |
| `admin` | optional `team_id` NULL = sieht alles / verwaltet alle Teams |

**Küchenchef mit mehreren Teams:** separate Accounts (`chef-kk`, `chef-auszeit`) — akzeptiert, kein Umschalter nötig.

### 2.6 Lokale Sessions (IndexedDB)

| Store | Ergänzung |
|--------|-----------|
| `order_round` | `team_id` |
| `inventory_session` | `team_id` |
| Payload-Snapshot | nur Daten des Teams |

**Regel:** Beim Laden prüfen: Session-`team_id` === User-`team_id`. Sonst Warnung + Neu laden.

⸻

## 3. Auswirkungen auf Module

### 3.1 Bestellrunde

- `/api/order/payload?target_date=…` → filtert Locations/Items nach `users.team_id`
- Dashboard „Bestellen“ startet Runde **für eigenes Team**
- Freie Positionen, Review, Output: unverändert im Ablauf, Daten team-gefiltert
- Parallel: Team A und Team B können **gleichzeitig** auf verschiedenen Geräten bestellen (getrennte IndexedDB-Sessions)

### 3.2 Inventur

- `/api/inventory/payload` → team-gefiltert
- CSV-Export: Spalte `team` oder Dateiname `Inventur_Auszeit_2026-04-12.csv`
- Inventur-Sessions pro Team getrennt (wie heute pro Gerät, zusätzlich team_id)

### 3.3 Stammdaten (Editor/Admin)

- **Lagerorte:** Anlegen nur innerhalb eigenes Team (Editor) bzw. Team-Auswahl (Admin)
- **Artikel:** Liste filtert nach Team; „Neu“ legt Artikel im eigenen Team an
- **Neue Artikel (Sammelliste):** nur Freitext-Einträge des **eigenen Teams** (Inventur + Bestellung der lokalen Session)
- **Import/Export:** CSV um Spalte `team` (Name oder Slug) erweitern

### 3.4 E-Mail / Output

| Platzhalter | Quelle Stufe 1 |
|-------------|----------------|
| `{{TEAM}}` | `teams.email_label` |
| `{{COMPANY}}` | unverändert global (`settings.company_name`) |
| `{{SUPPLIER}}` | Lieferant |
| `{{TARGET_DATE}}` | Lieferdatum |

**Empfehlung Betreff (global oder pro Team):**

```
Bestellung {{TEAM}} {{TARGET_DATE}}
```

**Body** (optionaler Satz im Team-Template):

```
Bestellung für {{TEAM}} – Lieferung am {{TARGET_DATE}}
```

Lieferanten-spezifisches Subject (`suppliers.email_subject_template`) kann `{{TEAM}}` ebenfalls nutzen.

Betrifft: `email-generator.js`, `EmailService.php`, `MailSenderService.php`, Settings-Hilfetexte.

⸻

## 4. Datenbank-Migration (Stufe 1)

### 4.1 Neue Tabelle `teams`

```sql
CREATE TABLE teams (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  slug VARCHAR(64) NOT NULL UNIQUE,
  email_label VARCHAR(128) NOT NULL DEFAULT '',
  order_email_subject_template VARCHAR(512) NULL,
  order_email_body_template TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 4.2 Default-Team + Datenübernahme

1. Team anlegen: `Kochkultur` (slug `kochkultur`) — übernimmt **alle** bestehenden locations/items
2. Spalten `team_id` an `locations`, `items`, `users` (nullable zuerst, dann NOT NULL wo sinnvoll)
3. FK + Indizes
4. `items`: Index `uq_items_team_name (team_id, name)` — **Migration:** globale Namens-Duplikate vorher bereinigen

### 4.3 Zweites Team anlegen

Admin legt „Auszeit“ an → neue Lagerorte → Artikel importieren oder aus Kochkultur kopieren (Admin-Werkzeug Stufe 1.1: „Artikel von Team X nach Team Y kopieren“).

⸻

## 5. UI / Navigation

### Stufe 1

- Kein Team-Umschalter
- Header oder Startseite: dezenter Hinweis **„Team: Auszeit“** (nur Info)
- Admin: Menüpunkt **Teams** (CRUD minimal: Name, E-Mail-Label, Templates)
- Admin: Benutzer-Formular → Feld **Team**

### Stufe 2 (optional, später)

- Admin + ggf. Editor: Dropdown „Arbeiten als: …“ (nur wenn `users.team_id` NULL oder Flag `can_switch_teams`)
- Session-Wechsel löscht/ARCHiviert lokale Runde nicht automatisch — Warnung

⸻

## 6. Entscheidungen (aus Abstimmung)

| Frage | Entscheidung |
|--------|----------------|
| Getrennte Bestellung? | **Ja**, jedes Team eigene Runde |
| Getrennte Inventur? | **Ja** |
| Andere ausgeblendete Artikel? | **Ja**, `active` pro Team-Artikel |
| Nutzer ↔ Team | **Fest** zugeordnet; Mehrfach-Accounts OK |
| Team-Wechsel in UI? | **Stufe 2**, nicht zwingend |
| E-Mail Team-Hinweis? | **Ja**, `{{TEAM}}` in Subject/Body |
| Lieferanten pro Team? | **Nein**, global |

⸻

## 7. Umsetzungsphasen

### Phase A – Fundament (Backend)

- [ ] Tabelle `teams`, Migration Default-Team
- [ ] `team_id` auf locations, items, users
- [ ] Repositories + APIs filtern nach Team
- [ ] Auth: `team_id` in Session nach Login
- [ ] Tests: Payload liefert nur Team-Daten

**Aufwand:** ~2–3 Tage

### Phase B – Frontend / Offline

- [ ] Payload + IndexedDB: `team_id` in Sessions
- [ ] Dashboard, Rundgänge, Inventur: Team-Kontext
- [ ] Sammelliste „Neue Artikel“ team-scoped
- [ ] CSV Import/Export Spalte `team`

**Aufwand:** ~2–3 Tage

### Phase C – E-Mail

- [ ] `{{TEAM}}` in JS + PHP
- [ ] Team-Templates in Admin-UI
- [ ] Settings-Hilfe aktualisieren
- [ ] Test: Auszeit-Mail an OGA mit erkennbarem Betreff

**Aufwand:** ~1 Tag

### Phase D – Admin & Zweit-Team

- [ ] Teams verwalten (Admin)
- [ ] Benutzer-Team zuweisen
- [ ] Dokumentation + Migrations-Anleitung für „Auszeit“ anlegen
- [ ] Optional: Artikel-Kopier-Werkzeug Team → Team

**Aufwand:** ~1–2 Tage

**Gesamt Stufe 1:** ca. **6–9 Tage** Entwicklung + Test

⸻

## 8. Risiken & Mitigation

| Risiko | Mitigation |
|--------|------------|
| Artikel-Duplikate zwischen Teams | Kopier-Werkzeug; Stufe 2 Katalog |
| `findByName` global | Auf `(team_id, name)` umstellen |
| Alte IndexedDB ohne team_id | Beim ersten Laden nach Update: Session invalidieren, neu laden |
| Admin ohne team_id sieht zu viel | Bewusst so; oder Admin muss Team wählen zum Bearbeiten |
| Lieferant verwirrt bei gleichem Firmennamen | `{{TEAM}}` prominent im Betreff |

⸻

## 9. Akzeptanzkriterien Stufe 1

1. Nutzer „auszeit-besteller“ sieht **nur** Auszeit-Lagerorte und -Artikel
2. Nutzer „kk-besteller“ sieht **nur** Kochkultur-Daten
3. Parallele Bestellrunden auf zwei Geräten (je Team) ohne Überschneidung
4. Inventur Auszeit exportiert CSV **nur** mit Auszeit-Artikeln
5. E-Mail an Lieferant enthält **„Auszeit“** (oder konfiguriertes `email_label`) im Betreff
6. Inaktiver Artikel in Team A erscheint in Team B nicht (wenn nicht dort angelegt)
7. Bestehende Installation nach Migration: ein Team „Kochkultur“, Verhalten wie bisher

⸻

## 10. Offen für Feinschliff vor Umsetzung

1. **Exakte Team-Namen** und `email_label` (Kochkultur vs. Service vs. Auszeit)
2. **Soll `{{COMPANY}}` durch Team ersetzt werden** oder bleibt Firma global und `{{TEAM}}` zusätzlich?
3. **CC-E-Mail** pro Team oder global?
4. **PDF-Anhang** (falls genutzt): Team im Kopf?
5. **Reihenfolge:** Phase A zuerst mit nur einem sichtbaren Team, dann Auszeit — oder direkt zwei Teams in Migration?

⸻

## 11. Empfehlung

**Stufe 1 mit festem Team pro Nutzer umsetzen** — passt zu eurem Workflow (eigene Lager, eigene Bestellung, eigene Inventur, E-Mail mit Team-Hinweis). **Team-Wechsel** erst nachbetten, wenn Mehrfach-Accounts im Alltag stören.

Nächster konkreter Schritt vor Code: **Feinschliff Abschnitt 10** (Namen, `{{COMPANY}}` vs. `{{TEAM}}`, CC), dann **Phase A** starten.
