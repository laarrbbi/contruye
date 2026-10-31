// Pruebas del motor frente a los originales. Ejecutar: node pruebas/validar.js (desde la carpeta del paquete)
const fs = require('fs'), path = require('path');
const { PlantillaEngine } = require(path.join(__dirname, '..', 'motor', 'plantillaEngine'));
const P = f => JSON.parse(fs.readFileSync(path.join(__dirname, f), 'utf8'));
const vista = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'plantilla', 'vista_simplificada.json'), 'utf8'));
let fallos = 0;
const ok = (cond, msg) => { console.log((cond ? '  ✔ ' : '  ✘ ') + msg); if (!cond) fallos++; };

console.log('1. Plantilla vacía (tal como viene) frente a los valores guardados por Excel');
{
  const m = PlantillaEngine.desdeArchivos(); m.calcular();
  const excel = P('valores_excel_plantilla_vacia.json');
  let n = 0, iguales = 0; const dif = [];
  for (const [hoja, celdas] of Object.entries(excel)) for (const [a, v] of Object.entries(celdas)) {
    const x = m.valor(hoja, a); if (typeof x !== 'number') continue; n++;
    if (Math.abs(x - v) <= 1e-6 * Math.max(1, Math.abs(v))) iguales++; else dif.push(`${hoja}!${a}`);
  }
  console.log(`   ${iguales} de ${n} celdas iguales a Excel; ${dif.length} distintas`);
  ok(dif.length === 0, "Todas las celdas con fórmula dan exactamente lo mismo que Excel, incluidas las fechas calculadas sobre fechas vacías");
}

console.log('2. Escenario de 24 viviendas: el resultado cumple las fórmulas del original');
{
  const m = PlantillaEngine.desdeArchivos(); m.setDatos(P('escenario_24_viviendas.json')); const r = m.calcular();
  const H = '3ª Coste Total Promoción';
  ok(r.convergido, `Converge en ${r.iteraciones} iteraciones (cambio máximo ${r.cambio_maximo.toExponential(2)} < 0,001)`);
  const tot = m.valor(H, 'C24'), caps = ['C6','C8','C10','C12','C14','C16','C18','C20','C22'].reduce((s, a) => s + m.valor(H, a), 0);
  ok(Math.abs(tot - caps) < 0.01, `Total (${tot.toFixed(2)}) = suma de capítulos (${caps.toFixed(2)})`);
  const loan = m.valor('3ª Préstamo hipotecario', 'D5'), pct = m.valor('3ª Préstamo hipotecario', 'D4');
  ok(Math.abs(loan - pct * tot) < 0.01, `Préstamo (${loan.toFixed(2)}) = ${pct * 100} % del total (${(pct * tot).toFixed(2)})`);
  const ref = P('valores_referencia_24_viviendas_fuera_de_ciclo.json'); let n = 0, ig = 0;
  for (const [hoja, celdas] of Object.entries(ref)) for (const [a, v] of Object.entries(celdas)) { const x = m.valor(hoja, a); if (typeof x !== 'number') continue; n++; if (Math.abs(x - v) <= 1e-6 * Math.max(1, Math.abs(v))) ig++; }
  ok(n > 24000 && ig === n, `Celdas fuera de los ciclos: ${ig} de ${n} iguales a la referencia calculada con LibreOffice sobre el .xls original`);
  let errores = 0; for (const h of m.tpl.hojas) for (const a of Object.keys(h.celdas)) { const v = m.valor(h.nombre, a); if (typeof v === 'string' && v.startsWith('#CYCLE')) errores++; }
  ok(errores === 0, 'Ninguna celda queda en ciclo sin resolver');
}

console.log('3. Ejemplo del profesional (archivo simplificado)');
{
  const m = PlantillaEngine.desdeArchivos(); m.setDatos(P('escenario_ejemplo_profesional.json')); m.calcular();
  const nums = vista.celdas.filter(c => c.tipo === 'numero' && ['D', 'N', 'V'].includes(c.simplificada.replace(/\d+/, '')));
  const exactas = nums.filter(c => Math.abs(m.valor(vista.hoja_modelo, c.modelo) - c.ejemplo_profesional) < 0.01);
  console.log(`   ${exactas.length} de ${nums.length} importes netos iguales al céntimo`);
  ok(exactas.length >= 75, 'Coinciden terreno, construcción, decenal, OCT, proyectos, licencias, jurídicos, acometidas y suma neta de suelo + obra. El resto depende del calendario y del plan de pagos del ejemplo, que no están en el resumen');
}
console.log(fallos ? `\n${fallos} prueba(s) fallida(s)` : '\nTodas las pruebas superadas');
process.exit(fallos ? 1 : 0);
