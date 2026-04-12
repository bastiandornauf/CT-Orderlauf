Perfekt. Das ist jetzt wirklich klar, konsistent und umsetzbar.
Ich baue dir daraus ein sauberes, praxisnahes Pflichtenheft V1, so dass du (oder eine KI / ein Entwickler) direkt anfangen kann.

⸻

📄 Pflichtenheft V1

Mobile Bestellhilfe für Küchenlager (Offlinefähig)

⸻

1. Ziel des Systems

Zweck

Die Web-App dient als mobiles, offlinefähiges Bestellwerkzeug, mit dem während eines Lager-Rundgangs Bestellmengen erfasst und anschließend in strukturierte Bestellungen (E-Mail oder Notiz) überführt werden.

Ziel

Reduktion von:
	•	Papierlisten
	•	doppelter Datenerfassung
	•	Übertragungsfehlern
	•	ineffizientem Wechsel zwischen Lager, Excel und E-Mail

⸻

2. Systemrahmen

Technische Basis
	•	Server: PHP + MySQL
	•	Frontend: Browser (iPhone optimiert)
	•	Keine externen kostenpflichtigen Dienste
	•	Offlinefähigkeit über Browser (IndexedDB + Cache)

Nutzung
	•	Vorbereitung mit Internet
	•	Rundgang ohne Internet
	•	Abschluss wieder mit Internet

⸻

3. Grundprinzip

Die App arbeitet in 3 klar getrennten Phasen:

1. Vorbereitung (online)
	•	relevante Daten werden geladen
	•	Tagesliste wird lokal gespeichert

2. Rundgang (offline)
	•	Bestellmengen werden erfasst
	•	Daten werden lokal gespeichert

3. Abschluss (online)
	•	Kontrolle
	•	Ausgabe (E-Mail / Notiz)

⸻

4. Kernfunktionen

⸻

4.1 Lieferantenverwaltung

Funktionen
	•	Lieferant anlegen, bearbeiten, deaktivieren
	•	Liefertage definieren
	•	Bestelltyp definieren:
	•	E-Mail
	•	Webshop (nur Liste)
	•	E-Mail-Adresse hinterlegen
	•	individuelle E-Mail-Vorlage hinterlegen

⸻

4.2 Artikelverwaltung

Artikel enthalten:
	•	Name
	•	Einheit / Gebinde
	•	Lagerort
	•	optional: Mindestbestand
	•	optional: Maximalbestand
	•	aktiv / inaktiv

Zuordnung:
	•	Artikel ↔ mehrere Lieferanten möglich
	•	pro Lieferant:
	•	Priorität

⸻

4.3 Lagerorte
	•	frei definierbar (z. B. Kühlung, TK, Trockenlager)
	•	Sortierreihenfolge festlegbar

⸻

4.4 Bestellrunde starten („Vorbereitung“)

Funktion:
	•	Button: „Bestellung starten“

Verhalten:
	•	System prüft:
	•	welche Lieferanten liefern am nächsten Tag
	•	(optional später: Bestellfristen)
	•	relevante Artikel werden geladen
	•	Daten werden lokal gespeichert (Offline-Basis)

⸻

4.5 Rundgang (Offline-Modus)

Grundprinzip:
	•	Erfassung erfolgt nach Lagerort sortiert

Darstellung:
	•	Lagerort → Liste von Artikeln

Pro Artikel:
	•	Name
	•	Einheit
	•	Eingabefeld für Bestellmenge

Verhalten:
	•	nur manuelle Mengeneingabe
	•	keine Pflicht zur Bestandsangabe
	•	leere Felder = kein Bedarf

Zusatz:
	•	freie Artikel / Notizen pro Lieferant möglich

Speicherung:
	•	jede Eingabe wird sofort lokal gespeichert
	•	Rundgang jederzeit unterbrechbar

⸻

4.6 Lieferantenzuordnung

Nach der Erfassung:

Logik:
	•	Artikel wird einem Lieferanten zugeordnet basierend auf:
	1.	Lieferfähigkeit am Zieltag
	2.	Priorität

Ziel:
	•	automatisch gruppierte Bestelllisten je Lieferant

⸻

4.7 Kontrollansicht

Anzeige:
	•	nur Artikel mit Bestellmenge > 0
	•	gruppiert nach Lieferant

Inhalte:
	•	Artikel
	•	Menge
	•	Einheit
	•	freie Zusatzartikel / Notizen

Funktionen:
	•	Bearbeiten möglich
	•	Positionen löschen
	•	finale Prüfung

⸻

4.8 Ausgabe

⸻

Fall A: E-Mail-Lieferant

Funktion:
	•	generierter E-Mail-Text basierend auf Vorlage

Inhalte:
	•	Betreff
	•	strukturierte Artikelliste
	•	Zusatztexte

Optionen:
	•	Öffnen im Mailprogramm (mailto)
	•	optional: direkter Versand (später)

CC:
	•	immer an dich selbst

⸻

Fall B: Webshop-Lieferant

Funktion:
	•	E-Mail oder Liste an dich selbst

Zweck:
	•	manuelle Bestellung im Webshop

⸻

5. Offline-Funktionalität

⸻

Anforderungen

Muss:
	•	Artikellisten offline verfügbar
	•	Eingaben offline speicherbar
	•	kein Datenverlust bei Tab-Schließen
	•	Wiederaufnahme möglich

⸻

Verhalten

Vor Rundgang:
	•	Daten werden lokal geladen

Während Rundgang:
	•	keine Serververbindung nötig

Nach Rundgang:
	•	Synchronisation / Ausgabe nur mit Netz

⸻

Technische Umsetzung (implizit)
	•	Service Worker (Caching)
	•	IndexedDB (lokale Daten)

⸻

6. Benutzeroberfläche (konzeptionell)

⸻

Startseite
	•	„Bestellung starten“
	•	„Rundgang fortsetzen“
	•	„Lieferanten“
	•	„Artikel“
	•	„Lagerorte“

⸻

Rundgang
	•	Tabs oder Scroll:
	•	nach Lagerort
	•	große Eingabefelder
	•	schnelle Bedienbarkeit

⸻

Kontrollansicht
	•	nach Lieferant gruppiert
	•	klare Liste
	•	Editierbar

⸻

Ausgabe
	•	pro Lieferant getrennt
	•	Buttons:
	•	„Mail öffnen“
	•	„kopieren“

⸻

7. Nicht-Ziele (bewusst ausgeschlossen in V1)
	•	keine automatische Bestellung
	•	keine Webshop-Integration
	•	keine Preisoptimierung
	•	keine KI-Entscheidungen
	•	keine komplexe Bestandsführung
	•	keine Pflicht-Historie

⸻

8. Erweiterungsmöglichkeiten (später)
	•	Preisverwaltung
	•	Bestellhistorie
	•	KI-Vorschläge („du bestellst sonst…“)
	•	automatische Mengenvorschläge
	•	Bestellfristen-Logik
	•	Mehrbenutzerbetrieb

⸻

9. Zentrale Designentscheidungen

⸻

1. Fokus auf manuelle Kontrolle

Du bleibst Entscheider → App ist Werkzeug

2. Lagerort vor Lieferant

Erfassung folgt deinem realen Bewegungsablauf

3. Offline First (für Erfassung)

System muss im Kühlhaus funktionieren

4. Trennung von:
	•	Erfassen
	•	Prüfen
	•	Ausgeben

⸻

10. Offene Punkte (klein, aber klärbar)

Das ist alles schon fast entschieden, aber zur finalen Schärfung:
	1.	Bestelltag immer „morgen“ oder wählbar?

    Wählbar ist besser

	2.	Mehrere Bestellungen parallel möglich oder nur eine aktiv?

    Eine Bestellung aber bei allen Lieferanten

	3.	Freie Artikel global oder pro Lieferant? (ich würde sagen: pro Lieferant)

    Ja, pro Lieferant    