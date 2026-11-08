# Genera las 5 plantillas pequeñas del curso (incluidas en el curso) en .xlsx y en JSON para el mismo motor.
# Cada una lleva la hoja «Parámetros legales» con las mismas celdas que la plantilla grande, así el administrador
# cambia los impuestos una vez y se aplican a todas (PlantillaEngine.setParametros).
import json, os, copy, openpyxl
from openpyxl.styles import Font, PatternFill, Alignment
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.workbook.properties import CalcProperties

AQUI = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(AQUI, '..', 'plantillas_pequenas')
os.makedirs(OUT, exist_ok=True)
GRANDE = json.load(open(os.path.join(AQUI, '..', 'plantilla', 'plantilla_libre.json')))
PL_CELLS = next(h for h in GRANDE['hojas'] if h['nombre'] == 'Parámetros legales')['celdas']
CCAA = [PL_CELLS[f'A{r}']['v'] for r in range(15, 34)]
Q = "'Parámetros legales'!"
IVA_G, IVA_R, AJD_PREST = Q + '$C$5', Q + '$C$6', Q + '$C$10'
ITP, AJD = Q + '$B$36', Q + '$B$37'
S = 'Calculadora'

def T(numero, archivo, titulo, modulo, filas, ccaa_celda=None, tabla=None):
    return dict(numero=numero, archivo=archivo, titulo=titulo, modulo=modulo, filas=filas, ccaa=ccaa_celda, tabla=tabla)
# filas: (fila, tipo, etiqueta, valor_o_formula, formato, nota)   tipo: in | out | sec
EUR, PCT, NUM, TXT = '#,##0.00 €', '0.00%', '#,##0', '@'
PLANTILLAS = [
 T(1, '1_compra_del_solar', 'Plantilla 1 · Compra del solar', 'Módulo 1 · Solar', [
  (4, 'in', 'Comunidad autónoma del solar', 'Comunitat Valenciana', TXT, 'Decide el ITP o el AJD (hoja Parámetros legales)'),
  (5, 'in', 'Precio del solar', 500000, EUR, ''),
  (6, 'in', '¿Lo vende una empresa con IVA? (SI/NO)', 'SI', TXT, 'SI: IVA + AJD. NO (particular): ITP'),
  (7, 'in', 'Edificabilidad (m² de techo)', 2000, NUM, ''),
  (8, 'in', 'Notaría y registro (% del precio, estimado)', 0.005, PCT, 'Pide presupuesto para afinar'),
  (9, 'in', 'Plusvalía (si la asumes), API y otros', 0, EUR, 'La plusvalía municipal la paga el vendedor salvo pacto'),
  (10, 'in', 'Due diligence, tasación y estudios', 3000, EUR, ''),
  (12, 'sec', 'RESULTADO', None, None, ''),
  (13, 'out', 'IVA del solar (lo adelantas y lo recuperas)', f'=IF(B6="SI",B5*{IVA_G},0)', EUR, 'Una SL promotora lo deduce'),
  (14, 'out', 'AJD', f'=IF(B6="SI",B5*{AJD},0)', EUR, ''),
  (15, 'out', 'ITP', f'=IF(B6="SI",0,B5*{ITP})', EUR, ''),
  (16, 'out', 'Notaría y registro', '=B5*B8', EUR, ''),
  (17, 'out', 'Coste del solar (sin IVA)', '=B5+B14+B15+B16+B9+B10', EUR, ''),
  (18, 'out', 'Dinero que necesitas el día de la compra', '=B17+B13', EUR, ''),
  (19, 'out', 'Repercusión del suelo (€/m² de techo)', '=IF(B7=0,0,B17/B7)', EUR, ''),
  (20, 'out', 'Tipo de AJD o ITP aplicado', f'=IF(B6="SI",{AJD},{ITP})', PCT, ''),
 ], ccaa_celda='B4'),
 T(2, '2_viabilidad_metodo_residual', 'Plantilla 2 · Viabilidad rápida y método residual', 'Módulo 2 · Análisis de viabilidad', [
  (4, 'in', 'Comunidad autónoma', 'Comunitat Valenciana', TXT, ''),
  (5, 'in', '¿El solar se compra con IVA? (SI/NO)', 'SI', TXT, ''),
  (6, 'in', 'Edificabilidad (m² construidos)', 2000, NUM, ''),
  (7, 'in', 'Número de viviendas', 20, NUM, ''),
  (8, 'in', 'Precio de venta (€/m² construido, sin IVA)', 2500, EUR, 'De tu estudio de mercado'),
  (9, 'in', 'Coste de construcción (€/m² construido)', 1200, EUR, 'Pide al menos 3 presupuestos'),
  (10, 'in', 'Urbanización y acometidas', 50000, EUR, ''),
  (11, 'in', 'Honorarios técnicos (% s/ construcción)', 0.08, PCT, ''),
  (12, 'in', 'Licencias, ICIO y tasas (% s/ construcción)', 0.05, PCT, 'ICIO: máximo legal 4 %'),
  (13, 'in', 'Seguros, OCT, control de calidad y otros (% s/ construcción)', 0.03, PCT, ''),
  (14, 'in', 'Comercialización y marketing (% s/ ventas)', 0.03, PCT, ''),
  (15, 'in', 'Gastos financieros (% s/ ventas)', 0.04, PCT, 'Afínalo con la Plantilla 3'),
  (16, 'in', 'Gastos generales de la promotora (% s/ ventas)', 0.02, PCT, ''),
  (17, 'in', 'Margen objetivo (% s/ ventas, antes de impuestos)', 0.18, PCT, 'Mínimo habitual: 15 %'),
  (18, 'in', 'Precio que piden por el solar', 600000, EUR, ''),
  (20, 'sec', 'RESULTADO', None, None, ''),
  (21, 'out', 'Ventas (sin IVA)', '=B6*B8', EUR, ''),
  (22, 'out', 'Construcción', '=B6*B9', EUR, ''),
  (23, 'out', 'Honorarios técnicos', '=B22*B11', EUR, ''),
  (24, 'out', 'Licencias, ICIO y tasas', '=B22*B12', EUR, ''),
  (25, 'out', 'Seguros, OCT, control y otros', '=B22*B13', EUR, ''),
  (26, 'out', 'Urbanización y acometidas', '=B10', EUR, ''),
  (27, 'out', 'Comercialización', '=B21*B14', EUR, ''),
  (28, 'out', 'Gastos financieros', '=B21*B15', EUR, ''),
  (29, 'out', 'Gastos generales', '=B21*B16', EUR, ''),
  (30, 'out', 'Costes sin suelo', '=SUM(B22:B29)', EUR, ''),
  (31, 'out', 'Impuesto de la compra del suelo (tipo)', f'=IF(B5="SI",{AJD},{ITP})', PCT, 'AJD si lleva IVA; ITP si no'),
  (32, 'out', 'Precio máximo del solar (método residual)', '=MAX(0,(B21*(1-B17)-B30)/(1+B31))', EUR, 'Lo máximo que puedes pagar y ganar tu margen objetivo'),
  (33, 'out', 'Coste del solar con impuestos (al precio pedido)', '=B18*(1+B31)', EUR, 'Sin notaría ni registro: afínalo con la Plantilla 1'),
  (34, 'out', 'Coste total de la promoción', '=B30+B33', EUR, ''),
  (35, 'out', 'Beneficio antes de impuestos', '=B21-B34', EUR, ''),
  (36, 'out', 'Margen sobre ventas', '=IF(B21=0,0,B35/B21)', PCT, ''),
  (37, 'out', '¿Cumple el margen objetivo?', '=IF(B18<=B32,"SÍ","NO")', TXT, ''),
  (38, 'out', 'Repercusión del suelo (€/m² construido)', '=IF(B6=0,0,B18/B6)', EUR, ''),
  (39, 'out', 'Precio medio por vivienda (sin IVA)', '=IF(B7=0,0,B21/B7)', EUR, ''),
  (40, 'out', 'Precio medio por vivienda (con IVA)', f'=B39*(1+{IVA_R})', EUR, 'IVA de vivienda nueva'),
 ], ccaa_celda='B4'),
 T(3, '3_prestamo_promotor', 'Plantilla 3 · Préstamo promotor', 'Módulo 5 · Financiación y contratación', [
  (4, 'in', 'Coste total de la promoción', 4000000, EUR, 'De la Plantilla 2'),
  (5, 'in', '% del coste que financia el banco', 0.7, PCT, ''),
  (6, 'in', 'Tipo de interés anual', 0.045, PCT, ''),
  (7, 'in', 'Comisión de apertura', 0.0075, PCT, ''),
  (8, 'in', 'Meses de obra (el préstamo se dispone poco a poco)', 20, NUM, ''),
  (9, 'in', 'Meses desde el fin de obra hasta las escrituras de venta', 4, NUM, ''),
  (10, 'in', 'Tasación y certificaciones de obra', 6000, EUR, ''),
  (11, 'in', 'Notaría y registro del préstamo', 4000, EUR, ''),
  (12, 'in', 'Responsabilidad hipotecaria (veces el principal)', 1.5, '0.00', ''),
  (14, 'sec', 'RESULTADO', None, None, ''),
  (15, 'out', 'Importe del préstamo', '=B4*B5', EUR, ''),
  (16, 'out', 'Fondos propios necesarios', '=B4-B15', EUR, ''),
  (17, 'out', 'Comisión de apertura', '=B15*B7', EUR, ''),
  (18, 'out', 'Intereses durante la obra (saldo medio: la mitad)', '=B15*B6*B8/12*0.5', EUR, 'Aproximación: disposición lineal'),
  (19, 'out', 'Intereses hasta las escrituras de venta', '=B15*B6*B9/12', EUR, ''),
  (20, 'out', 'AJD del préstamo a cargo del promotor', f'=B15*B12*{AJD_PREST}', EUR, 'Desde 2018 lo paga el banco (RDL 17/2018)'),
  (21, 'out', 'Tasación, notaría y registro', '=B10+B11', EUR, ''),
  (22, 'out', 'Coste financiero total', '=SUM(B17:B21)', EUR, ''),
  (23, 'out', 'Coste financiero sobre el coste de la promoción', '=IF(B4=0,0,B22/B4)', PCT, ''),
  (24, 'out', 'IVA de tasación, notaría y registro (lo recuperas)', f'=B21*{IVA_G}', EUR, ''),
 ]),
 T(4, '4_control_costes_obra', 'Plantilla 4 · Control de costes de obra', 'Módulo 7 · Control de obra', [], tabla=dict(
    capitulos=[('Movimiento de tierras', 60000, 60000, 62000), ('Cimentación', 180000, 180000, 186000), ('Estructura', 420000, 380000, 420000),
               ('Albañilería', 300000, 150000, 310000), ('Cubiertas', 90000, 0, 90000), ('Instalaciones', 380000, 60000, 380000),
               ('Carpintería', 210000, 0, 210000), ('Acabados', 330000, 0, 345000), ('Urbanización interior', 70000, 0, 70000),
               ('Seguridad y salud', 30000, 15000, 30000), ('Gestión de residuos', 15000, 7000, 15000), ('Control de calidad', 15000, 6000, 15000)])),
 T(5, '5_plan_ventas_cobros', 'Plantilla 5 · Plan de ventas y cobros', 'Módulo 8 · Comercialización', [
  (4, 'in', 'Número de viviendas', 20, NUM, ''),
  (5, 'in', 'Precio medio por vivienda (sin IVA)', 250000, EUR, ''),
  (6, 'in', 'Reserva (€ por vivienda)', 6000, EUR, 'Se descuenta en el contrato privado'),
  (7, 'in', 'A la firma del contrato privado (% del precio)', 0.10, PCT, ''),
  (8, 'in', 'Aplazado durante la obra (% del precio)', 0.10, PCT, ''),
  (9, 'in', 'Meses de pago aplazado', 18, NUM, ''),
  (10, 'in', 'Viviendas vendidas al mes', 2, NUM, ''),
  (12, 'sec', 'POR VIVIENDA', None, None, ''),
  (13, 'out', 'Precio con IVA', f'=B5*(1+{IVA_R})', EUR, 'IVA de vivienda nueva'),
  (14, 'out', 'Reserva', '=B6', EUR, ''),
  (15, 'out', 'Contrato privado (con IVA, descontada la reserva)', f'=MAX(0,B5*B7*(1+{IVA_R})-B6)', EUR, ''),
  (16, 'out', 'Cuota mensual durante la obra (con IVA)', f'=IF(B9=0,0,B5*B8*(1+{IVA_R})/B9)', EUR, ''),
  (17, 'out', 'Pago en la escritura (con IVA)', '=B13-B14-B15-B16*B9', EUR, ''),
  (18, 'out', 'Cobrado antes de la entrega (cantidades a cuenta)', '=B14+B15+B16*B9', EUR, 'Obligatorio avalarlas o asegurarlas (LOE, disp. adicional 1.ª)'),
  (20, 'sec', 'TOTAL DE LA PROMOCIÓN', None, None, ''),
  (21, 'out', 'Ventas sin IVA', '=B4*B5', EUR, ''),
  (22, 'out', 'IVA repercutido', f'=B21*{IVA_R}', EUR, ''),
  (23, 'out', 'Ventas con IVA', '=B21+B22', EUR, ''),
  (24, 'out', 'Cantidades a cuenta que debes garantizar', '=B18*B4', EUR, 'Con el interés legal del dinero si hay que devolverlas'),
  (25, 'out', 'Meses para venderlo todo', '=IF(B10=0,0,B4/B10)', '0.0', ''),
  (26, 'out', '% cobrado antes de la escritura', '=IF(B23=0,0,B24/B23)', PCT, ''),
 ]),
]

def tabla_obra(t):
    cells, filas = {}, []
    cab = ['Capítulo', 'Presupuesto contratado', 'Certificado a origen', 'Previsión final', 'Pendiente de certificar', 'Desviación (€)', 'Desviación (%)']
    for i, h in enumerate(cab): cells[chr(65 + i) + '4'] = ('sec', h, None)
    ent = []
    for k, (cap, pres, cert, prev) in enumerate(t['capitulos']):
        r = 5 + k
        cells[f'A{r}'] = ('in', cap, TXT); cells[f'B{r}'] = ('in', pres, EUR); cells[f'C{r}'] = ('in', cert, EUR); cells[f'D{r}'] = ('in', prev, EUR)
        cells[f'E{r}'] = ('out', f'=D{r}-C{r}', EUR); cells[f'F{r}'] = ('out', f'=D{r}-B{r}', EUR); cells[f'G{r}'] = ('out', f'=IF(B{r}=0,0,F{r}/B{r})', PCT)
        for col, lab, tipo in [('A', 'nombre', 'texto'), ('B', 'presupuesto', 'numero'), ('C', 'certificado a origen', 'numero'), ('D', 'previsión final', 'numero')]:
            ent.append(dict(seccion='Capítulos', hoja=S, celda=f'{col}{r}', etiqueta=f'Capítulo {k+1}: {lab}', tipo=tipo, unidad='€' if tipo == 'numero' else '', defecto=cells[f'{col}{r}'][1], nota='Ejemplo: sustitúyelo por tus datos', defecto_es_formula=False))
    n = 5 + len(t['capitulos']); last = n - 1
    cells[f'A{n}'] = ('sec', 'TOTAL', None)
    for col in 'BCDEF': cells[f'{col}{n}'] = ('out', f'=SUM({col}5:{col}{last})', EUR)
    cells[f'G{n}'] = ('out', f'=IF(B{n}=0,0,F{n}/B{n})', PCT)
    r = n + 2
    cells[f'A{r}'] = ('in', 'Retención de garantía (% de cada certificación)', None); cells[f'B{r}'] = ('in', 0.05, PCT)
    ent.append(dict(seccion='Obra', hoja=S, celda=f'B{r}', etiqueta='Retención de garantía', tipo='porcentaje', unidad='%', defecto=0.05, nota='', defecto_es_formula=False))
    out = [(r + 1, 'Retenido hasta hoy', f'=C{n}*B{r}', EUR), (r + 2, 'IVA soportado de lo certificado (vivienda)', f'=C{n}*{IVA_R}', EUR),
           (r + 3, 'Obra ejecutada (% de la previsión final)', f'=IF(D{n}=0,0,C{n}/D{n})', PCT), (r + 4, 'Desviación total sobre el presupuesto', f'=G{n}', PCT),
           (r + 5, 'A pagar a la constructora hasta hoy (con IVA, menos retención)', f'=C{n}*(1+{IVA_R})-C{n}*B{r}', EUR)]
    for rr, lab, f, fmt in out: cells[f'A{rr}'] = ('lab', lab, None); cells[f'B{rr}'] = ('out', f, fmt)
    return cells, ent

indice = []
for p in PLANTILLAS:
    wb = openpyxl.Workbook(); ws = wb.active; ws.title = S
    amarillo = PatternFill('solid', fgColor='FFF2CC')
    celdas_json, entradas = {}, []
    def put(a, v, fmt=None, tipo='out'):
        ws[a].value = v
        if fmt and fmt != TXT: ws[a].number_format = fmt
        c = {'f': v} if isinstance(v, str) and v.startswith('=') else {'v': v}
        if fmt and fmt != TXT: c['nf'] = fmt
        celdas_json[a] = c
        if tipo == 'in' and a[0] != 'A': ws[a].fill = amarillo
        if tipo == 'in' and a[0] == 'A' and p['tabla']: ws[a].fill = amarillo
    put('A1', p['titulo']); ws['A1'].font = Font(bold=True, size=14)
    put('A2', f"{p['modulo']} · Incluida en el curso CONSTRUYE. Rellena las casillas amarillas; los impuestos salen de la hoja Parámetros legales.")
    if p['tabla']:
        cells, entradas = tabla_obra(p['tabla'])
        for a, (tipo, v, fmt) in cells.items():
            put(a, v, fmt, tipo)
            if tipo in ('sec',) or (a[0] == 'A' and tipo == 'lab'): ws[a].font = Font(bold=(tipo == 'sec'))
        widths = [38, 20, 20, 20, 22, 18, 14]
    else:
        for (r, tipo, lab, v, fmt, nota) in p['filas']:
            put(f'A{r}', lab)
            if tipo == 'sec': ws[f'A{r}'].font = Font(bold=True); continue
            put(f'B{r}', v, fmt, tipo)
            if nota: put(f'C{r}', nota)
            if tipo == 'in':
                t_ = 'texto' if fmt == TXT else ('porcentaje' if fmt == PCT else 'numero')
                if 'SI/NO' in lab: t_ = 'SI/NO'
                e = dict(seccion='Datos', hoja=S, celda=f'B{r}', etiqueta=lab, tipo=t_, unidad='€' if fmt == EUR else ('%' if fmt == PCT else ''), defecto=v, nota=nota, defecto_es_formula=False)
                if p['ccaa'] == f'B{r}': e.update(tipo='lista: comunidades', opciones=CCAA)
                entradas.append(e)
        widths = [58, 20, 62]
    for i, w in enumerate(widths): ws.column_dimensions[chr(65 + i)].width = w
    # Hoja de parámetros legales: copia exacta de la plantilla grande
    pl = wb.create_sheet('Parámetros legales'); pl_json = copy.deepcopy(PL_CELLS)
    pl_json['B35'] = {'f': f"='{S}'!{p['ccaa']}"} if p['ccaa'] else {'v': 'Comunitat Valenciana'}
    if not p['ccaa']: pl_json['A35'] = {'v': 'Comunidad (esta plantilla no la usa)'}
    for a, c in pl_json.items():
        pl[a].value = c['f'] if 'f' in c else c.get('v')
        if 'nf' in c: pl[a].number_format = c['nf'].replace('dd/mm/yyyy', 'DD/MM/YYYY')
    pl['A1'].font = Font(bold=True, size=14)
    for col, w in zip('ABCDEF', [34, 58, 16, 22, 60, 70]): pl.column_dimensions[col].width = w
    if p['ccaa']:
        dv = DataValidation(type='list', formula1="'Parámetros legales'!$A$15:$A$33"); ws.add_data_validation(dv); dv.add(p['ccaa'])
    for r in range(1, 60):
        for col in 'ABCDEFG': ws[f'{col}{r}'].alignment = Alignment(wrap_text=True, vertical='top')
    wb.calculation = CalcProperties(fullCalcOnLoad=True)
    wb.save(os.path.join(OUT, p['archivo'] + '.xlsx'))
    tpl = {'nombre': p['titulo'], 'origen': 'CONSTRUYE (plantilla pequeña incluida en el curso)', 'modulo': p['modulo'],
           'calculo': {'iterativo': False, 'max_iteraciones': 1, 'cambio_maximo': 0.001},
           'hojas': [{'nombre': S, 'celdas': celdas_json}, {'nombre': 'Parámetros legales', 'celdas': pl_json}], 'cortes_de_ciclo': []}
    json.dump(tpl, open(os.path.join(OUT, p['archivo'] + '.json'), 'w'), ensure_ascii=False, indent=1)
    json.dump({'plantilla': p['titulo'], 'regla': 'Solo se escriben estas celdas. Los impuestos salen de Parámetros legales (los fija el administrador).',
               'porcentajes': 'En tanto por uno (0,05 = 5 %).', 'entradas': entradas}, open(os.path.join(OUT, p['archivo'] + '.entradas.json'), 'w'), ensure_ascii=False, indent=1)
    indice.append(dict(numero=p['numero'], titulo=p['titulo'], modulo=p['modulo'], excel=p['archivo'] + '.xlsx', plantilla=p['archivo'] + '.json', entradas=p['archivo'] + '.entradas.json', n_entradas=len(entradas)))
json.dump({'nota': 'Plantillas pequeñas incluidas en el curso. La plantilla profesional completa (PLANTILLA_LIBRE) es el extra de pago del final del curso.', 'plantillas': indice},
          open(os.path.join(OUT, 'indice.json'), 'w'), ensure_ascii=False, indent=1)
print(json.dumps(indice, ensure_ascii=False, indent=0))
