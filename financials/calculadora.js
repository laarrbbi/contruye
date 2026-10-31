// CONSTRUYE · La plantilla del profesional, lista para muchos cálculos seguidos.
//
// Envuelve el motor de curso/2_plantilla_financiera sin tocarlo: ni el motor,
// ni la plantilla, ni ninguna fórmula cambian. Construir el motor cuesta unos
// 3 s, así que se construye una vez y se reutiliza. Antes de cada cálculo se
// devuelve exactamente al estado de recién construido: cada celda de entrada
// vuelve a su contenido original (valor o fórmula por defecto) y cada celda
// de corte de ciclo vuelve a 0, como la deja el constructor del motor. Así un
// cálculo nunca hereda nada del anterior (test/calculadora.test.js lo prueba
// celda a celda frente a un motor nuevo).
'use strict';
const fs = require('node:fs');
const path = require('node:path');

const PAQUETE = process.env.PLANTILLA_DIR
  || path.join(__dirname, '..', 'curso', '2_plantilla_financiera');

const { PlantillaEngine, decode } = require(path.join(PAQUETE, 'motor', 'plantillaEngine.js'));

function leer(nombre) {
  return JSON.parse(fs.readFileSync(path.join(PAQUETE, 'plantilla', nombre), 'utf8'));
}

class Calculadora {
  constructor() {
    this.plantilla = leer('plantilla_libre.json');
    this.entradas = leer('entradas.json');
    this.vista = leer('vista_simplificada.json');
    this.motor = new PlantillaEngine(this.plantilla, this.entradas);

    // Contenido original de cada celda de entrada: su fórmula por defecto o
    // su valor, tal como viene en la plantilla (null si estaba vacía).
    const hojas = Object.fromEntries(this.plantilla.hojas.map((h) => [h.nombre, h.celdas]));
    this.originales = this.entradas.entradas.map((e) => {
      const celda = hojas[e.hoja] && hojas[e.hoja][e.celda];
      const contenido = celda === undefined ? null : (celda.f !== undefined ? celda.f : celda.v);
      return { hoja: e.hoja, celda: e.celda, contenido };
    });
    this.hojasDisponibles = this.plantilla.hojas.map((h) => ({ nombre: h.nombre, dimension: h.dimension }));
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
  }

  // datos = { 'Datos de Partida': { C9: 24, D79: '01/01/2027', … } }
  // hojas = nombres de hojas completas que se quieren de vuelta.
  calcular(datos, hojas = []) {
    for (const nombre of hojas) {
      if (!this.motor.ids[nombre] || nombre === '__iteracion__') throw new EntradaNoValida(`La hoja ${nombre} no existe en la plantilla`);
    }
    this.restablecer();
    try {
      this.motor.setDatos(datos || {});
    } catch (e) {
      this.restablecer();
      throw new EntradaNoValida(e.message);
    }
    const calculo = this.motor.calcular();
    return {
      calculo,
      resumen: this.motor.resumenCoste(),
      vista: this.motor.vistaSimplificada(this.vista),
      hojas: Object.fromEntries(hojas.map((nombre) => [nombre, this.hoja(nombre)])),
    };
  }

  // Una hoja como la mostraría Excel: cada celda con su valor calculado y su
  // formato de número original.
  hoja(nombre) {
    const h = this.plantilla.hojas.find((x) => x.nombre === nombre);
    const celdas = {};
    for (const [addr, c] of Object.entries(h.celdas)) {
      celdas[addr] = { v: this.motor.valor(nombre, addr), nf: c.nf };
    }
    return { nombre, dimension: h.dimension, celdas };
  }
}

class EntradaNoValida extends Error {}

module.exports = { Calculadora, EntradaNoValida, PAQUETE };
