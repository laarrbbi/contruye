# Prepara para el curso las 3 plantillas que envió el profesional el 11/11/2026 (los originales quedan intactos en originales/):
#  1. Números gordos del solar  -> plantillas_pequenas/numeros_gordos_solar.xlsx (+ .json y .entradas.json para el motor)
#  2. Base de datos de suelo    -> plantillas_pequenas/base_datos_suelo.xlsx (hecha a partir de su esquema)
#  3. Guía de análisis de viabilidad -> excel_corregido/Guia_Analisis_de_Viabilidad_corregida.xlsx
#     (la plantilla corregida con el ejemplo de la guía y sus notas; el ejemplo está en pruebas/escenario_guia_profesional.json)
import json, os, datetime, copy, openpyxl
from openpyxl.styles import Font, PatternFill, Alignment
from openpyxl.comments import Comment
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.worksheet.table import Table, TableStyleInfo
from openpyxl.workbook.properties import CalcProperties

AQUI = os.path.dirname(os.path.abspath(__file__)); RAIZ = os.path.join(AQUI, '..')
ORI = os.path.join(RAIZ, 'originales'); PEQ = os.path.join(RAIZ, 'plantillas_pequenas'); EXC = os.path.join(RAIZ, 'excel_corregido')
AMARILLO = PatternFill('solid', fgColor='FFF2CC')
NOTA = 'CONSTRUYE'

# ------------------------------------------------------------------ 1. Números gordos del solar
wb = openpyxl.load_workbook(os.path.join(ORI, 'Plantilla_calculo_precio_solar.xlsx'))
ws = wb['Números gordos']
assert ws['C9'].value == '=C5*C7*100' and ws['C19'].value == '=(C17*C18*100)+C16+C15' and ws['C23'].value == '=C19*C20*100' and ws['C25'].value == '=C24*0.3'
cambios = []
def cambia(a, v, motivo, fmt=None):
    cambios.append(dict(celda=a, antes=ws[a].value, despues=v, motivo=motivo)); ws[a].value = v
    if fmt: ws[a].number_format = fmt
    ws[a].comment = Comment(f'Corregido por CONSTRUYE: {motivo}', NOTA)
cambia('B7', 'Edificabilidad terciario/comercial (m²t/m²s)', 'La etiqueta no decía la unidad.')
cambia('C7', 0, 'Era un % con ×100 dentro de la fórmula. Ahora es el coeficiente m²t/m²s tal cual.', '0.0000" m²t/m²s"')
cambia('C8', '=C6', 'Estaba escrito a mano y repetía C6.')
cambia('C9', '=C5*C7', 'Quitado el ×100: superficie × coeficiente.')
cambia('B18', 'Licencias, proyectos y otros (% s/ construcción)', 'La etiqueta estaba cortada.')
cambia('C18', 0.40, 'Se escribía 1,40 % para decir «coste × 1,4». Ahora es el 40 % que se suma a la construcción (mismo resultado).', '0.00%')
cambia('C19', '=C17*(1+C18)+C16+C15', 'Mismo cálculo sin el ×100: construcción + 40 % + urbanización + suelo.')
cambia('B20', 'Beneficio sobre coste (%)', 'La etiqueta no decía sobre qué.')
cambia('C20', 0.30, 'Se escribía 1,30 % para decir «coste × 1,3». Ahora es el 30 % de beneficio sobre coste (mismo resultado).', '0.00%')
cambia('C23', '=C19*(1+C20)', 'Mismo cálculo sin el ×100.')
cambia('B25', 'Beneficio industrial (ventas − coste)', 'Decía «30 %», pero un 30 % sobre coste es un 23 % sobre ventas.')
cambia('C25', '=C24-C19*C10', 'Era el 30 % de las ventas (599.558 € en el ejemplo); el beneficio real es ventas − coste (461.218 €).')
ws['B26'].value = 'Margen sobre ventas'; ws['C26'].value = '=IF(C24=0,0,C25/C24)'; ws['C26'].number_format = '0.00%'
ws['B27'].value = 'Coste total de la promoción'; ws['C27'].value = '=C19*C10'; ws['C27'].number_format = '#,##0.00\\ "€"'
ws['B29'].value = 'Cifras sin IVA. El precio del solar no incluye ITP o AJD, notaría ni registro: afínalo con la Plantilla 1 · Compra del solar.'
ws['B29'].font = Font(italic=True)
for a in ['C4', 'C5', 'C6', 'C7', 'C11', 'C12', 'C13', 'C15', 'C16', 'C17', 'C18', 'C20', 'C21']: ws[a].fill = AMARILLO
wb.calculation = CalcProperties(fullCalcOnLoad=True)
wb.save(os.path.join(PEQ, 'numeros_gordos_solar.xlsx'))
celdas = {}
for row in ws.iter_rows():
    for c in row:
        if c.value is None: continue
        d = {'f': c.value} if isinstance(c.value, str) and c.value.startswith('=') else {'v': c.value}
        if c.number_format and c.number_format != 'General' and not isinstance(c.value, str): d['nf'] = c.number_format
        celdas[c.coordinate] = d
json.dump({'nombre': 'Números gordos del solar (plantilla del profesional, corregida)', 'origen': 'originales/Plantilla_calculo_precio_solar.xlsx',
           'modulo': 'Módulo 1 · Solar', 'calculo': {'iterativo': False, 'max_iteraciones': 1, 'cambio_maximo': 0.001},
           'hojas': [{'nombre': 'Números gordos', 'celdas': celdas}], 'cortes_de_ciclo': [], 'correcciones': cambios},
          open(os.path.join(PEQ, 'numeros_gordos_solar.json'), 'w'), ensure_ascii=False, indent=1)
H = 'Números gordos'
ent = [('C4', 'Situación urbanística', 'texto', '', 'Urbano consolidado'), ('C5', 'Superficie del solar', 'numero', 'm²s', 344),
       ('C6', 'Edificabilidad residencial (techo total)', 'numero', 'm²t', 1586.49), ('C7', 'Edificabilidad terciario/comercial', 'numero', 'm²t/m²s', 0),
       ('C11', 'Ocupación', 'porcentaje', '%', 1), ('C12', 'Alturas', 'texto', '', 'B+5'), ('C13', 'Producto resultante', 'texto', '', '18 viviendas (2 son áticos)'),
       ('C15', 'Repercusión del suelo', 'numero', '€/m²t', 45), ('C16', 'Costes de urbanización', 'numero', '€/m²t', 0),
       ('C17', 'Coste de construcción', 'numero', '€/m²t', 660), ('C18', 'Licencias, proyectos y otros', 'porcentaje', '% s/ construcción', 0.40),
       ('C20', 'Beneficio sobre coste', 'porcentaje', '%', 0.30), ('C21', 'Vivienda tipo', 'numero', 'm²c', 90)]
json.dump({'plantilla': 'Números gordos del solar', 'regla': 'Solo se escriben estas celdas.', 'porcentajes': 'En tanto por uno (0,30 = 30 %).',
           'entradas': [dict(seccion='Solar', hoja=H, celda=a, etiqueta=l, tipo=t, unidad=u, defecto=d, nota='Ejemplo del profesional', defecto_es_formula=False) for a, l, t, u, d in ent]},
          open(os.path.join(PEQ, 'numeros_gordos_solar.entradas.json'), 'w'), ensure_ascii=False, indent=1)

# ------------------------------------------------------------------ 2. Base de datos de suelo (a partir del esquema)
esq = openpyxl.load_workbook(os.path.join(ORI, 'Esquema_BBDD_Gestion_de_Suelo.xlsx'))['Esquema BBDD']
def lista(desde, hasta): return [esq[f'A{r}'].value for r in range(desde, hasta + 1) if esq[f'A{r}'].value]
LISTAS = {
    'Zona': ['Casco urbano', 'Área metropolitana', 'Provincia', 'Costa'],
    'Clasificación del suelo': lista(10, 14),
    'Calificación / tipología': lista(16, 24),
    'Instrumento de planeamiento': lista(26, 30) + ['Otro'],
    'Impuestos': ['Persona física - ITP', 'Persona jurídica - IVA'],
    'Sí / No': ['Sí', 'No'],
}
assert esq['A5'].value.startswith('Casco Urbano') and len(LISTAS['Calificación / tipología']) == 9
contacto = [esq[f'D{r}'].value for r in range(4, 16)]
SOLARES = ['Referencia', 'Zona', 'Dirección / ubicación', 'Población', 'Clasificación del suelo', 'Calificación / tipología', 'Instrumento de planeamiento',
           'PAI aprobado', 'Plan parcial', 'Proyecto de urbanización', 'Proyecto de reparcelación', 'Licencia', 'Otros (situación urbanística)',
           'Superficie registral (m²)', 'Superficie catastral (m²)', 'Superficie real (m²)', 'Superficie estudiada (m²)', 'Edificabilidad (m²t)',
           'Producto resultante', 'Precio (€)', 'Impuestos', 'Forma de pago', 'Honorarios de intermediación', 'Propietario', 'Captador',
           'Observaciones', 'Histórico', 'Documentos vinculados']
LISTA_DE = {'Zona': 'Zona', 'Clasificación del suelo': 'Clasificación del suelo', 'Calificación / tipología': 'Calificación / tipología',
            'Instrumento de planeamiento': 'Instrumento de planeamiento', 'Impuestos': 'Impuestos', 'PAI aprobado': 'Sí / No', 'Plan parcial': 'Sí / No',
            'Proyecto de urbanización': 'Sí / No', 'Proyecto de reparcelación': 'Sí / No', 'Licencia': 'Sí / No'}
CLIENTES = ['Referencia'] + contacto + ['Demanda: zona', 'Demanda: clasificación del suelo', 'Demanda: calificación / tipología', 'Demanda: superficie (m²)', 'Demanda: unidades', 'Demanda: precio (€)']
bd = openpyxl.Workbook(); hoja0 = bd.active; hoja0.title = 'Léeme'
textos = ['BASE DE DATOS DE GESTIÓN DE SUELO', 'Plantilla del profesional · Módulo 1 · Solar. Hecha a partir de su esquema (originales/Esquema_BBDD_Gestion_de_Suelo.xlsx).',
          '', 'Cómo se usa:', '1. Da de alta a propietarios, clientes y captadores en sus hojas, con una referencia (P-001, C-001, K-001…).',
          '2. Cada solar va en una fila de «Solares»: zona, situación urbanística, superficies, edificabilidad, precio, propietario y captador.',
          '3. En «Ficha» escribe la referencia de un solar y tendrás su ficha lista para copiar en un email.',
          '4. Las listas desplegables están en «Listas»: puedes añadir valores (por ejemplo, las zonas de tu provincia).',
          '', 'Los valores de «Zona» del profesional eran: Casco urbano Castellón-Valencia-Alicante, Área metropolitana, Provincia y Costa.',
          'Los instrumentos de planeamiento cambian de nombre según la comunidad (el PAI es valenciano): usa «Otro» y anótalo en Observaciones.']
for i, t in enumerate(textos, 1): hoja0[f'A{i}'].value = t
hoja0['A1'].font = Font(bold=True, size=14); hoja0.column_dimensions['A'].width = 120
li = bd.create_sheet('Listas')
for j, (nombre, vals) in enumerate(LISTAS.items()):
    col = chr(65 + j); li[f'{col}1'].value = nombre; li[f'{col}1'].font = Font(bold=True); li.column_dimensions[col].width = 52
    for i, v in enumerate(vals, 2): li[f'{col}{i}'].value = v
COL_LISTA = {n: (chr(65 + j), len(v)) for j, (n, v) in enumerate(LISTAS.items())}
def tabla(nombre, cols, filas=200, ejemplo=None):
    w = bd.create_sheet(nombre)
    for j, c in enumerate(cols, 1):
        cell = w.cell(row=1, column=j, value=c); cell.font = Font(bold=True, color='FFFFFF'); cell.fill = PatternFill('solid', fgColor='1F4E78')
        cell.alignment = Alignment(wrap_text=True, vertical='top'); w.column_dimensions[cell.column_letter].width = 20 if len(c) < 22 else 28
        if c in LISTA_DE:
            col, n = COL_LISTA[LISTA_DE[c]]
            dv = DataValidation(type='list', formula1=f"Listas!${col}$2:${col}${n+1}", allow_blank=True); w.add_data_validation(dv)
            dv.add(f'{cell.column_letter}2:{cell.column_letter}{filas+1}')
    if ejemplo:
        for j, v in enumerate(ejemplo, 1): w.cell(row=2, column=j, value=v)
    w.freeze_panes = 'B2'
    return w
ej = ['S-001', 'Casco urbano', '(ejemplo) Calle …', '…', 'Urbano', 'Residencial - Plurifamiliar/Colectiva - Entre medianeras', 'ED - Estudio de Detalle',
      'No', 'No', 'No', 'No', 'No', '', 344, 344, 344, 344, 1586.49, '18 viviendas (2 son áticos)', 71392.05, 'Persona jurídica - IVA', 'Contado', '', 'P-001', 'K-001', 'Ejemplo: bórralo', '', '']
tabla('Solares', SOLARES, ejemplo=ej)
tabla('Propietarios', ['Referencia'] + contacto); tabla('Clientes', CLIENTES); tabla('Captadores', ['Referencia'] + contacto)
fi = bd.create_sheet('Ficha')
fi['A1'].value = 'FICHA DEL SOLAR'; fi['A1'].font = Font(bold=True, size=14)
fi['A3'].value = 'Referencia del solar:'; fi['B3'].value = 'S-001'; fi['B3'].fill = AMARILLO
for i, campo in enumerate(SOLARES[1:], 5):
    col = openpyxl.utils.get_column_letter(SOLARES.index(campo) + 1)
    fi[f'A{i}'].value = campo
    fi[f'B{i}'].value = f'=IFERROR(INDEX(Solares!${col}$2:${col}$201,MATCH($B$3,Solares!$A$2:$A$201,0))&"","")'
fi.column_dimensions['A'].width = 34; fi.column_dimensions['B'].width = 70
bd.save(os.path.join(PEQ, 'base_datos_suelo.xlsx'))

# ------------------------------------------------------------------ 3. Guía de análisis de viabilidad, sobre la plantilla corregida
esc = json.load(open(os.path.join(RAIZ, 'pruebas', 'escenario_guia_profesional.json')))
ENT = {e['hoja'] + '!' + e['celda']: e for e in json.load(open(os.path.join(RAIZ, 'plantilla', 'entradas.json')))['entradas']}
g = openpyxl.load_workbook(os.path.join(EXC, 'PLANTILLA_LIBRE_corregida.xlsx'))
n = 0
for hoja, cs in esc.items():
    for a, v in cs.items():
        if hoja + '!' + a not in ENT: continue          # D133 y D154 ya son parámetros legales
        if isinstance(v, str) and len(v) == 10 and v[2] == '/' and v[5] == '/':
            d_, m_, y_ = map(int, v.split('/')); v = datetime.datetime(y_, m_, d_)
        g[hoja][a].value = v; n += 1
dp = g['Datos de Partida']
NOTAS = {'D80': 'Persona física o jurídica (del vendedor).',
         'D96': 'Total 10 meses aprox. desde la escritura de compraventa del suelo: + 3 meses proyecto básico, licencias y tasa de licencia de obra; + 5 meses proyecto de ejecución; + 1 mes notaría y división horizontal; + 1 mes comienzo de obra y acta de replanteo.',
         'D97': 'Desde la escritura de compraventa del suelo.', 'D101': 'Meses de ejecución: de 1 a 24.', 'D114': 'Meses de ejecución: de 1 a 24.',
         'D121': 'Estimación de proyectos sin presupuestos, establecida por Planificaciones Urbanas.', 'D142': 'Por 5 socios durante 24 meses.',
         'D150': 'Fecha de la escritura del suelo.', 'D151': 'Fecha de inicio de la construcción, + 2 meses en libre o 3 en VPO.', 'D152': 'Al terminar la construcción.',
         'D158': 'Desde la escritura del suelo hasta el fin de la construcción.', 'C163': 'La captación de socios, hasta terminar la promoción.',
         'D184': 'Rellenar uno u otro (porcentaje o cuantía fija).', 'D186': 'Desde el mes 2 hasta el fin de la construcción, más julios y diciembres.',
         'D79': 'EJEMPLO DE LA GUÍA DEL PROFESIONAL. Son datos provisionales para un estudio previo. No se han tenido en cuenta los costes que puede suponer cuadrar el cash-flow.'}
for a, t in NOTAS.items(): dp[a].comment = Comment('Nota del profesional: ' + t, 'Nicolás Cabañas'); dp[a].comment.width = 320; dp[a].comment.height = 140
g.calculation = CalcProperties(iterate=True, iterateCount=100, iterateDelta=0.001, fullCalcOnLoad=True, calcMode='auto')
g.save(os.path.join(EXC, 'Guia_Analisis_de_Viabilidad_corregida.xlsx'))
print('números gordos: 12 cambios + 2 filas nuevas | base de datos: hojas', bd.sheetnames, '| guía: entradas escritas', n, 'notas', len(NOTAS))

# ------------------------------------------------------------------ índice de plantillas
idx_path = os.path.join(PEQ, 'indice.json'); idx = json.load(open(idx_path))
idx['nota'] = ('Plantillas incluidas en el curso: las 5 de CONSTRUYE y 2 del profesional. La plantilla financiera profesional completa '
               '(PLANTILLA_LIBRE corregida), su versión simplificada y la Guía de análisis de viabilidad son el extra de pago del final del curso.')
idx['plantillas_del_profesional'] = [
    dict(id='base_datos_suelo', titulo='Plantilla del profesional · Base de datos de gestión de suelo', modulo='Módulo 1 · Solar', leccion='1.1',
         excel='base_datos_suelo.xlsx', plantilla=None, entradas=None, uso='Descarga (Excel). Solares, propietarios, clientes y captadores, con listas y ficha del solar',
         original='originales/Esquema_BBDD_Gestion_de_Suelo.xlsx'),
    dict(id='numeros_gordos_solar', titulo='Plantilla del profesional · Números gordos del solar', modulo='Módulo 1 · Solar', leccion='1.1',
         excel='numeros_gordos_solar.xlsx', plantilla='numeros_gordos_solar.json', entradas='numeros_gordos_solar.entradas.json',
         uso='Calculadora y descarga. Filtro rápido: precio del solar por repercusión, coste, precio de venta y beneficio',
         original='originales/Plantilla_calculo_precio_solar.xlsx'),
]
idx['extra'] = [
    dict(titulo='Plantilla financiera profesional (corregida)', excel='../excel_corregido/PLANTILLA_LIBRE_corregida.xlsx', plantilla='../plantilla/plantilla_libre.json', entradas='../plantilla/entradas.json'),
    dict(titulo='Plantilla simplificada con el ejemplo real (corregida)', excel='../excel_corregido/Plantilla_simplificada_corregida.xlsx'),
    dict(titulo='Guía de análisis de viabilidad: ejemplo completo del profesional (22 viviendas)', modulo='Módulo 2 · Análisis de viabilidad',
         excel='../excel_corregido/Guia_Analisis_de_Viabilidad_corregida.xlsx', escenario='../pruebas/escenario_guia_profesional.json',
         original='originales/Plantilla_Guia_Analisis_de_Viabilidad.xlsx'),
]
json.dump(idx, open(idx_path, 'w'), ensure_ascii=False, indent=1)
print('índice actualizado')
