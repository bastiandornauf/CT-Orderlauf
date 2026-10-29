"""Liest die Transgourmet-Ordersätze (Java Excel API, fehlende XF-Einträge) robust ein."""
import sys, collections, xlrd

def open_book(path):
    bk = xlrd.open_workbook(path, on_demand=True)
    m = collections.defaultdict(lambda: xlrd.XL_CELL_TEXT)
    m.update(bk._xf_index_to_xl_type_map)
    bk._xf_index_to_xl_type_map = m
    return bk

if __name__ == '__main__':
    bk = open_book(sys.argv[1])
    for i in range(bk.nsheets):
        sh = bk.sheet_by_index(i)
        print('==', sh.name, sh.nrows, 'x', sh.ncols)
        for r in range(min(sh.nrows, int(sys.argv[2]) if len(sys.argv) > 2 else 12)):
            print(r, [str(c.value)[:28] for c in sh.row(r)])
