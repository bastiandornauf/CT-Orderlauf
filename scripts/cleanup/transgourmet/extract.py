"""Ordersatz → JSON: ein Eintrag je Artikelnr mit Gebinde- und Anbruch-Preis."""
import sys, json, collections
import os; sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from readxls import open_book

def num(v):
    try: return float(v)
    except (TypeError, ValueError): return None

def load(path):
    sh = open_book(path).sheet_by_name('Artikel')
    hdr = [str(c.value) for c in sh.row(0)]
    arts = {}
    for r in range(1, sh.nrows):
        row = dict(zip(hdr, [c.value for c in sh.row(r)]))
        nr = str(int(num(row['Artikelnr']))) if num(row['Artikelnr']) else str(row['Artikelnr'])
        a = arts.setdefault(nr, {'nr': nr, 'text1': str(row['Artikeltext1']).strip(), 'text2': str(row['Artikeltext2']).strip(),
             'unit': row['Einheit'], 'rezept': num(row['Rezeptmenge']), 'rezept_unit': row['Einheit Rezeptmenge'],
             'netto_kg': num(row['Nettogewicht in KG']), 'mwst': num(row['MwSt in %']),
             'kat': ' / '.join(x for x in [row['Katalogebene 1'], row['Katalogebene 2'], row['Katalogebene 3']] if x),
             'valid_from': row['Preis gültig von'], 'herkunft': row['Preis Herkunft'], 'gebinde': None, 'anbruch': None})
        entry = {'inhalt': num(row['Inhalt']), 'liefereinheit': row['Liefereinheit'],
                 'preis_einzel': num(row['Preis pro Einzeleinheit']), 'preis_gebinde': num(row['Preis pro Gebinde'])}
        a['anbruch' if str(row['Anbruch']).strip() == 'A' else 'gebinde'] = entry
    return arts

if __name__ == '__main__':
    out = {}
    for tag, path in [('2026-03', sys.argv[1]), ('2026-10', sys.argv[2])]:
        out[tag] = load(path)
        c = collections.Counter(a['gebinde']['liefereinheit'] if a['gebinde'] else 'nur Anbruch' for a in out[tag].values())
        print(tag, len(out[tag]), 'Artikel; Anbruch möglich:', sum(1 for a in out[tag].values() if a['anbruch']), '; Liefereinheiten:', dict(c.most_common(8)))
        print('  Preis-Herkunft:', collections.Counter(a['herkunft'] for a in out[tag].values()), '| gültig von:', collections.Counter(a['valid_from'] for a in out[tag].values()).most_common(3))
    json.dump(out, open(sys.argv[3], 'w'), ensure_ascii=False, indent=1)
