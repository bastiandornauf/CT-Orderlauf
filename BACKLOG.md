# Backlog (bewusst zurückgestellt)

Kurzfassung der zuletzt besprochenen Verbesserungen ohne aktuellen Implementierungsbedarf.

- [ ] **Ausgabe:** Zeichenzahl bzw. geschätzte `mailto`-URL-Länge + optionaler Warnhinweis bei sehr langem Text
- [ ] **API:** Hinweis in der Antwort, wenn beim CC-Parsing Adressen verworfen wurden (Frontend ggf. Toast)
- [ ] **Frontend:** Kurze Sperre / Entprellung gegen Doppelklick beim Senden
- [ ] **Server:** Empfänger `to` gegen Lieferanten-Stammdaten prüfen
- [ ] **Versand:** Message-ID / Sende-Log dauerhaft speichern

## Deploy-Checkliste

- Bei Änderungen an CSS/JS in `public/assets/` reicht jetzt ein Reload, weil
  `layout.php` Asset-URLs mit `?v=<filemtime>` versieht und der Service Worker
  innerhalb von ~2.5 s Netz nutzt, sonst aus dem Cache liefert.
- Bei Änderungen an `public/sw.js` zur Sicherheit weiterhin `CACHE`-Version
  hochzählen (zwingt activate → alte Caches löschen).

## Offline-Verhalten (kurz)

- HTML/CSS/JS und Icons werden vom Service Worker mit **Network-First +
  2.5 s Timeout** geliefert: gutes Netz → frisch, schwaches Netz / offline
  (z. B. Kühlhaus) → schnelle Antwort aus dem Cache, Hintergrund-Update
  beim nächsten Versuch.
- Bestellrunden-Daten liegen in **IndexedDB** (Mengen, Notizen, Stammdaten-
  Snapshot). API-Aufrufe wie `/api/order/payload` laufen nicht durch den
  Service Worker; das Frontend prüft `navigator.onLine` vor Server-Calls.
