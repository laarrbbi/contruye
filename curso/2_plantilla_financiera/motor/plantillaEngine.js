// CONSTRUYE · Motor de la plantilla PLANTILLA_LIBRE
// Calcula la plantilla del profesional con sus fórmulas ORIGINALES, sin modificar ninguna.
// - Fórmulas: plantilla/plantilla_libre.json (exportadas celda a celda del .xls original).
// - Referencias circulares: se resuelven como Excel con cálculo iterativo (máx. 100 iteraciones, cambio máx. 0,001).
//   Las 64 celdas que rompen los ciclos están en plantilla_libre.json → cortes_de_ciclo. Su fórmula original se evalúa
//   en una hoja auxiliar y su valor se copia a la celda en cada iteración, igual que hace Excel al iterar.
// Requiere: hyperformula@2.7.1 (licencia GPLv3 o comercial: ver ESPECIFICACION_TECNICA.md).
'use strict';
const fs = require('fs');
const path = require('path');
const { HyperFormula, FunctionPlugin, FunctionArgumentType } = require('hyperformula');

// Calendario de Excel (sistema 1900): el día 1 es 01/01/1900, existe el 29/02/1900 ficticio (día 60)
// y el día 0 es el "0 de enero de 1900": YEAR(0)=1900, MONTH(0)=1, DAY(0)=0. La plantilla calcula fechas
// a partir de celdas de fecha vacías (día 0), así que hay que reproducir este comportamiento para dar lo mismo que Excel.
class FechasExcel extends FunctionPlugin {
  anio(ast, st) { return this.runFunction(ast.args, st, this.metadata('YEAR'), s => { const n = Math.floor(s); return n === 0 ? 1900 : this.dateTimeHelper.numberToSimpleDate(n).year; }); }
  mes(ast, st) { return this.runFunction(ast.args, st, this.metadata('MONTH'), s => { const n = Math.floor(s); return n === 0 ? 1 : this.dateTimeHelper.numberToSimpleDate(n).month; }); }
  dia(ast, st) { return this.runFunction(ast.args, st, this.metadata('DAY'), s => { const n = Math.floor(s); return n === 0 ? 0 : this.dateTimeHelper.numberToSimpleDate(n).day; }); }
}
FechasExcel.implementedFunctions = {
  YEAR: { method: 'anio', parameters: [{ argumentType: FunctionArgumentType.NUMBER, minValue: 0 }] },
  MONTH: { method: 'mes', parameters: [{ argumentType: FunctionArgumentType.NUMBER, minValue: 0 }] },
  DAY: { method: 'dia', parameters: [{ argumentType: FunctionArgumentType.NUMBER, minValue: 0 }] },
};
for (const fn of ['YEAR', 'MONTH', 'DAY']) HyperFormula.unregisterFunction(fn);
HyperFormula.registerFunctionPlugin(FechasExcel, { enGB: { YEAR: 'YEAR', MONTH: 'MONTH', DAY: 'DAY' } });
const OPCIONES_EXCEL = { licenseKey: 'gpl-v3', useArrayArithmetic: false, smartRounding: false, precisionRounding: 15,
  leapYear1900: true, nullDate: { year: 1899, month: 12, day: 31 } };

const ADDR = /^([A-Z]{1,3})(\d+)$/;
function decode(addr) {
  const m = ADDR.exec(addr); if (!m) throw new Error('Celda no válida: ' + addr);
  let col = 0; for (const ch of m[1]) col = col * 26 + (ch.charCodeAt(0) - 64);
  return { row: Number(m[2]) - 1, col: col - 1 };
}
function encode(row, col) {
  let s = ''; let c = col + 1; while (c > 0) { const r = (c - 1) % 26; s = String.fromCharCode(65 + r) + s; c = Math.floor((c - 1) / 26); }
  return s + (row + 1);
}
// Añade el nombre de la hoja a las referencias que no lo llevan (para evaluar la fórmula desde la hoja auxiliar).
function qualify(f, sheetName) {
  const parts = f.split(/('(?:[^']|'')*'![$]?[A-Z]{1,3}[$]?\d+(?::[$]?[A-Z]{1,3}[$]?\d+)?|"[^"]*")/);
  return parts.map((p, i) => (i % 2 === 1) ? p : p.replace(/(^|[^A-Za-z0-9_.!'$])([$]?[A-Z]{1,3}[$]?\d+(?::[$]?[A-Z]{1,3}[$]?\d+)?)(?![A-Za-z0-9_(])/g,
    (m, pre, ref) => `${pre}'${sheetName.replace(/'/g, "''")}'!${ref}`)).join('');
}
// dd/mm/aaaa o aaaa-mm-dd → número de serie de Excel (válido para fechas desde el 01/03/1900)
function fechaASerie(s) {
  let y, m, d;
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) [y, m, d] = s.split('-').map(Number);
  else if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(s)) [d, m, y] = s.split('/').map(Number);
  else throw new Error('Fecha no válida: ' + s);
  return Math.round((Date.UTC(y, m - 1, d) - Date.UTC(1899, 11, 30)) / 86400000);
}

class PlantillaEngine {
  constructor(plantilla, entradas) {
    this.tpl = plantilla;
    this.entradas = entradas ? Object.fromEntries(entradas.entradas.map(e => [`${e.hoja}!${e.celda}`, e])) : null;
    const sheets = {};
    for (const h of plantilla.hojas) {
      const rows = [];
      for (const [addr, c] of Object.entries(h.celdas)) {
        const { row, col } = decode(addr);
        while (rows.length <= row) rows.push([]);
        rows[row][col] = c.f !== undefined ? c.f : c.v;
      }
      sheets[h.nombre] = rows;
    }
    this.hf = HyperFormula.buildFromSheets(sheets, OPCIONES_EXCEL);
    this.ids = Object.fromEntries(this.hf.getSheetNames().map(n => [n, this.hf.getSheetId(n)]));
    // hoja auxiliar de iteración
    this.hf.addSheet('__iteracion__'); this.aux = this.hf.getSheetId('__iteracion__');
    this.cortes = plantilla.cortes_de_ciclo.map(c => ({ ...c, ...decode(c.cell), sheetId: this.ids[c.sheet] }));
    this.hf.suspendEvaluation();
    this.cortes.forEach((c, i) => {
      this.hf.setCellContents({ sheet: this.aux, row: i, col: 0 }, [[qualify(c.formula, c.sheet)]]);
      this.hf.setCellContents({ sheet: c.sheetId, row: c.row, col: c.col }, [[0]]);
    });
    this.hf.resumeEvaluation();
  }

  static desdeArchivos(dir = path.join(__dirname, '..', 'plantilla')) {
    const tpl = JSON.parse(fs.readFileSync(path.join(dir, 'plantilla_libre.json'), 'utf8'));
    const ent = JSON.parse(fs.readFileSync(path.join(dir, 'entradas.json'), 'utf8'));
    return new PlantillaEngine(tpl, ent);
  }

  // datos = { 'Datos de Partida': { C9: 24, D79: '01/01/2027', D80: 'SI', ... }, '3ª Proyectos': {...} }
  // Solo admite celdas del diccionario de entradas (no se puede sobrescribir ninguna fórmula del modelo salvo
  // las que el original trae como valor por defecto editable, marcadas con defecto_es_formula).
  setDatos(datos) {
    this.hf.suspendEvaluation();
    for (const [hoja, celdas] of Object.entries(datos)) {
      for (const [addr, valor] of Object.entries(celdas)) {
        const meta = this.entradas && this.entradas[`${hoja}!${addr}`];
        if (this.entradas && !meta) throw new Error(`La celda ${hoja}!${addr} no es una entrada de la plantilla`);
        let v = valor;
        if (meta && meta.tipo === 'fecha' && typeof v === 'string') v = fechaASerie(v);
        const { row, col } = decode(addr);
        this.hf.setCellContents({ sheet: this.ids[hoja], row, col }, [[v]]);
      }
    }
    this.hf.resumeEvaluation();
    return this;
  }

  calcular() {
    const { max_iteraciones = 100, cambio_maximo = 0.001 } = this.tpl.calculo || {};
    let it = 0, cambio = Infinity;
    while (it < max_iteraciones && cambio >= cambio_maximo) {
      const nuevos = this.cortes.map((c, i) => this.hf.getCellValue({ sheet: this.aux, row: i, col: 0 }));
      const viejos = this.cortes.map(c => this.hf.getCellValue({ sheet: c.sheetId, row: c.row, col: c.col }));
      cambio = 0;
      this.hf.suspendEvaluation();
      this.cortes.forEach((c, i) => {
        let nv = nuevos[i];
        if (nv !== null && typeof nv === 'object') nv = 0; // un error dentro del ciclo se trata como 0 durante la iteración
        const ov = viejos[i];
        cambio = Math.max(cambio, (typeof nv === 'number' && typeof ov === 'number') ? Math.abs(nv - ov) : (nv === ov ? 0 : 1));
        this.hf.setCellContents({ sheet: c.sheetId, row: c.row, col: c.col }, [[nv]]);
      });
      this.hf.resumeEvaluation();
      it++;
    }
    this.ultimoCalculo = { iteraciones: it, cambio_maximo: cambio, convergido: cambio < cambio_maximo };
    return this.ultimoCalculo;
  }

  // Valor de una celda como lo mostraría Excel: celda vacía referenciada = 0; errores como texto (#VALUE!, #DIV/0!...)
  valor(hoja, addr) {
    const { row, col } = decode(addr);
    const v = this.hf.getCellValue({ sheet: this.ids[hoja], row, col });
    if (v === null) { const f = this.hf.getCellFormula({ sheet: this.ids[hoja], row, col }); return f ? 0 : null; }
    if (typeof v === 'object') return '#' + (v.type === 'VALUE' ? 'VALUE!' : v.type === 'DIV_BY_ZERO' ? 'DIV/0!' : v.type);
    return v;
  }

  hoja(hoja) {
    const out = {}; const h = this.tpl.hojas.find(x => x.nombre === hoja);
    for (const addr of Object.keys(h.celdas)) out[addr] = this.valor(hoja, addr);
    return out;
  }

  // Vista simplificada = '3ª Coste Total Promoción' con la misma disposición que el archivo simplificado del profesional
  vistaSimplificada(vista) {
    return vista.celdas.map(c => ({ celda: c.simplificada, texto: c.texto, valor: c.tipo === 'numero' ? this.valor(vista.hoja_modelo, c.modelo) : undefined }));
  }

  resumenCoste() {
    const H = '3ª Coste Total Promoción';
    const fila = (lab, r) => ({ concepto: lab, neto: this.valor(H, 'C' + r), con_iva: this.valor(H, 'E' + r), iva: this.valor(H, 'G' + r), pct: this.valor(H, 'I' + r) });
    return [fila('Terreno', 6), fila('Construcción', 8), fila('Urbanización', 10), fila('Proyectos', 12), fila('Licencias', 14), fila('Varios', 16),
            fila('Gestión', 18), fila('Gastos préstamo hipotecario', 20), fila('Gastos financiación externa', 22), fila('TOTAL', 24),
            { concepto: 'Préstamo hipotecario', neto: this.valor(H, 'C28') }, { concepto: 'Coeficiente de paso', neto: this.valor(H, 'C50') }];
  }
}

module.exports = { PlantillaEngine, fechaASerie, decode, encode };
