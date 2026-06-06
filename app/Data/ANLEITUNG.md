# CT-Orderlauf – Anleitung für Anwender

Diese Anleitung richtet sich an **Küche, Lager und Büro**: Sie beschreibt den **Bestellablauf** und die wichtigsten Bedienelemente. Technische Installation steht im [README](../README.md) und in [WEBSPACE-INSTALL.md](../WEBSPACE-INSTALL.md).

---

## 1. Worum es geht

Mit **CT-Orderlauf** erfassen Sie eine **Bestellrunde** für mehrere Lieferanten: zuerst den **Rundgang** durch die Lagerorte (Mengen eintragen), dann die **Kontrolle** (Zuordnung, Korrekturen), danach die **Ausgabe** (E-Mails oder PDFs).  

Zusätzlich gibt es einen eigenen Ablauf **Inventur** (monatliche Bestandszählung) – **getrennt** von der Bestellung, mit CSV-Export für Excel.

**Wichtig:** Ihre **Eingaben der laufenden Runde** werden im **Browser** gespeichert (offlinefähig). Sie gehen **nicht** automatisch auf den Server – erst wenn Sie Daten laden, Mails senden oder Seiten mit dem Server abgleichen, wird kommuniziert. Das gilt auch für die **Inventur** (nur lokal bis zum CSV-Export).

---

## 2. Anmeldung und Oberfläche

- **Anmelden** mit Benutzername und Passwort (vergeben von der Verwaltung).
- Oben: **Name der App**, **Offline**-Hinweis (wenn keine Netzverbindung), Ihr **Konto**, ggf. **Kompaktmodus** (weniger Hilfstexte), **Menü** (☰ auf dem Handy).
- **Abmelden** finden Sie im **Menü** unten.
- Steht über der Seite ein gelber Balken **„Testbetrieb“**, werden E-Mails **nicht** an die echten Lieferanten geschickt, sondern an die Testadresse aus den Einstellungen – das ist Absicht.

---

## 3. Startseite („Start“)

### Bestellrunde (oben)

- Hier sehen Sie, ob **bereits eine Runde** läuft (z. B. „Rundgang läuft“ oder „Bereit zur Kontrolle“).
- **Rundgang fortsetzen** – weiter im Lager erfassen.  
- **Kontrolle / Abschluss** – Bestellung prüfen und Lieferanten zuordnen.  
- **Ausgabe** – erscheint, wenn die Runde **zur Kontrolle bereit** ist (nach „Weiter zur Ausgabe“ aus der Kontrolle bzw. entsprechendem Stand).

### Neue Bestellrunde (darunter)

- **Ziel-Datum** wählen und **Bestellrunde laden** klicken. Dafür müssen Sie **online** sein.
- **Achtung:** **Bestellrunde laden** startet eine **neue** Runde und **löscht alle bisherigen lokalen Eingaben** dieser Runde im Browser. Wenn Sie nur weitermachen wollen, nutzen Sie **Rundgang fortsetzen** oder **Kontrolle** – nicht erneut „Bestellrunde laden“.
- Wenn eine Warnung erscheint, dass eine laufende Runde verworfen wird: **Abbrechen**, wenn Sie unsicher sind.

### Stammdaten (unten, einklappbar)

- Nur sichtbar, wenn Sie dazu **berechtigt** sind: Links zu **Artikeln**, **Lieferanten**, **Lagerorten**. Zum Pflegen der Stammdaten; für den täglichen Bestelllauf nicht zwingend nötig.

### Menü (☰)

- **Start**, **Bestellen**, ggf. **Lagerorte**, **Lieferanten**, **Artikel**, **Import**, **Inventur** (eigene Seite), **Einstellungen**, **Mein Konto**, **Abmelden**.

---

## 4. Die vier Schritte (Vorbereiten → Rundgang → Kontrolle → Ausgabe)

Oben auf den Bestellseiten sehen Sie die **Schritte 1 bis 4**. Sie können die **Zahl oder die Beschriftung antippen**, um direkt zu diesem Schritt zu wechseln (sofern die Seite erreichbar ist). Ihre Daten bleiben im Browser gespeichert.

| Schritt | Bedeutung |
|--------|-----------|
| **1 Vorbereiten** | Auf der **Startseite**: Datum wählen, **Bestellrunde laden** (nur für eine **neue** Runde). |
| **2 Rundgang** | Pro **Lagerort** Tab, **Mengen** eintragen, optional **freie Zeilen** (Text + Menge + Lieferant). |
| **3 Kontrolle** | Nur bestellte Positionen; **Lieferant wechseln**, wo nötig; **Notizen** pro Lieferant; **Weiter zur Ausgabe**, wenn alles passt. |
| **4 Ausgabe** | **E-Mail-Texte** je Lieferant, **PDF**, ggf. **Outlook-Export** oder **Direktversand** – je nach Einrichtung. |

---

## 5. Rundgang (Schritt 2)

- **Tabs** oben: Wechsel zwischen den **Lagerorten**.
- Bei jedem Artikel die **Menge** eintragen (leer lassen = nicht bestellen).
- **Freie Position**: Bezeichnung und Menge; Lieferant kann im Rundgang oder **später in der Kontrolle** gewählt werden.
- Wenn Sie fertig sind: **Weiter zur Kontrolle** (o. ä.) – die Runde wird als **bereit zur Kontrolle** markiert.

*Tipp:* Kurz **offline** arbeiten geht; zum **Laden** einer neuen Runde oder **Synchronisieren** mit geänderten Stammdaten sind Sie **online** nötig.

---

## 6. Kontrolle (Schritt 3)

- **Problemartikel** (z. B. kein Lieferant am Tag): zuerst beheben.
- **Freie Positionen ohne Lieferant**: Lieferant **auswählen**, sonst geht es nicht zur Ausgabe.
- **Mehrere mögliche Lieferanten**: Kacheln oder Liste nutzen, um den **richtigen** zu wählen.
- **Zusatz je Lieferant**: Freitextfeld, wird in die Mail übernommen.
- Wenn Sie **zwischendurch Stammdaten** geändert haben (z. B. Betreff-Vorlage): auf der Kontrollseite **„vom Server aktualisieren“** oder den Tab kurz wechseln – dann werden Vorlagen und Einstellungen neu geladen.
- **Weiter zur Ausgabe** ist gesperrt, solange noch offene Probleme oder freie Zeilen ohne Lieferant bestehen.

---

## 7. Ausgabe (Schritt 4)

- Pro **Lieferant** sehen Sie **Betreff** und **Text** der Mail (Vorschau).
- **CC** (Kopie an die Küche/Büro): Wenn in den Einstellungen eingetragen, wird die Adresse **auf der Seite angezeigt** – so sehen Sie, wohin die Kopie geht.
- **Kopieren** / **Mail öffnen**: Öffnet Ihr **E-Mail-Programm** mit vorausgefülltem Empfänger, Betreff und Text (und CC, sofern gesetzt).
- **Direktversand** (falls aktiviert): Mails gehen **vom Server**; pro Lieferant **Senden** – Sie erhalten keine automatische „Zustellbestätigung“ von der App.
- **PDF**: Bestellung als Datei herunterladen.
- **Outlook-Export** (falls sichtbar): Dateien für ein **Outlook-Makro** – siehe Ordner `outlook-macro` bzw. die dortige Kurzanleitung.
- **Bestellrunde abschließen**: Bestätigt **lokal**, dass Sie fertig sind (kein Nachweis beim Lieferanten).
- **Neue Bestellrunde**: Löscht die **lokale** Runde und alle Eingaben dieser Runde – nur nutzen, wenn Sie wirklich von vorn beginnen wollen.

### Unterschrift / Besteller in der Mail

- Unter **Mein Konto** (oder **Benutzer** bearbeiten) kann ein **Anzeigename** gepflegt werden (z. B. „Bastian Dornauf“).
- In E-Mail-Vorlagen (Einstellungen, Lieferant) steht der Platzhalter **`{{USER}}`** – wird beim Erzeugen der Mail durch den **aktuell angemeldeten** Nutzer ersetzt (Anzeigename, sonst Benutzername).
- Beispiel im Mail-Text: `Mit freundlichen Grüßen` + Zeile mit `{{USER}}`.

---

## 8. Inventur (Bestandszählung)

Die **Inventur** ist ein **eigener Ablauf** neben der Bestellung. Ihre Zählungen werden **nur lokal** im Browser gespeichert und beeinflussen **keine** Bestell-Mengen. Eine laufende **Bestellrunde** können Sie parallel fortsetzen.

### Start und Unterbrechung

- Im **Menü**: **Inventur** (eigene Seite, nach Import) – Stichtag, optional Bezeichnung → **Inventur starten** (dafür **online**).
- **Inventur fortsetzen** / **Inventur abschließen** auf derselben Seite oder in den Schritten Rundgang / Abschluss.
- Unterwegs **unterbrechen** ist unkritisch: Daten bleiben auf dem **gleichen Gerät** im Browser (z. B. am nächsten Tag weiterzählen).

### Rundgang

- Wie bei der Bestellung: **Tabs** nach **Lagerort**, **Suche** nach Artikelname.
- Es erscheinen nur **aktive** Artikel (inaktive Artikel sind ausgeschlossen).
- Pro Artikel:
  - **Leer lassen** = noch **nicht gezählt** (Status später `offen` in der CSV).
  - **Leer / 0** = bewusst **kein Bestand** (Status `gezaehlt_0`).
  - **Menge eintragen** = gezählter Bestand (Status `gezaehlt`).
- Sie müssen **nicht** jeden Artikel im Rundgang anfassen. Offene Positionen klären Sie am besten im **Abschluss**.

### Abschluss (empfohlene Vorgehensweise)

1. **Zum Abschluss** wählen (oder Menü **Inventur** → **Inventur abschließen**).
2. Block **„Noch nicht gezählt“**: Liste aller Artikel ohne Eingabe.
   - Noch im Lager nachzählen? → **Zurück zum Rundgang**.
   - Bewusst leer? → pro Zeile **Leer / 0** (ohne nochmal durch alle Lager zu laufen).
3. **CSV herunterladen & abschließen**:
   - Die Datei enthält **alle** Artikel der Inventur mit Spalte **`status`**:
     - `offen` – nicht gezählt  
     - `gezaehlt_0` – gezählt, Bestand null  
     - `gezaehlt` – Menge in Spalte `menge`  
   - In **Excel** filtern Sie nach `status`, formatieren und werten aus.
   - Optional Spalten **`bewertungspreis`** und **`wert`** (wenn am Artikel ein Bewertungspreis gepflegt ist).

**Nach dem Export** ist die Inventur **gesperrt**: keine Änderungen mehr am Rundgang. Sie können die CSV **erneut herunterladen** oder die Session **lokal löschen** (Seite **Inventur** oder Abschluss → **Inventur beenden**). Für die nächste Monats-Inventur: **Neue Inventur starten**.

### Bewertungspreis (optional)

- Rollen mit Stammdaten-Recht: am **Artikel** Feld **Bewertungspreis pro Einheit** (nur für Inventur).
- Die **Bestellung** nutzt diesen Preis nicht.
- **Massenpflege per CSV:** Auf **Artikel** → **↓ Preise** exportieren (`artikel_bewertungspreise.csv`), Preise in Excel eintragen (Spalte `bewertungspreis`, Komma als Dezimaltrenner), unter **Import** den Typ **Bewertungspreise (nur Preise)** wählen und importieren. Der vollständige Artikel-Export (**↓ CSV**) enthält die Spalte `bewertungspreis` ebenfalls.

### Kurz-Checkliste Inventur (monatlich)

1. **Online** → **Inventur starten**.  
2. **Rundgang** (ggf. über mehrere Tage, offline möglich).  
3. **Abschluss** → offene Artikel prüfen → **CSV** sichern.  
4. In Excel auswerten; Inventur auf dem Gerät **beenden**, wenn alles erledigt ist.

---

## 9. Stammdaten (nur mit Berechtigung)

- **Artikel**, **Lieferanten**, **Lagerorte** pflegen Sie über das **Menü** oder die einklappbare Sektion auf der Startseite.
- Änderungen gelten für **neu geladene** Runden und können auf **Kontrolle/Ausgabe** per **Aktualisieren** oder Tab-Wechsel nachgezogen werden (siehe oben).
- **CSV Import/Export** (Menü **Import**, auf Listen **↓ CSV**): Artikel inkl. optionaler Spalte **`bewertungspreis`**; für reine Preislisten den schlanken Export **↓ Preise** und Import-Typ **Bewertungspreise**.

---

## 10. Häufige Fragen

**Ich sehe „Offline“ – was tun?**  
Ohne Netz können Sie im Rundgang oft weiterarbeiten. Zum **Neu laden** der Runde, **Stammdaten vom Server** oder **Versand** brauchen Sie wieder **Online**.

**Meine Runde ist plötzlich weg.**  
Meist: **Neue Runde geladen** statt **Fortsetzen**, oder Browserdaten gelöscht / anderes Gerät. Bestellrunden liegen **pro Browser** – kein automatisches Backup auf dem Server.

**Ich bekomme meine CC-Mail nicht.**  
Beim **Direktversand** hängt das vom Mail-Server ab; bei **„Mail öffnen“** prüfen Sie, ob im Programm **CC** gesetzt ist. Bei **Testbetrieb** gehen alle Mails an die Testadresse.

**Wo melde ich mich ab?**  
Im **Menü** (☰) ganz unten: **Abmelden**.

**Inventur und Bestellung gleichzeitig?**  
Ja. Die Daten liegen **getrennt** im Browser. Die Bestellrunde wird durch die Inventur **nicht** überschrieben.

**Warum stehen in der CSV auch „offene“ Artikel?**  
Damit Sie in Excel sehen, was **noch nicht** gezählt wurde (`status` = `offen`). Sie müssen nicht jeden Artikel vorher einzeln „abfertigen“.

---

## 11. Kurz-Checkliste pro Bestelltag

1. **Online** gehen → Startseite.  
2. Entweder **Rundgang fortsetzen** oder **Neue Bestellrunde** laden (nur wenn wirklich neu).  
3. **Rundgang** durchgehen → **Kontrolle** → **Ausgabe**.  
4. Mails/PDFs erledigen → **Bestellrunde abschließen**, wenn alles passt.

Bei Fragen zur **Einrichtung** (Mail, SMTP, Benutzer) wendet euch an die **IT oder Verwaltung**; diese Anleitung beschreibt nur die **Bedienung** der App.
