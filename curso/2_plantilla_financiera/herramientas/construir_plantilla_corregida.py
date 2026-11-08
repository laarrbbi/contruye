# Construye la plantilla corregida a partir del export ORIGINAL (plantilla_libre_original.json) y deja el registro de cada cambio
# en correcciones.json. Es la receta exacta de la corrección: ejecutándolo se obtiene el mismo plantilla_libre.json.
# Uso: python3 build_v3.py <modo>   modo = "2002" (parámetros con los valores del original, para verificar)
#                                    modo = "final" (valores vigentes 2026)
import json, re, sys, os, collections
import re,collections
def split_args(s):
    # s = content inside parentheses; split top-level commas
    args=[];depth=0;cur='';q=False
    for ch in s:
        if ch=='"': q=not q
        if not q:
            if ch=='(': depth+=1
            elif ch==')': depth-=1
            elif ch==',' and depth==0: args.append(cur);cur='';continue
        cur+=ch
    args.append(cur);return args
def as_if(e):
    e=e.strip()
    while e.startswith('(') and matching(e)==len(e)-1: e=e[1:-1].strip()
    if e.startswith('IF(') and matching(e,2)==len(e)-1:
        a=split_args(e[3:-1])
        if len(a)==3: return a
    return None
def matching(e,start=0):
    depth=0;q=False
    for i in range(start,len(e)):
        ch=e[i]
        if ch=='"': q=not q
        if q: continue
        if ch=='(': depth+=1
        elif ch==')':
            depth-=1
            if depth==0: return i
    return -1
DATEC=re.compile(r'^\(?DATE\(.*\)\s*=\s*DATE\(.*\)\)?$')
def chain(f):
    e=f[1:] if f.startswith('=') else f
    pairs=[];cur=e
    while True:
        a=as_if(cur)
        if not a: break
        c,v,el=a
        if not DATEC.match(c.strip()): break
        pairs.append((c,v)); cur=el
    return pairs,cur.strip()

BASE = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'plantilla') + os.sep
MODO = sys.argv[1] if len(sys.argv) > 1 else 'final'
RED = len(sys.argv) > 2 and sys.argv[2] == 'sin_redondeo'   # pesetas sin redondear (solo para verificar)
t = json.load(open(BASE + 'plantilla_libre_original.json'))
H = {h['nombre']: h for h in t['hojas']}
log = []
PL = 'Parámetros legales'
Q = "'Parámetros legales'!"

def get(h, a):
    c = H[h]['celdas'].get(a)
    if c is None: return None
    return c['f'] if 'f' in c else c.get('v')

def setc(grupo, hoja, celda, nuevo, motivo, es_formula=None):
    cells = H[hoja]['celdas']
    c = cells.get(celda)
    antes = None if c is None else (c['f'] if 'f' in c else c.get('v'))
    if c is None: c = {}; cells[celda] = c
    if es_formula is None: es_formula = isinstance(nuevo, str) and nuevo.startswith('=')
    if es_formula: c.pop('v', None); c['f'] = nuevo
    else: c.pop('f', None); c['v'] = nuevo
    log.append(dict(grupo=grupo, hoja=hoja, celda=celda, antes=antes, despues=nuevo, motivo=motivo))

def re_row(a):
    return int(re.match(r'[A-Z]+(\d+)$', a).group(1))
def col2n(c):
    n = 0
    for ch in c: n = n * 26 + ord(ch) - 64
    return n
def n2col(n):
    s = ''
    while n: n, r = divmod(n - 1, 26); s = chr(65 + r) + s
    return s

# ---------------------------------------------------------------- 1. Errores de fórmula que cambian importes
VAR = '3ª Licencias y Varios'
setc('1 Importes', '3ª Coste Total Promoción', 'E16', f"=SUM('{VAR}'!G23,'{VAR}'!G33,'{VAR}'!G47:G55)",
     'Varios con IVA no sumaba las acometidas (G33). Ahora suma lo mismo que Varios neto (C16).')
setc('1 Importes', '3ª Coste Total Promoción', 'W45', '=SUM(W31:W44)',
     'El total con IVA del cuadro de la derecha empezaba en la fila 35 y dejaba fuera las acometidas (fila 33). Ahora suma igual que U45.')
setc('1 Importes', 'Estudio Económico', 'CQ45', '=SUM(CQ31:CQ44)',
     'Copia del mismo cuadro en Estudio Económico, con el mismo error que W45.')
setc('1 Importes', '3ª Terrenos', 'J31', "=IF('Datos de Partida'!E90=\"SI\",'Datos de Partida'!C90,0)",
     'El aval del 5º pago del suelo leía la fila 91 (títulos) en vez de la 90, como hacen J27:J30 con sus filas.')
setc('1 Importes', '3ª Gestión', 'D66', '=B66*D27',
     'El IVA de captación usaba el tipo de la fila de gestión (B67) en vez del suyo (B66). Hoy no cambia el importe porque los dos son iguales.')
setc('1 Importes', '6ª Cash-flow', 'BO104', '=SUM(BK104:BN104)',
     'Faltaba el total de los 4 años de la fila de acometidas.')
f = get('6ª Cash-flow', 'BO211')
assert 'BO94,BO112' in f
setc('1 Importes', '6ª Cash-flow', 'BO211', f.replace('BO94,BO112', 'BO94,BO104,BO112'),
     'El total de pagos de los 4 años no incluía las acometidas (fila 104). Ahora coincide con la suma de los totales de cada año.')

# Cadenas de fechas del cash-flow: si dos pagos caen en el mismo mes, el original solo contaba el primero
def chain_to_sum(f):
    pairs, tail = chain(f)
    if len(pairs) < 2: return None
    parts = [f'IF({c},{v},0)' for c, v in pairs]
    if tail != '0': parts.append(tail)
    return '=' + '+'.join(parts)
nchain = 0
for a, c in list(H['6ª Cash-flow']['celdas'].items()):
    f = c.get('f')
    if not f or 'DATE(' not in f: continue
    g = chain_to_sum(f)
    if g:
        setc('1 Importes · cash-flow', '6ª Cash-flow', a, g,
             'Pagos o cobros con fecha: si dos caían en el mismo mes, solo se contaba el primero. Ahora se suman todos los del mes.')
        nchain += 1


# Liquidación del IVA en el cash-flow: el original la tenía enlazada a mano (V34, AC209, AI209, BG209) para el calendario del ejemplo
CF = '6ª Cash-flow'
MCOLS = [n2col(i) for blk in [('C','N'),('R','AC'),('AG','AR'),('AV','BG')] for i in range(col2n(blk[0]), col2n(blk[1]) + 1)]
assert len(MCOLS) == 48 and all(get(CF, X + '249') for X in MCOLS) and all(get(CF, X + '232') for X in MCOLS)
assert not [a for a in H[CF]['celdas'] if re_row(a) in range(259, 264)]
MI = 'Liquidación automática del IVA: el original la tenía enlazada a mano a unas pocas celdas (V34, AC209, AI209, BG209) que solo cuadraban con el calendario del ejemplo. Ahora se liquida cada trimestre (o cada mes si C256 = SI): el saldo positivo se paga el mes siguiente; el negativo se compensa y lo que quede en diciembre se pide en devolución y se cobra 6 meses después. Lo pendiente se liquida en el último mes del cash-flow.'
setc('1 Importes · IVA cash-flow', CF, 'B259', 'LIQUIDACIÓN DEL IVA (automática)', MI, es_formula=False)
setc('1 Importes · IVA cash-flow', CF, 'B260', 'IVA pendiente de compensar', MI, es_formula=False)
setc('1 Importes · IVA cash-flow', CF, 'B261', 'IVA a ingresar (se paga el mes siguiente)', MI, es_formula=False)
setc('1 Importes · IVA cash-flow', CF, 'B262', 'IVA a devolver (se cobra 6 meses después)', MI, es_formula=False)
for i, X in enumerate(MCOLS):
    Pv = MCOLS[i - 1] if i else None; last = i == 47
    S = f'({Pv}260+{X}249)' if Pv else f'({X}249)'
    settle = 'TRUE()' if last else f'OR($C$256="SI",MOD(MONTH({X}232),3)=0)'
    yend = 'TRUE()' if last else f'MONTH({X}232)=12'
    setc('1 Importes · IVA cash-flow', CF, X + '260', f'=IF({settle},IF({S}>=0,0,IF({yend},0,{S})),{S})', MI)
    setc('1 Importes · IVA cash-flow', CF, X + '261', f'=IF({settle},MAX(0,{S}),0)', MI)
    setc('1 Importes · IVA cash-flow', CF, X + '262', f'=IF(AND({settle},{yend},{S}<0),-{S},0)', MI)
    pago = f'={Pv}261' if Pv else '=0'
    if last: pago = f'={Pv}261+{X}261'
    setc('1 Importes · IVA cash-flow', CF, X + '209', pago, MI)
    if last: dev = '=' + '+'.join(MCOLS[j] + '262' for j in range(41, 48))
    else: dev = f'={MCOLS[i-6]}262' if i >= 6 else '=0'
    setc('1 Importes · IVA cash-flow', CF, X + '34', dev, MI)

# Cash-flow del suelo: ITP y AJD comprobaban si el tipo era exactamente 8 % o 1 % (los de 2002); con otros tipos salían 0
for X in MCOLS:
    f87, f88 = get(CF, X + '87'), get(CF, X + '88')
    assert "C$34=(8/100)),((7/100)*" in f87 and "C$34=(1/100)),((1/100)*" in f88, X
    cond = f"DATE(YEAR({X}79),MONTH({X}79),DAY({X}79))=DATE(YEAR('3ª Terrenos'!$G$34),MONTH('3ª Terrenos'!$G$34),DAY({X}79))"
    MS = 'El ITP y el AJD del suelo en el cash-flow solo salían si el tipo era exactamente el 8 % o el 1 % de 2002 (y el 8 % se partía en 7 % + 1 %). Ahora pagan lo que calcula 3ª Terrenos (D34): ITP si la compra no lleva IVA, AJD si la lleva.'
    setc('1 Importes · cash-flow', CF, X + '87', f"=IF({cond},IF('Datos de Partida'!$D$80=\"SI\",0,'3ª Terrenos'!$D$34),0)", MS)
    setc('1 Importes · cash-flow', CF, X + '88', f"=IF({cond},IF('Datos de Partida'!$D$80=\"SI\",'3ª Terrenos'!$D$34,0),0)", MS)

# ---------------------------------------------------------------- 2. Errores visibles (#VALUE!, #DIV/0!)
for r in range(151, 170):
    setc('2 Errores visibles', '2º Auxiliar', f'B{r}', f'=B{r-59}',
         'Etiqueta de modelo: sumaba 1 a un texto («MOD 24» + 1) y daba #VALUE!. Ahora sigue el mismo patrón que las filas 127-150 (= etiqueta del bloque de garajes).')
P = '3ª Proyectos'
divs = {'AJ32': "=IF(AI24=0,0,AI32/AI24)", 'O50': "=IF($Q$50=0,0,N50/$Q$50)", 'R50': "=IF($P$46=0,0,Q50/$P$46)",
        'Z50': "=IF($AB$50=0,0,Y50/$AB$50)", 'AC50': "=IF($AA$46=0,0,AB50/$AA$46)",
        'O51': "=IF($Q$50=0,0,N51/$Q$50)", 'R51': "=IF($P$46=0,0,Q51/$P$46)",
        'Z51': "=IF($AB$50=0,0,Y51/$AB$50)", 'AC51': "=IF($AA$46=0,0,AB51/$AA$46)",
        'O52': "=IF(N46=0,0,N52/N46)", 'Z52': "=IF(Y46=0,0,Y52/Y46)", 'AH61': "=IF(AG61=0,0,AI61/AG61)"}
for a, nf in divs.items():
    old = get(P, a); core = old[1:]
    assert core.replace('$', '') in nf.replace('$', ''), (a, old, nf)
    setc('2 Errores visibles', P, a, nf, 'División entre cero sin datos (#DIV/0!). Si el divisor es 0 da 0; con datos, el mismo resultado.')

# ---------------------------------------------------------------- 3. Coeficientes mostrados en el resumen (no cambian importes)
A2 = '2º Auxiliar'
setc('3 Resumen', A2, 'I20', '=IF(G63<=0,0,E63/G63)',
     'Coeficiente construida/útil de viviendas: usaba solo el primer modelo (fila 20). Ahora usa el total del bloque, como el resto de bloques.')
setc('3 Resumen', A2, 'I68', '=IF(G119<=0,0,E119/G119)',
     'Coeficiente de garajes: usaba solo los garajes libres (fila 117). Ahora usa el total de garajes (fila 119), como el resto de bloques.')
setc('3 Resumen', '8º Resumen', 'F22', "='2º Auxiliar'!I68",
     'La fila de garajes mostraba el coeficiente de viviendas (I20). Ahora muestra el de garajes.')
setc('3 Resumen', '8º Resumen', 'F30', "='2º Auxiliar'!I225",
     'La fila de elementos no vinculados mostraba el coeficiente de locales (I232). Ahora muestra el suyo.')

# ---------------------------------------------------------------- 4. Controles «O.K.» robustos
def split_cmp(e):
    depth = 0; q = False
    for i, ch in enumerate(e):
        if ch == '"': q = not q
        if q: continue
        if ch == '(': depth += 1
        elif ch == ')': depth -= 1
        elif ch == '=' and depth == 0 and e[i-1] not in '<>' and (i + 1 < len(e) and e[i+1] != '>'):
            return e[:i], e[i+1:]
    return None
def strip_par(e):
    e = e.strip()
    while e.startswith('(') and matching(e) == len(e) - 1: e = e[1:-1].strip()
    return e
def unint(e):
    e = strip_par(e)
    if e.startswith('INT(') and matching(e, 3) == len(e) - 1: return strip_par(e[4:-1]), True
    return e, False
def robust(cmp):
    s = split_cmp(strip_par(cmp))
    if not s: return None
    (l, il), (r, ir) = unint(s[0]), unint(s[1])
    tol = '1' if (il and ir) else '0.01'
    return f'ABS(({l})-({r}))<{tol}'
nctl = 0
for h in t['hojas']:
    for a, c in list(h['celdas'].items()):
        f = c.get('f') or ''
        if '"O.K."' not in f and '"o.k."' not in f: continue
        body = f[1:]
        assert body.startswith('IF(') and matching(body, 2) == len(body) - 1, (h['nombre'], a, f)
        args = split_args(body[3:-1])
        cond = strip_par(args[0])
        if cond.startswith('AND(') and matching(cond, 3) == len(cond) - 1:
            sub = [robust(x) for x in split_args(cond[4:-1])]
            assert all(sub), (a, f)
            newcond = 'AND(' + ','.join(sub) + ')'
        else:
            newcond = robust(cond); assert newcond, (a, f)
        nf = '=IF(' + ','.join([newcond] + args[1:]) + ')'
        setc('4 Controles', h['nombre'], a, nf,
             'Control «O.K.»: comparaba importes con igualdad exacta (o con INT) y fallaba por decimales de redondeo. Ahora admite una diferencia menor de 1 céntimo (o de 1 € donde el original usaba INT).')
        nctl += 1

# ---------------------------------------------------------------- 5. Etiquetas y un dato que estaba fijo
DP = 'Datos de Partida'
setc('5 Etiquetas y datos', DP, 'E7', 'COSTE', 'La columna decía «PVP CONSTRUCCIÓN», pero el modelo la usa como coste de construcción por m² (2º Auxiliar E20 × B6). Ahora dice «COSTE CONSTRUCCIÓN».', es_formula=False)
setc('5 Etiquetas y datos', DP, 'C115', 'CERTIFICACIÓN CONTROL CALIDAD URBANIZACIÓN', 'Decía «…OBRA», pero solo se usa en la urbanización (3ª Urbanización E47).', es_formula=False)
setc('5 Etiquetas y datos', DP, 'C116', 'CERTIFICACIÓN SEGURIDAD URBANIZACIÓN', 'Errata («URBANIZACCIÓN»).', es_formula=False)
setc('5 Etiquetas y datos', DP, 'C102', 'CONTROL DE CALIDAD OBRA (% s/ construcción)', 'Nueva casilla de dato: el control de calidad de la obra estaba fijo al 1,5 % dentro de una fórmula.', es_formula=False)
setc('5 Etiquetas y datos', DP, 'D102', 0.015, 'Nuevo dato editable, con el mismo 1,5 % del original por defecto.', es_formula=False)
H[DP]['celdas']['D102']['nf'] = '0.00%'
assert get(P, 'D53') == "=0.015*'3ª Construcción'!D5"
setc('5 Etiquetas y datos', P, 'D53', "='Datos de Partida'!D102*'3ª Construcción'!D5", 'Control de calidad de la obra: ahora lee el dato de Datos de Partida D102 en vez de un 1,5 % fijo.')

# ---------------------------------------------------------------- 6. Parámetros legales
# Valores: (código, etiqueta, valor_2026, valor_2002, base legal, fuente/nota, dónde se usa)
PARAMS = [
 ('IVA_GENERAL', 'IVA, tipo general', 0.21, 0.16, 'Ley 37/1992 del IVA, art. 90', 'AEAT', 'Suelo con IVA, honorarios, notaría, registro, tasaciones, urbanización, control de calidad, gestión, varios, cash-flow'),
 ('IVA_REDUCIDO', 'IVA, tipo reducido (vivienda y obra de vivienda)', 0.10, 0.07, 'Ley 37/1992 del IVA, art. 91.Uno', 'AEAT', 'Certificaciones de obra, cantidades a cuenta, ingresos de la cooperativa, cash-flow'),
 ('IPS', 'Impuesto sobre las Primas de Seguros', 0.08, 0.06, 'Ley 13/1996, art. 12 (8 % desde la Ley 3/2017)', 'Hacienda', 'Seguro decenal (3ª Construcción)'),
 ('CLEA', 'Recargo del Consorcio para la liquidación de entidades aseguradoras (CLEA)', 0.0015, 0.005, 'Estatuto legal del Consorcio de Compensación de Seguros', 'Consorcio de Compensación de Seguros', 'Seguro decenal y seguro de cantidades a cuenta'),
 ('INTERES_LEGAL', 'Interés legal del dinero (devolución de cantidades a cuenta)', 0.0325, 0.06, 'Ley 38/1999 (LOE), disp. adicional 1.ª; Ley de Presupuestos (prorrogada en 2026)', 'BOE / OCU', 'Seguro de cantidades a cuenta (3ª Licencias y Varios K11)'),
 ('AJD_PRESTAMO', 'AJD del préstamo hipotecario a cargo del promotor', 0.0, 0.0, 'RDL 17/2018: el sujeto pasivo es el prestamista (el banco)', 'BOE', 'Préstamo hipotecario (Datos de Partida D154)'),
]
# Comunidades: (nombre, ITP, AJD, nota) — Anexo B del curso (consultado en octubre de 2026)
CCAA = [
 ('Andalucía', 0.07, 0.012, ''),
 ('Aragón', 0.08, 0.015, 'ITP progresivo 8–10 %: se usa el 8 %; ajústalo al valor del solar'),
 ('Principado de Asturias', 0.08, 0.012, 'ITP progresivo 8–10 %: se usa el 8 %'),
 ('Illes Balears', 0.08, 0.015, 'ITP progresivo 8–13 %: se usa el 8 %'),
 ('Canarias', 0.065, 0.01, 'AJD 1 % en operaciones con IGIC (0,75 % el resto). En Canarias se aplica IGIC, no IVA: esta plantilla calcula con IVA'),
 ('Cantabria', 0.09, 0.015, ''),
 ('Castilla-La Mancha', 0.09, 0.015, ''),
 ('Castilla y León', 0.08, 0.015, 'ITP progresivo 8–10 %: se usa el 8 %'),
 ('Cataluña', 0.10, 0.015, 'ITP 10 % hasta 600.000 €; 11 % hasta 900.000 €; 12 % hasta 1,5 M€; 13 % más'),
 ('Comunitat Valenciana', 0.09, 0.014, 'ITP 11 % por encima de 1 M€ (Ley 5/2025)'),
 ('Extremadura', 0.08, 0.015, 'ITP progresivo 8–11 %: se usa el 8 %'),
 ('Galicia', 0.08, 0.015, ''),
 ('Comunidad de Madrid', 0.06, 0.0075, ''),
 ('Región de Murcia', 0.0775, 0.015, ''),
 ('Comunidad Foral de Navarra', 0.06, 0.005, 'Régimen foral'),
 ('País Vasco', 0.04, 0.005, 'Régimen foral; hasta 7 % en bases altas según territorio'),
 ('La Rioja', 0.07, 0.01, ''),
 ('Ciudad Autónoma de Ceuta', 0.03, 0.0025, 'Tipo estatal con bonificación del 50 %: confírmalo. Se aplica IPSI, no IVA'),
 ('Ciudad Autónoma de Melilla', 0.03, 0.0025, 'Tipo estatal con bonificación del 50 %: confírmalo. Se aplica IPSI, no IVA'),
]
CCAA_DEFECTO = 'Comunitat Valenciana'
cells = {}
def put(a, v, nf=None):
    c = {'f': v} if isinstance(v, str) and v.startswith('=') else {'v': v}
    if nf: c['nf'] = nf
    cells[a] = c
put('A1', 'PARÁMETROS LEGALES Y FISCALES')
put('A2', 'Los actualiza el administrador de CONSTRUYE y se aplican a todas las plantillas. No los cambies aquí salvo que sepas que la ley ha cambiado.')
put('A3', 'Revisados el:'); put('B3', 46334, 'dd/mm/yyyy')   # 08/11/2026
for i, h in enumerate(['Código', 'Parámetro', 'Valor vigente', 'Valor en el original (2002)', 'Base legal', 'Dónde se usa']):
    put(n2col(i + 1) + '4', h)
PROW = {}
for i, (cod, lab, v26, v02, base, fuente, uso) in enumerate(PARAMS):
    r = 5 + i; PROW[cod] = r
    put(f'A{r}', cod); put(f'B{r}', lab); put(f'C{r}', v26 if MODO == 'final' else v02, '0.00%')
    put(f'D{r}', v02, '0.00%'); put(f'E{r}', base); put(f'F{r}', uso)
put('A13', 'IMPUESTOS AUTONÓMICOS DE LA COMPRA DEL SUELO Y DE LA OBRA NUEVA')
for i, h in enumerate(['Comunidad autónoma', 'ITP (compra a particular)', 'AJD (escrituras con IVA, obra nueva y división horizontal)', 'Nota']):
    put(n2col(i + 1) + '14', h)
for i, (n, itp, ajd, nota) in enumerate(CCAA):
    r = 15 + i
    put(f'A{r}', n)
    put(f'B{r}', itp if MODO == 'final' else 0.08, '0.00%')
    put(f'C{r}', ajd if MODO == 'final' else 0.01, '0.00%')
    if nota: put(f'D{r}', nota)
put('A35', 'Comunidad de esta promoción'); put('B35', "='Datos de Partida'!D93")
put('A36', 'ITP aplicado'); put('B36', '=VLOOKUP(B35,$A$15:$C$33,2,0)', '0.00%')
put('A37', 'AJD aplicado'); put('B37', '=VLOOKUP(B35,$A$15:$C$33,3,0)', '0.00%')
t['hojas'].append({'nombre': PL, 'dimension': 'A1:F37', 'celdas': cells}); H[PL] = t['hojas'][-1]
log.append(dict(grupo='6 Parámetros legales', hoja=PL, celda='A1:F37', antes=None, despues='hoja nueva',
                motivo='Hoja nueva con los impuestos y tipos que fija la ley. El administrador los cambia y se aplican a todas las plantillas.'))
def pref(cod): return f"{Q}$C${PROW[cod]}"
ITP, AJD = f"{Q}$B$36", f"{Q}$B$37"

setc('5 Etiquetas y datos', DP, 'C93', 'COMUNIDAD AUTÓNOMA DEL SOLAR (ITP / AJD)', 'Nueva casilla: los tipos de ITP y AJD dependen de la comunidad.', es_formula=False)
setc('5 Etiquetas y datos', DP, 'D93', CCAA_DEFECTO, 'Nuevo dato: comunidad autónoma de la promoción (lista de la hoja Parámetros legales).', es_formula=False)

def param_cell(hoja, celda, cod, motivo, esperado):
    old = get(hoja, celda); assert abs(old - esperado) < 1e-12, (hoja, celda, old)
    setc('6 Parámetros legales', hoja, celda, '=' + pref(cod), motivo)
G = 'Tipo fijo de 2002 (16 %) → IVA general de Parámetros legales.'
Rd = 'Tipo fijo de 2002 (7 %) → IVA reducido de Parámetros legales.'
for hoja, celda in [('3ª Terrenos', 'C56'), ('3ª Construcción', 'W68'), ('3ª Construcción', 'C73'), ('3ª Construcción', 'C74'),
                    ('3ª Urbanización', 'E11'), ('3ª Proyectos', 'E3'), (VAR, 'C66'), (VAR, 'C67'), (VAR, 'C69'), (VAR, 'C70'), (VAR, 'C71'),
                    ('3ª Préstamo hipotecario', 'B72'), ('3ª Préstamo hipotecario', 'B73'), ('3ª Préstamo hipotecario', 'B76'), ('3ª Préstamo hipotecario', 'B77'),
                    ('3ª Gestión', 'B66'), ('3ª Gestión', 'B67'), ('5º Forma de Pago', 'AX59'), ('6ª Cash-flow', 'D37'), ('6ª Cash-flow', 'C213')]:
    param_cell(hoja, celda, 'IVA_GENERAL', G, 0.16)
for hoja, celda in [('3ª Construcción', 'D11'), ('3ª Préstamo hipotecario', 'B69'), ('5º Forma de Pago', 'AX58'), ('6ª Cash-flow', 'C37'), ('6ª Cash-flow', 'D213')]:
    param_cell(hoja, celda, 'IVA_REDUCIDO', Rd, 0.07)
param_cell('3ª Construcción', 'AF70', 'IPS', 'IPS fijo de 2002 (6 %) → Parámetros legales (8 % vigente).', 0.06)
param_cell('3ª Construcción', 'AF67', 'CLEA', 'Recargo del Consorcio fijo (0,5 %) → Parámetros legales.', 0.005)
param_cell(VAR, 'K11', 'INTERES_LEGAL', 'Interés garantizado fijo de 2002 (6 %) → interés legal del dinero de Parámetros legales (LOE, disp. adicional 1.ª).', 0.06)
setc('6 Parámetros legales', '3ª Construcción', 'AF69', 'Es el tipo de la hoja Parámetros legales sobre la prima o cuota del seguro', 'El texto decía «Es el 6 %…», el tipo de 2002.', es_formula=False)
# Literales dentro de fórmulas
def subst(hoja, celda, pares, motivo):
    f = get(hoja, celda); g = f
    for a, b in pares:
        assert a in g, (hoja, celda, a, f); g = g.replace(a, b)
    setc('6 Parámetros legales', hoja, celda, g, motivo)
subst('3ª Terrenos', 'D12', [('0.16', pref('IVA_GENERAL'))], 'IVA del suelo fijo al 16 % → IVA general de Parámetros legales.')
subst('3ª Terrenos', 'C34', [('"SI",0.01,0.08)', f'"SI",{AJD},{ITP})')], 'AJD 1 % e ITP 8 % fijos → AJD e ITP de la comunidad elegida (Parámetros legales).')
subst(P, 'E53', [('(1+0.16)', f"(1+{pref('IVA_GENERAL')})")], 'IVA del control de calidad fijo al 16 % → IVA general.')
subst(VAR, 'L6', [('*1.07', f"*(1+{pref('IVA_REDUCIDO')})")], 'Cantidades a cuenta con IVA fijo del 7 % → IVA reducido.')
for r in range(15, 52):
    f = get(VAR, f'K{r}')
    if not f or '1.07' not in f: continue
    subst(VAR, f'K{r}', [('*1.07', f"*(1+{pref('IVA_REDUCIDO')})"), ('(1+0.005)', f"(1+{pref('CLEA')})"), ('(1+0.16)', f"(1+{pref('IVA_GENERAL')})")],
          'Seguro de cantidades a cuenta: IVA 7 %, recargo 0,5 % e IVA 16 % fijos → Parámetros legales.')
assert get(DP, 'D133') == 0.01 and get(DP, 'D154') == 0
setc('6 Parámetros legales', DP, 'D133', '=' + AJD, 'AJD de obra nueva y división horizontal: era un 1 % escrito a mano → AJD de la comunidad elegida.')
setc('6 Parámetros legales', DP, 'D154', '=' + pref('AJD_PRESTAMO'), 'AJD de la garantía hipotecaria → Parámetros legales (desde 2018 lo paga el banco: 0 %).')

# ---------------------------------------------------------------- 7. Pesetas → euros
PTS = re.compile(r'(?<![\w.$/^])(\d+(?:\.\d+)?)\s*/\s*166\.386')
def eur(m):
    v = float(m.group(1)) / 166.386
    if RED: return repr(v)
    s = f'{round(v, 2):.2f}'.rstrip('0').rstrip('.')
    return s
npts = 0
for h in t['hojas']:
    for a, c in list(h['celdas'].items()):
        f = c.get('f')
        if not f or '166.386' not in f: continue
        g = PTS.sub(eur, f)
        if g != f:
            setc('7 Pesetas a euros', h['nombre'], a, g, 'Importe escrito en pesetas (N/166,386) → el mismo importe en euros, redondeado al céntimo como las tarifas oficiales en euros.')
            npts += 1
# Cuántas quedan con 166.386 (conversiones euro→peseta para mostrar, no importes)
resto = [(h['nombre'], a) for h in t['hojas'] for a, c in h['celdas'].items() if '166.386' in (c.get('f') or '')]

# ---------------------------------------------------------------- metadatos y salida
t['nombre'] = 'PLANTILLA_LIBRE (corregida v3)'
t['calculo'] = {**t.get('calculo', {}), 'iterativo': True}
cnt = collections.Counter(x['grupo'] for x in log)
print('MODO', MODO, 'cambios', len(log), dict(cnt), 'cadenas', nchain, 'controles', nctl, 'pesetas', npts, 'quedan ×166,386 (solo muestran pesetas):', len(resto))
out = BASE + ('plantilla_libre.json' if MODO == 'final' and not RED else f'_verif_{MODO}{"_sr" if RED else ""}.json')
json.dump(t, open(out, 'w'), ensure_ascii=False, separators=(',', ':'))
def compactar(log):
    out = []; grupos = collections.OrderedDict()
    for x in log:
        if x['grupo'].startswith('1 Importes · '):
            k = (x['grupo'], x['hoja'], re_row(x['celda']))
            if k not in grupos: grupos[k] = dict(grupo=x['grupo'], hoja=x['hoja'], fila=k[2], celdas=[], motivo=x['motivo'], ejemplo=dict(celda=x['celda'], antes=x['antes'], despues=x['despues'])); out.append(grupos[k])
            grupos[k]['celdas'].append(x['celda'])
        else: out.append(x)
    for g in grupos.values(): g['n'] = len(g['celdas']); g['celdas'] = f"{g['celdas'][0]}…{g['celdas'][-1]}" if len(g['celdas']) > 2 else ','.join(g['celdas'])
    return out
if MODO == 'final' and not RED:
    json.dump({'version': 3, 'base': 'plantilla_libre_original.json (export exacto de PLANTILLA_LIBRE.xls)', 'resumen': dict(cnt),
               'parametros_legales': [dict(codigo=p[0], etiqueta=p[1], celda=f'{PL}!C{PROW[p[0]]}', valor=p[2], valor_2002=p[3], base_legal=p[4], fuente=p[5], uso=p[6]) for p in PARAMS],
               'comunidades': [dict(nombre=c[0], itp=c[1], ajd=c[2], nota=c[3], celdas=f'{PL}!B{15+i}:C{15+i}') for i, c in enumerate(CCAA)],
               'cambios': compactar(log)}, open(BASE + 'correcciones.json', 'w'), ensure_ascii=False, indent=1)
print('escrito', out)
