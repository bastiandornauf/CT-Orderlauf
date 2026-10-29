# Einmalige Bereinigung: Transgourmet-Ordersatz → Orderlauf

Nur lokal, wird nicht deployt. Ordersätze und Ergebnisse liegen außerhalb des Repos
(`~/Downloads/preise orderlauf/`).

1. `extract.py <ordersatz-alt.XLS> <ordersatz-neu.XLS> ordersatz.json` – beide Ordersätze einlesen
   (braucht `xlrd==2.0.1`; die TG-Dateien haben fehlende XF-Einträge, siehe `readxls.py`)
2. Artikel aus der DB als TSV exportieren (id, name, unit, loc, active, sup)
3. `mapping.py` – von Hand geprüfte Zuordnung Orderlauf-ID → TG-Art.-Nr.
4. `build.py ordersatz.json items.tsv "Abgleich Transgourmet.xlsx"` – Prüf-Tabelle zum Abhaken
5. `find.py ordersatz.json <regex> …` – gezielte Suche im Ordersatz

Gebinde werden aus Rezeptmenge **und** Bezeichnung abgeleitet; widersprechen sie sich, bleibt
das Gebinde leer (keine geratene Umrechnung).
