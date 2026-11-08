// CONSTRUYE · Servicio de las plantillas financieras.
//
// Solo lo llama la API de Laravel (que comprueba antes que el alumno tiene
// acceso a la plantilla, y le pasa los parámetros legales vigentes): escucha
// en 127.0.0.1 salvo que PLANTILLA_HOST diga otra cosa (en Docker, la red
// interna). HyperFormula, el motor de hojas de cálculo, es GPLv3: corre aquí,
// en el servidor, y no se distribuye al navegador (ESPECIFICACION_TECNICA.md,
// punto 10).
//
//   GET  /salud                 → { ok: true }
//   GET  /hojas                 → las 18 hojas de la plantilla profesional y su tamaño
//   POST /calcular              { datos, hojas?, parametros? } → { calculo, resumen, vista, parametros, hojas }
//   POST /pequenas/:n/calcular  { datos, parametros? } → { calculo, parametros, hoja }
'use strict';
const http = require('node:http');
const { Calculadora, PlantillasPequenas, EntradaNoValida } = require('./calculadora');

const MAX_CUERPO = 512 * 1024;

function responder(res, estado, cuerpo) {
  const json = JSON.stringify(cuerpo);
  res.writeHead(estado, { 'Content-Type': 'application/json; charset=utf-8', 'Content-Length': Buffer.byteLength(json) });
  res.end(json);
}

function leerCuerpo(req) {
  return new Promise((resolve, reject) => {
    let tamano = 0;
    const partes = [];
    req.on('data', (parte) => {
      tamano += parte.length;
      if (tamano > MAX_CUERPO) { reject(Object.assign(new Error('Cuerpo demasiado grande'), { estado: 413 })); req.destroy(); return; }
      partes.push(parte);
    });
    req.on('end', () => resolve(Buffer.concat(partes).toString('utf8')));
    req.on('error', reject);
  });
}

const esObjeto = (x) => typeof x === 'object' && x !== null && !Array.isArray(x);

function crearServidor(calculadora, pequenas) {
  return http.createServer(async (req, res) => {
    try {
      if (req.method === 'GET' && req.url === '/salud') return responder(res, 200, { ok: true });
      if (req.method === 'GET' && req.url === '/hojas') return responder(res, 200, { hojas: calculadora.hojasDisponibles });
      const pequena = req.url.match(/^\/pequenas\/([1-9])\/calcular$/);
      if (req.method === 'POST' && (req.url === '/calcular' || pequena)) {
        let peticion;
        try { peticion = JSON.parse(await leerCuerpo(req)); } catch (e) {
          return responder(res, e.estado || 400, { error: e.estado ? e.message : 'JSON no válido' });
        }
        const { datos = {}, hojas = [], parametros = {} } = peticion || {};
        if (!esObjeto(datos) || !esObjeto(parametros) || !Array.isArray(hojas)) {
          return responder(res, 422, { error: 'Se espera { datos: { hoja: { celda: valor } }, parametros: {}, hojas: [] }' });
        }
        if (pequena) return responder(res, 200, pequenas.calcular(Number(pequena[1]), datos, parametros));
        return responder(res, 200, calculadora.calcular(datos, hojas, parametros));
      }
      return responder(res, 404, { error: 'No encontrado' });
    } catch (e) {
      if (e instanceof EntradaNoValida) return responder(res, 422, { error: e.message });
      console.error(e);
      return responder(res, 500, { error: 'Error al calcular la plantilla' });
    }
  });
}

if (require.main === module) {
  const inicio = Date.now();
  const calculadora = new Calculadora();
  const pequenas = new PlantillasPequenas();
  const puerto = Number(process.env.PLANTILLA_PORT || 3100);
  const host = process.env.PLANTILLA_HOST || '127.0.0.1';
  crearServidor(calculadora, pequenas).listen(puerto, host, () => {
    console.log(`Plantillas cargadas en ${(Date.now() - inicio) / 1000} s · escuchando en http://${host}:${puerto}`);
  });
}

module.exports = { crearServidor };
