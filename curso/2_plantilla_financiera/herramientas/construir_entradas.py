# Genera plantilla/entradas.json (entradas del usuario de la plantilla corregida) a partir de entradas_original.json.
import json,sys
import os
BASE=os.path.join(os.path.dirname(os.path.abspath(__file__)),'..','plantilla')+os.sep

e=json.load(open(BASE+'entradas_original.json'))
CC=['Andalucía','Aragón','Principado de Asturias','Illes Balears','Canarias','Cantabria','Castilla-La Mancha','Castilla y León','Cataluña','Comunitat Valenciana','Extremadura','Galicia','Comunidad de Madrid','Región de Murcia','Comunidad Foral de Navarra','País Vasco','La Rioja','Ciudad Autónoma de Ceuta','Ciudad Autónoma de Melilla']
out=[]
for x in e['entradas']:
    if x['hoja']=='Datos de Partida' and x['celda'] in ('D133','D154'): continue
    if x['hoja']=='Datos de Partida' and x['celda']=='D80':
        x=dict(x,nota='SI: IVA general + AJD de la comunidad. NO: ITP de la comunidad. Los tipos están en la hoja Parámetros legales (los fija el administrador)')
    if x['hoja']=='Datos de Partida' and x['celda']=='D115':
        x=dict(x,etiqueta='Certificación de control de calidad de la urbanización',nota='La usa 3ª Urbanización E47. La etiqueta original decía «OBRA»; corregida')
    out.append(x)
    if x['hoja']=='Datos de Partida' and x['celda']=='D80':
        out.append(dict(seccion='Suelo',hoja='Datos de Partida',celda='D93',etiqueta='Comunidad autónoma del solar',tipo='lista: '+'/'.join(CC),unidad='',defecto='Comunitat Valenciana',nota='Decide el ITP y el AJD que se aplican (hoja Parámetros legales). Casilla nueva',defecto_es_formula=False,opciones=CC))
    if x['hoja']=='Datos de Partida' and x['celda']=='D101':
        out.append(dict(seccion='Construcción',hoja='Datos de Partida',celda='D102',etiqueta='Control de calidad de la obra',tipo='porcentaje',unidad='% sobre construcción',defecto=0.015,nota='Antes estaba fijo al 1,5 % dentro de 3ª Proyectos D53. Casilla nueva',defecto_es_formula=False))
assert any(x['celda']=='D93' for x in out) and any(x['celda']=='D102' for x in out)
e['entradas']=out
e['plantilla']='PLANTILLA_LIBRE (corregida v3)'
e['parametros_legales']='Las celdas de la hoja «Parámetros legales» no son entradas del usuario: las fija el administrador (ver parametros_legales.json) y se aplican a todas las plantillas.'
json.dump(e,open(BASE+'entradas.json','w'),ensure_ascii=False,indent=1)
print(len(e['entradas']))
