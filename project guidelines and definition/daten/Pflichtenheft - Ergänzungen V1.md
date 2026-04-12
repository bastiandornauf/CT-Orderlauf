# ERGÄNZUNGEN

Hier sind einige Details, die in den Pflichtenheften bisher unklar geblieben sind. 

## Ergänzung 1: Master-Workflow / End-to-End-Systemablauf

Systemablauf

Der vollständige Systemablauf der Anwendung gliedert sich in sechs Phasen.

Phase 1 – Vorbereitung

Der Nutzer startet eine neue Bestellrunde über die Funktion „Bestellung starten“.

Dabei wählt er ein Ziel-Datum aus. In der Praxis ist dies häufig der nächste Tag, das Datum muss jedoch grundsätzlich frei wählbar sein.

Anschließend lädt das System alle für die Bestellrunde notwendigen Daten vom Server:
	•	Artikel
	•	Lagerorte
	•	Lieferanten
	•	Lieferanten-Liefertage
	•	Artikel-Lieferanten-Zuordnungen inkl. Prioritäten
	•	Vorlagen und Konfigurationsdaten für die Ausgabe

Diese Daten werden lokal auf dem Gerät gespeichert, damit der Rundgang später offline durchgeführt werden kann.

Nach erfolgreicher Vorbereitung befindet sich die Bestellrunde im Zustand prepared.

Phase 2 – Rundgang / Erfassung

Der Nutzer startet den Rundgang. Die App zeigt die Artikel nach Lagerort gruppiert an.

Während des Rundgangs erfasst der Nutzer:
	•	Bestellmengen pro Artikel
	•	optionale Notizen
	•	optionale freie Zusatzpositionen

Alle Eingaben werden unmittelbar lokal gespeichert. Dadurch kann der Rundgang auch ohne Netz durchgeführt, unterbrochen und später fortgesetzt werden.

Während der aktiven Erfassung befindet sich die Bestellrunde im Zustand active.
Wird sie unterbrochen, wechselt sie in den Zustand paused.

Phase 3 – Rückkehr in den Online-Bereich

Nach dem Rundgang kehrt der Nutzer in einen Bereich mit Netzempfang zurück.

Die App erkennt den Online-Status, lädt jedoch nicht automatisch neue Inhalte nach und überschreibt keine bestehenden lokalen Eingaben. Der vorbereitete lokale Stand bleibt maßgeblich für diese Bestellrunde.

Danach kann der Nutzer in die Kontrollansicht wechseln. Der Zustand lautet nun ready_for_review.

Phase 4 – Kontrolle

In der Kontrollansicht werden nur Positionen mit erfasster Bestellmenge angezeigt.

Die Positionen werden nach Lieferanten gruppiert. Die Lieferantenzuordnung erfolgt nach der definierten Lieferantenlogik.

Der Nutzer kann in dieser Phase:
	•	Mengen korrigieren
	•	Positionen entfernen
	•	Notizen ergänzen
	•	den vorgeschlagenen Lieferanten manuell ändern

Phase 5 – Ausgabe

Für jeden Lieferanten erzeugt das System eine Ausgabe:
	•	bei Mail-Lieferanten: strukturierter Mailtext mit Betreff, Empfänger und CC
	•	bei Webshop-Lieferanten: strukturierte Bestellliste bzw. Erinnerung an den Nutzer selbst

Die Ausgabe erfolgt in Version 1 primär über:
	•	Kopieren des Mailtexts
	•	optional mailto:-Link

Nach Erzeugung der Ausgabe wechselt die Bestellrunde in den Zustand finalized.

Phase 6 – Abschluss

Nach Abschluss kann die Bestellrunde lokal:
	•	gelöscht
	•	oder für die aktuelle Sitzung abgeschlossen werden

Eine dauerhafte serverseitige Historie ist in Version 1 nicht zwingend vorgesehen.

⸻

## Ergänzung2: Lieferanten-Entscheidungslogik

Ziel

Jeder Artikel mit Bestellmenge soll für die Ausgabe genau einem Lieferanten zugeordnet werden.

Grundlogik

Für jeden Artikel mit Bestellmenge gilt:
	1.	Es werden alle Lieferanten betrachtet, bei denen dieser Artikel überhaupt angelegt ist.
	2.	Lieferanten, die für das gewählte Ziel-Datum nicht liefern können, werden ausgeschlossen.
	3.	Die verbleibenden Lieferanten werden nach Priorität sortiert.
	4.	Der Lieferant mit der höchsten Priorität wird als Standardzuordnung gewählt.

Wichtige fachliche Regel

Ein Artikel darf nur einem Lieferanten zugeordnet werden, bei dem dieser Artikel tatsächlich im System hinterlegt ist.

Das bedeutet:
	•	Priorität entscheidet nur zwischen zulässigen Lieferanten
	•	nicht hinterlegte Lieferanten sind immer ausgeschlossen

Damit ist eine „harte Negativgrenze“ gegeben:
Ein Gemüselieferant ohne Fleischartikel darf diese Artikel nicht erhalten, auch nicht durch manuelle Umverteilung.

Problemfälle

Wenn für einen Artikel mit Bestellmenge kein geeigneter Lieferant für das Ziel-Datum verfügbar ist, wird die Position in eine separate Liste „Problemartikel“ übernommen.

Diese Liste wird dem Nutzer in der Kontrollansicht klar angezeigt.

Beispiele:
	•	kein zugeordneter Lieferant liefert am gewählten Datum
	•	Artikel ist zwar erfasst, aber aktuell keinem gültigen Lieferanten zuweisbar

Manuelle Änderung in der Kontrollansicht

In der Kontrollansicht darf der Nutzer den vorgeschlagenen Lieferanten manuell ändern.

Dabei gelten zwei Regeln:
	1.	Es dürfen nur Lieferanten ausgewählt werden, bei denen der Artikel im System angelegt ist.
	2.	Lieferanten ohne Artikelzuordnung sind nicht auswählbar.

Dadurch bleibt die manuelle Kontrolle möglich, ohne die Stammdatenlogik zu brechen.

⸻

## Ergänzung3: Lokale Offline-Datenhaltung mit IndexedDB

Ziel

Die App speichert alle für die Erfassung notwendigen Arbeitsdaten lokal auf dem Gerät, damit der Rundgang offline durchgeführt werden kann.

Grundsatz

IndexedDB dient ausschließlich der lokalen Arbeits- und Offline-Speicherung einer Bestellrunde.
Stammdaten bleiben serverseitig führend.

Lokal zu speichernde Daten

1. Bestellrunde

Store: order_round
	•	id
	•	status
	•	created_at
	•	target_date
	•	prepared_at
	•	review_ready_at
	•	finalized_at

2. Artikel

Store: items
	•	id
	•	name
	•	unit
	•	location_id
	•	active

3. Lagerorte

Store: locations
	•	id
	•	name
	•	sort_order

4. Lieferanten

Store: suppliers
	•	id
	•	name
	•	order_type
	•	email
	•	active

5. Lieferanten-Liefertage

Store: supplier_delivery_days
	•	supplier_id
	•	weekday

6. Artikel-Lieferanten-Zuordnungen

Store: item_supplier_links
	•	item_id
	•	supplier_id
	•	priority

7. Erfasste Positionen

Store: order_entries
	•	item_id
	•	quantity
	•	note
	•	selected_supplier_id (optional, falls schon manuell geändert)
	•	is_free_item

8. Freie Zusatzpositionen

Entweder im selben Store order_entries mit Kennzeichnung is_free_item = true oder in separatem Store free_entries

Empfohlene Felder:
	•	label
	•	quantity
	•	note
	•	supplier_id

9. Metadaten

Store: meta
	•	last_sync
	•	app_version
	•	prepared_flag
	•	offline_ready_flag

Speicherregel

Die lokale Datenhaltung soll:
	•	nach jeder Eingabe aktualisiert werden
	•	nicht nur Formularzustände puffern
	•	einen vollständigen Wiedereinstieg in eine unterbrochene Bestellrunde ermöglichen

Kein Zweck von IndexedDB

IndexedDB ist in Version 1 nicht die langfristige Historie und nicht das führende Stammdatensystem.

⸻

## Ergänzung4: Definierte CSV-Importformate

Ziel

Importe sollen nur über klar definierte CSV-Strukturen erfolgen.
Beliebige Excel-Dateien werden nicht direkt interpretiert.

Zeichensatz und Trennzeichen

Empfohlen:
	•	UTF-8
	•	Semikolon ; als Trennzeichen

⸻

4.1 Importformat: Lagerorte

Felder

name;sort_order

Beispiel

Kühlung;1
Tiefkühlung;2
Trockenlager;3


⸻

4.2 Importformat: Lieferanten

Felder

name;email;type;active

Werte
	•	type: mail oder webshop
	•	active: 1 oder 0

Beispiel

Gemüse Meyer;bestellung@gemuese-meyer.de;mail;1
Metro Shop;intern@betrieb.de;webshop;1


⸻

4.3 Importformat: Lieferanten-Liefertage

Felder

supplier_name;delivery_days

Format

delivery_days als kommaseparierte Wochentagsliste
1 = Montag bis 7 = Sonntag

Beispiel

Gemüse Meyer;1,3,5
Metro Shop;1,2,3,4,5,6


⸻

4.4 Importformat: Artikel

Felder

name;location;unit;min_stock;max_stock;active

Beispiel

Milch 3,5%;Kühlung;Kiste;2;10;1
Rinderhack;Kühlung;kg;0;0;1
Pommes 10mm;Tiefkühlung;Beutel;2;8;1

Hinweis: min_stock und max_stock sind in Version 1 optional fachlich nutzbar, dürfen aber bereits gepflegt werden.

⸻

4.5 Importformat: Artikel-Lieferanten-Zuordnung

Felder

item_name;supplier_name;priority

Beispiel

Milch 3,5%;Gemüse Meyer;2
Milch 3,5%;Metro Shop;1
Rinderhack;Fleisch Becker;1

Validierungsregeln

Beim Import müssen mindestens folgende Prüfungen erfolgen:
	•	Pflichtfelder vorhanden
	•	referenzierte Lagerorte existieren
	•	referenzierte Lieferanten existieren
	•	referenzierte Artikel existieren
	•	Priorität ist numerisch
	•	type ist gültig
	•	active ist gültig

Importablauf
	1.	CSV hochladen
	2.	Vorschau anzeigen
	3.	Validierungsfehler sichtbar machen
	4.	Import bestätigen
	5.	Ergebnis protokollieren

⸻

## Ergänzung5: Zustandsmodell der App

Ziel

Das Zustandsmodell beschreibt die fachlichen und technischen Zustände der App sowie deren Übergänge.

Hauptzustände einer Bestellrunde

Zustand	Beschreibung
idle	Keine aktive Bestellrunde vorhanden
prepared	Bestellrunde vorbereitet, Daten lokal geladen
active	Rundgang läuft, Eingaben werden erfasst
paused	Rundgang wurde unterbrochen, kann fortgesetzt werden
ready_for_review	Rundgang abgeschlossen, Kontrolle möglich
finalized	Ausgabe wurde erzeugt, Bestellrunde abgeschlossen

Zusätzliche Systemzustände

Verbindungsstatus

Zustand	Beschreibung
online	Serverzugriff vorhanden
offline	Kein Serverzugriff vorhanden

UI-Zustände

Zustand	Beschreibung
loading	Daten werden geladen
error	Ein Fehler ist aufgetreten
empty	Es gibt keine anzuzeigenden Daten
offline_ready	Lokale Daten sind vollständig geladen und offline nutzbar

Zustandsübergänge

Von	Nach	Auslöser
idle	prepared	Bestellrunde wird vorbereitet und lokal gespeichert
prepared	active	Nutzer startet den Rundgang
active	paused	Nutzer unterbricht den Rundgang
paused	active	Nutzer setzt den Rundgang fort
active	ready_for_review	Nutzer beendet den Rundgang
ready_for_review	finalized	Ausgabe wird erzeugt
finalized	idle	Bestellrunde wird abgeschlossen und zurückgesetzt

Zustandsregeln
	•	Ohne vorbereitete lokale Daten darf kein Rundgang gestartet werden.
	•	Im Zustand offline darf der Rundgang weitergeführt, aber nicht final ausgegeben werden.
	•	Im Zustand ready_for_review dürfen Mengen, Notizen und Lieferantenzuordnungen noch geändert werden.
	•	Im Zustand finalized sind keine inhaltlichen Änderungen mehr vorgesehen, außer durch Neustart oder neue Bestellrunde.

Sichtbarkeit im UI

Die App soll folgende Zustände für den Nutzer sichtbar machen:
	•	Online / Offline
	•	lokale Speicherung aktiv
	•	Rundgang aktiv / pausiert
	•	Kontrolle möglich
	•	Ausgabe erzeugt
	•	Problemartikel vorhanden

