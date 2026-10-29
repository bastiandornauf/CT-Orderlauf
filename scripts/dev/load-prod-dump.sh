#!/usr/bin/env bash
# Spielt einen phpMyAdmin-Export der Produktion in die lokale Docker-DB ein.
# Die lokale DB wird dabei komplett ersetzt. Danach ist Mailversand entschärft:
# Testbetrieb an, Direktversand aus, SMTP-Passwort leer.
#
#   scripts/dev/load-prod-dump.sh ~/Backups/orderlauf/prod-2026-10-29-1146.sql
set -euo pipefail

dump="${1:?Pfad zum SQL-Export angeben}"
[ -f "$dump" ] || { echo "Datei nicht gefunden: $dump" >&2; exit 1; }

cd "$(dirname "$0")/../.."
db() { docker compose exec -T db mariadb -uroot -proot_secret "$@"; }

db -e "DROP DATABASE IF EXISTS ct_orderlauf;
       CREATE DATABASE ct_orderlauf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
       GRANT ALL ON ct_orderlauf.* TO 'ct_user'@'%';"
db ct_orderlauf < "$dump"

db ct_orderlauf -e "
  UPDATE settings SET value = '1' WHERE key_name = 'dev_mode';
  UPDATE settings SET value = '0' WHERE key_name = 'send_email_direct';
  UPDATE settings SET value = ''  WHERE key_name = 'smtp_pass';"

db ct_orderlauf -e "
  SELECT 'items' AS tabelle, COUNT(*) AS anzahl FROM items
  UNION ALL SELECT 'item_supplier', COUNT(*) FROM item_supplier
  UNION ALL SELECT 'suppliers', COUNT(*) FROM suppliers
  UNION ALL SELECT 'locations', COUNT(*) FROM locations
  UNION ALL SELECT 'pending_items', COUNT(*) FROM pending_items
  UNION ALL SELECT 'users', COUNT(*) FROM users;
  SELECT key_name, value FROM settings WHERE key_name IN ('dev_mode','send_email_direct','smtp_pass');"
