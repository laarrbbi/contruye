# CONSTRUYE · Plantillas financieras para el software

La plantilla del profesional (`PLANTILLA_LIBRE`), **corregida** y con los impuestos en una hoja de **Parámetros legales**, y las **5 plantillas pequeñas** del curso. Todas se calculan con el mismo motor.

- `excel_corregido/`: la plantilla corregida en Excel (18 hojas, cálculo iterativo activado) y el simplificado corregido. Es lo que descarga el alumno que compra el extra.
- `plantilla/`: la plantilla corregida para el motor (`plantilla_libre.json`), sus entradas, los parámetros legales, el registro de cambios y el export exacto del original.
- `plantillas_pequenas/`: las 5 plantillas incluidas en el curso, en Excel y en JSON, con `indice.json`.
- `motor/`: motor de cálculo en Node.js, con el cálculo iterativo de Excel y `setParametros()` para los parámetros legales.
- `pruebas/`: 39 comprobaciones (original frente a Excel y LibreOffice, corregida, parámetros legales, plantillas pequeñas).
- `herramientas/`: los scripts que generan todo lo anterior a partir de los originales.
- `originales/`: los dos archivos del profesional, sin tocar.
- `CAMBIOS.md`: qué se ha corregido, en claro, y lo que falta que decida el profesional.
- `ESPECIFICACION_TECNICA.md`: estructura, entradas, parámetros legales, referencias circulares, validación e integración.

```bash
cd motor
npm install
npm run ejemplo   # calcula una promoción de 24 viviendas con la plantilla corregida
npm test          # repite las 39 comprobaciones (unos 60 s)
```
