# CT-Orderlauf – Anleitung für Anwender

Diese Anleitung richtet sich an **Küche, Lager und Büro**: Sie beschreibt den **Bestellablauf** und die wichtigsten Bedienelemente. Technische Installation steht im [README](../README.md) und in [WEBSPACE-INSTALL.md](../WEBSPACE-INSTALL.md).

---

## 1. Worum es geht

Mit **CT-Orderlauf** erfassen Sie eine **Bestellung** für mehrere Lieferanten: zuerst den **Rundgang** durch die Lagerorte (Mengen eintragen), dann die **Kontrolle** (Zuordnung, Korrekturen), danach den **Versand** (E-Mails oder PDFs).

Zusätzlich gibt es einen eigenen Ablauf **Inventur** (monatliche Bestandszählung) – **getrennt** von der Bestellung, mit CSV-Export für Excel.

**Wichtig:** Ihre **Eingaben der laufenden Runde** werden im **Browser** gespeichert (offlinefähig). Sie gehen **nicht** automatisch auf den Server – erst wenn Sie Daten laden, Mails senden oder Seiten mit dem Server abgleichen, wird kommuniziert. Das gilt auch für die **Inventur** (nur lokal bis zum CSV-Export).

---

## 2. Anmeldung und Oberfläche

- **Anmelden** mit Benutzername und Passwort (vergeben von der Verwaltung).
- Oben: **Name der App**, **Offline**-Hinweis (wenn keine Netzverbindung), Ihr **Konto**, **Menü** (☰).
- **Abmelden** finden Sie im **Menü** unten.
- Steht über der Seite ein gelber Balken **„Testbetrieb“**, werden E-Mails **nicht** an die echten Lieferanten geschickt, sondern an die Testadresse aus den Einstellungen – das ist Absicht.

---

## 3. Startseite („Start“)

### Laufende Bestellung (oben, nur wenn etwas läuft)

- Status z. B. „Rundgang läuft“ oder „Bereit zur Kontrolle“.
- **Rundgang fortsetzen** bzw. **Rundgang beginnen** – weiter im Lager erfassen.
- **Kontrolle** – Bestellung prüfen und Lieferanten zuordnen.
- **Versand** – erscheint, wenn die Bestellung zur Kontrolle bereit ist.

### Neue Bestellung

- **Wunsch-Lieferdatum** wählen und **Bestellung beginnen** (dafür **online**).
- Das überschreibt eine laufende Bestellung. Weitermachen: oben **Rundgang fortsetzen** oder **Kontrolle**, nicht erneut beginnen.
- Unter dem Button eine Zeile zu den Lieferterminen; Details klappen auf Klick auf.

### Stammdaten (unten, einklappbar)

- Nur sichtbar, wenn Sie dazu **berechtigt** sind: Links zu **Artikeln**, **Lieferanten**, **Lagerorten**. Zum Pflegen der Stammdaten; für den täglichen Bestelllauf nicht zwingend nötig.

### Menü (☰)

- Über **☰**: **Start**, **Inventur**, ggf. **Lagerorte**, **Lieferanten**, **Artikel**, **Artikel-Vorschläge**, **Import / Export**, **Einstellungen**, **Mein Konto**, **Abmelden**.

---

## 4. Die vier Schritte (Vorbereiten → Rundgang → Kontrolle → Versand)

Oben auf den Bestellseiten sehen Sie die **Schritte 1 bis 4**. Zahl oder Beschriftung antippen wechselt den Schritt. Die Daten bleiben im Browser gespeichert.

| Schritt | Bedeutung |
|--------|-----------|
| **1 Vorbereiten** | Auf der **Startseite**: Datum wählen, **Bestellung beginnen** (nur für eine **neue** Bestellung). |
| **2 Rundgang** | Pro **Lagerort** Tab, **Mengen** eintragen, optional **freier Artikel**. |
| **3 Kontrolle** | Nur bestellte Positionen; **Lieferant wechseln**, wo nötig; **Notizen** pro Lieferant; **Weiter zum Versand**, wenn alles passt. |
| **4 Versand** | **E-Mail-Texte** je Lieferant, **PDF**, ggf. **Outlook-Export** oder **Direktversand** – je nach Einrichtung. |

---

## 5. Rundgang (Schritt 2)

- **Tabs** oben: Wechsel zwischen den **Lagerorten**.
- Bei jedem Artikel die **Menge** eintragen (leer lassen = nicht bestellen).
- **Freier Artikel**: Bezeichnung, Menge und optional **Gebinde/Einheit** (z. B. Kiste, Bund, kg); Lieferant kann im Rundgang oder **später in der Kontrolle** gewählt werden. Jeder freie Artikel landet zusätzlich in den **Artikel-Vorschlägen**.
- Wenn Sie fertig sind: **Weiter zur Kontrolle** (o. ä.) – die Runde wird als **bereit zur Kontrolle** markiert.

*Tipp:* Kurz **offline** arbeiten geht; zum **Laden** einer neuen Runde oder **Synchronisieren** mit geänderten Stammdaten sind Sie **online** nötig.

---

## 6. Kontrolle (Schritt 3)

- **Problemartikel** (z. B. kein Lieferant am Tag): zuerst beheben.
- **Freie Artikel ohne Lieferant**: Lieferant **auswählen**, sonst geht es nicht zum Versand.
- **Mehrere mögliche Lieferanten**: Kacheln oder Liste nutzen, um den **richtigen** zu wählen.
- **Zusatz je Lieferant**: Freitextfeld, wird in die Mail übernommen.
- Wenn Sie **zwischendurch Stammdaten** geändert haben (z. B. Betreff-Vorlage): auf der Kontrollseite **„vom Server aktualisieren“** oder den Tab kurz wechseln – dann werden Vorlagen und Einstellungen neu geladen.
- **Weiter zum Versand** ist gesperrt, solange noch offene Probleme oder freie Artikel ohne Lieferant bestehen.

---

## 7. Versand (Schritt 4)

- Pro **Lieferant** sehen Sie **Betreff** und **Text** der Mail (Vorschau).
- **CC** (Kopie an die Küche/Büro): Wenn in den Einstellungen eingetragen, wird die Adresse **auf der Seite angezeigt** – so sehen Sie, wohin die Kopie geht.
- **Kopieren** / **Mail öffnen**: Öffnet Ihr **E-Mail-Programm** mit vorausgefülltem Empfänger, Betreff und Text (und CC, sofern gesetzt).
- **Direktversand** (falls aktiviert): Mails gehen **vom Server**; pro Lieferant **Senden** – Sie erhalten keine automatische „Zustellbestätigung“ von der App.
- **PDF**: Bestellung als Datei herunterladen.
- **Outlook-Export** (falls sichtbar): Dateien für ein **Outlook-Makro** – siehe Ordner `outlook-macro` bzw. die dortige Kurzanleitung.
- **Bestellung abschließen**: Bestätigt **lokal**, dass Sie fertig sind (kein Nachweis beim Lieferanten).
- **Neue Bestellung**: Löscht die **lokale** Bestellung und alle Eingaben – nur nutzen, wenn Sie wirklich von vorn beginnen wollen.

### Unterschrift / Besteller in der Mail

- Unter **Mein Konto** (oder **Benutzer** bearbeiten) kann ein **Anzeigename** gepflegt werden (z. B. „Bastian Dornauf“).
- In E-Mail-Vorlagen (Einstellungen, Lieferant) steht der Platzhalter **`{{USER}}`** – wird beim Erzeugen der Mail durch den **aktuell angemeldeten** Nutzer ersetzt (Anzeigename, sonst Benutzername).
- Beispiel im Mail-Text: `Mit freundlichen Grüßen` + Zeile mit `{{USER}}`.

---

## 8. Inventur (Bestandszählung)

Die **Inventur** ist ein **eigener Ablauf** neben der Bestellung. Ihre Zählungen werden **nur lokal** im Browser gespeichert und beeinflussen **keine** Bestell-Mengen. Eine laufende **Bestellung** können Sie parallel fortsetzen.

### Start und Unterbrechung

- Im **Menü**: **Inventur** (eigene Seite, nach Import) – Stichtag, optional Bezeichnung → **Inventur starten** (dafür **online**).
- **Inventur fortsetzen** / **Inventur abschließen** auf derselben Seite oder in den Schritten Rundgang / Abschluss.
- Unterwegs **unterbrechen** ist unkritisch: Daten bleiben auf dem **gleichen Gerät** im Browser (z. B. am nächsten Tag weiterzählen).

### Rundgang

- Wie bei der Bestellung: **Tabs** nach **Lagerort**, **Suche** nach Artikelname **und Gebinde/Einheit**.
- Die **Gebindegröße/Einheit** steht unter dem Artikelnamen.
- Es erscheinen nur **aktive** Artikel (inaktive Artikel sind ausgeschlossen).
- Pro Artikel:
  - **Leer lassen** = noch **nicht gezählt** (Status später `offen` in der CSV).
  - **Leer / 0** = bewusst **kein Bestand** (Status `gezaehlt_0`).
  - **Menge eintragen** = gezählter Bestand (Status `gezaehlt`).
- **Freier Artikel**: Unten im jeweiligen Lagerort Bezeichnung, Gebinde/Einheit und Menge eintragen → **Hinzufügen**. Diese freien Artikel kommen in die CSV (Status `frei_gezaehlt`) und in die **Artikel-Vorschläge**.
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
     - `frei_gezaehlt` – per Freitext erfasster Artikel (nicht im Stamm)  
   - In **Excel** filtern Sie nach `status`, formatieren und werten aus.
   - Optional Spalten **`bewertungspreis`** und **`wert`** (wenn am Artikel ein Bewertungspreis gepflegt ist).

**Nach dem Export** ist die Inventur **gesperrt**: keine Änderungen mehr am Rundgang. Sie können die CSV **erneut herunterladen** oder die Session **lokal löschen** (Seite **Inventur** oder Abschluss → **Inventur beenden**). Für die nächste Monats-Inventur: **Neue Inventur starten**.

### Artikel-Vorschläge (eigene Seite unter „Artikel")

Alle per **Freitext** erfassten Positionen – aus der **Inventur** *und* aus der **Bestellung** – sammeln sich unter **Artikel-Vorschläge** (Menü, Hinweis im Inventur-Abschluss, oder auf der Artikelliste). Adresse: `/items/pending`.

Die Liste ist ein **dauerhafter, gemeinsamer Sammler** auf dem Server: Einträge bleiben erhalten, wenn Sie eine neue Bestellung beginnen, eine Bestellung abschließen, eine Inventur abschließen oder lokal löschen. So können Sie alle paar Wochen prüfen, welche Freitext-Artikel zu **Regulars** geworden sind.

- **Alle Nutzer zahlen in denselben Topf ein** – egal wer die Bestellung oder Inventur gemacht hat und auf welchem Gerät. Auch Nutzer mit „Nur Bestellen“ tragen bei, sehen die Liste aber nicht.
- Gleiche Bezeichnungen werden **über alle Nutzer hinweg zusammengefasst**. Ein Zähler zeigt, wie oft der Artikel erfasst wurde (`3× erfasst`), dazu **Zuerst**- und **Zuletzt**-Datum sowie wer ihn zuletzt getippt hat. Häufigste stehen oben.
- Pro Eintrag **Bezeichnung**, **Gebinde/Einheit**, **Lagerort** und ggf. **Lieferant** prüfen/ergänzen.
- **Lagerort** wird aus der Erfassung übernommen (Inventur: aktueller Lagerort-Tab; Bestellung: Lagerort der freien Position).
- **Gebinde/Einheit** wird aus der Erfassung übernommen – in der Bestellung gibt es dafür beim freien Artikel ein eigenes Feld, damit die Einheit nicht in die Bezeichnung getippt werden muss.
- **Lieferant** wird bei Freitext aus der **Bestellung** übernommen, sofern in der Runde oder Kontrolle zugeordnet.
- Mit **Stammdaten-Recht** und **online**: **In Stammdaten übernehmen** legt den Artikel direkt an – einzeln pro Zeile oder per **Alle übernehmen**. Er steht dann ab der **nächsten** Inventur/Bestellung im Katalog (der aktuelle Stand ist eine lokale Kopie). Der Eintrag verschwindet danach aus der Sammelliste.
- **Verwerfen** entfernt einen Eintrag aus der Liste, ohne ihn anzulegen. Wird derselbe Artikel **später erneut** per Freitext erfasst, erscheint er wieder – aus Einmal-Notizen können so über Wochen doch noch Stammartikel werden.
- Ohne Stammdaten-Recht ist die Seite nicht erreichbar: **Auswerten und Übernehmen darf nur Administrator oder Stammdaten.** „Nur Bestellen“ sieht die Seite und den Hinweis im Inventur-Abschluss nicht.
- Freitext aus dem Rundgang wird **beim Öffnen der Kontrolle** bzw. des **Inventur-Abschlusses** an den Server übertragen – dafür ist einmal Verbindung nötig. Offline erfasste Artikel warten auf dem Gerät und gehen nicht verloren; sie erscheinen bei den Kollegen erst nach dieser Übertragung.
- **Verwerfen** und **Übernehmen** wirken für alle Nutzer und brauchen eine Verbindung.

### Bewertungspreis (optional)

- Rollen mit Stammdaten-Recht: am **Artikel** Feld **Bewertungspreis pro Einheit** (nur für Inventur).
- Die **Bestellung** nutzt diesen Preis nicht.
- **Massenpflege per CSV:** Unter **Import / Export** **Artikel** herunterladen, Spalte **`bewertungspreis`** in Excel ändern, als **Artikel** oder Typ **Bewertungspreise** wieder importieren.

### Kurz-Checkliste Inventur (monatlich)

1. **Online** → **Inventur starten**.  
2. **Rundgang** (ggf. über mehrere Tage, offline möglich).  
3. **Abschluss** → offene Artikel prüfen → **CSV** sichern.  
4. In Excel auswerten; Inventur auf dem Gerät **beenden**, wenn alles erledigt ist.

---

## 9. Stammdaten (nur mit Berechtigung)

- **Artikel**, **Lieferanten**, **Lagerorte** pflegen Sie über das **Menü** oder die einklappbare Sektion auf der Startseite.
- Änderungen gelten für **neu begonnene** Bestellungen und können auf **Kontrolle/Versand** per **Aktualisieren** oder Tab-Wechsel nachgezogen werden (siehe oben).
- **CSV Import/Export** (Menü **Import / Export**): Artikel inkl. optionaler Spalte **`bewertungspreis`**; reine Preispflege über dieselbe Spalte oder Import-Typ **Bewertungspreise**.

---

## 10. Häufige Fragen

**Ich sehe „Offline“ – was tun?**  
Ohne Netz können Sie im Rundgang oft weiterarbeiten. Zum **Neu laden** der Runde, **Stammdaten vom Server** oder **Versand** brauchen Sie wieder **Online**.

**Meine Runde ist plötzlich weg.**  
Meist: **Neue Bestellung begonnen** statt **Fortsetzen**, oder Browserdaten gelöscht / anderes Gerät. Bestellungen liegen **pro Browser** – kein automatisches Backup auf dem Server.

**Ich bekomme meine CC-Mail nicht.**  
Beim **Direktversand** hängt das vom Mail-Server ab; bei **„Mail öffnen“** prüfen Sie, ob im Programm **CC** gesetzt ist. Bei **Testbetrieb** gehen alle Mails an die Testadresse.

**Wo melde ich mich ab?**  
Im **Menü** (☰) ganz unten: **Abmelden**.

**Inventur und Bestellung gleichzeitig?**  
Ja. Die Daten liegen **getrennt** im Browser. Die Bestellung wird durch die Inventur **nicht** überschrieben.

**Warum stehen in der CSV auch „offene“ Artikel?**  
Damit Sie in Excel sehen, was **noch nicht** gezählt wurde (`status` = `offen`). Sie müssen nicht jeden Artikel vorher einzeln „abfertigen“.

---

## 11. Kurz-Checkliste pro Bestelltag

1. **Online** gehen → Startseite.  
2. Entweder **Rundgang fortsetzen** oder **Bestellung beginnen** (nur wenn wirklich neu).  
3. **Rundgang** durchgehen → **Kontrolle** → **Versand**.  
4. Mails/PDFs erledigen → **Bestellung abschließen**, wenn alles passt.

Bei Fragen zur **Einrichtung** (Mail, SMTP, Benutzer) wendet euch an die **IT oder Verwaltung**; diese Anleitung beschreibt nur die **Bedienung** der App.
