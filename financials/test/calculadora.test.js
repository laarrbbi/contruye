// La calculadora reutiliza un mismo motor para muchos cálculos. Estas pruebas
// comprueban que eso no cambia ningún resultado, que los parámetros legales
// que manda la plataforma se aplican (y no se quedan para el cálculo
// siguiente) y que el servicio HTTP devuelve lo mismo que el motor.
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { Calculadora, PlantillasPequenas, PAQUETE, PARAMETROS_DEL_PAQUETE } = require('../calculadora');
const { crearServidor } = require('../server');

const escenario = (nombre) => JSON.parse(fs.readFileSync(path.join(PAQUETE, 'pruebas', nombre), 'utf8'));
const veinticuatro = escenario('escenario_24_viviendas.json');
const profesional = escenario('escenario_ejemplo_profesional.json');

const calculadora = new Calculadora();
const pequenas = new PlantillasPequenas();
const totalConIva = (r) => r.resumen.find((f) => f.concepto === 'TOTAL').con_iva;

function todasLasCeldas(calc) {
  const valores = {};
  for (const h of calc.plantilla.hojas) {
    for (const addr of Object.keys(h.celdas)) valores[`${h.nombre}!${addr}`] = calc.motor.valor(h.nombre, addr);
  }
  return valores;
}

test('el ejemplo del paquete (24 viviendas en la Comunitat Valenciana) da 6.918.553,32 € con IVA', () => {
  const r = calculadora.calcular(veinticuatro, [], PARAMETROS_DEL_PAQUETE);
  assert.ok(r.calculo.convergido);
  assert.equal(Math.round(totalConIva(r) * 100) / 100, 6918553.32);
  assert.equal(r.parametros.IVA_GENERAL, 0.21);
});

test('los parámetros legales que manda la plataforma cambian el resultado, y solo el de ese cálculo', () => {
  // Con el IVA general al 22 %, el total sube como en la prueba del paquete.
  const r = calculadora.calcular(veinticuatro, [], { IVA_GENERAL: 0.22 });
  assert.equal(Math.round(totalConIva(r) * 100) / 100, 6943595.13);
  assert.equal(r.parametros.IVA_GENERAL, 0.22);
  // El siguiente cálculo, sin parámetros, vuelve a los del paquete.
  const despues = calculadora.calcular(veinticuatro);
  assert.equal(Math.round(totalConIva(despues) * 100) / 100, 6918553.32);
  // La comunidad del solar elige el ITP y el AJD: otro AJD, otro total.
  const otroAjd = calculadora.calcular(veinticuatro, [], { comunidades: { 'Comunitat Valenciana': { itp: 0.1, ajd: 0.02 } } });
  assert.notEqual(Math.round(totalConIva(otroAjd) * 100) / 100, 6918553.32);
  // Un valor mal escrito (21 en vez de 0,21) se rechaza.
  assert.throws(() => calculadora.calcular(veinticuatro, [], { IVA_GENERAL: 21 }), /tanto por uno/);
});

test('un cálculo no hereda nada del anterior: celda a celda igual que un motor nuevo', () => {
  // Motor recién construido, un solo cálculo.
  const nueva = new Calculadora();
  nueva.calcular(veinticuatro);
  const esperado = todasLasCeldas(nueva);

  // El motor compartido, después de otro escenario distinto y otros parámetros.
  calculadora.calcular(profesional, [], { IVA_REDUCIDO: 0.12, comunidades: { 'Cataluña': { itp: 0.11, ajd: 0.02 } } });
  calculadora.calcular(veinticuatro);
  const obtenido = todasLasCeldas(calculadora);

  const distintas = Object.keys(esperado).filter((k) => {
    const a = esperado[k]; const b = obtenido[k];
    return typeof a === 'number' && typeof b === 'number' ? Math.abs(a - b) > 1e-9 * Math.max(1, Math.abs(a)) : a !== b;
  });
  assert.equal(distintas.length, 0, `Celdas distintas: ${distintas.slice(0, 5).join(', ')}`);
});

test('sin datos vuelve a la plantilla tal como viene', () => {
  const nueva = new Calculadora();
  nueva.motor.calcular();
  const vacia = todasLasCeldas(nueva);

  calculadora.calcular(veinticuatro);
  calculadora.calcular({});
  const despues = todasLasCeldas(calculadora);

  for (const k of Object.keys(vacia)) {
    const a = vacia[k]; const b = despues[k];
    if (typeof a === 'number') assert.ok(Math.abs(a - b) <= 1e-9 * Math.max(1, Math.abs(a)), k);
    else assert.equal(b, a, k);
  }
});

test('el servicio HTTP calcula, rechaza celdas que no son de entrada y devuelve hojas completas', async () => {
  const servidor = crearServidor(calculadora, pequenas).listen(0, '127.0.0.1');
  await new Promise((r) => servidor.once('listening', r));
  const base = `http://127.0.0.1:${servidor.address().port}`;
  try {
    const salud = await fetch(`${base}/salud`);
    assert.equal(salud.status, 200);

    const r = await fetch(`${base}/calcular`, { method: 'POST', body: JSON.stringify({ datos: veinticuatro, hojas: ['8º Resumen'] }) });
    assert.equal(r.status, 200);
    const cuerpo = await r.json();
    assert.ok(cuerpo.calculo.convergido);
    const total = cuerpo.resumen.find((f) => f.concepto === 'TOTAL');
    assert.ok(Math.abs(total.neto - 6097401.00) < 0.01, `Total ${total.neto}`); // the package's own figure (pruebas/validar.js)
    assert.ok(cuerpo.vista.length > 400);
    assert.ok(Object.keys(cuerpo.hojas['8º Resumen'].celdas).length > 800);

    // Una fórmula del profesional no se puede sobrescribir.
    const formula = await fetch(`${base}/calcular`, { method: 'POST', body: JSON.stringify({ datos: { '3ª Coste Total Promoción': { C24: 1 } } }) });
    assert.equal(formula.status, 422);

    const hoja = await fetch(`${base}/calcular`, { method: 'POST', body: JSON.stringify({ datos: {}, hojas: ['No existe'] }) });
    assert.equal(hoja.status, 422);

    const roto = await fetch(`${base}/calcular`, { method: 'POST', body: '{no es json' });
    assert.equal(roto.status, 400);

    const mal = await fetch(`${base}/calcular`, { method: 'POST', body: JSON.stringify({ datos: {}, parametros: { IVA_GENERAL: 21 } }) });
    assert.equal(mal.status, 422);

    // Una plantilla pequeña: la del solar, con su ejemplo.
    const solar = await fetch(`${base}/pequenas/1/calcular`, { method: 'POST', body: JSON.stringify({ datos: {} }) });
    assert.equal(solar.status, 200);
    assert.equal((await solar.json()).hoja.celdas.B17.v, 512500);
    const noHay = await fetch(`${base}/pequenas/9/calcular`, { method: 'POST', body: JSON.stringify({ datos: {} }) });
    assert.equal(noHay.status, 422);
  } finally {
    servidor.close();
  }
});

test('las 5 plantillas pequeñas dan el resultado de su ejemplo, con los parámetros que se les pasan', () => {
  const ejemplo = { 1: ['B17', 512500], 2: ['B35', 1107600], 3: ['B22', 178000], 4: ['B24', 900900], 5: ['B24', 1100000] };
  for (const [numero, [celda, valor]] of Object.entries(ejemplo)) {
    const r = pequenas.calcular(Number(numero), {});
    assert.ok(Math.abs(r.hoja.celdas[celda].v - valor) < 0.005, `Plantilla ${numero}: ${r.hoja.celdas[celda].v}`);
  }
  // Compra a un particular en Madrid: ITP del 6 % de 500.000 €.
  const madrid = pequenas.calcular(1, { Calculadora: { B4: 'Comunidad de Madrid', B6: 'NO' } });
  assert.equal(madrid.hoja.celdas.B15.v, 30000);
  // El administrador cambia el ITP de Madrid y la plantilla lo aplica; después vuelve.
  const cambiado = pequenas.calcular(1, { Calculadora: { B4: 'Comunidad de Madrid', B6: 'NO' } }, { comunidades: { 'Comunidad de Madrid': { itp: 0.05 } } });
  assert.equal(cambiado.hoja.celdas.B15.v, 25000);
  assert.equal(pequenas.calcular(1, { Calculadora: { B4: 'Comunidad de Madrid', B6: 'NO' } }).hoja.celdas.B15.v, 30000);
  // Solo se escriben sus entradas.
  assert.throws(() => pequenas.calcular(1, { Calculadora: { B17: 1 } }), /no es una entrada/);
  assert.throws(() => pequenas.calcular(1, { 'Parámetros legales': { C5: 0.5 } }), /no es una entrada/);
});
