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

test('la línea de órdenes (cli.js) calcula igual que el servicio, para la vista previa sin servidor', () => {
  const { execFileSync } = require('node:child_process');
  const cli = (peticion) => JSON.parse(execFileSync(process.execPath, [path.join(__dirname, '..', 'cli.js')], { input: JSON.stringify(peticion) }).toString());

  const solar = cli({ path: '/pequenas/1/calcular', body: { datos: {}, parametros: { comunidades: { 'Comunitat Valenciana': { ajd: 0.02 } } } } });
  assert.equal(solar.status, 200);
  assert.equal(solar.body.hoja.celdas.B14.v, 10000); // AJD 2 % de 500.000 €
  assert.equal(cli({ path: '/pequenas/1/calcular', body: { datos: { Calculadora: { B17: 1 } } } }).status, 422);
  assert.equal(cli({ path: '/otra', body: {} }).status, 404);

  const grande = cli({ path: '/calcular', body: { datos: veinticuatro, parametros: PARAMETROS_DEL_PAQUETE } });
  assert.equal(Math.round(totalConIva(grande.body) * 100) / 100, 6918553.32);
});

test('las tarifas de referencia que fija la plataforma cambian el resultado, solo en sus celdas y solo en ese cálculo', () => {
  // La tarifa del OCT, misión E, del tramo de 300.506,05 a 450.759,08 €, y la tasa básica del seguro decenal.
  const conTarifas = calculadora.calcular(veinticuatro, [], PARAMETROS_DEL_PAQUETE, {
    '3ª Construcción': { R39: 0.02, AI78: 0.008 },
    '3ª Terrenos': { J24: 0.01 },
  });
  assert.equal(calculadora.motor.valor('3ª Construcción', 'AI78'), 0.008);
  assert.notEqual(Math.round(totalConIva(conTarifas) * 100) / 100, 6918553.32);

  // El cálculo siguiente, sin tarifas, vuelve a las del paquete.
  const despues = calculadora.calcular(veinticuatro, [], PARAMETROS_DEL_PAQUETE);
  assert.equal(calculadora.motor.valor('3ª Construcción', 'AI78'), 0.005225);
  assert.equal(Math.round(totalConIva(despues) * 100) / 100, 6918553.32);

  // Ni una fórmula, ni una entrada, ni un valor que no sea un número.
  assert.throws(() => calculadora.calcular(veinticuatro, [], {}, { '3ª Construcción': { T39: 0.1 } }), /no es una tarifa de referencia/);
  assert.throws(() => calculadora.calcular(veinticuatro, [], {}, { 'Datos de Partida': { D147: 0.1 } }), /no es una tarifa de referencia/);
  assert.throws(() => calculadora.calcular(veinticuatro, [], {}, { '3ª Construcción': { R39: '1 %' } }), /se espera un número/);
  assert.equal(Math.round(totalConIva(calculadora.calcular(veinticuatro)) * 100) / 100, 6918553.32);
});

test('los números gordos del solar (plantilla del profesional) dan su ejemplo, sin parámetros legales que aplicar', () => {
  const r = pequenas.calcular('numeros_gordos_solar', {});
  const v = (celda) => r.hoja.celdas[celda].v;
  // Los mismos resultados que comprueba el paquete (validar.js, apartado 8).
  assert.ok(Math.abs(v('C14') - 71392.05) < 0.01, `precio del solar ${v('C14')}`);
  assert.ok(Math.abs(v('C19') - 969) < 1e-9 && Math.abs(v('C23') - 1259.7) < 1e-9);
  assert.ok(Math.abs(v('C25') - 461192.64) < 0.01, `beneficio ${v('C25')}`);
  assert.equal(r.parametros, null);
  // La plataforma manda siempre los parámetros vigentes: esta plantilla no los usa y calcula igual.
  const conParametros = pequenas.calcular('numeros_gordos_solar', {}, PARAMETROS_DEL_PAQUETE);
  assert.equal(conParametros.hoja.celdas.C14.v, v('C14'));
  // Sus entradas cambian el resultado, solo en ese cálculo.
  const otra = pequenas.calcular('numeros_gordos_solar', { 'Números gordos': { C15: 60 } });
  assert.ok(Math.abs(otra.hoja.celdas.C14.v - 1586.49 * 60) < 0.01);
  assert.ok(Math.abs(pequenas.calcular('numeros_gordos_solar', {}).hoja.celdas.C14.v - 71392.05) < 0.01);
  // Solo se escriben sus entradas; una plantilla sin calculadora no existe aquí.
  assert.throws(() => pequenas.calcular('numeros_gordos_solar', { 'Números gordos': { C25: 1 } }), /no es una entrada/);
  assert.throws(() => pequenas.calcular('base_datos_suelo', {}), /No hay plantilla/);
});

test('el aviso de calendario (Datos de Partida E79) sale con el cálculo de la plantilla profesional', () => {
  assert.match(calculadora.calcular(veinticuatro).calendario, /^Calendario O\.K\./);
  // El ejemplo de la guía del profesional, sin D133 ni D154 (ahora son parámetros legales): sus pagos caen antes del cash-flow.
  const guia = escenario('escenario_guia_profesional.json');
  delete guia['Datos de Partida'].D133;
  delete guia['Datos de Partida'].D154;
  const r = calculadora.calcular(guia, [], PARAMETROS_DEL_PAQUETE);
  assert.ok(r.calculo.convergido);
  assert.match(r.calendario, /^AVISO/);
});

test('la línea de órdenes calcula también los números gordos', () => {
  const { execFileSync } = require('node:child_process');
  const cli = (peticion) => JSON.parse(execFileSync(process.execPath, [path.join(__dirname, '..', 'cli.js')], { input: JSON.stringify(peticion) }).toString());
  const r = cli({ path: '/pequenas/numeros_gordos_solar/calcular', body: { datos: {}, parametros: PARAMETROS_DEL_PAQUETE } });
  assert.equal(r.status, 200);
  assert.ok(Math.abs(r.body.hoja.celdas.C14.v - 71392.05) < 0.01);
  assert.equal(cli({ path: '/pequenas/no_existe/calcular', body: { datos: {} } }).status, 422);
});
