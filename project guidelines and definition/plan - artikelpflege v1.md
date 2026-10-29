# Plan: Artikelpflege, Lieferantenartikel, Preise, Inventurbewertung (v1)

Stand: 2026-10-29 · Analyse vor der Umsetzung, noch nichts implementiert.
Grundlage: Auftrag „Orderlauf – Erweiterung der Artikelverwaltung, Preisverwaltung und intelligente Artikelpflege“.

---

## A. Befund: Was es schon gibt

### Git

- Arbeitsbranch lokal: `new-ui` (= `origin/new-ui`), 7 Commits vor `main`.
- `main` ist 3 Commits vor `origin/main` (nicht gepusht).
- Tag `live-2026-09-16` = letzter verifizierter Produktionsstand (laut WEBSPACE-INSTALL.md, Abschnitt 10).
- Nicht committet: `CLAUDE.md`, `.cursor/`.
- **Offen:** Welcher Stand läuft gerade live – `live-2026-09-16`, `main` oder `new-ui`? Der Webspace-Mount (Mountain Duck) war bei der Analyse nicht erreichbar, der Vergleich aus WEBSPACE-INSTALL.md §10 konnte nicht laufen.

### Datenmodell (`database/schema.sql`)

| Tabelle | Relevantes |
|---|---|
| `items` | `id`, `name`, `unit` (Freitext: „Karton“, „Ktn“, „Beutel (1 kg)“, „Wanne (5 l)“ …), `location_id`, `sort_order`, `min_stock`, `max_stock`, `valuation_price DECIMAL(10,2)` (manueller Bewertungspreis pro Einheit), `active` |
| `item_supplier` | `item_id`, `supplier_id`, `priority`; UNIQUE (`item_id`, `supplier_id`) – nur Zuordnung, keine Produktdaten |
| `suppliers` | Stammdaten, Mail-Vorlagen, Liefertage |
| `pending_items` | Freitext-Sammlung mit `dedupe_key` (normalisiert) – Muster für Idempotenz wiederverwendbar |

Nicht vorhanden: Alias, Kategorie (der Lagerort übernimmt diese Rolle faktisch), Hersteller/EAN, Lieferanten-Bezeichnung, Artikelnummer, Gebinde, Preise je Lieferant, Preishistorie, Bestellhistorie, gespeicherte Inventuren.

### Kritische Code-Stellen

1. **`ItemRepository::setSupplierLinks()` löscht alle `item_supplier`-Zeilen eines Artikels und legt sie neu an.** Aufgerufen von Artikeleditor (`ItemController::save`), Schnellbearbeitung im Rundgang (`ItemApiController`) und CSV-Import. Folge: Alles, was an `item_supplier`-Zeilen oder deren IDs hängt, geht bei jedem Speichern in der **alten** Version verloren. → Lieferantenartikel dürfen **nicht** als Spalten in `item_supplier` und nicht per FK auf `item_supplier.id` gespeichert werden.
2. **Bestellausgabe:** Positionen werden im Browser gebaut (`order-pages.js` ca. Z. 762 und 1343: `label: item.name`, `unit: item.unit`). Mail-Text (`email-generator.js`), PDF (`PdfService`) und Outlook-Export nehmen dieses `label`. Hier wird der Lieferantenname eingesetzt – eine zentrale Stelle, zweimal vorhanden.
3. **Bestellmenge** ist ein Freitext-String (`orch.parseQuantity`) in `item.unit`. Gebinde sind heute nur im Einheitentext versteckt.
4. **Inventur** liegt komplett im Browser (IndexedDB). `/api/inventory/payload` bekommt zwar `stichtag`, nutzt ihn aber nicht für Preise. Bewertung = Menge × `items.valuation_price`, eingefroren beim Start im Katalog-Snapshot. „Abgeschlossen“ heißt heute: CSV heruntergeladen. **Auf dem Server wird keine Inventur gespeichert.**
5. **IndexedDB:** Neue Felder in vorhandenen Objekten (Items, Links) brauchen **keine** Erhöhung von `DB_VERSION` – nur neue Stores/Indizes. Das ist wichtig für den Rollback (WEBSPACE-INSTALL.md §10).
6. **Kein Basispfad:** Routen und JS-Aufrufe sind absolut (`/api/...`, `/order/round`). Eine Installation unter `/orderlauf-test/` geht nicht ohne Umbau → Subdomain.
7. **Testbetrieb (`dev_mode`, `dev_email`) liegt in der Tabelle `settings`**, wäre bei gemeinsamer DB also auch für die Produktion aktiv. → Die Testinstanz braucht einen Schalter in der `.env`.
8. **Frühere Bereinigungen** (`20260413_cleanup_duplicate_items.sql`) haben Artikel per `DELETE` entfernt. Künftig nur noch archivieren/zusammenführen.
9. **Keine automatisierten Tests vorhanden.**

---

## B. Antworten auf die 10 Fragen

**1. Was lässt sich weiterverwenden?**
`items` bleibt der interne Artikel (stabile ID). `item_supplier` bleibt „bestellbar bei + Priorität“ und steuert die bestehende Lieferantenauswahl (`supplier-logic.js`) unverändert. `items.active` dient als Archiv-Flag. `pending_items.dedupe_key` ist das Vorbild für Idempotenz. Rollen: `requireEditor()`.

**2. Welche Schemaänderungen sind wirklich nötig?** (alle additiv, siehe C)
- `items`: `alias`, `base_unit`, `unit_factor`, `category` (optional, siehe Entscheidung E5), `merged_into_id`
- neu `supplier_articles`
- neu `supplier_article_prices`
- neu `item_merge_log` und `dedupe_decisions`
- neu `synonyms` (oder zunächst PHP-Datei, siehe E8)

**3. Wie sind Artikel und Lieferanten verknüpft?**
n:m über `item_supplier` (`item_id`, `supplier_id`, `priority`), höchste Priorität bevorzugt; die Bestellrunde wählt pro Position einen Lieferanten unter denen, die zum Zieldatum liefern. Produktdaten je Lieferant gibt es nicht.

**4. Was existiert teilweise?**
- Bewertungspreis (`valuation_price`) inkl. CSV-Import und -Export – manuell, ohne Datum, Herkunft oder Lieferant.
- Normalisierung und Dedupe für Freitext-Artikel (`pending_items`).
- Artikelsuche (nur `name`/`unit`, `LIKE`).
- `SupplierMatchService` (Lieferanten-Zuordnung beim Import) – vor Phase 4 auf wiederverwendbare Normalisierung prüfen.
- Inventur-Stichtag als Feld vorhanden, aber ohne Wirkung auf Preise.

**5. Testinstallation mit minimalem Aufwand**
Subdomain (z. B. `test-orderlauf.<domain>`) beim selben Hoster, Document Root auf eine zweite Kopie `…/orderlauf-test/public`, eigene `.env`.
Vorteile: Eigener Origin, dadurch eigener Service Worker und eigene IndexedDB. Daten auf den Handys können sich nicht mit der Produktion vermischen. Das wäre bei einem Unterordner auf derselben Domain nicht so.
Schutz: HTTP Basic Auth per `.htaccess` + App-Login. Neue `.env`-Option `APP_TEST_INSTANCE=1`: Sie erzwingt Testbetrieb (Mails nur an `dev_email`), zeigt ein Banner „TESTVERSION“ und ist unabhängig von `settings`. Eine kleine Codeänderung im Feature-Branch, ca. 20 Zeilen.

**6. Was ist sicher rückwärtskompatibel?**
- Neue Spalten in `items` mit `NULL`-Default: Der alte Code nennt bei `INSERT`/`UPDATE` explizit seine Spalten und lässt neue unberührt. Kein `SELECT *` mit Positionszugriff gefunden.
- Neue Tabellen: Der alte Code kennt sie nicht.
- `supplier_articles` hängt an (`item_id`, `supplier_id`) statt an `item_supplier.id`. Das Löschen und Neuanlegen durch den alten Code macht die Daten deshalb nicht kaputt. Entfernt jemand in der alten Version eine Zuordnung, bleibt der Lieferantenartikel als „ohne Zuordnung“ stehen. Die Artikelpflege meldet das.
- **Nicht** kompatibel und daher zu vermeiden: Spalten umbenennen oder löschen, `valuation_price` umdeuten, `item_supplier` ändern, Artikel löschen.
- Grenzfall Zusammenführen: Die alte Version sieht den Quellartikel danach als inaktiv. Für Geräte mit offener Bestellrunde oder Inventur, in der der Quellartikel steckt, siehe Risiken (Frage 9).

**7. Was beantwortet der Code schon?**
- Bestellhistorie: gibt es nicht (bestätigt). Bestätigte Einkäufe lassen sich nicht aus Bestellungen ableiten.
- Gespeicherte Inventuren: gibt es nicht. Das Archiv ist die CSV-Datei, die Preise sind im Gerät eingefroren.
- Bestelleinheit = `items.unit`, je Artikel und nicht je Lieferant.
- Berechtigungen: Stammdaten ab `editor`.

**8. Offene fachliche Entscheidungen** → Abschnitt E.

**9. Risiken beim Zusammenführen**
- Offene Bestellrunden und Inventuren auf Geräten verweisen per `item_id` auf den Quellartikel. → Den Quellartikel nie löschen, nur `active = 0` und `merged_into_id` setzen. Die Payloads liefern eine Abbildungstabelle `merged_into`, damit das Frontend Einträge umhängen kann. Empfehlung: nicht während einer laufenden Inventur zusammenführen; die Oberfläche warnt.
- Konflikt beim selben Lieferanten: Beide Artikel haben Pütz, aber mit verschiedenen Gebinden. → Mehrere Lieferantenartikel je (Artikel, Lieferant) sind erlaubt, einer davon ist `is_default` für die Bestellung. Der Dialog fragt, welcher es sein soll. Es geht nichts verloren.
- `item_supplier`-Priorität: Doppelte Lieferanten zusammenlegen, die höhere Priorität gewinnt, der Dialog zeigt das an.
- Abweichende `unit`, `min_stock`/`max_stock` oder `location_id` → Der Benutzer wählt pro Feld, die Werte des Quellartikels landen im Merge-Log.
- Die alte Version schreibt parallel auf einen der beiden Artikel. → Merge in einer Transaktion mit `SELECT … FOR UPDATE`. Danach sieht die alte Version den Quellartikel als inaktiv.
- Fachlich falsche Zusammenführung → Das Merge-Log speichert den vollständigen Vorher-Zustand als JSON. Damit ist ein „Rückgängig“ möglich, solange danach nichts Widersprüchliches passiert ist.

**10. Kleine, überprüfbare Schritte** → Abschnitt D. Jeder Schritt ist ein eigener Commit, lokal getestet und einzeln deploybar.

---

## C. Datenmodell (Entwurf)

```sql
-- 20261101_items_alias_units.sql
ALTER TABLE items
  ADD COLUMN alias VARCHAR(255) NULL AFTER name,
  ADD COLUMN base_unit VARCHAR(8) NULL COMMENT 'kg|l|stk – Vergleichs-/Inventureinheit',
  ADD COLUMN unit_factor DECIMAL(12,4) NULL COMMENT 'base_unit je 1 items.unit, NULL = unbekannt/variiert',
  ADD COLUMN category VARCHAR(64) NULL COMMENT 'vorgesehen, noch ohne UI (E5)',
  ADD COLUMN merged_into_id INT UNSIGNED NULL,
  ADD INDEX idx_items_alias (alias);

-- 20261101_supplier_articles.sql
CREATE TABLE supplier_articles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  supplier_id INT UNSIGNED NOT NULL,
  supplier_label VARCHAR(255) NULL COMMENT 'formelle Bestellbezeichnung; NULL = items.name',
  supplier_sku VARCHAR(64) NULL,
  ean VARCHAR(14) NULL,
  brand VARCHAR(128) NULL,
  pack_size DECIMAL(12,4) NULL,
  pack_unit VARCHAR(8) NULL COMMENT 'kg|g|l|ml|stk',
  order_unit VARCHAR(64) NULL COMMENT 'NULL = items.unit',
  is_default TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'wird für Bestellung bei diesem Lieferanten genutzt',
  active TINYINT(1) NOT NULL DEFAULT 1,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sa_item_supplier (item_id, supplier_id),
  INDEX idx_sa_sku (supplier_id, supplier_sku),
  CONSTRAINT fk_sa_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sa_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT
);

-- 20261101_supplier_article_prices.sql
CREATE TABLE supplier_article_prices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_article_id INT UNSIGNED NOT NULL,
  price_type VARCHAR(16) NOT NULL COMMENT 'known|purchase',
  amount DECIMAL(12,4) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  is_net TINYINT(1) NOT NULL DEFAULT 1,
  per_qty DECIMAL(12,4) NOT NULL DEFAULT 1,
  per_unit VARCHAR(16) NOT NULL COMMENT 'order|kg|g|l|ml|stk – order = je Bestelleinheit',
  price_date DATE NOT NULL,
  source VARCHAR(16) NOT NULL COMMENT 'manual|shop|pricelist|invoice|import',
  document_ref VARCHAR(128) NULL,
  dedupe_key VARCHAR(191) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  UNIQUE KEY uq_sap_dedupe (dedupe_key),
  INDEX idx_sap_lookup (supplier_article_id, price_type, price_date),
  CONSTRAINT fk_sap_article FOREIGN KEY (supplier_article_id) REFERENCES supplier_articles(id) ON DELETE RESTRICT
);
-- dedupe_key = sha1(article|type|date|amount|per_qty|per_unit|document_ref) → derselbe Beleg kann nicht doppelt rein.
-- Kein UPDATE auf Preise: Korrektur = neuer Datensatz bzw. Löschen mit Log.

-- 20261101_item_maintenance.sql
CREATE TABLE dedupe_decisions (item_a INT UNSIGNED, item_b INT UNSIGNED, decision VARCHAR(16) COMMENT 'separate|later',
  decided_by INT UNSIGNED NULL, decided_at DATETIME NOT NULL, PRIMARY KEY (item_a, item_b));  -- item_a < item_b
CREATE TABLE item_merge_log (id …, target_item_id, source_item_id, before_json MEDIUMTEXT, merged_by, merged_at);
CREATE TABLE maintenance_log (id …, action VARCHAR(32), item_id NULL, detail_json TEXT, user_id, created_at);
```

- Geld: `DECIMAL` in der DB. Gerechnet wird in PHP mit Integer-Cent bzw. `bcmath`, falls beim Hoster vorhanden (zu prüfen); sonst mit skalierten Integern in einem Helfer `Money`. Im JS nur Anzeige, keine Bewertungsrechnung im Browser. Die Inventurwerte rechnet künftig der Server.
- Einheiten zentral: `app/Helpers/Units.php` (kanonisch `kg/g/l/ml/stk`, Dimension, Faktor, Normalisierung von „Kg“, „Ktn“, „Btl“ …) plus JS-Spiegel nur für die Anzeige. Unbekannt oder inkompatibel → `null`, nie raten.
- `valuation_price` bleibt unverändert (alte Version, manueller Wert), siehe E3.

---

## D. Umsetzungsschritte

### Phase 0 – Absichern (keine Codeänderung an der App)
0.1 Klären, welcher Stand live ist (Mount verbinden, Vergleich aus WEBSPACE-INSTALL.md §10).
0.2 `CLAUDE.md` committen; `.cursor/` entweder committen oder in `.gitignore` aufnehmen.
0.3 Tag `pre-artikelpflege` auf den verifizierten Live-Stand setzen und pushen; Branch `feature/artikelpflege` vom gewünschten Basis-Branch (siehe E0).
0.4 DB-Backup: Dump über phpMyAdmin/Hoster-Panel → lokal außerhalb des Webroots ablegen, z. B. `~/Backups/orderlauf/`, nicht im Repo. Wiederherstellung **lokal** in Docker testen (Import in eine leere DB, App gegen diese DB starten, Zählung der Artikel/Lieferanten/Zuordnungen vergleichen).
0.5 Den Prod-Dump als lokale Entwicklungsdatenbank verwenden: Analyse des echten Bestands (Anzahl Artikel, Einheiten, Lieferanten-Abdeckung, offensichtliche Dubletten).
0.6 Test-Grundgerüst: `tests/` mit einfachem PHP-Runner (`php tests/run.php`), kein Framework auf dem Server; DB-Tests gegen Docker-DB. Alternativ PHPUnit nur als `require-dev` (E9).

### Phase 1 – Artikelmodell
1.1 Migration `items_alias_units` + `schema.sql` nachziehen; Repository liest/schreibt `alias`.
1.2 Anzeigename-Regel zentral: PHP `ItemDisplay::name()` und JS `displayName(item)`; eingesetzt in Rundgang, Kontrolle, Artikelliste, Suche, Inventur. Bestell-`label` bleibt vorerst `item.name`.
1.3 Suche: `name`, `alias`, `supplier_label`, `supplier_sku`, `ean` (Server-Liste + Rundgang-Filter im Browser).
1.4 Migration `supplier_articles`; Startbefüllung: **keine** automatische Anlage – fehlende Lieferantenartikel = Pflegebedarf. (Option: für jede `item_supplier`-Zeile einen leeren Default-Artikel anlegen, E6.)
1.5 Editor: Abschnitt „Bei Lieferant“ je Zuordnung (Bezeichnung, Art.-Nr., Gebinde). `setSupplierLinks()` im neuen Code als Upsert statt Löschen/Neuanlegen.
1.6 Bestellausgabe: Payload liefert pro Link `supplier_label`, `supplier_sku`, `order_unit`; `label` in den zwei Stellen von `order-pages.js` = `supplier_label ?? item.name`. Mail/PDF/Outlook unverändert im Format (E4: Art.-Nr. mitsenden?).
1.7 (entfällt – keine Testinstanz, siehe E12)

### Phase 2 – Gebinde und Preise
2.1 `Units`- und `Money`-Helfer mit Tests (Umrechnung, inkompatible Einheiten, Rundung).
2.2 Migration `supplier_article_prices`; Repository mit Dedupe; „aktueller bekannter Preis“ = neuester `known`- oder `purchase`-Preis je Lieferantenartikel.
2.3 Erfassung im Editor: „Preis eintragen“ (Betrag, je, Datum, Art: Listenpreis / bestätigter Einkauf, Beleg). Gleicher Wert wie der letzte → kein neuer Datensatz. Historie aufklappbar.
2.4 Rundgang: dezente Zeile unter dem Artikel, z. B. `Pütz 24,80 €/Ktn · 2,48 €/kg · 03.10.` (bei mehreren Lieferanten antippbar). Fehlend → „Preis unbekannt“. Keine Auto-Wahl.

### Phase 3 – Inventur
3.1 Server-Service `InventoryValuation`: je Artikel letzter `purchase`-Preis mit `price_date <= stichtag` über alle Lieferantenartikel; Umrechnung auf `items.unit` über `base_unit`/`unit_factor` bzw. Gebinde; Ergebnis mit Quelle (Lieferant, Datum, Basis, umgerechneter Preis) oder Grund „kein Einkaufspreis“ / „nicht umrechenbar“.
3.2 `/api/inventory/payload` liefert diese Bewertung (Stichtag wird jetzt wirksam); Snapshot im Gerät wie bisher → einmal gestartete Inventur ändert sich nicht.
3.3 Rundgang-Hinweis, Abschluss-Summe und CSV um Spalten `preisquelle`, `preisdatum`, `lieferant`, `preisbasis` erweitern; „nicht bewertet“ explizit.
3.4 Regressionstest: Inventur ohne neue Preise verhält sich wie bisher (E3).

### Phase 4 – Artikelpflege (`/items/maintenance`, ab `editor`)
4.1 Prüf-Service `ItemQualityChecks`: jede Prüfung = eine Methode mit Schweregrad `fehler|hinweis`, Ergebnisliste aus dem echten Bestand. Übersicht mit Zählern, Filter, Sprung in den Editor, „nächster Artikel“.
4.2 Preisprüfungen: Faktor 10/100 gegenüber Vorpreis, Abweichung > X % zwischen Lieferanten (nur bei vergleichbarer Einheit), Preis älter als N Monate, fehlende Preisbasis.
4.3 `DuplicateFinder`: Normalisierung (Kleinbuchstaben, Umlaute, Einheiten- und Mengenangaben abtrennen), Tokens, Synonyme, Levenshtein/`similar_text` auf normalisierten Tokens, Bonus für gleiche EAN/Art.-Nr., **Malus für Gegensatzpaare** (mit Stein/entsteint, TK/frisch, ganz/geschnitten). Ergebnis = Score + Begründungen. Abgelehnte Paare aus `dedupe_decisions` ausblenden.
4.4 Zusammenführungs-Dialog: Gegenüberstellung, Ziel wählen, Name/Alias editieren, Feldkonflikte entscheiden, Default-Lieferantenartikel je Lieferant wählen; eine Transaktion, Merge-Log, Quelle `active=0`, `merged_into_id`.
4.5 Sammelbearbeitung: Alias, Lagerort/Kategorie, Archivieren für mehrere ausgewählte Artikel; alles in `maintenance_log`.

### Phase 5 – Datenbereinigung (einmalig, außerhalb der App)
Skripte unter `scripts/cleanup/` (lokal, nicht deployt): Quellen → CSV (Lieferant, Art.-Nr., Bezeichnung, Gebinde, Preis, Datum, Beleg) → Abgleich gegen DB-Kopie → **Vorschlagsdatei** (alt/neu, Konfidenz, unklar) → Freigabe durch dich → idempotente SQL-Migration (`INSERT … ON DUPLICATE KEY` über `dedupe_key` bzw. Art.-Nr.) → Ausführung erst lokal, dann nach Backup produktiv → Artikelpflege-Prüfungen erneut. Zusammenführungen nur über den Dialog aus 4.4.

### Phase 6 – Produktivsetzung
Gesamttest lokal gegen frische Kopie der Prod-DB (Migrationen dort genau so ausführen wie später live; Mails nur im Testbetrieb an `dev_email`; Handy-Test im WLAN über die Mac-IP) → Backup → Migrationen produktiv → Merge in den Live-Branch → Upload (inkl. `sw.js` mit neuer `CACHE`-Version, `DB_VERSION` unverändert) → Rundgang/Kontrolle/Versand (Testbetrieb) und Inventur prüfen → Tag `live-JJJJ-MM-TT` → Testinstanz löschen bzw. Basic Auth + Subdomain abschalten.
Rollback: Code wie WEBSPACE-INSTALL.md §10 auf `pre-artikelpflege`; neue Tabellen/Spalten bleiben stehen (alter Code ignoriert sie). Daten-Rollback nur über das Backup und nur, wenn die Bereinigung selbst fehlerhaft war.

---

## E. Entscheidungen (geklärt am 2026-10-29)

| # | Frage | Entscheidung |
|---|---|---|
| E0 | Basis-Stand | Der Stand auf dem Webspace ist maßgeblich; `new-ui` ist vermutlich ausgerollt. Vor dem Tag wird per Datei-Vergleich (Mountain Duck, nur lesend) bestätigt, dass `new-ui` dem Webspace entspricht. Abweichungen werden vorher geklärt. |
| E1 | Zähleinheit Inventur | Es wird weiter in `items.unit` gezählt. Bewertet wird über `unit_factor` bzw. das Gebinde des zuletzt gekauften Artikels. |
| E2 | Bestätigte Einkäufe | Keine dauerhafte Rechnungserfassung. Preise werden **manuell ausgelöst** eingegeben, mit Art „Listenpreis“ oder „bestätigter Einkauf“. Rechnungen werden nur gelegentlich übernommen, außerhalb der App (wie Phase 5). |
| E3 | `valuation_price` | Wird parallel unverändert weitergeführt. Die App wählt **nicht** automatisch zwischen den beiden. Die Inventur-CSV enthält beide Spalten: Bewertungspreis (BP) und erfasster Einkaufspreis inkl. Quelle und Datum. Die Auswahl passiert in Excel. |
| E4 | Art.-Nr. in Mail/PDF | Optional: wird nur mitgeschickt, wenn eine hinterlegt ist. Kein Pflichtfeld. |
| E5 | Kategorie | Keine Oberfläche, aber die Spalte `items.category VARCHAR(64) NULL` wird gleich mit angelegt. |
| E6 | Lieferantenartikel automatisch anlegen | Nein. Fehlende Einträge erscheinen als Aufgabe in der Artikelpflege. |
| E7 | Inventuren auf dem Server speichern | Nein. |
| E8 | Synonymliste | Zuerst als Datei im Code (`app/Data/synonyms.php`). Ergänzt wird bei Bedarf in einer Sitzung, danach Deploy. Eine Pflege in der App kommt erst, wenn die Liste oft geändert wird. |
| E9 | Tests | PHPUnit nur lokal (`require-dev`), wird nicht deployt. |
| E10 | Mehrere Gebinde beim selben Lieferanten | Das werden zwei interne Artikel. `supplier_articles` bleibt trotzdem ohne UNIQUE (`item_id`, `supplier_id`) und hat `is_default`, damit beim Zusammenführen nichts verloren geht. |
| E11 | Quellen für die Bereinigung | Uneinheitliche PDFs, die unregelmäßig kommen. Ablauf: PDFs in einen Ordner außerhalb des Repos → Extraktion mit Sichtprüfung → Vorschlagstabelle → deine Freigabe → idempotentes SQL. |
| E12 | Testinstanz | **Entfällt.** Getestet wird lokal (Docker mit Kopie der Produktions-DB), danach wird die Produktion direkt umgestellt (siehe Phase 6). |

---

## F. Detailplan Datenpflege-Modul (Phase 4, Stand 2026-10-29)

### Rahmen (neu entschieden)

- **Rechnungen und Lieferscheine kommen nicht als Feature in Orderlauf.** Der User fotografiert sie gelegentlich und pflegt sie gemeinsam mit Claude ein – auch für das Live-System. Das Modul muss diesen Weg gut unterstützen, aber keine Belege lesen.
- Erste Datenpflege (Transgourmet-Ordersatz) ist als Daten-Migration erledigt: `database/private/20261029_data_transgourmet_ordersatz.sql` (nicht im Git, Repo ist öffentlich). Ablauf und Skripte: `scripts/cleanup/transgourmet/`.

### Ist-Stand im echten Bestand (lokal, nach Transgourmet-Abgleich)

| Prüfung | Anzahl |
|---|---|
| aktiv ohne Lieferant | 0 |
| aktiv in inaktivem Lagerort (SB) | 28 |
| aktiv ohne Bestelldaten | 234 |
| aktiv ohne Preis | 234 |
| aktiv ohne Einkaufspreis (Inventur) | 302 |
| verschiedene Einheiten-Schreibweisen | 116 |

### Bausteine in Umsetzungsreihenfolge

**4.0 Grundlage: zusammengeführte Artikel auf Geräten** (vor jedem Zusammenführen in der App)
Payloads für Bestellung und Inventur liefern `merged_into` (alte → neue ID). Offene Bestellrunden und Inventuren hängen Einträge eines zusammengeführten Artikels beim nächsten Online-Kontakt um, statt sie zu verlieren. Kein `DB_VERSION`-Sprung.

**4.1 Pflege-Übersicht `/pflege`** (ab Rolle editor, Menü „Datenpflege“)
Kacheln mit Anzahl je Prüfung, Fehler (rot) getrennt von Hinweisen (grau). Klick → gefilterte Liste → Bearbeiten im bestehenden Listen-Dialog → „nächster Artikel“. Keine eigene Bearbeitungsmaske.

| Prüfung | Art |
|---|---|
| aktiv ohne aktiven Lieferanten | Fehler |
| Bestelldaten ohne Zuordnung (Lieferant entfernt, Daten noch da) | Fehler |
| gleiche Art.-Nr. bei mehreren Artikeln desselben Lieferanten | Fehler |
| aktiv in inaktivem Lagerort | Hinweis |
| ohne Bestelldaten / ohne Art.-Nr. – nur bei Lieferanten mit Art.-Nr. (Schalter je Lieferant) | Hinweis |
| ohne Preis · Preis älter als N Monate · ohne Einkaufspreis | Hinweis |
| Gebinde fehlt · Zähleinheit für Inventur nicht umrechenbar | Hinweis |
| Einheiten-Schreibweise uneinheitlich (Ktn/Karton, Kg/kg, Btl/Beutel) | Hinweis |
| langer Name ohne Alias | Hinweis |

**4.2 Preisauffälligkeiten** (Teil der Übersicht)
Sprung > Faktor 3 bzw. ~10 zum Vorpreis desselben Lieferantenartikels (Kommafehler), Abweichung > X % zwischen Lieferanten nur bei gleichem Vergleichspreis-Bezug (€/kg zu €/kg). Nur Anzeige, nie automatische Korrektur.

**4.3 Dubletten-Vorschläge**
`DuplicateFinder`: Normalisierung (Umlaute, Einheiten/Mengen abtrennen), Tokens, Synonymliste `app/Data/synonyms.php` (Pommes/Frites, TK/tiefgekühlt, o. Stein/entsteint …), **Gegensatzpaare als Malus** (mit/ohne Stein, frisch/TK, ganz/geschnitten, glatt/kraus, hell/dunkel, Größenangaben L/M/XL). Gleiche Art.-Nr./EAN = starkes Signal. Ergebnis: Paar + Score + Begründung. Entscheidung „getrennt lassen“ / „später“ in neuer Tabelle `dedupe_decisions` – erscheint dann nicht wieder.
Erfahrung aus dem echten Bestand: reine Namensähnlichkeit liefert fast nur Varianten (Petersilie glatt/kraus, Handschuhe L/M/XL, Pirulo-Sorten) – die Gegensatzpaare sind wichtiger als das Fuzzy-Matching.

**4.4 Zusammenführen-Dialog**
Gegenüberstellung beider Artikel (Name, Alias, Einheit, Lager, Min/Max, Lieferanten mit Priorität, Bestelldaten, letzte Preise). Ziel wählen, Feldkonflikte je Feld entscheiden. Eine Transaktion wie in der Transgourmet-Migration bereits erprobt: Protokoll mit Vorher-Stand → Bestelldaten/Preise umhängen → Zuordnungen zusammenführen (höhere Priorität gewinnt) → Lieferanten der Quelle behalten ihre bisherige Bestellbezeichnung → Quelle inaktiv + `merged_into_id`. „Rückgängig“ aus dem Protokoll, solange danach nichts Widersprüchliches passiert ist.

**4.5 Sammelbearbeitung**
Mehrere Artikel auswählen → Lagerort setzen, archivieren (inaktiv), Einheit vereinheitlichen, Lieferant als Alternative mit Priorität ergänzen. Jede Aktion im `maintenance_log`.

**4.6 Pflegepaket einspielen (Option, für den Foto-Workflow)**
Claude erzeugt aus Fotos/Listen ein strukturiertes Paket (JSON: Preise, Bestelldaten, Alias, Zuordnungen – je Eintrag mit Beleg und Datum). Im Modul: Datei wählen → Vorschau alt/neu → nur angehakte Zeilen übernehmen. Läuft über dieselben Repositories wie die App (Prüfungen, Doppel-Schutz, Protokoll). Alternative ohne Feature: wie bisher idempotentes SQL, das der User in phpMyAdmin ausführt.

### Neue Tabellen

`dedupe_decisions (item_a, item_b, decision, decided_by, decided_at)` – mehr nicht; `maintenance_log` und `merged_into_id` gibt es schon. Synonymliste als Datei. Ggf. Spalte `suppliers.has_skus` für die Art.-Nr.-Prüfung.

### Entscheidungen Phase 4 (2026-10-29)

| # | Frage | Entscheidung |
|---|---|---|
| F1 | Pflegepaket-Import | Ja, bauen (4.6) |
| F2 | Preis veraltet | nach 6 Monaten – relevant für Inventur **und** Kalkulation |
| F3 | Lieferanten mit Art.-Nr. | Schalter `suppliers.has_skus`; an: Transgourmet, Pütz, HANOS. Aus: OGA, Gastro Service |
| F4 | Rückgängig für Zusammenführen | Kein Rückgängig-Knopf; deutliche Warnung vorab, Vorher-Stand im Protokoll |
| F5 | Lagerort SB | Strandbar, nur im Sommer – inaktiv ist Absicht, keine Prüfung „aktiv in inaktivem Lagerort“ |

4.0 entfällt: Bestellrunden und Inventuren arbeiten mit dem eingefrorenen Snapshot im Gerät – ein zusammengeführter Artikel bleibt dort bis Rundenende erhalten.
