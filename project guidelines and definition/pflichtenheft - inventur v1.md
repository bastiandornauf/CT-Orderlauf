# Pflichtenheft Inventur V1

**CT-Orderlauf – Erweiterung „Inventur“**

Dokumentstatus: V1 (Planung, umsetzungsreif für Stufe 1)  
Bezug: [pflichtenheft v1.md](pflichtenheft%20v1.md), [Pflichtenheft - Ergänzungen V1.md](daten/Pflichtenheft%20-%20Ergänzungen%20V1.md)

⸻

## 1. Ziel

### Zweck

Die bestehende Web-App CT-Orderlauf wird um einen **eigenständigen Inventur-Workflow** erweitert. Nutzer können monatlich (oder nach Bedarf) Lagerbestände per Rundgang erfassen und als **Inventur-Bestandsliste** (CSV für Excel) exportieren.

Die Inventur nutzt dieselben **Artikel- und Lagerort-Stammdaten** wie die Bestellrunde, arbeitet aber **logisch und technisch getrennt** von Bestellungen, Lieferanten und E-Mail-Ausgabe.

### Ziele

- Papier-Inventurlisten und doppelte Erfassung reduzieren
- Bewegungsablauf im Lager beibehalten (Rundgang nach Lagerort)
- Offlinefähige Erfassung (Kühlhaus / schlechtes Netz)
- Professionelle Unterscheidung: **nicht gezählt** vs. **gezählt mit 0** vs. **gezählt mit Bestand**
- Optional: **Bewertung** des Lagerbestands über einen Bewertungspreis pro Artikel
- Monatliche Wiederholbarkeit (Session pro Inventur-Lauf)

### Leitplanke (verbindlich)

**Der laufende Betrieb der Bestellrunde darf durch die Inventur zu keinem Zeitpunkt gefährdet werden** — weder durch Datenverlust, noch durch blockierte Abläufe, noch durch unbeabsichtigtes Überschreiben lokaler Bestelldaten. Die Inventur ist ein **Zusatzmodul** mit strikter Trennung; die Bestellrunde bleibt das prioritäre Tagesgeschäft.

### Nicht-Ziel (gesamt)

- Kein Ersatz für Buchhaltungs-ERP oder gesetzliche Jahresinventur mit Prüfer-Signatur
- Keine automatische Bestandsführung / permanente Lagerbuchung im Tagesgeschäft
- Keine Webshop- oder Lieferanten-Integration in der Inventur

⸻

## 2. Systemrahmen

### Technische Basis (unverändert)

- Server: PHP 8.2+, MySQL 8
- Frontend: Browser, PWA, iPhone-optimiert
- Offline: IndexedDB + Service Worker (analog Bestellrunde)
- Keine kostenpflichtigen Zusatzdienste

### Abgrenzung zur Bestellrunde

| Aspekt | Bestellrunde | Inventur |
|--------|--------------|----------|
| Zweck | Bestellmengen → Lieferanten | Gezählte Bestände → Liste/Archiv |
| Lokale Stores | `order_round`, `order_entries`, … | `inventory_session`, `inventory_lines`, … |
| Parallelität | Eine aktive Bestellrunde | Eine aktive Inventur-Session |
| Lieferanten | relevant | **nicht** relevant |
| Ausgabe | E-Mail / PDF / Outlook | **CSV** (Excel) |
| Mengenlogik | leer = kein Bedarf | leer = **offen**; 0 nur nach expliziter Bestätigung |

Eine gleichzeitig laufende Bestellrunde und Inventur-Session ist **technisch möglich**; die **Bestellrunde hat immer Vorrang** in UI und bei Datenoperationen (siehe Abschnitt 2.1).

⸻

### 2.1 Betriebssicherheit – Bestellrunde nicht gefährden

Dieser Abschnitt ist **verbindlich** für alle Stufen (insbesondere Stufe 1).

#### Fachliche Priorität

| Regel | Beschreibung |
|-------|----------------|
| **Bestellung zuerst** | Dashboard, Navigation und kritische Hinweise behandeln eine **aktive oder pausierte Bestellrunde** als wichtiger als eine Inventur. |
| **Kein Zwang** | Inventur starten/beenden ist **optional**; niemand muss Inventur laufen haben, um bestellen zu können. |
| **Keine Blockade** | Inventur darf keine Bestell-Schritte (Vorbereiten, Rundgang, Kontrolle, Ausgabe) sperren oder verstecken. |
| **Getrennte Ausgabe** | Inventur erzeugt **kein** E-Mail-, PDF- oder Lieferanten-Output und ändert keine Bestell-Zuordnungen. |

#### Technische Isolation (IndexedDB / Client)

**Verboten** (hart — bei Code-Review und Tests prüfen):

- Inventur-Funktionen dürfen **niemals** `order_round`, `order_entries`, `supplier_notes`, `suppliers`, `supplier_delivery_days`, `item_supplier_links` oder bestellrunden-spezifische `meta`-Einträge **löschen, leeren oder überschreiben**.
- `savePreparedSnapshot()` / `clearOrderRound()` (Bestellrunde) dürfen **niemals** `inventory_session`, `inventory_lines` oder inventur-eigene Katalog-Kopien leeren.
- Es gibt **keinen** gemeinsamen „alles löschen“-Pfad für Bestellung und Inventur.
- Die Inventur ruft **nicht** `/api/order/payload` auf und nutzt **keine** Bestell-Prepare-Logik, die `order_entries` mit leert.

**Pflicht** (Stufe 1):

| Maßnahme | Zweck |
|----------|--------|
| Eigene Stores `inventory_session`, `inventory_lines` | Zählstände vollständig getrennt |
| Eigene Funktion `saveInventorySnapshot()` (o. ä.) | Löscht/ersetzt **nur** Inventur-Stores |
| Eigene Funktion `clearInventorySession()` | Löscht **nur** Inventur-Stores |
| Eigener API-Endpunkt `GET /api/inventory/payload` | Keine Seiteneffekte auf Bestell-Controller |
| **Katalog-Kopie** `inventory_catalog` (Items + Locations zum Inventur-Start) | Verhindert, dass „Bestellrunde laden“ die Inventur-Stammdaten wegputzt, während parallel gezählt wird |

**Hintergrund Katalog-Kopie:** Heute leert `savePreparedSnapshot()` beim Bestell-Prepare auch die Stores `items` und `locations`. Liest die Inventur dieselben Stores und würde jemand unterdessen eine Bestellrunde neu laden, ginge Inventur-Bezug verloren oder es entstünden Inkonsistenzen. Daher hält die Inventur beim Start eine **eigene, unveränderliche Kopie** der Artikel/Lagerorte in `inventory_catalog` (oder äquivalent benannt). Die Bestellrunde darf ihre Stores weiter wie bisher pflegen — **ohne** die Inventur-Kopie anzufassen.

#### Server (MySQL)

- Migration `valuation_price` (und später `barcode`) sind **additive** Änderungen an `items` — bestehende Bestell-Queries und APIs bleiben kompatibel.
- Kein Pflicht-Schreiben in Bestell-Tabellen durch Inventur-Abschluss (Stufe 1: Inventur bleibt clientseitig bis CSV-Export).
- Inventur-Endpunkte unter eigenem Pfad (`/api/inventory/…`), getrennt von `/api/order/…`.

#### UI / Nutzerführung

- Wenn **Bestellrunde aktiv/pausiert** und **Inventur aktiv**: dezenter Hinweis auf Inventur-Screens („Bestellrunde läuft parallel“), **kein** Auto-Abbruch der Bestellung.
- Wenn **Bestellrunde aktiv/pausiert**: Button „Inventur starten“ zeigt **keinen** Dialog, der die Bestellrunde beendet; Inventur startet unabhängig (eigener Snapshot).
- Optional Stufe 2: Hinweis auf dem Dashboard, nicht blockierend.

#### Akzeptanz – Betriebssicherheit (zusätzlich zu Abschnitt 7)

1. Mit **laufender Bestellrunde** (Mengen erfasst): Inventur starten → Bestell-Mengen und `order_entries` **unverändert**; Bestell-Rundgang weiter nutzbar.
2. Während **laufender Inventur**: „Bestellung starten“ / „Bestellrunde laden“ wie bisher → Inventur-Zählungen in `inventory_lines` **unverändert**; Inventur-Rundgang weiter nutzbar (Katalog aus `inventory_catalog`).
3. Inventur **abschließen** / lokale Inventur löschen → Bestellrunde **unberührt**.
4. Bestellrunde **finalisieren** / löschen → Inventur **unberührt** (falls noch offen).
5. Regression: bestehende Bestell-Testszenarien (Offline-Rundgang, Kontrolle, Ausgabe) ohne Inventur-Nutzung **unverändert** möglich.

⸻

## 3. Grundprinzip – Phasen

Der Inventur-Workflow gliedert sich in **drei Phasen** (analog Bestellrunde, ohne Lieferanten-Kontrolle):

### Phase 1 – Vorbereitung (online)

- Nutzer startet **„Inventur starten“**
- Optional: Bezeichnung / Stichtag (z. B. „Inventur 2026-06“, Default: heutiges Datum)
- System lädt vom Server und speichert lokal:
  - aktive **Lagerorte**
  - aktive **Artikel** (inkl. Einheit, Lagerort, Sortierung, `valuation_price` ab Stufe 1)
- Zustand der Session: `prepared`

### Phase 2 – Rundgang (offline möglich)

- Darstellung nach **Lagerort** (Tabs / Wechsel wie Bestellrunde)
- Pro Artikel: Zählstatus und Menge erfassen
- Speicherung **sofort lokal** (unterbrechbar, monatlich fortsetzbar)
- Zustände: `active` (Erfassung läuft), `paused` (unterbrochen)

### Phase 3 – Abschluss (online für Export empfohlen)

- Kurze **Übersicht**: Anzahl gezählt / offen / Summe Wert (wenn Preise gepflegt)
- Warnung bei noch **offenen** Positionen (Abschluss trotzdem möglich)
- **CSV-Export** der Inventur-Bestandsliste
- Session lokal als `finalized` markieren; lokale Daten danach löschbar (wie Bestellrunde)

⸻

## 4. Kernfunktionen nach Stufen

### Stufe 1 – Inventur-MVP (Pflicht für erste Lieferung)

#### 4.1 Navigation / Einstieg

- Dashboard: Button **„Inventur starten“** bzw. **„Inventur fortsetzen“** / **„Inventur abschließen“** (abhängig vom Session-Zustand)
- Eigener Menübereich oder klar getrennter Workflow (keine Vermischung mit Bestell-Schritten 1–4)

#### 4.2 Stammdaten-Erweiterung Artikel

Neues Feld am Artikel (serverseitig + in Artikel-Formular für Rollen `admin` / `editor`):

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|--------------|
| `valuation_price` | DECIMAL(10,2) NULL | nein | Bewertungspreis pro **Einheit** (€); nur für Inventur-Export |

Bestehende Felder unverändert: Name, Einheit, Lagerort, Min/Max (nur Bestell-Kontext), aktiv.

**Inaktive Artikel** (`active = 0`) erscheinen **nicht** in der Inventur.

#### 4.3 Zählstatus pro Artikel (Kernlogik)

Jeder Artikel in der aktiven Inventur-Session hat genau einen von drei **fachlichen** Zuständen:

| Zustand | Technisch | Anzeige | Export |
|---------|-----------|---------|--------|
| Nicht gezählt | `status = open`, `quantity = null` | optional dezent / ausgeblendet in „nur Erledigte“-Ansicht | **nein** |
| Gezählt = 0 | `status = counted`, `quantity = 0` | erledigt, Menge 0 | **ja** |
| Gezählt > 0 | `status = counted`, `quantity > 0` | erledigt mit Menge | **ja** |

**Regeln:**

- Leeres Mengenfeld beim Verlassen des Artikels → bleibt **`open`** (nicht automatisch 0).
- Explizite Aktion **„Leer / 0“** (oder äquivalent: Bestätigung „bestätigt leer“) → **`counted`**, Menge `0`.
- Stepper oder Eingabe einer Menge **> 0** → **`counted`** mit dieser Menge.
- Menge auf 0 per Stepper setzen → nur mit gleicher expliziter 0-Bestätigung wie oben (nicht stillschweigend beim Leeren des Feldes).

Es werden **nicht** alle Artikel mit Bestand 0 vorab in der Liste hervorgehoben; die Liste zeigt pro Lagerort die **aktiven Artikel** des Ortes. Fortschritt: **„X von Y gezählt, Z offen“**.

#### 4.4 Rundgang-UI

Wiederverwendung des bewährten Musters aus der Bestellrunde:

- Lagerort-Tabs, „Nächstes Lager“
- Suche über alle Artikel (namensbasiert)
- Mengen-Stepper (+/−) und Freitext-Eingabe (Dezimaltrennzeichen `,` oder `.`)
- **Keine** Lieferanten-Filter, **keine** freien Bestellpositionen, **keine** Bestell-Notizen pro Lieferant

Zusatz gegenüber Bestellrunde:

- Button **„Leer / 0“** pro Artikelzeile
- Visueller Unterschied: offen vs. gezählt (z. B. Häkchen / Farbe / Icon)
- Optional: Filter „nur offene“ / „alle“ (Stufe 1 minimal: alle Artikel des Lagerorts sichtbar)

#### 4.5 Abschluss & Export

**CSV** (Download im Browser):

- Encoding: **UTF-8 mit BOM**
- Trennzeichen: **`;`** (Excel DE)
- Dateiname-Vorschlag: `Inventur_YYYY-MM-DD.csv` oder inkl. Bezeichnung aus Vorbereitung

**Spalten (Stufe 1):**

| Spalte | Inhalt |
|--------|--------|
| `stichtag` | Datum der Inventur (Vorbereitung) |
| `bezeichnung` | optionale Session-Bezeichnung |
| `lagerort` | Name |
| `artikel_id` | interne ID |
| `artikel` | Name |
| `einheit` | Einheit |
| `status` | `offen` \| `gezaehlt_0` \| `gezaehlt` |
| `menge` | nur bei gezählt (0 oder >0) |
| `bewertungspreis` | aus Stammdaten oder leer |
| `wert` | `menge * bewertungspreis` wenn Preis gesetzt, sonst leer |

**Alle** Katalog-Artikel in der CSV; Spalte **`status`**: `offen` | `gezaehlt_0` | `gezaehlt` (Filter in Excel).  
Im Abschluss: Liste **noch nicht gezählter** Artikel mit Hinweis und optional **Leer / 0** ohne erneuten Rundgang.

Nach Export: Session `finalized`; Nutzer kann Session **löschen** (IndexedDB leeren für Inventur-Stores).

#### 4.6 Offline & Persistenz (IndexedDB)

Neue Stores (DB-Version increment, Migration in `storage.js`):

| Store | Inhalt | Von Bestell-`clear*` berührt? |
|-------|--------|-------------------------------|
| `inventory_session` | id, status, label, stichtag, timestamps, … | **Nein** |
| `inventory_lines` | item_id, status, quantity, updated_at | **Nein** |
| `inventory_catalog` | Kopie items + locations beim Inventur-Start | **Nein** |

Beim **„Inventur starten“**:

- Nur `saveInventorySnapshot()` → leert/ersetzt **ausschließlich** die drei Inventur-Stores oben.
- Rundgang liest Artikel/Lagerorte aus **`inventory_catalog`**, nicht aus den von der Bestellrunde veränderbaren `items`/`locations`-Stores.
- Inventur-Zeilen **nie** in `order_entries` schreiben.

**Nur eine** aktive Inventur-Session (`inventory_session` id=1 analog `order_round`).

#### 4.7 Rollen & Rechte

Analog Bestellrunde:

| Rolle | Inventur starten / zählen | Export | Bewertungspreis pflegen |
|-------|---------------------------|--------|-------------------------|
| `order` | ja | ja | nein |
| `editor` | ja | ja | ja |
| `admin` | ja | ja | ja |

(Server-Routen mit `AuthMiddleware` wie bestehende Module.)

#### 4.8 API / Server (Stufe 1)

- `GET /api/inventory/payload` — Artikel + Lagerorte (nur `active`), **ohne** Lieferanten; Response-Form analog Order-Payload, aber **eigener** Controller/Route
- Keine Pflicht-Speicherung der abgeschlossenen Inventur auf dem Server in Stufe 1
- Migration SQL: `items.valuation_price` (additiv; Bestell-APIs unverändert nutzbar)

⸻

### Stufe 2 – Komfort & Bewertung

- **CSV Import/Export** Artikel-Stammdaten mit Spalte `bewertungspreis`; eigener Export/Import **Bewertungspreise** (Massenpflege, nur Preise) — **umgesetzt**
- Summenzeilen im CSV (pro Lagerort, gesamt **Wert**)
- Abschluss-Warnung mit Liste der **offenen** Artikel (kurz, ohne Export-Pflicht)
- Optional: zweites CSV-Blatt oder zweite Datei `…_offen.csv` mit offenen Positionen
- Session-Bezeichnung Pflicht/Standard verbessern (Monats-Inventur)
- Dashboard-Hinweis (nicht blockierend), wenn Bestellrunde und Inventur parallel aktiv
- Bewertungspreis in Inventur-Rundgang **nur lesbar** (optional)
- **Kein** Feature, das eine laufende Bestellrunde automatisch beendet, pausiert oder neu lädt

⸻

**Nicht in aktueller Planung:** Stufe 3 (Barcode), Stufe 4 (Server-Historie), Stufe 5 (Rollen-Erweiterungen) – bewusst zurückgestellt.

### Stufe 3 – Barcode (zurückgestellt)

- Neues Feld `items.barcode` (VARCHAR, Index, optional UNIQUE)
- Scan-Eingabe im Rundgang:
  - Kamera (Browser-API, HTTPS)
  - Bluetooth-Handscanner (Tastatur-Emulation in Suchfeld)
- Treffer: Sprung zum Artikel, Fokus Menge
- **Priorität Regal-Etiketten** (interner Code, z. B. Artikel-ID oder fest vergebener Code), nicht Produkt-EAN
- Optional: PDF-Generator **Regal-Etiketten** zum Ausdrucken

⸻

### Stufe 4 – Server-Historie (monatliche Archive, zurückgestellt)

- Tabellen (Vorschlag):

```sql
inventory_sessions (
  id, label, stichtag, closed_at, closed_by_user_id, created_at
)
inventory_session_lines (
  session_id, item_id, quantity, status, -- status immer 'counted' beim Import
  item_name_snapshot, unit_snapshot, location_name_snapshot,
  valuation_price_snapshot, value_computed
)
```

- Abschluss: CSV **und** POST zum Speichern der Session
- UI: Liste vergangener Inventuren, CSV erneut laden
- Optional Stufe 4b: Vergleich zur **letzten** Session (Δ Menge, Δ Wert) – rein informativ

⸻

### Stufe 5 – Optional (nur bei Bedarf, zurückgestellt)

- Parallele Zählung durch zwei Nutzer (Lagerort-„Claim“)
- Sperre Inventur während aktiver Bestellrunde
- DATEV / Buchhaltungs-Schnittstelle
- Excel-Vorlage mit vordefinierten Formeln (Download-Link)

⸻

## 5. UI-Struktur (Nutzer sichtbar)

### 5.1 Dashboard

Zusätzlich zu Bestell-Buttons:

- **Inventur starten** (keine aktive Session)
- **Inventur fortsetzen** (status `active` / `paused`)
- **Inventur abschließen** (status bereit für Abschluss – ab Stufe 1 nach Rundgang direkt oder über Zwischenschritt „Übersicht“)

### 5.2 Screen „Inventur vorbereiten“

- Feld Bezeichnung (optional, Placeholder: „Inventur Juni 2026“)
- Stichtag (Default: heute)
- Button: Stammdaten laden / Inventur starten
- Hinweis: Offline-Rundgang möglich nach Laden

### 5.3 Screen „Inventur-Rundgang“

- Kopf: Fortschritt global und pro Lagerort
- Artikelzeile: Name, Einheit, Status, Stepper, „Leer / 0“
- Suche (namensbasiert, über alle Lagerorte)

### 5.4 Screen „Inventur abschließen“

- Statistik: gezählt / offen / Gesamtwert (wenn Preise)
- Warnung bei offenen Positionen
- Button **CSV herunterladen**
- Button **Inventur beenden** (finalized + optional lokale Daten löschen)

⸻

## 6. Datenmodell – Übersicht

### Stufe 1 – Server (MySQL)

```sql
ALTER TABLE items
  ADD COLUMN valuation_price DECIMAL(10,2) NULL
  COMMENT 'Bewertungspreis pro Einheit fuer Inventur';
```

### Stufe 1 – Client (IndexedDB)

Siehe Abschnitt 4.6; keine neuen Pflicht-Felder auf dem Server für Session-Lines.

### Stufe 3 – Server

```sql
ALTER TABLE items
  ADD COLUMN barcode VARCHAR(64) NULL,
  ADD INDEX idx_items_barcode (barcode);
```

### Stufe 4 – Server

Siehe Stufe 4 in Abschnitt 4.

⸻

## 7. Akzeptanzkriterien Stufe 1

1. Nutzer kann Inventur starten, Stammdaten offline nutzen, Rundgang unterbrechen und Tage später fortsetzen.
2. Artikel ohne Eingabe erscheinen in der CSV mit `status` = `offen`.
3. Artikel mit **„Leer / 0“** erscheinen mit `status` = `gezaehlt_0`, Menge `0`.
4. Artikel mit Menge > 0 erscheinen mit korrekter Menge und Wert (wenn Bewertungspreis gesetzt).
5. Bestellrunde und Inventur überschreiben sich **nicht** (getrennte Stores / keine `order_entries` für Inventur).
6. Inaktive Artikel sind im Rundgang **nicht** sichtbar.
7. `editor`/`admin` können Bewertungspreis am Artikel pflegen; `order` nicht.
8. Alle **Betriebssicherheits**-Kriterien aus Abschnitt 2.1 sind erfüllt (parallele Nutzung, kein gegenseitiges Löschen).

⸻

## 8. Implementierungsreihenfolge (für Entwicklung)

1. Migration `valuation_price` + Artikel-UI (additiv)
2. IndexedDB: `inventory_*` Stores + `saveInventorySnapshot` / `clearInventorySession` (**Whitelist** nur Inventur-Stores)
3. `GET /api/inventory/payload` (getrennt von Order)
4. Routen + Views: prepare → round → finalize
5. Alpine/JS: **eigenes** Modul (`inventory-pages.js`), **kein** Eingriff in `order-pages.js` außer Dashboard-Buttons
6. CSV-Export im Abschluss
7. Dashboard-Integration (Bestell-Buttons unverändert priorisiert)
8. Manuelle Tests: 0 vs. offen, Export Excel DE, **plus** parallele Bestell+Inventur gemäß Abschnitt 2.1

⸻

## 9. Entscheidungen (festgelegt)

| Frage | Entscheidung |
|-------|----------------|
| Alle 0-Bestände vorab anzeigen? | **Nein** |
| 0 vs. nicht gezählt? | **Ja**, drei Zustände (Abschnitt 4.3) |
| Häufigkeit | **Monatlich** (Session pro Lauf; Archiv Stufe 4) |
| Bewertungspreis | **Ein Preis pro Artikel** |
| Barcode zu Beginn? | **Nein** (Stufe 3) |
| Export-Format | **CSV** für Excel |
| Inaktive Artikel | **Ausgeschlossen** |
| Nach Abschluss ändern? | **Nein** (nach CSV-Export gesperrt; CSV erneut laden möglich) |
| Offene Artikel im Rundgang? | **Nicht** alles vorab ankreuzen; Abschluss zeigt **Liste offen** |
| Server-Historie Stufe 1? | **Nein** (CSV lokal archivieren) |
| Bestellbetrieb gefährden? | **Nein** — verbindliche Isolation (Abschnitt 2.1) |
| Inventur und Bestellung parallel? | **Ja**, technisch; Bestellung hat Vorrang; getrennte Daten |

⸻

## 10. Offene Punkte (gering, Stufe 2+)

1. **Zwischenscreen „Übersicht“** vor Abschluss wie Bestell-Kontrolle – oder direkt von Rundgang zu Abschluss? (Empfehlung Stufe 1: kurzer Übersicht-Screen.)
2. **Dezimalmengen** (kg): gleiche Freitext-Logik wie Bestellrunde (`parseQuantity`) – in Stufe 1 **ja**.
3. ~~Gemeinsame Stammdaten-Stores~~ → **entschieden:** eigene `inventory_catalog`-Kopie (Abschnitt 2.1 / 4.6), damit „Bestellrunde laden“ die Inventur nicht stört.

⸻

## 11. Referenz – Bezug zum Haupt-Pflichtenheft

Die Inventur übernimmt bewusst:

- Lagerort-first-Rundgang
- Offline-First-Erfassung
- Trennung Erfassen / Abschließen
- PWA- und Rollenmodell

Die Inventur **ersetzt nicht** Abschnitt 4.5–4.7 (Bestellrunde, Lieferanten, Ausgabe) des Haupt-Pflichtenhefts, sondern ergänzt ein paralleles Modul.

⸻

*Ende Pflichtenheft Inventur V1*
