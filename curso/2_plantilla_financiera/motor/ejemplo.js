// Ejemplo de uso: calcula una promoción y muestra el coste total (hoja '3ª Coste Total Promoción').
const { PlantillaEngine } = require('./plantillaEngine');
const t0 = Date.now();
const motor = PlantillaEngine.desdeArchivos();
motor.setDatos({
  'Datos de Partida': {
    D1: 'Residencial Ejemplo', C9: 24, D9: 100, E9: 1050, C10: 24, D10: 25, E10: 600, C12: 24, D12: 6, E12: 600,
    B22: 12, C22: 75, B23: 12, C23: 82, C51: 25, C52: 25, D51: 6, D52: 6,
    D79: '01/01/2027', D80: 'SI', D81: 0.04, D82: 1300000, D83: 0.02, C86: '01/02/2027', E86: 'NO', E92: '01/02/2027',
    D96: '01/10/2027', D97: 60, D99: 0.05, D101: 18,
    D108: 90000, D109: '01/01/2029', D110: 60, D112: 0.05, D114: 4,
    D146: 0.8, D147: 0.045, D148: 0.15, D150: '01/10/2027', D151: '01/10/2027', D152: '01/04/2029',
    D157: 0.12, D158: 30, D159: '01/01/2027', C163: 4, C164: 4, C165: 4, C166: 4, C167: 4, C168: 4, C184: 0.2, D186: 24
  }
});
const r = motor.calcular();
console.log(`Calculado en ${(Date.now() - t0) / 1000} s · ${r.iteraciones} iteraciones · cambio máximo ${r.cambio_maximo.toExponential(2)}`);
const eur = v => typeof v === 'number' ? v.toLocaleString('es-ES', { maximumFractionDigits: 2 }) + ' €' : v;
for (const f of motor.resumenCoste()) console.log(f.concepto.padEnd(30), (f.concepto === 'Coeficiente de paso' ? f.neto.toFixed(4) : eur(f.neto)).padStart(18), f.con_iva !== undefined ? eur(f.con_iva).padStart(18) : '');
