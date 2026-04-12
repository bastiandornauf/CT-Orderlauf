# CT-Orderlauf – Outlook-Makro

## Workflow

1. **App** – Rundgang durchführen, zur Ausgabe-Seite gehen
2. **Ausgabe-Seite** – Button **„Für Outlook exportieren"** klicken
   - Lädt `ct-orderlauf-export.xml` in den Downloads-Ordner
   - Lädt alle Lieferanten-PDFs in den Downloads-Ordner
3. **Outlook** – Makro `ImportBestellungen` ausführen
   - Erstellt für jeden Mail-Lieferanten einen Entwurf mit PDF-Anhang
   - Öffnet alle Entwürfe zur Kontrolle
4. Mails kurz prüfen, **Senden**

---

## Einmalige Einrichtung

### 1. XML-Verweis aktivieren

Im Outlook-VBA-Editor (Alt+F11):

`Extras` → `Verweise` → Haken bei **„Microsoft XML, v6.0"** setzen → OK

### 2. Makro importieren

Im VBA-Editor:

`Datei` → `Datei importieren` → `CT_Orderlauf.bas` auswählen

### 3. Tastenkürzel belegen (empfohlen)

`Datei` → `Optionen` → `Menüband anpassen` → `Tastenkombinationen: Anpassen`  
Kategorie: **Makros** → `CT_Orderlauf.ImportBestellungen` → z.B. `Strg+Umschalt+B`

---

## Einstellungen im Makro

Am Anfang der Datei `CT_Orderlauf.bas` gibt es zwei Konstanten:

| Konstante | Standard | Bedeutung |
|---|---|---|
| `EXPORT_FOLDER` | `""` | Leer = automatisch Downloads-Ordner des Benutzers. Alternativ fester Pfad, z.B. `"C:\Bestellungen\"` |
| `AUTO_SEND` | `False` | `False` = Entwürfe öffnen zur Kontrolle. `True` = sofort senden ohne Anzeige |

---

## Hinweise

- **Webshop-Lieferanten** (Transgourmet, Gastro Service) werden übersprungen – die werden online bestellt, nicht per Mail.
- Die PDFs müssen im gleichen Ordner liegen wie die XML-Datei.
- Das Makro kann beliebig oft ausgeführt werden (erzeugt neue Entwürfe).
