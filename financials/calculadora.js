// CONSTRUYE · Las plantillas del curso, listas para muchos cálculos seguidos.
//
// Envuelve el motor de curso/2_plantilla_financiera sin tocarlo: ni el motor,
// ni las plantillas, ni ninguna fórmula cambian. Dos clases:
//
// - Calculadora: la plantilla profesional corregida (PLANTILLA_LIBRE, el extra
//   de Financials). Construir su motor cuesta unos 3 s, así que se construye
//   una vez y se reutiliza.
// - PlantillasPequenas: las 5 plantillas incluidas en el curso (módulos 1, 2,
//   5, 7 y 8). Sin ciclos: calculan en milisegundos.
//
// Antes de cada cálculo, cada motor vuelve exactamente al estado de recién
// construido: cada celda de entrada a su contenido original (valor o fórmula
// por defecto), cada celda de corte de ciclo a 0 y la hoja «Parámetros
// legales» a los valores del paquete. Después se aplican los parámetros
// legales vigentes que manda la plataforma (los fija el administrador) y las
// entradas del usuario. Así un cálculo nunca hereda nada del anterior
// (test/calculadora.test.js lo prueba celda a celda frente a un motor nuevo).
'use strict';
const fs = require('node:fs');
const path = require('node:path');

const PAQUETE = process.env.PLANTILLA_DIR
  || path.join(__dirname, '..', 'curso', '2_plantilla_financiera');

const { PlantillaEngine, decode } = require(path.join(PAQUETE, 'motor', 'plantillaEngine.js'));

function leer(...partes) {
  return JSON.parse(fs.readFileSync(path.join(PAQUETE, ...partes), 'utf8'));
}

// Los parámetros legales del paquete, en el formato de setParametros().
const PARAMETROS_LEGALES = leer('plantilla', 'parametros_legales.json');
const PARAMETROS_DEL_PAQUETE = Object.freeze({
  ...Object.fromEntries(PARAMETROS_LEGALES.parametros.map((p) => [p.codigo, p.valor])),
  comunidades: Object.fromEntries(PARAMETROS_LEGALES.comunidades.map((c) => [c.nombre, { itp: c.itp, ajd: c.ajd }])),
});

class EntradaNoValida extends Error {}

// Un motor y lo necesario para devolverlo a su estado de recién construido.
class Plantilla {
  constructor(plantilla, entradas) {
    this.plantilla = plantilla;
    this.entradas = entradas;
    this.motor = new PlantillaEngine(plantilla, entradas, PARAMETROS_LEGALES);

    // Contenido original de cada celda de entrada: su fórmula por defecto o
    // su valor, tal como viene en la plantilla (null si estaba vacía).
    const hojas = Object.fromEntries(plantilla.hojas.map((h) => [h.nombre, h.celdas]));
    this.originales = entradas.entradas.map((e) => {
      const celda = hojas[e.hoja] && hojas[e.hoja][e.celda];
      const contenido = celda === undefined ? null : (celda.f !== undefined ? celda.f : celda.v);
      return { hoja: e.hoja, celda: e.celda, contenido };
    });
  }

  // Deja el motor como recién construido.
  restablecer() {
    const hf = this.motor.hf;
    hf.suspendEvaluation();
    for (const o of this.originales) {
      const { row, col } = decode(o.celda);
      hf.setCellContents({ sheet: this.motor.ids[o.hoja], row, col }, [[o.contenido]]);
    }
    for (const c of this.motor.cortes) {
      hf.setCellContents({ sheet: c.sheetId, row: c.row, col: c.col }, [[0]]);
    }
    hf.resumeEvaluation();
    this.motor.setParametros(PARAMETROS_DEL_PAQUETE);
  }

  // Parámetros legales vigentes y entradas del usuario, en ese orden, sobre
  // el motor recién restablecido. Un valor no admitido deja el motor limpio.
  preparar(datos, parametros) {
    this.restablecer();
    try {
      this.motor.setParametros(parametros || {});
      this.motor.setDatos(datos || {});
    } catch (e) {
      this.restablecer();
      throw new EntradaNoValida(e.message);
    }
  }

  // Una hoja como la mostraría Excel: cada celda con su valor calculado y su
  // formato de número original.
  hoja(nombre) {
    const h = this.plantilla.hojas.find((x) => x.nombre === nombre);
    const celdas = {};
    for (const [addr, c] of Object.entries(h.celdas)) {
      celdas[addr] = { v: this.motor.valor(nombre, addr), nf: c.nf };
    }
    return { nombre, dimension: h.dimension || null, celdas };
  }
}

class Calculadora extends Plantilla {
  constructor() {
    super(leer('plantilla', 'plantilla_libre.json'), leer('plantilla', 'entradas.json'));
    this.vista = leer('plantilla', 'vista_simplificada.json');
    this.hojasDisponibles = this.plantilla.hojas.map((h) => ({ nombre: h.nombre, dimension: h.dimension }));
  }

  // datos = { 'Datos de Partida': { C9: 24, D79: '01/01/2027', … } }
  // hojas = nombres de hojas completas que se quieren de vuelta.
  // parametros = { IVA_GENERAL: 0.21, …, comunidades: { 'Cataluña': { itp, ajd } } }
  calcular(datos, hojas = [], parametros = {}) {
    for (const nombre of hojas) {
      if (!this.motor.ids[nombre] || nombre === '__iteracion__') throw new EntradaNoValida(`La hoja ${nombre} no existe en la plantilla`);
    }
    this.preparar(datos, parametros);
    const calculo = this.motor.calcular();
    return {
      calculo,
      resumen: this.motor.resumenCoste(),
      vista: this.motor.vistaSimplificada(this.vista),
      parametros: this.motor.parametrosVigentes(),
      hojas: Object.fromEntries(hojas.map((nombre) => [nombre, this.hoja(nombre)])),
    };
  }
}

// Las 5 plantillas pequeñas del curso, por número (plantillas_pequenas/indice.json).
class PlantillasPequenas {
  constructor() {
    this.indice = leer('plantillas_pequenas', 'indice.json');
    this.plantillas = new Map(this.indice.plantillas.map((p) => [
      p.numero,
      new Plantilla(leer('plantillas_pequenas', p.plantilla), leer('plantillas_pequenas', p.entradas)),
    ]));
  }

  calcular(numero, datos, parametros = {}) {
    const plantilla = this.plantillas.get(numero);
    if (!plantilla) throw new EntradaNoValida(`No hay plantilla ${numero}`);
    plantilla.preparar(datos, parametros);
    const calculo = plantilla.motor.calcular();
    return {
      calculo,
      parametros: plantilla.motor.parametrosVigentes(),
      // La hoja de la plantilla (la primera; la otra es «Parámetros legales»).
      hoja: plantilla.hoja(plantilla.plantilla.hojas[0].nombre),
    };
  }
}

module.exports = { Calculadora, PlantillasPequenas, EntradaNoValida, PAQUETE, PARAMETROS_DEL_PAQUETE };
