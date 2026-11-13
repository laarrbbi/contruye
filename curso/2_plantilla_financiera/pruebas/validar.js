// Pruebas del motor. Ejecutar: npm test (desde motor/) o node pruebas/validar.js
// 1-3: la plantilla ORIGINAL (plantilla_libre_original.json) da lo mismo que Excel y LibreOffice.
// 4:   la plantilla CORREGIDA con los impuestos de 2002 da los mismos importes que el original, salvo los errores corregidos.
// 5:   la plantilla CORREGIDA con los impuestos vigentes: sin errores, todos los controles en O.K. y el cash-flow cuadra.
// 6:   los parámetros legales los cambia el administrador y se aplican; el usuario no puede tocarlos.
'use strict';
const fs = require('fs');
const path = require('path');
const { PlantillaEngine } = require(path.join(__dirname, '..', 'motor', 'plantillaEngine'));
const P = f => JSON.parse(fs.readFileSync(path.join(__dirname, f), 'utf8'));
const orig = () => PlantillaEngine.desdeArchivos(undefined, 'plantilla_libre_original.json', 'entradas_original.json');
const corr = () => PlantillaEngine.desdeArchivos();
let fallos = 0;
const ok = (c, msg) => { console.log(`  ${c ? '✔' : '✘'} ${msg}`); if (!c) fallos++; };
const H = '3ª Coste Total Promoción', CF = '6ª Cash-flow', PL = 'Parámetros legales';
const FILAS = [6, 8, 10, 12, 14, 16, 18, 20, 22, 24];
// Las celdas D133 y D154 eran datos en el original y ahora salen de Parámetros legales
const paraCorregida = d => { const o = JSON.parse(JSON.stringify(d)); if (o['Datos de Partida']) { delete o['Datos de Partida'].D133; delete o['Datos de Partida'].D154; } return o; };
const errores = m => { let n = 0; for (const h of m.tpl.hojas) for (const a of Object.keys(h.celdas)) { const v = m.valor(h.nombre, a); if (typeof v === 'string' && v.startsWith('#')) n++; } return n; };
const controlesMal = m => { const out = []; for (const h of m.tpl.hojas) for (const [a, c] of Object.entries(h.celdas)) if (c.f && /"O\.K\."|"o\.k\."/.test(c.f) && !/^o\.k\.$/i.test(String(m.valor(h.nombre, a)))) out.push(`${h.nombre}!${a}`); return out; };
const VALORES_2002 = { IVA_GENERAL: 0.16, IVA_REDUCIDO: 0.07, IPS: 0.06, CLEA: 0.005, INTERES_LEGAL: 0.06, AJD_PRESTAMO: 0 };
const con2002 = m => { const cc = {}; for (const c of m.parametros.comunidades) cc[c.nombre] = { itp: 0.08, ajd: 0.01 }; return m.setParametros({ ...VALORES_2002, comunidades: cc }); };

console.log('1. Plantilla ORIGINAL vacía frente a los valores guardados por Excel');
{
  const m = orig(); m.calcular();
  const excel = P('valores_excel_plantilla_vacia.json');
  let n = 0, iguales = 0; const dif = [];
  for (const [hoja, celdas] of Object.entries(excel)) for (const [a, v] of Object.entries(celdas)) {
    const x = m.valor(hoja, a); if (typeof x !== 'number') continue; n++;
    if (Math.abs(x - v) <= 1e-6 * Math.max(1, Math.abs(v))) iguales++; else dif.push(`${hoja}!${a}`);
  }
  console.log(`   ${iguales} de ${n} celdas iguales a Excel; ${dif.length} distintas`);
  ok(dif.length === 0, 'Todas las celdas con fórmula dan exactamente lo mismo que Excel, incluidas las fechas calculadas sobre fechas vacías');
}

console.log('2. Plantilla ORIGINAL, escenario de 24 viviendas: el resultado cumple las fórmulas del original');
{
  const m = orig(); m.setDatos(P('escenario_24_viviendas.json')); const r = m.calcular();
  ok(r.convergido, `Converge en ${r.iteraciones} iteraciones (cambio máximo ${r.cambio_maximo.toExponential(2)} < 0,001)`);
  const tot = m.valor(H, 'C24'), caps = ['C6', 'C8', 'C10', 'C12', 'C14', 'C16', 'C18', 'C20', 'C22'].reduce((s, a) => s + m.valor(H, a), 0);
  ok(Math.abs(tot - caps) < 0.01, `Total (${tot.toFixed(2)}) = suma de capítulos (${caps.toFixed(2)})`);
  const loan = m.valor('3ª Préstamo hipotecario', 'D5'), pct = m.valor('3ª Préstamo hipotecario', 'D4');
  ok(Math.abs(loan - pct * tot) < 0.01, `Préstamo (${loan.toFixed(2)}) = ${pct * 100} % del total (${(pct * tot).toFixed(2)})`);
  const ref = P('valores_referencia_24_viviendas_fuera_de_ciclo.json'); let n = 0, ig = 0;
  // 6ª Cash-flow!M6 es =TODAY() (fecha de hoy): esa celda y sus 15 copias cambian cada día y no se comparan
  const hoyRef = ref[CF].M6, hoy = m.valor(CF, 'M6'); let deHoy = 0;
  for (const [hoja, celdas] of Object.entries(ref)) for (const [a, v] of Object.entries(celdas)) { const x = m.valor(hoja, a); if (typeof x !== 'number') continue; if (v === hoyRef && x === hoy && hoja === CF) { deHoy++; continue; } n++; if (Math.abs(x - v) <= 1e-6 * Math.max(1, Math.abs(v))) ig++; }
  ok(n > 24000 && ig === n && deHoy === 16, `Celdas fuera de los ciclos: ${ig} de ${n} iguales a la referencia calculada con LibreOffice sobre el .xls original (más ${deHoy} que muestran la fecha de hoy)`);
  ok(m.ultimoCalculo.convergido, 'Ninguna celda queda en ciclo sin resolver');
}

console.log('3. Plantilla ORIGINAL, ejemplo del profesional (archivo simplificado)');
{
  const m = orig(); m.setDatos(P('escenario_ejemplo_profesional.json')); m.calcular();
  const vista = P('../plantilla/vista_simplificada.json');
  const nums = vista.celdas.filter(c => c.tipo === 'numero' && ['D', 'N', 'V'].includes(c.simplificada.replace(/\d+/, '')));
  const exactas = nums.filter(c => Math.abs(m.valor(vista.hoja_modelo, c.modelo) - c.ejemplo_profesional) < 0.01);
  console.log(`   ${exactas.length} de ${nums.length} importes netos iguales al céntimo`);
  ok(exactas.length >= 75, 'Coinciden terreno, construcción, decenal, OCT, proyectos, licencias, jurídicos, acometidas y suma neta de suelo + obra. El resto depende del calendario y del plan de pagos del ejemplo, que no están en el resumen');
}

console.log('4. Plantilla CORREGIDA con los impuestos de 2002: mismos importes que el original salvo los errores corregidos');
{
  const vo = orig(); vo.calcular(); const vc = con2002(corr()); vc.calcular();
  ok(errores(vo) === 52 && errores(vc) === 0, `Plantilla vacía: ${errores(vo)} celdas con error en el original (#VALUE! y #DIV/0!) → ${errores(vc)} en la corregida`);
  for (const [nombre, esc] of [['24 viviendas', 'escenario_24_viviendas.json'], ['ejemplo del profesional', 'escenario_ejemplo_profesional.json']]) {
    const a = orig(); a.setDatos(P(esc)); a.calcular();
    const b = con2002(corr()); b.setDatos(paraCorregida(P(esc))); const r = b.calcular();
    ok(r.convergido && r.iteraciones === a.ultimoCalculo.iteraciones, `${nombre}: converge en ${r.iteraciones} iteraciones, como el original`);
    const dif = FILAS.map(f => Math.max(Math.abs(b.valor(H, 'C' + f) - a.valor(H, 'C' + f)), Math.abs(b.valor(H, 'G' + f) - a.valor(H, 'G' + f))));
    ok(Math.max(...dif) < 1, `${nombre}: neto e IVA de cada capítulo iguales al original (diferencia máxima ${Math.max(...dif).toFixed(2)} €, por pasar las pesetas a euros al céntimo)`);
    const malasA = FILAS.filter(f => Math.abs(a.valor(H, 'E' + f) - a.valor(H, 'C' + f) - a.valor(H, 'G' + f)) > 0.01);
    const malasB = FILAS.filter(f => Math.abs(b.valor(H, 'E' + f) - b.valor(H, 'C' + f) - b.valor(H, 'G' + f)) > 0.01);
    ok(malasA.join() === '16,24' && malasB.length === 0, `${nombre}: neto + IVA = con IVA en todos los capítulos (el original fallaba en las filas ${malasA.join(' y ')}: faltaban las acometidas con IVA)`);
    const acom = b.valor('3ª Licencias y Varios', 'G33');
    ok(Math.abs(b.valor(H, 'E24') - a.valor(H, 'E24') - acom) < 1, `${nombre}: el total con IVA sube ${(b.valor(H, 'E24') - a.valor(H, 'E24')).toFixed(2)} €, las acometidas con IVA que faltaban`);
  }
}

console.log('5. Plantilla CORREGIDA con los impuestos vigentes');
{
  const v = corr(); v.calcular();
  ok(errores(v) === 0 && controlesMal(v).length === 0, 'Plantilla vacía: ninguna celda con error y todos los controles en O.K.');
  const base = paraCorregida(P('escenario_24_viviendas.json'));
  const VARIANTES = {
    'base (Comunitat Valenciana, suelo con IVA)': {},
    'suelo sin IVA (ITP) en Madrid': { D80: 'NO', D93: 'Comunidad de Madrid' },
    'Cataluña': { D93: 'Cataluña' },
    '5º pago del suelo avalado': { C90: '01/06/2027', D90: 100000, E90: 'SI' },
    'disposiciones del préstamo en meses distintos': { D151: '01/12/2027' },
  };
  for (const [n, cambios] of Object.entries(VARIANTES)) {
    const m = corr(); const d = JSON.parse(JSON.stringify(base)); Object.assign(d['Datos de Partida'], cambios); m.setDatos(d); const r = m.calcular();
    const mal = controlesMal(m), err = errores(m);
    const cuadre = FILAS.every(f => Math.abs(m.valor(H, 'E' + f) - m.valor(H, 'C' + f) - m.valor(H, 'G' + f)) < 0.01);
    const caja = Math.abs(m.valor(CF, 'BP238') - m.valor(H, 'C24')) < 0.01 && Math.abs(m.valor(CF, 'BP236') - m.valor(CF, 'BP238')) < 1 && Math.abs(m.valor(CF, 'BO236') - m.valor(CF, 'BO238')) < 1;
    ok(r.convergido && err === 0 && mal.length === 0 && cuadre && caja, `24 viviendas, ${n}: converge (${r.iteraciones} it.), 0 errores, ${mal.length ? 'controles mal: ' + mal.join(', ') : 'todos los controles en O.K.'}, neto + IVA = con IVA, y el cash-flow paga exactamente el coste y cobra lo mismo que paga (total ${m.valor(H, 'E24').toFixed(2)} € con IVA)`);
  }
  const m = corr(); m.setDatos(paraCorregida(P('escenario_24_viviendas.json'))); m.calcular();
  const o = orig(); o.setDatos(P('escenario_24_viviendas.json')); o.calcular();
  ok(Math.abs(m.valor(CF, 'BP238') - m.valor(H, 'C24')) < 0.01 && Math.abs(o.valor(CF, 'BP238') - o.valor(H, 'C24')) > 1000,
     `Cash-flow: pagos sin IVA = coste neto (${m.valor(CF, 'BP238').toFixed(2)} €). En el original faltaban ${(o.valor(H, 'C24') - o.valor(CF, 'BP238')).toFixed(2)} € cuando dos pagos del préstamo caían en el mismo mes`);
  ok(m.valor(PL, 'B36') === 0.09 && m.valor(PL, 'B37') === 0.014 && Math.abs(m.valor('3ª Terrenos', 'D34') - 0.014 * m.valor('3ª Terrenos', 'B8')) < 0.01, 'Suelo con IVA en la Comunitat Valenciana: AJD 1,4 % del precio (ITP 9 % si fuera sin IVA)');
  ok(m.valor('3ª Construcción', 'D11') === 0.10 && m.valor('3ª Proyectos', 'E3') === 0.21, 'IVA de la obra 10 % e IVA general 21 %');
  const esp = P('escenario_ejemplo_profesional.json'); const e = corr(); e.setDatos(paraCorregida(esp)); e.calcular();
  const malE = controlesMal(e).sort().join(', ');
  ok(errores(e) === 0 && malE === '5º Forma de Pago!F62, 5º Forma de Pago!P62, 6ª Cash-flow!BO244, 6ª Cash-flow!BP242',
     `Ejemplo del profesional: 0 errores. Solo avisan los 4 controles del plan de pagos de los socios, que no tenemos de su ejemplo (el plan reconstruido recauda ${(e.valor(CF, 'BP236') - e.valor(CF, 'BP238')).toFixed(2)} € más que el coste)`);
  const datos = paraCorregida(P('escenario_24_viviendas.json')); Object.assign(datos['Datos de Partida'], { C90: '01/06/2027', D90: 100000, E90: 'SI' });
  const a = orig(); a.setDatos(P('escenario_24_viviendas.json')); a.setDatos({ 'Datos de Partida': { C90: '01/06/2027', D90: 100000, E90: 'SI' } }); a.calcular();
  const b = corr(); b.setDatos(datos); b.calcular(); const fecha = b.valor('Datos de Partida', 'C90');
  ok(a.valor('3ª Terrenos', 'J31') === 0 && b.valor('3ª Terrenos', 'J31') === fecha && fecha > 0, `5º pago avalado: la fecha del aval (J31) era 0 en el original y ahora es la del 5º pago (${fecha})`);
}

console.log('6. Parámetros legales: los cambia el administrador y se aplican a todas las plantillas');
{
  const m = corr(); m.setDatos(paraCorregida(P('escenario_24_viviendas.json'))); m.calcular(); const antes = m.valor(H, 'E24');
  m.setParametros({ IVA_GENERAL: 0.22 }); m.calcular();
  ok(m.valor(H, 'E24') > antes && m.valor('3ª Proyectos', 'E3') === 0.22, `Si el IVA general pasara al 22 %, el total con IVA sube de ${antes.toFixed(2)} a ${m.valor(H, 'E24').toFixed(2)} €`);
  m.setParametros({ comunidades: { 'Comunitat Valenciana': { ajd: 0.015 } } }); m.calcular();
  ok(m.valor(PL, 'B37') === 0.015 && m.valor('Datos de Partida', 'D133') === 0.015, 'Cambiar el AJD de una comunidad cambia el AJD del suelo y el de obra nueva y división horizontal');
  let bloqueado = false; try { m.setDatos({ [PL]: { C5: 0.5 } }); } catch (e) { bloqueado = true; }
  ok(bloqueado, 'El usuario no puede escribir en Parámetros legales con setDatos');
  let rechazado = false; try { m.setParametros({ IVA_GENERAL: 21 }); } catch (e) { rechazado = true; }
  ok(rechazado, 'Un valor mal escrito (21 en vez de 0,21) se rechaza');
  const reg = P('../plantilla/correcciones.json');
  ok(reg.parametros_legales.length === 6 && reg.comunidades.length === 19 && reg.cambios.length > 0, `Registro de cambios: ${Object.values(reg.resumen).reduce((s, x) => s + x, 0)} celdas, en ${Object.keys(reg.resumen).length} grupos (correcciones.json)`);
}

console.log('7. Plantillas pequeñas del curso: mismo motor y mismos parámetros legales');
{
  const dir = path.join(__dirname, '..', 'plantillas_pequenas');
  const idx = P('../plantillas_pequenas/indice.json');
  const esperado = { 1: ['B17', 512500], 2: ['B35', 1107600], 3: ['B22', 178000], 4: ['B24', 900900], 5: ['B24', 1100000] };
  for (const p of idx.plantillas) {
    const m = PlantillaEngine.desdeArchivos(dir, p.plantilla, p.entradas); m.calcular();
    const [celda, valor] = esperado[p.numero];
    ok(errores(m) === 0 && Math.abs(m.valor('Calculadora', celda) - valor) < 0.01, `${p.titulo}: 0 errores y el ejemplo da ${valor.toLocaleString('es-ES')} € en ${celda} (comprobado también en LibreOffice)`);
  }
  const v = PlantillaEngine.desdeArchivos(dir, '5_plan_ventas_cobros.json', '5_plan_ventas_cobros.entradas.json'); v.calcular(); const antes = v.valor('Calculadora', 'B22');
  v.setParametros({ IVA_REDUCIDO: 0.12 }); v.calcular();
  ok(antes === 500000 && v.valor('Calculadora', 'B22') === 600000, 'El administrador cambia el IVA reducido y la plantilla de ventas lo aplica (IVA repercutido de 500.000 a 600.000 € con un 12 %)');
  const s = PlantillaEngine.desdeArchivos(dir, '1_compra_del_solar.json', '1_compra_del_solar.entradas.json'); s.setDatos({ Calculadora: { B4: 'Comunidad de Madrid', B6: 'NO' } }); s.calcular();
  ok(s.valor('Calculadora', 'B15') === 30000, 'Compra a un particular en Madrid: ITP 6 % de 500.000 € = 30.000 €');
}

console.log('8. Plantillas del profesional (11/11/2026)');
{
  const dir = path.join(__dirname, '..', 'plantillas_pequenas');
  const ng = PlantillaEngine.desdeArchivos(dir, 'numeros_gordos_solar.json', 'numeros_gordos_solar.entradas.json'); ng.calcular();
  const N = 'Números gordos', v = a => ng.valor(N, a);
  ok(errores(ng) === 0 && Math.abs(v('C14') - 71392.05) < 0.01 && Math.abs(v('C19') - 969) < 1e-9 && Math.abs(v('C23') - 1259.7) < 1e-9,
     'Números gordos: mismo precio del solar (71.392,05 €), coste (969 €/m²t) y precio de venta (1.259,70 €/m²t) que el original, sin los ×100');
  ok(Math.abs(v('C25') - (v('C24') - v('C27'))) < 0.01 && Math.abs(v('C25') - 461192.64) < 0.01 && Math.abs(v('C26') - 0.3 / 1.3) < 1e-9,
     `Números gordos: beneficio = ventas − coste = ${v('C25').toFixed(2)} € (el original daba el 30 % de las ventas, 599.550,44 €); margen sobre ventas ${(v('C26') * 100).toFixed(2)} %`);
  // Guía de análisis de viabilidad: el modelo original con su ejemplo da lo mismo que su Excel
  const esc = P('escenario_guia_profesional.json'), ref = P('valores_guia_profesional.json');
  const o = orig(); o.setDatos(esc); const ro = o.calcular(); let n = 0, ig = 0;
  // Estudio Económico T18 es una fecha calculada sobre una fecha vacía (el ejemplo no tiene urbanización): el Excel de 2026 da un día más que el de 2002
  for (const [h, c] of Object.entries(ref)) for (const [a, x] of Object.entries(c)) { if (h === 'Estudio Económico' && a === 'T18') continue; const y = o.valor(h, a); if (typeof y !== 'number') continue; n++; if (Math.abs(x - y) <= 1e-6 * Math.max(1, Math.abs(x))) ig++; }
  ok(ro.convergido && n > 5000 && ig === n, `Guía del profesional: con su ejemplo (22 viviendas), el modelo original da lo mismo que su Excel en ${ig} de ${n} celdas de coste, préstamo, gestión, estudio económico y resumen`);
  const g = corr(); g.setDatos(paraCorregida(esc)); const rg = g.calcular();
  const aviso = String(g.valor('Datos de Partida', 'E79'));
  ok(rg.convergido && errores(g) === 0 && aviso.startsWith('AVISO'), 'Guía sobre la plantilla corregida: converge y 0 errores. El aviso de calendario salta: su ejemplo tiene pagos antes del primer mes del cash-flow (suelo y proyectos en el mes cero o antes)');
  const b = corr(); b.setDatos(paraCorregida(P('escenario_24_viviendas.json'))); b.calcular();
  ok(String(b.valor('Datos de Partida', 'E79')).startsWith('Calendario O.K.'), 'Con fechas coherentes (24 viviendas), el aviso de calendario dice «Calendario O.K.»');
}

console.log(fallos ? `\n${fallos} prueba(s) fallida(s)` : '\nTodas las pruebas correctas');
process.exitCode = fallos ? 1 : 0;
