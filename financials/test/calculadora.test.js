// La calculadora reutiliza un mismo motor para muchos cálculos. Estas pruebas
// comprueban que eso no cambia ningún resultado y que el servicio HTTP
// devuelve lo mismo que el motor del profesional.
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { Calculadora, PAQUETE } = require('../calculadora');
const { crearServidor } = require('../server');

const escenario = (nombre) => JSON.parse(fs.readFileSync(path.join(PAQUETE, 'pruebas', nombre), 'utf8'));
const veinticuatro = escenario('escenario_24_viviendas.json');
const profesional = escenario('escenario_ejemplo_profesional.json');

const calculadora = new Calculadora();

function todasLasCeldas(calc) {
  const valores = {};
  for (const h of calc.plantilla.hojas) {
    for (const addr of Object.keys(h.celdas)) valores[`${h.nombre}!${addr}`] = calc.motor.valor(h.nombre, addr);
  }
  return valores;
}

test('un cálculo no hereda nada del anterior: celda a celda igual que un motor nuevo', () => {
  // Motor recién construido, un solo cálculo.
  const nueva = new Calculadora();
  nueva.calcular(veinticuatro);
  const esperado = todasLasCeldas(nueva);

  // El motor compartido, después de otro escenario distinto.
  calculadora.calcular(profesional);
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
  const servidor = crearServidor(calculadora).listen(0, '127.0.0.1');
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
    assert.ok(Math.abs(total.neto - 6056013.13) < 0.01, `Total ${total.neto}`);
    assert.ok(cuerpo.vista.length > 400);
    assert.ok(Object.keys(cuerpo.hojas['8º Resumen'].celdas).length > 800);

    // Una fórmula del profesional no se puede sobrescribir.
    const formula = await fetch(`${base}/calcular`, { method: 'POST', body: JSON.stringify({ datos: { '3ª Coste Total Promoción': { C24: 1 } } }) });
    assert.equal(formula.status, 422);

    const hoja = await fetch(`${base}/calcular`, { method: 'POST', body: JSON.stringify({ datos: {}, hojas: ['No existe'] }) });
    assert.equal(hoja.status, 422);

    const roto = await fetch(`${base}/calcular`, { method: 'POST', body: '{no es json' });
    assert.equal(roto.status, 400);
  } finally {
    servidor.close();
  }
});
