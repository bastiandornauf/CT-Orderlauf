---
name: beleg-einpflegen
description: Rechnungen, Lieferscheine, Preislisten oder Webshop-Screenshots (Fotos/PDFs) für CT-Orderlauf auswerten und daraus ein Pflegepaket (JSON) mit Preisen und Bestelldaten erstellen, das der User unter Datenpflege → Pflegepaket einspielen übernimmt. Verwenden, sobald der User einen Beleg schickt und Preise, Art.-Nr. oder Gebinde in Orderlauf einpflegen will.
---

# Beleg in Orderlauf einpflegen

Ziel: Aus einem Beleg ein **Pflegepaket** machen, das der User selbst prüft und einspielt.
Claude schreibt **nie direkt** in die Live-Datenbank und legt **keine neuen Artikel** an.

## 0. Grundlage besorgen

Den aktuellen Artikelstamm brauchst du für die Zuordnung (IDs, Art.-Nr., letzte Preise):

- **Live-System:** User bitten, unter *Datenpflege → Artikelstamm exportieren* die Datei
  `orderlauf-stammdaten-JJJJ-MM-TT.json` zu laden und den Pfad zu nennen (meist `~/Downloads/`).
- **Nur lokal testen:** lokale Docker-DB, sofern frisch aus dem Prod-Export geladen (`scripts/dev/load-prod-dump.sh`).

Ist der Export älter als der letzte Pflegestand, frischen holen – sonst stimmen „bisher“-Werte und Doppel-Prüfung nicht.

## 1. Beleg lesen

Kopf: **Lieferant**, **Belegart** (Rechnung, Lieferschein, Angebot/Preisliste, Webshop), **Beleg-Nr.**,
**Datum** (Rechnungs- bzw. Lieferdatum → `price_date`).

Je Position: Art.-Nr., Bezeichnung, Menge, Einheit, **Einzelpreis netto**, ggf. Gesamtpreis.

- Unleserliches nicht raten – nachfragen oder Position auslassen und benennen.
- Nicht übernehmen: Pfand/Leergut, Fracht, Mindermengenzuschlag, Rabatt-/Bonuszeilen, Gutschriften. Rabatte, die
  sich klar auf eine Position beziehen, in den Einzelpreis einrechnen und in `note` vermerken.
- Bruttopreise (z. B. Webshop für Endkunden) in netto umrechnen oder `"is_net": false` setzen – und im Ergebnis sagen.
- Gewichtsware (Fleisch, Fisch, Käse „ca. 2,5 kg“): Kilopreis mit `"per_unit": "kg"`, nicht den Stückpreis.
- Mengenstaffeln: den tatsächlich berechneten Preis nehmen.

Erst die **Transkription als Tabelle** zeigen (Pos., Art.-Nr., Bezeichnung, Menge, Einheit, Einzelpreis) und
Auffälligkeiten nennen. Bei mehreren Fotos zuerst prüfen, ob es dieselbe Rechnung ist (Seiten) oder mehrere.

## 2. Zuordnen

Reihenfolge der Sicherheit:

1. **Art.-Nr.** des Lieferanten stimmt mit `supplier_articles.supplier_sku` überein → sicher.
2. **Bestellbezeichnung** (`supplier_label`) desselben Lieferanten stimmt (fast) überein → sicher.
3. Name/Alias ähnlich und Einheit/Gebinde plausibel → **prüfen**, dem User vorlegen.
4. Kein Treffer → unter „nicht in Orderlauf“ auflisten. Nicht alles gehört in Orderlauf (andere Abteilung, seltene
   Bestellungen) – keine Artikel anlegen, nur fragen, ob einzelne aufgenommen werden sollen (das macht der User in der App).

Ist der Lieferant dem Artikel noch nicht zugeordnet, aber klar richtig: zuerst eine `link`-Änderung
(Priorität **unter** dem bisherigen Hauptlieferanten, der bisherige bleibt Prio-Lieferant), dann Preis/Bestelldaten.

**Plausibilität:** Neuen Preis mit `latest_known`/`latest_purchase` im Export vergleichen (gleiche Preisbasis!).
Faktor ≥ 3 oder Einheit passt nicht (Karton vs. Stück, kg vs. Beutel) → als „prüfen“ markieren und erklären.

## 3. Paket bauen

- Rechnung / Lieferschein mit Preisen → `"price_type": "purchase"`, `"source": "invoice"`
  (das sind die **bestätigten Einkäufe** für die Inventurbewertung).
- Angebot / Preisliste / Ordersatz → `"known"`, `"pricelist"`; Webshop-Screenshot → `"known"`, `"shop"`.
- `document_ref` = Beleg-Nr. (sorgt dafür, dass derselbe Beleg nicht doppelt eingeht).
- `item_name` immer mitgeben (Name oder Alias aus dem Export) – die Vorschau warnt, wenn ID und Name nicht passen.
- `note`: z. B. `"Pos. 3"` oder Hinweise zu Rabatt/Umrechnung.
- Bestelldaten (`article`) nur ergänzen, wenn sicher: Art.-Nr., formelle Bezeichnung des Lieferanten, Gebinde
  (`pack_size` + `pack_unit` kg|g|l|ml|stk – nur beides zusammen). Vorhandene Werte nur ändern, wenn der Beleg sie klar widerlegt.
- Alias/Name/Einheit nur auf ausdrücklichen Wunsch des Users.

```json
{
  "format": "orderlauf-pflegepaket",
  "version": 1,
  "title": "Pütz Rechnung 4711 vom 20.10.2026",
  "source": { "supplier": "Pütz", "document_ref": "RE 4711", "date": "2026-10-20" },
  "changes": [
    { "op": "price", "item_id": 180, "item_name": "Oliven schwarz", "price_type": "purchase", "source": "invoice",
      "amount": "24,80", "per_qty": "1", "per_unit": "order", "note": "Pos. 3" },
    { "op": "article", "item_id": 180, "item_name": "Oliven schwarz", "supplier": "Pütz",
      "supplier_sku": "12345", "supplier_label": "Oliven schwarz o. Stein 4,25 kg", "pack_size": "4,25", "pack_unit": "kg" },
    { "op": "link", "item_id": 155, "item_name": "Kidneybohnen", "supplier": "Pütz", "priority": 0 },
    { "op": "price", "item_id": 150, "item_name": "Kalbsrücken", "price_type": "purchase", "amount": "38,90", "per_unit": "kg" }
  ]
}
```

`source.supplier`, `source.document_ref` und `source.date` gelten als Standard für alle Zeilen ohne eigene Angabe.
Weitere Änderungsarten: `alias`, `name`, `unit` (`{ "op": "unit", "item_id": …, "unit": "Karton" }`),
`unit_factor` (`"unit_factor": "5", "base_unit": "kg|l|stk"`). Beträge als Text mit Komma oder Punkt.
`per_unit`: `order` = je Bestelleinheit des Artikels, sonst `kg|g|l|ml|stk`; `per_qty` = Menge dieser Basis (Standard 1).

## 4. Ablegen und übergeben

- Speichern unter `~/Backups/orderlauf/pakete/JJJJ-MM-TT_<lieferant>_<beleg-nr>.json`.
- **Nie ins Git-Repo** – es ist öffentlich und Pakete enthalten Einkaufskonditionen.
- Dem User kurz sagen: wie viele Positionen zugeordnet, welche „prüfen“, welche nicht in Orderlauf; dann
  *Datenpflege → Pflegepaket einspielen → Datei wählen → Vorschau prüfen → übernehmen*.
  Ein erneutes Einspielen desselben Pakets ändert nichts (Zeilen stehen auf „schon so“).
