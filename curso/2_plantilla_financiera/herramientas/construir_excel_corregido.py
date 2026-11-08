# Genera PLANTILLA_LIBRE_corregida.xlsx: el .xls original convertido a .xlsx + exactamente los cambios de plantilla_libre.json
import json, sys, openpyxl, datetime
from openpyxl.styles import Font, PatternFill, Alignment
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.workbook.properties import CalcProperties
from openpyxl.comments import Comment
import os
# Paso previo: soffice --headless --convert-to xlsx --outdir conv ../originales/PLANTILLA_LIBRE.xls
B = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'plantilla') + os.sep
O = json.load(open(B + 'plantilla_libre_original.json')); N = json.load(open(B + 'plantilla_libre.json'))
wb = openpyxl.load_workbook('conv/PLANTILLA_LIBRE.xlsx')
HO = {h['nombre']: h['celdas'] for h in O['hojas']}; HN = {h['nombre']: h['celdas'] for h in N['hojas']}
def cont(c):
    if c is None: return None
    return c['f'] if 'f' in c else c.get('v')
# 1) el .xlsx convertido tiene las mismas fórmulas que el export original
dif = 0
for nombre, cells in HO.items():
    ws = wb[nombre]
    for a, c in cells.items():
        if 'f' in c and ws[a].value != c['f']: dif += 1
print('fórmulas del .xlsx distintas del export original:', dif)
assert dif == 0
# 2) aplicar los cambios
n = 0
for nombre, cells in HN.items():
    if nombre not in HO: continue
    ws = wb[nombre]
    for a, c in cells.items():
        if cont(c) != cont(HO[nombre].get(a)):
            v = cont(c)
            ws[a].value = v
            if 'nf' in c and a in ('D102',): ws[a].number_format = c['nf']
            n += 1
print('celdas cambiadas', n)
# 3) hoja nueva de parámetros legales
PLn = 'Parámetros legales'
ws = wb.create_sheet(PLn, 0 if False else len(wb.sheetnames))
for a, c in HN[PLn].items():
    v = cont(c); ws[a].value = v
    if 'nf' in c: ws[a].number_format = c['nf'].replace('dd/mm/yyyy', 'DD/MM/YYYY')
ws['B3'].value = datetime.date(2026, 11, 8); ws['B3'].number_format = 'DD/MM/YYYY'
ws['A1'].font = Font(bold=True, size=14)
for a in ['A4', 'B4', 'C4', 'D4', 'E4', 'F4', 'A14', 'B14', 'C14', 'D14', 'A13', 'A35', 'A36', 'A37']: ws[a].font = Font(bold=True)
amarillo = PatternFill('solid', fgColor='FFF2CC')
for r in list(range(5, 11)): ws[f'C{r}'].fill = amarillo
for r in range(15, 34): ws[f'B{r}'].fill = amarillo; ws[f'C{r}'].fill = amarillo
for col, w in zip('ABCDEF', [34, 58, 16, 22, 60, 70]): ws.column_dimensions[col].width = w
for r in range(1, 38):
    for col in 'ABCDEF': ws[f'{col}{r}'].alignment = Alignment(wrap_text=True, vertical='top')
# lista desplegable de comunidades en Datos de Partida D93
dp = wb['Datos de Partida']
dv = DataValidation(type='list', formula1="'Parámetros legales'!$A$15:$A$33", allow_blank=False)
dp.add_data_validation(dv); dv.add('D93')
dp['D93'].comment = Comment('Elige la comunidad autónoma del solar. Decide el ITP y el AJD (hoja Parámetros legales).', 'CONSTRUYE')
dp['D102'].comment = Comment('Antes estaba fijo al 1,5 % dentro de la fórmula de 3ª Proyectos D53.', 'CONSTRUYE')
# 4) cálculo iterativo activado (las referencias circulares del préstamo son a propósito)
wb.calculation = CalcProperties(iterate=True, iterateCount=100, iterateDelta=0.001, fullCalcOnLoad=True, calcMode='auto')
out = sys.argv[1] if len(sys.argv) > 1 else 'PLANTILLA_LIBRE_corregida.xlsx'
wb.save(out)
# 5) comprobación: el archivo guardado tiene exactamente las fórmulas del JSON corregido
wb2 = openpyxl.load_workbook(out)
bad = 0; nf = 0
for nombre, cells in HN.items():
    w = wb2[nombre]
    for a, c in cells.items():
        if 'f' in c:
            nf += 1
            if w[a].value != c['f']: bad += 1
print('fórmulas en el .xlsx corregido:', nf, 'distintas del JSON corregido:', bad, '| iteración:', wb2.calculation.iterate, wb2.calculation.iterateCount, wb2.calculation.iterateDelta)
assert bad == 0
