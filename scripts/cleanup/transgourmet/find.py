import sys, json, re
oz = json.load(open(sys.argv[1]))
def fmt(a):
    g, an = a['gebinde'], a['anbruch']
    s = f"{a['nr']:>7} {a['text1'][:34]:34} {a['text2'][:14]:14}"
    if g: s += f" | {g['inhalt']:g}x{a['unit']} je {g['liefereinheit']} {g['preis_gebinde']:.2f}€"
    if an: s += f" | Anbr {an['preis_einzel']:.2f}€/{a['unit']}"
    s += f" | {a['rezept'] or 0:g}{a['rezept_unit']}"
    return s
for q in sys.argv[2:]:
    rx = re.compile(q, re.I)
    hits = [a for a in oz['2026-10'].values() if rx.search(a['text1'] + ' ' + a['text2'])]
    print(f'--- {q} ({len(hits)})')
    for a in hits[:14]: print('  ' + fmt(a))
