Technische Dokumentation

Ergänzung zum Pflichtenheft

Projekt: Offlinefähige mobile Bestellhilfe für Küchenlager

⸻

1. Ziel der technischen Umsetzung

Die Anwendung soll als kleine, robuste, offlinefähige Web-App umgesetzt werden, die auf einem bestehenden Webspace mit PHP und MySQL betrieben werden kann.

Die technische Umsetzung soll dabei:
	•	ohne kostenpflichtige Dienste auskommen
	•	ohne schwergewichtige Infrastruktur auskommen
	•	mobil auf dem iPhone gut funktionieren
	•	im Kern offlinefähig sein
	•	leicht wartbar bleiben
	•	von KI-Assistenten weiterentwickelbar sein
	•	trotzdem sauber strukturiert und professionell aufgebaut sein

⸻

2. Technologiestack

Backend
	•	PHP 8+
	•	MySQL
	•	JSON-API für interaktive Funktionen
	•	klassische serverseitige Auslieferung für Grundseiten und Verwaltungsbereiche

Frontend
	•	HTML5
	•	CSS3
	•	JavaScript (ES6+)
	•	Alpine.js als leichtes Frontend-Hilfsmittel
	•	Progressive Web App-Konzept
	•	Service Worker
	•	IndexedDB für lokale Offline-Daten

Import / Export
	•	CSV als primäres Importformat
	•	optional CSV-Export

E-Mail
	•	primär Generierung von Mailtexten
	•	optional mailto:-Links
	•	Direktversand erst später, falls technisch zuverlässig möglich

⸻

3. Architekturentscheidung

Grundsatz

Es wird kein schwergewichtiges JavaScript-Framework wie React, Angular oder Vue als Hauptbasis verwendet.

Stattdessen wird eine hybride Architektur genutzt:
	•	serverseitig gerenderte Basisstruktur mit PHP
	•	Alpine.js für leichte Interaktivität
	•	modularer JavaScript-Code für Offline-Logik, IndexedDB, Service Worker und Rundgangsprozess

Begründung

Diese Architektur ist für das Projekt am besten geeignet, weil sie:
	•	auf einfachem PHP-Webspace lauffähig ist
	•	keinen komplexen Build-Prozess zwingend erfordert
	•	überschaubar bleibt
	•	trotzdem modern genug für eine klare UI und Offline-Funktionalität ist

⸻

4. Technische Leitprinzipien

4.1 Trennung von Verantwortlichkeiten

Die Anwendung muss sauber getrennt sein in:
	•	Inhalt / Daten
	•	Darstellung / Design
	•	Interaktionslogik
	•	Persistenz
	•	Serverlogik

4.2 Mobile First

Alle zentralen Arbeitsoberflächen werden zuerst für mobile Nutzung gedacht.

4.3 Offline First für die Erfassung

Der Rundgang muss auch ohne Netz funktionieren.

4.4 Progressive Enhancement

Die Anwendung soll in ihrer Grundstruktur robust bleiben und zusätzliche Komfortfunktionen schrittweise ergänzen.

4.5 Einfachheit vor technischer Eitelkeit

Es werden nur so viele Abstraktionen eingeführt, wie dem Projekt wirklich helfen.

⸻

5. Frontend-Konzept

5.1 HTML-Struktur

HTML dient ausschließlich der semantischen Struktur, nicht der visuellen Gestaltung.

Es sollen verwendet werden:
	•	sinnvolle HTML5-Elemente
	•	klare Abschnittsstrukturen
	•	semantisch nachvollziehbare Container
	•	wiederverwendbare Komponentenblöcke

5.2 CSS-Konzept

CSS ist vollständig für Darstellung, Abstände, Farben, Typografie, Layout und Zustände zuständig.

Verbindliche Regeln
	•	kein Inline-CSS
	•	keine Style-Attribute im HTML
	•	keine hartcodierten Einzelstile in Templates, wenn dafür bereits Klassen existieren oder entstehen sollten
	•	CSS nur in zentralen Stylesheets oder klar getrennten Komponenten-Styles
	•	Wiederverwendung vorhandener Klassen vor Einführung neuer Speziallösungen

5.3 JavaScript-Konzept

JavaScript steuert:
	•	Interaktionen
	•	Zustandswechsel
	•	Offline-Speicherung
	•	Datenkommunikation mit dem Backend
	•	Eingabelogik
	•	UI-State-Handling

Alpine.js

Alpine.js darf für leichte UI-Zustände und kleine interaktive Komponenten verwendet werden, insbesondere für:
	•	Tabs
	•	aufklappbare Bereiche
	•	Modals
	•	Statusanzeigen
	•	Formularzustände
	•	lokale UI-Interaktionen

Nicht mit Alpine.js lösen

Komplexe Geschäftslogik, IndexedDB-Zugriffe und Service-Worker-Logik sollen nicht in chaotischen Inline-Ausdrücken in HTML verschwinden, sondern in sauber getrennten JavaScript-Modulen liegen.

⸻

6. Designsystem und Look

Zielbild

Die Anwendung soll modern, ruhig, klar und funktional aussehen.

Nicht gewünscht:
	•	verspielte Optik
	•	inkonsistente Farben
	•	spontane Einzellösungen
	•	visuelle Unruhe
	•	überdesignte Effekte

Designprinzipien
	•	reduzierter, heller Grundlook
	•	klare Hierarchien
	•	große Touch-Flächen
	•	konsistente Abstände
	•	klare Karten- und Blockstruktur
	•	sparsame Akzentfarbe
	•	gute Lesbarkeit auch in Arbeitsumgebungen

Technische Umsetzung

Es soll ein kleines, internes Designsystem verwendet werden.

Dazu gehören:
	•	Farbvariablen
	•	Typografievariablen
	•	Abstandsskalen
	•	Radiusdefinitionen
	•	Schatten- und Linienlogik
	•	Statusfarben
	•	Standardkomponenten

CSS Custom Properties

Globale Designwerte sollen zentral als CSS-Variablen definiert werden, zum Beispiel für:
	•	Farben
	•	Abstände
	•	Schriftgrößen
	•	Border-Radius
	•	Schatten
	•	Layer / Z-Index-Stufen

⸻

7. Komponentenstrategie

Die Oberfläche soll aus wiederverwendbaren UI-Bausteinen aufgebaut sein.

Standardkomponenten
	•	Button
	•	Icon-Button
	•	Input
	•	Zahlenfeld
	•	Select
	•	Card
	•	Section Header
	•	Status Badge
	•	Tab Navigation
	•	Toolbar
	•	List Item
	•	Modal
	•	Toast / Hinweisbox
	•	Formulargruppe
	•	leere Zustandsanzeige
	•	Ladeanzeige

Grundregel

Komponenten werden nicht für Einzelfälle „schnell speziell“ gebaut, sondern möglichst allgemein und wiederverwendbar.

⸻

8. Zustandsmodell im Frontend

Die App muss klar sichtbare Zustände besitzen.

Relevante States
	•	nicht eingeloggt
	•	eingeloggt
	•	online
	•	offline
	•	Bestellrunde nicht geladen
	•	Bestellrunde geladen
	•	Rundgang aktiv
	•	Rundgang pausiert
	•	Abschluss bereit
	•	Ausgabe generiert
	•	Fehlerzustand
	•	Ladezustand
	•	leerer Zustand

Regel

Jeder State muss:
	•	fachlich klar benannt sein
	•	technisch eindeutig repräsentiert werden
	•	im UI nachvollziehbar angezeigt werden

⸻

9. Offline-Technik

Service Worker

Wird verwendet für:
	•	Caching statischer Assets
	•	Offline-Grundfunktion der App
	•	Laden vorbereiteter Oberflächen

IndexedDB

Wird verwendet für:
	•	lokal vorbereitete Bestellrunden
	•	lokale Artikeldaten für den Rundgang
	•	Eingaben während des Rundgangs
	•	Wiederaufnahme unterbrochener Bestellrunden

Regel

Offline-Datenhaltung muss strukturiert erfolgen.
Kein unübersichtliches Vermischen von:
	•	Formzustand
	•	UI-State
	•	Stammdaten
	•	Rundgangsdaten

⸻

10. Backend-Konzept

Struktur

Das Backend wird modular aufgebaut in Bereichen für:
	•	Authentifizierung
	•	Stammdaten
	•	Bestellprozess
	•	Import
	•	E-Mail-Ausgabe
	•	Konfiguration

API-Prinzip

API-Endpunkte liefern und empfangen JSON.

Regel

Jeder Endpunkt soll:
	•	klar abgegrenzt sein
	•	eine fachliche Aufgabe haben
	•	keine versteckte Mehrfachverantwortung besitzen
	•	konsistente Antwortformate liefern

⸻

11. Datenmodell und Persistenz

Primärspeicher

MySQL ist der zentrale Serverseitige Datenspeicher.

Lokalspeicher

IndexedDB dient ausschließlich der Offline- und Arbeitszustandsspeicherung.

Grundsatz
	•	Stammdaten liegen serverseitig
	•	temporäre Rundgangsdaten liegen lokal
	•	Abschlussdaten können optional serverseitig persistiert werden

⸻

12. Importstrategie

Primärformat

CSV ist das Standardimportformat.

Gründe
	•	einfach
	•	robust
	•	leicht aus Excel exportierbar
	•	leicht validierbar
	•	serverseitig gut verarbeitbar

Importprinzip

Importe müssen über definierte Formate erfolgen.
Keine „magische“ Interpretation beliebiger Excel-Dateien im Produktivbetrieb.

Empfohlene Importarten
	•	Lieferanten
	•	Artikel
	•	Lagerorte
	•	Artikel-Lieferanten-Zuordnungen

Importprozess
	•	Datei hochladen
	•	Vorschau erzeugen
	•	Validierungsfehler anzeigen
	•	Import bestätigen
	•	Ergebnis protokollieren

⸻

13. E-Mail-Strategie

Version 1

Die App erzeugt:
	•	Betreff
	•	Empfänger
	•	CC
	•	Mailtext

Die Ausgabe erfolgt über:
	•	Kopieren
	•	optional mailto:

Grund

Das ist robuster als früher Direktversand unter unsicherem Hosting.

Vorlagen

Es wird pro Lieferant eine konfigurierbare Mailvorlage unterstützt.

⸻

14. Authentifizierung und Zugriff

Empfehlung

Ein einfaches Login-System mit Benutzername und Passwort.

Nicht empfohlen

Geheime Links als alleinige Hauptsicherung.

Begründung

Auch für ein kleines internes Tool ist ein einfaches Login:
	•	sauberer
	•	sicherer
	•	besser für mehrere Nutzer
	•	leichter nachvollziehbar

Minimum
	•	Passwort-Hashing
	•	Session-Login
	•	HTTPS
	•	Logout
	•	optional „angemeldet bleiben“

⸻

15. Installations- und Mandantenstrategie

Primärempfehlung

Eigene Installation pro Nutzungseinheit / Betrieb.

Begründung

Für die Größenordnung ist das einfacher als Mandantenfähigkeit.

Optional später

Eine Outlet-Logik innerhalb einer Installation wäre möglich, ist aber kein Ziel von Version 1.

⸻

16. Codequalitätsregeln für das gesamte Projekt

Diese Regeln gelten projektweit und sind verbindlich.

Allgemein
	•	keine unnötige Komplexität
	•	keine toten Codepfade
	•	keine unbenutzten Funktionen
	•	keine Copy-Paste-Strukturen, wenn sinnvoll abstrahierbar
	•	klare Benennung vor cleverer Benennung
	•	Funktionen und Module klein und eindeutig halten

Struktur
	•	eine Datei, ein klarer Verantwortungsbereich
	•	keine riesigen Monolithdateien
	•	Templates, Logik und Styles sauber trennen
	•	wiederverwendbare Teile extrahieren

Benennung
	•	konsistente englische oder konsistent deutsche technische Benennung, nicht beides gemischt
	•	fachliche Begriffe müssen im ganzen Projekt gleich heißen
	•	kein variables Namenschaos

Kommentare
	•	Kommentare erklären das Warum, nicht stumpf das Was
	•	keine Kommentarwüsten
	•	keine widersprüchlichen Kommentare
	•	lieber klarer Code als kommentierter unklarer Code

⸻

17. Verbindliche KI-Regeln für Codegenerierung

Dieser Abschnitt ist ausdrücklich als Arbeitsregelwerk für KI-Assistenten gedacht.

17.1 Architekturregeln

Die KI muss bestehende Architekturentscheidungen respektieren und darf nicht eigenmächtig neue Frameworks oder Paradigmen einführen.

Insbesondere darf die KI nicht ohne ausdrückliche Freigabe:
	•	React
	•	Vue
	•	Angular
	•	Tailwind-Build-Setup
	•	TypeScript-Zwang
	•	externe UI-Bibliotheken
	•	komplexe State-Management-Libraries

einführen.

17.2 Trennung von Struktur und Darstellung

Die KI muss strikt zwischen HTML-Struktur, CSS-Darstellung und JavaScript-Logik trennen.

Verbindlich
	•	kein Inline-CSS
	•	keine Style-Attribute im HTML
	•	keine Layoutlogik direkt im Markup, wenn sie in CSS-Klassen abbildbar ist
	•	keine JavaScript-Geschäftslogik in HTML-Attributketten verstecken

17.3 CSS-Regeln

Die KI muss:
	•	logische, wiederverwendbare Klassen erstellen
	•	bestehende Klassen wiederverwenden, wenn sie semantisch passen
	•	keine Einmal-Klassen für triviale Abweichungen erzeugen
	•	Styles zentral pflegen
	•	Designwerte über Variablen organisieren

Verboten
	•	Inline-CSS
	•	hartcodierte Einzelwerte im Markup
	•	ungeprüfte Duplikate bestehender Klassen
	•	spontan eingeführte Farb- und Abstandschaoswerte

17.4 HTML-Regeln

Die KI muss:
	•	semantisches HTML verwenden
	•	klare Hierarchien aufbauen
	•	Komponenten konsistent strukturieren
	•	Accessibility-Grundlagen beachten

Verboten
	•	div-Suppe ohne Struktur
	•	wahllose Klassenflut ohne erkennbares System
	•	rein visuelle Klassennamen, wenn funktionale oder komponentenbezogene Benennung sinnvoller ist

17.5 JavaScript-Regeln

Die KI muss:
	•	JavaScript in eigene Dateien oder klar getrennte Module legen
	•	Funktionen klein halten
	•	DOM-Zugriffe sauber kapseln
	•	keine globale Variablenverschmutzung erzeugen
	•	Fehlerzustände sichtbar behandeln

Alpine.js-Regel

Alpine.js darf für leichte UI-Zustände verwendet werden, aber nicht als Sammelbecken für komplexe Geschäftslogik.

17.6 Backend-Regeln

Die KI muss:
	•	PHP-Code modular halten
	•	Datenbankzugriffe kapseln
	•	keine SQL-Strings wild im ganzen Projekt verteilen
	•	Prepared Statements verwenden
	•	Eingaben validieren und bereinigen
	•	konsistente JSON-Antworten liefern

17.7 Wiederverwendung

Bevor neuer Code erzeugt wird, muss die KI prüfen:
	•	existiert bereits eine passende Komponente?
	•	existiert bereits eine passende Klasse?
	•	existiert bereits eine Utility-Funktion?
	•	existiert bereits ein API-Muster?

Erst wenn das nicht der Fall ist, darf neuer Code ergänzt werden.

17.8 Refactoring-Regel

Wenn die KI neuen Code ergänzt und erkennt, dass bestehender Code unnötig dupliziert oder strukturell gebrochen wird, soll sie eine saubere Refaktorierung bevorzugen statt zusätzlichen Flickcode zu erzeugen.

17.9 Keine stillen Architekturbrüche

Die KI darf keine neuen Patterns oder Strukturansätze einführen, die dem restlichen Projekt widersprechen.

17.10 Dokumentationspflicht

Bei größeren Änderungen soll die KI:
	•	betroffene Module benennen
	•	die Änderung kurz begründen
	•	neue Komponenten oder Klassen nachvollziehbar benennen

⸻

18. Empfohlene Frontend-Konventionen

CSS-Klassen

Empfohlen ist eine klare, logische Benennung nach Komponenten und Rollen, zum Beispiel:
	•	app-header
	•	page-toolbar
	•	card
	•	card__title
	•	form-group
	•	input
	•	button
	•	button--primary
	•	status-badge
	•	status-badge--offline

Es muss nicht dogmatisch BEM sein, aber die Benennung soll:
	•	konsistent
	•	wiederverwendbar
	•	lesbar
	•	logisch verschachtelbar

Utility-Klassen

Kleine Utilities sind erlaubt, aber sparsam.

Nicht gewünscht ist ein Wildwuchs aus 100 Mikroklassen.

⸻

19. Empfohlene Projektstruktur

Beispielhaft:
	•	/public
	•	index.php
	•	assets/
	•	css/
	•	js/
	•	icons/
	•	/app
	•	/Controllers
	•	/Services
	•	/Repositories
	•	/Views
	•	/Middleware
	•	/config
	•	/storage
	•	/database
	•	/docs

Frontend-Dateien
	•	Styles nach Basis, Komponenten, Seiten und Utilities trennen
	•	JavaScript nach Modulen und Verantwortlichkeiten trennen

⸻

20. Empfohlene CSS-Struktur

Zum Beispiel:
	•	base.css
	•	tokens.css
	•	layout.css
	•	components.css
	•	forms.css
	•	states.css
	•	pages/*.css

Oder als zusammengeführte Build-Datei, aber logisch getrennt.

Wichtig ist nicht die Dateianzahl, sondern die klare Verantwortung.

⸻

21. Empfohlene JavaScript-Struktur

Zum Beispiel:
	•	app.js
	•	api.js
	•	storage.js
	•	offline.js
	•	order-round.js
	•	email-preview.js
	•	ui-state.js

Alpine-Komponenten dürfen ergänzt werden, aber die Kernlogik bleibt in benannten Modulen.

⸻

22. Qualitätsziel

Das Ergebnis soll kein „KI-Projekt“ sein, das man sofort erkennt, weil es:
	•	chaotisch gewachsen ist
	•	Inline-CSS nutzt
	•	doppelte Komponenten enthält
	•	inkonsistente Zustände hat
	•	beliebige Patterns mischt

Sondern ein kleines, ernstzunehmendes, sauberes Produktivwerkzeug.

⸻

23. Abschlussbewertung

Mit dieser Architektur und diesen Regeln bekommst du genau den Mittelweg, der für dein Projekt sinnvoll ist:
	•	nicht altbacken
	•	nicht überengineered
	•	nicht framework-schwer
	•	aber klar modern, modular und wartbar

Und ja: Mit so einem Regelwerk kann man sehr gut ohne großes Framework arbeiten, ohne im „klassischen PHP-CSS-Chaos“ zu landen.