# CT-Orderlauf – Anleitung für Anwender

Diese Anleitung richtet sich an **Küche, Lager und Büro**: Sie beschreibt den **Bestellablauf** und die wichtigsten Bedienelemente. Technische Installation steht im [README](../README.md) und in [WEBSPACE-INSTALL.md](../WEBSPACE-INSTALL.md).

---

## 1. Worum es geht

Mit **CT-Orderlauf** erfassen Sie eine **Bestellrunde** für mehrere Lieferanten: zuerst den **Rundgang** durch die Lagerorte (Mengen eintragen), dann die **Kontrolle** (Zuordnung, Korrekturen), danach die **Ausgabe** (E-Mails oder PDFs).  

**Wichtig:** Ihre **Eingaben der laufenden Runde** werden im **Browser** gespeichert (offlinefähig). Sie gehen **nicht** automatisch auf den Server – erst wenn Sie Daten laden, Mails senden oder Seiten mit dem Server abgleichen, wird kommuniziert.

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

- **Start**, **Bestellen** (springt zur neuen Runde auf der Startseite), ggf. **Lagerorte**, **Lieferanten**, **Artikel**, **Import**, **Einstellungen**, **Mein Konto**, **Abmelden**.

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

---

## 8. Stammdaten (nur mit Berechtigung)

- **Artikel**, **Lieferanten**, **Lagerorte** pflegen Sie über das **Menü** oder die einklappbare Sektion auf der Startseite.
- Änderungen gelten für **neu geladene** Runden und können auf **Kontrolle/Ausgabe** per **Aktualisieren** oder Tab-Wechsel nachgezogen werden (siehe oben).

---

## 9. Häufige Fragen

**Ich sehe „Offline“ – was tun?**  
Ohne Netz können Sie im Rundgang oft weiterarbeiten. Zum **Neu laden** der Runde, **Stammdaten vom Server** oder **Versand** brauchen Sie wieder **Online**.

**Meine Runde ist plötzlich weg.**  
Meist: **Neue Runde geladen** statt **Fortsetzen**, oder Browserdaten gelöscht / anderes Gerät. Bestellrunden liegen **pro Browser** – kein automatisches Backup auf dem Server.

**Ich bekomme meine CC-Mail nicht.**  
Beim **Direktversand** hängt das vom Mail-Server ab; bei **„Mail öffnen“** prüfen Sie, ob im Programm **CC** gesetzt ist. Bei **Testbetrieb** gehen alle Mails an die Testadresse.

**Wo melde ich mich ab?**  
Im **Menü** (☰) ganz unten: **Abmelden**.

---

## 10. Kurz-Checkliste pro Bestelltag

1. **Online** gehen → Startseite.  
2. Entweder **Rundgang fortsetzen** oder **Neue Bestellrunde** laden (nur wenn wirklich neu).  
3. **Rundgang** durchgehen → **Kontrolle** → **Ausgabe**.  
4. Mails/PDFs erledigen → **Bestellrunde abschließen**, wenn alles passt.

Bei Fragen zur **Einrichtung** (Mail, SMTP, Benutzer) wendet euch an die **IT oder Verwaltung**; diese Anleitung beschreibt nur die **Bedienung** der App.
