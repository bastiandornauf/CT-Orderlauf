"""Freigaben aus der Prüf-Tabelle → idempotente Daten-Migration (SQL)."""
import sys, os, json, re, hashlib
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import build
from build import okt, mrz, items, mode, offer
import openpyxl

TG = 3
DOC_OKT, DOC_MRZ = 'TG-Ordersatz 2026-10-20', 'TG-Ordersatz 2026-03-16'
SKIP = {263, 305, 326}                      # ohne Treffer – bewusst unbearbeitet
MERGES = {148: 276, 325: 268}               # Quelle → Ziel (Dubletten)
OVERRIDES = {
    176: {'order_unit': 'Flasche', 'per_qty': 6, 'pack': (1, 'l')},     # einzelne Flasche, TG-Preis gilt je 6
    254: {'pack': (4.8, 'l')},                                           # 2 × 2,4 l (laut User korrekt)
    264: {'pack': (7, 'kg')},                                            # 10 l / 7 kg (laut User korrekt)
    261: {'unit': 'Karton (3×1,75 kg)'},                                 # auf Karton umstellen
    279: {'unit': 'Karton (10×100 St.)'},                                # auf Karton umstellen
    256: {'nr': '797474', 'order_unit': 'Karton'},                       # Salomon statt TGQ; nur im Karton
    270: {'brand': 'Casa Rinaldi'},
    257: {'unit_factor': (5, 'kg')},                                     # Karton (5 kg), Preis je kg
}

def q(v):
    if v is None: return 'NULL'
    if isinstance(v, (int, float)): return repr(v)
    return "'" + str(v).replace('\\', '\\\\').replace("'", "''") + "'"

def units(x):  # Skala 4 wie App\Helpers\Money
    return int(round(float(x) * 10000))

def money(x): return f"{units(x) / 10000:.4f}"

wb = openpyxl.load_workbook(sys.argv[3], data_only=True)
ws = wb['Transgourmet-Artikel']
approved = {}
for r in ws.iter_rows(min_row=4, values_only=True):
    if r[0] is None: continue
    if str(r[16] or '').strip().lower() in ('ja', '*'):
        approved[int(r[0])] = str(r[5]) if r[5] else None
sup = {i: (items[i]['sup'] or '') for i in items}
multi = {i for i in approved if ',' in sup[i]} | {t for s, t in MERGES.items() if ',' in sup[s] or 'Transgourmet' not in sup[s]}

out = ["-- Daten-Migration: Transgourmet-Ordersatz (März + Oktober 2026) → Bestelldaten, Preise, Alias.",
       "-- Erzeugt aus „Abgleich Transgourmet 2026-10.xlsx“ (Freigaben des Users). Idempotent:",
       "-- mehrfaches Ausführen ändert nichts (Namens-Guards, NOT EXISTS, dedupe_key).",
       "-- Voraussetzung: 20261029_items_alias_supplier_articles.sql + 20261029_supplier_article_prices.sql.",
       "SET NAMES utf8mb4;", "START TRANSACTION;", ""]
stats = {'renamed': 0, 'labelled': 0, 'prices': 0}

def article_sql(iid, nr, ov):
    a = okt.get(nr) or mrz.get(nr)
    it = items[iid]
    m = mode(ov.get('unit', it['unit']), a)
    o = offer(a, m)
    size, su = (ov['pack'] if 'pack' in ov else (o['size'], o['su']))
    label, brand, order_unit = a['text1'], ov.get('brand'), ov.get('order_unit')
    per_qty = ov.get('per_qty', 1)
    lines = [f"-- {iid} {it['name']} → TG {nr} {label}"]
    if iid not in multi:
        lines.append(f"UPDATE items SET alias = {q(it['name'])}, name = {q(label)} WHERE id = {iid} AND name = {q(it['name'])} AND alias IS NULL;")
        stats['renamed'] += 1
    if 'unit' in ov:
        lines.append(f"UPDATE items SET unit = {q(ov['unit'])} WHERE id = {iid} AND unit = {q(it['unit'])};")
    if 'unit_factor' in ov:
        f, b = ov['unit_factor']
        lines.append(f"UPDATE items SET unit_factor = {money(f)}, base_unit = {q(b)} WHERE id = {iid} AND unit_factor IS NULL;")
    vals = [q(label), q(nr), q(brand), money(size) if size else 'NULL', q(su), q(order_unit)]
    cond = f"item_id = {iid} AND supplier_id = {TG} AND is_default = 1 AND active = 1"
    lines.append(f"INSERT INTO supplier_articles (item_id, supplier_id, supplier_label, supplier_sku, brand, pack_size, pack_unit, order_unit, is_default, active)\n"
                 f"  SELECT {iid}, {TG}, {', '.join(vals)}, 1, 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM supplier_articles WHERE {cond});")
    lines.append(f"UPDATE supplier_articles SET supplier_label = {vals[0]}, supplier_sku = {vals[1]}, brand = {vals[2]}, pack_size = {vals[3]}, pack_unit = {vals[4]}, order_unit = {vals[5]}\n"
                 f"  WHERE {cond} AND (supplier_sku IS NULL OR supplier_sku = {q(nr)});")
    stats['labelled'] += 1
    for tag, src, date, doc in [('2026-10', okt, '2026-10-24', DOC_OKT), ('2026-03', mrz, '2026-03-16', DOC_MRZ)]:
        if nr not in src: continue
        a2 = src[nr]
        o2 = offer(a2, m if a2[m] else ('gebinde' if a2['gebinde'] else 'anbruch'))
        amt, pq = units(o2['price'] * (1 if 'per_qty' not in ov else 1)), units(per_qty)
        key_doc = doc.lower()
        lines.append(f"INSERT IGNORE INTO supplier_article_prices (supplier_article_id, price_type, amount, is_net, per_qty, per_unit, price_date, source, document_ref, dedupe_key)\n"
                     f"  SELECT sa.id, 'known', {money(o2['price'])}, 1, {money(per_qty)}, {q(o2['per'])}, '{date}', 'pricelist', {q(doc)},\n"
                     f"         SHA1(CONCAT_WS('|', sa.id, 'known', '{date}', '{amt}', '{pq}', {q(o2['per'])}, 'net', {q(key_doc)}))\n"
                     f"  FROM supplier_articles sa WHERE sa.{cond.replace(' AND ', ' AND sa.')} AND sa.supplier_sku = {q(nr)} ORDER BY sa.id LIMIT 1;")
        stats['prices'] += 1
    return lines

# 1) Zusammenlegungen zuerst (Protokoll mit Vorher-Stand)
for src, dst in MERGES.items():
    s_it, d_it = items[src], items[dst]
    out += [f"-- Dublette: {src} „{s_it['name']}“ → {dst} „{d_it['name']}“",
            "INSERT INTO maintenance_log (action, item_id, detail_json, created_at)",
            f"  SELECT 'item_merged', {dst}, JSON_OBJECT('source', JSON_OBJECT('id', i.id, 'name', i.name, 'alias', i.alias, 'unit', i.unit, 'location_id', i.location_id,",
            "         'min_stock', i.min_stock, 'max_stock', i.max_stock, 'valuation_price', i.valuation_price, 'active', i.active),",
            f"         'links', (SELECT JSON_ARRAYAGG(JSON_OBJECT('supplier_id', supplier_id, 'priority', priority)) FROM item_supplier WHERE item_id = {src}),",
            "         'reason', 'Dublette laut Abgleich Transgourmet 2026-10'), NOW()",
            f"  FROM items i WHERE i.id = {src} AND i.merged_into_id IS NULL;",
            # Lieferanten der Quelle bekommen ihre bisherige Bezeichnung als Bestellbezeichnung
            f"INSERT INTO supplier_articles (item_id, supplier_id, supplier_label, is_default, active)",
            f"  SELECT {dst}, l.supplier_id, (SELECT name FROM items WHERE id = {src}), 1, 1 FROM item_supplier l",
            f"  WHERE l.item_id = {src} AND l.supplier_id <> {TG} AND (SELECT merged_into_id FROM items WHERE id = {src}) IS NULL",
            f"    AND NOT EXISTS (SELECT 1 FROM supplier_articles sa WHERE sa.item_id = {dst} AND sa.supplier_id = l.supplier_id AND sa.is_default = 1 AND sa.active = 1);",
            f"UPDATE supplier_articles SET item_id = {dst} WHERE item_id = {src};",
            f"INSERT INTO item_supplier (item_id, supplier_id, priority) SELECT {dst}, s.supplier_id, s.priority FROM (SELECT supplier_id, priority FROM item_supplier WHERE item_id = {src}) s",
            "  ON DUPLICATE KEY UPDATE priority = GREATEST(item_supplier.priority, VALUES(priority));",
            f"UPDATE items SET active = 0, merged_into_id = {dst} WHERE id = {src} AND merged_into_id IS NULL;", ""]

# 2) Bestelldaten + Preise
for iid, nr in approved.items():
    if iid in SKIP or iid in MERGES: continue
    ov = OVERRIDES.get(iid, {})
    nr = ov.get('nr', nr)
    if not nr: continue
    out += article_sql(iid, nr, ov) + [""]

out += ["COMMIT;", ""]
open(sys.argv[4], 'w', encoding='utf8').write('\n'.join(out))
print(stats, '| mehrere Lieferanten (Name bleibt):', sorted(multi))
