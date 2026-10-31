// CONSTRUYE · Servicio de la plantilla financiera.
//
// Solo lo llama la API de Laravel (que comprueba antes que el alumno tiene
// Financials): escucha en 127.0.0.1 salvo que PLANTILLA_HOST diga otra cosa
// (en Docker, la red interna). HyperFormula, el motor de hojas de cálculo, es
// GPLv3: corre aquí, en el servidor, y no se distribuye al navegador
// (ESPECIFICACION_TECNICA.md, punto 10).
//
//   GET  /salud     → { ok: true }
//   GET  /hojas     → las 17 hojas de la plantilla y su tamaño
//   POST /calcular  { datos, hojas? } → { calculo, resumen, vista, hojas }
'use strict';
const http = require('node:http');
const { Calculadora, EntradaNoValida } = require('./calculadora');

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

function crearServidor(calculadora) {
  return http.createServer(async (req, res) => {
    try {
      if (req.method === 'GET' && req.url === '/salud') return responder(res, 200, { ok: true });
      if (req.method === 'GET' && req.url === '/hojas') return responder(res, 200, { hojas: calculadora.hojasDisponibles });
      if (req.method === 'POST' && req.url === '/calcular') {
        let peticion;
        try { peticion = JSON.parse(await leerCuerpo(req)); } catch (e) {
          return responder(res, e.estado || 400, { error: e.estado ? e.message : 'JSON no válido' });
        }
        const { datos = {}, hojas = [] } = peticion || {};
        if (typeof datos !== 'object' || Array.isArray(datos) || !Array.isArray(hojas)) {
          return responder(res, 422, { error: 'Se espera { datos: { hoja: { celda: valor } }, hojas: [] }' });
        }
        return responder(res, 200, calculadora.calcular(datos, hojas));
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
  const puerto = Number(process.env.PLANTILLA_PORT || 3100);
  const host = process.env.PLANTILLA_HOST || '127.0.0.1';
  crearServidor(calculadora).listen(puerto, host, () => {
    console.log(`Plantilla cargada en ${(Date.now() - inicio) / 1000} s · escuchando en http://${host}:${puerto}`);
  });
}

module.exports = { crearServidor };
