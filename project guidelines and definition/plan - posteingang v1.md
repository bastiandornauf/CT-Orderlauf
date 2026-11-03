# Plan: Posteingang v1

Stand: 03.11.2026 – freigegeben und umgesetzt (lokal getestet, noch nicht live).

## Ziel

Das IONOS-Postfach, über das Orderlauf Bestellungen verschickt, wird von der App mitgelesen:

1. **Lieferantenantworten** sollen auffallen (Hinweis auf der Startseite).
2. **Weitergeleitete Belege** (Rechnungen, Lieferschein-Fotos) werden gesammelt, damit sie später gebündelt per Pflegepaket eingepflegt werden können.

Sichtbar für **Stammdaten** und **Admin** (wie Datenpflege und Preise), nicht für „Nur Bestellen“.

## Abruf

- Eigener kleiner IMAP-Client über `stream_socket_client` (TLS, `imap.ionos.de:993`), analog zum eigenen SMTP-Code in `MailSenderService`. **Keine** Abhängigkeit von `ext-imap`: Die Erweiterung ist ab PHP 8.4 nicht mehr Teil von PHP und ist auf dem Webspace nicht sicher vorhanden.
- Zugangsdaten: dieselben wie beim SMTP-Versand (`smtp_user`/`smtp_pass`), IMAP-Host per Einstellung `imap_host` (Standard `imap.ionos.de`).
- Nur lesen: `UID FETCH … BODY.PEEK[]`. Die Mails bleiben ungelesen und unverändert im Postfach. Die App merkt sich `UIDVALIDITY` und die höchste gesehene UID.
- Auslöser: Startseite eines Admins, höchstens alle 10 Minuten (Zeitstempel in `settings`) und zusätzlich über den Button „Jetzt abrufen“. Kein Cron nötig.
- Startpunkt beim ersten Lauf: nur Mails der letzten 30 Tage.

## Einordnung

| Bedingung | Art | Verhalten |
|---|---|---|
| Absender ist eine eigene Adresse **und** Betreff beginnt mit `Re:`/`AW:`/`Antw:` | `nachtrag` | ausgeblendet, sofort erledigt |
| Absender ist eine eigene Adresse (sonst) | `beleg` | Anhänge speichern, Status *offen* |
| Absender passt zu `suppliers.email` (Domain-Vergleich als Rückfall) | `lieferant` | Hinweis auf der Startseite, Status *neu* |
| alles andere | `sonstiges` | in der Liste, unauffällig |

Eigene Adressen sind als Einstellung `inbox_own_addresses` auf der Posteingangsseite pflegbar. Sie sind nicht im Repo vorbelegt, weil das Repo öffentlich ist.
Eigene Adressen, die bei einem Lieferanten nur als Platzhalter stehen (Transgourmet = Webshop), und deren Domain werden nie einem Lieferanten zugeordnet.

Die Bedingungen werden von oben nach unten geprüft, die erste passende gilt.

## Daten

Neue Tabelle `inbox_messages` (Migration `YYYYMMDD_inbox_messages.sql` + `schema.sql`):
`id, imap_uid, message_id, received_at, from_email, from_name, subject, body_text (gekürzt), kind, supplier_id NULL, status (neu|erledigt), attachments JSON, created_at`.

Anhänge (PDF/JPG/PNG/HEIC) liegen **außerhalb von `public/`** unter `storage/inbox/<id>/` und werden nur über einen Admin-Endpunkt ausgeliefert. Gespeichert werden nur die Anhänge, die ganze Mail nicht.

## Oberfläche

- **Startseite (Admin):** „Posteingang: 1 Antwort von *Lieferant*, 3 offene Belege“ → Link.
- **Seite `/posteingang`:** Tabs *Neu* / *Belege* / *Alle*. Jede Zeile zeigt Absender, Betreff und Datum, aufklappbar sind der Text und die Anhänge. Buttons: „Erledigt“ und „Art ändern“.
- **Belege → „Offene als ZIP herunterladen“:** Das ZIP enthält die Anhänge und eine `belege.json` mit IDs, Absender, Betreff und Datum.

## Auswertung (manuell, wie bisher)

1. ZIP herunterladen und Claude sagen „pflege die Belege ein“.
2. Der Skill `beleg-einpflegen` liest das ZIP und erzeugt ein Pflegepaket. Neu im Paket: `inbox_message_ids`.
3. Beim Einspielen des Pakets werden diese Belege automatisch auf *erledigt* gesetzt.

Kein API-Schlüssel auf dem Server, keine laufenden Kosten.

## Nicht in v1

- Antworten aus der App heraus
- Zuordnung einer Antwort zu einer konkreten Bestellung (die App speichert keine Bestellungen auf dem Server)
- Automatische KI-Auswertung auf dem Server
- Löschen oder Verschieben von Mails im Postfach

## Offene Punkte

- Ob das Speichern unter `storage/` auf dem Webspace beschreibbar ist, wird beim ersten Abruf geprüft. Die App zeigt einen verständlichen Fehler, falls nicht.
- Wie groß das Postfach ist: Der erste Abruf ist auf 30 Tage begrenzt.
