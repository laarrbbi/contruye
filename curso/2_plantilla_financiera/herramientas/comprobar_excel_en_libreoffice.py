# Uso: python3 comprobar_excel_en_libreoffice.py <ruta absoluta .xlsx> <escenario.json> <salida.json> [pasadas=40]
# Abre el .xlsx corregido en LibreOffice, mete el escenario de 24 viviendas, recalcula con iteración y vuelca valores clave
import uno, json, sys, time, subprocess, datetime, re
from com.sun.star.beans import PropertyValue
xlsx, esc, out = sys.argv[1], sys.argv[2], sys.argv[3]
p = subprocess.Popen(['soffice', '--headless', '--invisible', '--norestore', '--accept=socket,host=localhost,port=2002;urp;'])
ctx = None
for _ in range(60):
    try:
        local = uno.getComponentContext()
        res = local.ServiceManager.createInstanceWithContext('com.sun.star.bridge.UnoUrlResolver', local)
        ctx = res.resolve('uno:socket,host=localhost,port=2002;urp;StarOffice.ComponentContext'); break
    except Exception: time.sleep(1)
smgr = ctx.ServiceManager
desk = smgr.createInstanceWithContext('com.sun.star.frame.Desktop', ctx)
def pv(n, v): x = PropertyValue(); x.Name = n; x.Value = v; return x
doc = desk.loadComponentFromURL(uno.systemPathToFileUrl(xlsx), '_blank', 0, (pv('Hidden', True),))
doc.IsIterationEnabled = True; doc.IterationCount = 100; doc.IterationEpsilon = 0.001
sheets = doc.Sheets
def serie(s):
    d, m, y = map(int, s.split('/')); return (datetime.date(y, m, d) - datetime.date(1899, 12, 30)).days
E = json.load(open(esc))
for hoja, celdas in E.items():
    sh = sheets.getByName(hoja)
    for a, v in celdas.items():
        c = sh.getCellRangeByName(a)
        if isinstance(v, str) and re.match(r'^\d\d/\d\d/\d{4}$', v): c.setValue(serie(v))
        elif isinstance(v, (int, float)): c.setValue(v)
        else: c.setString(v)
for _ in range(int(sys.argv[4]) if len(sys.argv)>4 else 3): doc.calculateAll()
res = {}
H = sheets.getByName('3ª Coste Total Promoción')
for r in [6, 8, 10, 12, 14, 16, 18, 20, 22, 24]:
    res[f'C{r}'] = H.getCellRangeByName(f'C{r}').getValue(); res[f'E{r}'] = H.getCellRangeByName(f'E{r}').getValue(); res[f'G{r}'] = H.getCellRangeByName(f'G{r}').getValue()
CF = sheets.getByName('6ª Cash-flow')
for a in ['BP236', 'BP238', 'BO236', 'BO238']: res['CF_' + a] = CF.getCellRangeByName(a).getValue()
PL = sheets.getByName('Parámetros legales'); res['PL_B36'] = PL.getCellRangeByName('B36').getValue(); res['PL_B37'] = PL.getCellRangeByName('B37').getValue()
# controles y errores en todo el libro
ctl_mal = []; err = 0
for i in range(sheets.Count):
    sh = sheets.getByIndex(i); cur = sh.createCursor(); cur.gotoEndOfUsedArea(False)
    R, C = cur.RangeAddress.EndRow, cur.RangeAddress.EndColumn
    rng = sh.getCellRangeByPosition(0, 0, C, R); forms = rng.getFormulaArray(); data = rng.getDataArray()
    for r in range(R + 1):
        for c in range(C + 1):
            f = forms[r][c]
            if f.startswith('='):
                cell = sh.getCellByPosition(c, r)
                if cell.getError() != 0: err += 1
                if '"O.K."' in f or '"o.k."' in f:
                    if str(data[r][c]).lower() != 'o.k.': ctl_mal.append(f'{sh.Name}!{c},{r}:{data[r][c]}')
res['errores'] = err; res['controles_mal'] = ctl_mal
json.dump(res, open(out, 'w'), ensure_ascii=False, indent=1)
doc.close(True)
try: desk.terminate()
except Exception: pass
p.terminate()
print('ok', err, len(ctl_mal))
