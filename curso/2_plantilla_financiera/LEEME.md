# CONSTRUYE · Plantilla financiera para el software

La plantilla del profesional (`PLANTILLA_LIBRE.xls` y su versión simplificada), lista para integrarla en la plataforma **sin cambiar ninguna fórmula**.

- `originales/`: los dos archivos originales, sin tocar.
- `plantilla/`: la plantilla exportada celda a celda (30.795 fórmulas), el diccionario de entradas y la vista simplificada.
- `motor/`: motor de cálculo en Node.js que ejecuta las fórmulas originales, con el cálculo iterativo de Excel.
- `pruebas/`: comprobaciones frente a Excel, LibreOffice y el ejemplo del profesional.
- `ESPECIFICACION_TECNICA.md`: el estudio completo de la plantilla. Incluye estructura, entradas, salidas, referencias circulares, parámetros de 2002, incidencias del original y cómo integrarlo.

```bash
cd motor
npm install
npm run ejemplo   # calcula una promoción de 24 viviendas
npm test          # repite todas las comprobaciones
```
