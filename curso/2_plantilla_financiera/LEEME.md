# CONSTRUYE · Plantillas financieras para el software

La plantilla del profesional (`PLANTILLA_LIBRE`), **corregida** y con los impuestos en una hoja de **Parámetros legales**, las **5 plantillas pequeñas** del curso y las **2 plantillas del profesional** del Módulo 1. Todas se calculan con el mismo motor.

- `excel_corregido/`: la plantilla corregida en Excel (18 hojas, cálculo iterativo activado), el simplificado corregido y la Guía de análisis de viabilidad del profesional sobre la plantilla corregida. Es lo que descarga el alumno que compra el extra.
- `plantilla/`: la plantilla corregida para el motor (`plantilla_libre.json`), sus entradas, los parámetros legales, el registro de cambios y el export exacto del original.
- `plantillas_pequenas/`: las 5 plantillas de CONSTRUYE y las 2 del profesional (base de datos de suelo y números gordos), en Excel y, las calculadoras, en JSON. Todo listado en `indice.json`.
- `motor/`: motor de cálculo en Node.js, con el cálculo iterativo de Excel y `setParametros()` para los parámetros legales.
- `pruebas/`: 44 comprobaciones (original frente a Excel y LibreOffice, corregida, parámetros legales, plantillas pequeñas).
- `herramientas/`: los scripts que generan todo lo anterior a partir de los originales.
- `originales/`: los archivos del profesional, sin tocar (los dos primeros y los 3 del 11/11/2026).
- `CAMBIOS.md`: qué se ha corregido, en claro, y lo que falta que decida el profesional.
- `ESPECIFICACION_TECNICA.md`: estructura, entradas, parámetros legales, referencias circulares, validación e integración.

```bash
cd motor
npm install
npm run ejemplo   # calcula una promoción de 24 viviendas con la plantilla corregida
npm test          # repite las 44 comprobaciones (unos 60 s)
```
