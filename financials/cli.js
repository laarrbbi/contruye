// CONSTRUYE · Un cálculo por la entrada y la salida estándar.
//
// Para donde el servicio no puede quedarse escuchando (la vista previa en
// Vercel: la API lo arranca en su misma función). Recibe por stdin
//   { "path": "/calcular" | "/pequenas/N/calcular", "body": { datos, hojas?, parametros?, tarifas? } }
// y escribe por stdout { "status": 200 | 422 | 404 | 500, "body": … }: lo mismo
// que respondería server.js, con las mismas clases y el mismo motor.
'use strict';
const { Calculadora, PlantillasPequenas, EntradaNoValida } = require('./calculadora');

const esObjeto = (x) => typeof x === 'object' && x !== null && !Array.isArray(x);

function calcular(peticion) {
  const { path, body = {} } = esObjeto(peticion) ? peticion : {};
  const { datos = {}, hojas = [], parametros = {}, tarifas = {} } = esObjeto(body) ? body : {};
  if (!esObjeto(datos) || !esObjeto(parametros) || !esObjeto(tarifas) || !Array.isArray(hojas)) {
    return { status: 422, body: { error: 'Se espera { datos: { hoja: { celda: valor } }, parametros: {}, hojas: [] }' } };
  }
  const pequena = /^\/pequenas\/([1-9])\/calcular$/.exec(String(path));
  if (pequena) return { status: 200, body: new PlantillasPequenas().calcular(Number(pequena[1]), datos, parametros) };
  if (path === '/calcular') return { status: 200, body: new Calculadora().calcular(datos, hojas, parametros, tarifas) };
  return { status: 404, body: { error: 'No encontrado' } };
}

let entrada = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', (parte) => { entrada += parte; });
process.stdin.on('end', () => {
  let respuesta;
  try {
    respuesta = calcular(JSON.parse(entrada));
  } catch (e) {
    if (e instanceof EntradaNoValida) {
      respuesta = { status: 422, body: { error: e.message } };
    } else if (e instanceof SyntaxError) {
      respuesta = { status: 400, body: { error: 'JSON no válido' } };
    } else {
      console.error(e);
      respuesta = { status: 500, body: { error: 'Error al calcular la plantilla' } };
    }
  }
  process.stdout.write(JSON.stringify(respuesta));
});
