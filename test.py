
# Script Python para generar INSERTs SQL desde el catálogo SIGECOF
# Basado en el PDF "CATEGORIAS DEL SISTEMA.pdf"

import re
from datetime import datetime

# Datos extraídos del PDF (ejemplo con las primeras páginas para demostración)
# En producción, leerías el PDF completo con PyPDF2, pdfplumber o similar

datos_pdf = """
01000-0000     PRODUCTOS ALIMENTICIOS Y AGROPECUARIOS 402010000
01010-0000     ALIMENTOS Y BEBIDAS PARA PERSONAS 402010100
01010-0001     ACEITES VEGETALES 402010100
01010-0002     ACELGAS 402010100
01010-0003     ACHICORIA 402010100
01010-0004     AGUACATES 402010100
01010-0005     AGUARDIENTES 402010100
01010-0006     AGUAS MINERALES 402010100
01010-0007     AJI DULCE 402010100
01010-0008     AJI PICANTE 402010100
01010-0009     AJO PORRO 402010100
01010-0010     AJONJOLI 402010100
01010-0011     AJOS 402010100
01010-0012     ALBAHACA 402010100
01010-0013     ALBARICOQUES 402010100
01010-0014     ALCACHOFAS 402010100
01010-0015     ALCAPARRA 402010100
01010-0016     ALMEJAS 402010100
01010-0017     ALMENDRAS 402010100
01010-0018     ANIS 402010100
01010-0019     ANIS ESTRELLADO 402010100
01010-0020     ANN 402010100
01010-0021     APIO 402010100
01010-0022     APIO ESPANA 402010100
01010-0023     ARENQUES 402010100
01010-0024     ARROZ 402010100
01010-0025     ARVEJAS 402010100
01010-0026     ATUN 402010100
01010-0027     AUYAMA 402010100
01010-0028     AVELLANAS 402010100
01010-0029     AVENA 402010100
01010-0030     AZAFRAN 402010100
01010-0031     AZUCAR 402010100
01010-0032     BACALAO 402010100
01010-0033     BATATA 402010100
01010-0034     BERENJENAS 402010100
01010-0035     BERRO 402010100
01010-0036     BIZCOCHOS 402010100
01010-0037     BOCADILLOS 402010100
01010-0038     BOMBONES 402010100
01010-0039     BRANDY 402010100
01010-0040     BROCOLI 402010100
01010-0041     CACAO 402010100
01010-0042     CAFE 402010100
01020-0000     ALIMENTOS PARA ANIMALES 402010200
01020-0001     AFRECHOS 402010200
01020-0002     ALFALFAS 402010200
01020-0003     ALIMENTOS CONCENTRADOS 402010200
01020-0004     ALPISTES 402010200
01020-0005     AVENA 402010200
01020-0006     CEBADA 402010200
01020-0007     CENTENO 402010200
01020-0008     GRANOS 402010200
01020-0009     HARINAS DE CARNES Y PESCADO 402010200
01020-0010     HENO 402010200
01020-0011     LECHE 402010200
01020-0012     LEGUMBRES 402010200
01020-0013     MAZ 402010200
01020-0014     MELAZA 402010200
01020-0015     PASTOS 402010200
01020-0016     PIENSO 402010200
01020-0017     PREPARADOS (MARCA COMERCIAL) 402010200
01020-0018     REMOLACHAS 402010200
01020-0019     SAL 402010200
01030-0000     PRODUCTOS AGRICOLAS Y PECUARIOS 402010300
01030-0001     ABONOS PREPARADOS 402010300
01030-0002     ACIDO DE FOSFATO 402010300
01030-0003     ACIDO FOSFRICO 402010300
02000-0000     PRODUCTO DE MINAS Y CANTERAS 402020000
02010-0000     CARBN MINERAL 402020100
02020-0000     PETRLEO CRUDO Y GAS NATURAL 402020200
02030-0000     MINERAL DE HIERRO 402020300
02040-0000     MINERAL NO FERROSO 402020400
02050-0000     PIEDRA, ARCILLA Y ARENA 402020500
02060-0000     MINERAL PARA LA FABRICACIN DE PRODUCTOS QUMICOS 402020600
02070-0000     SAL INDUSTRIAL 402020700
02990-0000     OTROS PRODUCTOS DE MINAS Y CANTERAS 402029900
03000-0000     TEXTILES Y VESTUARIOS 402030000
03010-0000     TEXTILES 402030100
03020-0000     PRENDAS DE VESTIR 402030200
03020-0001     ABALORIOS 402030200
03020-0002     BARBOQUEJOS 402030200
03020-0003     BOTONES 402030200
"""

def parse_linea(linea):
    """Parsea una línea del PDF extrayendo código, denominación y cuenta presupuestaria"""
    # Limpiar la línea
    linea = linea.strip()
    if not linea:
        return None
    
    # Patrón para capturar: CÓDIGO (con o sin guión) + DENOMINACIÓN + CUENTA (9 dígitos)
    # Ejemplo: 01000-0000     PRODUCTOS ALIMENTICIOS Y AGROPECUARIOS 402010000
    patron = r'^(\d{5}(?:-\d{4})?)\s+(.+?)\s+(\d{9})$'
    match = re.match(patron, linea)
    
    if match:
        codigo = match.group(1)
        denominacion = match.group(2).strip()
        cuenta = match.group(3)
        return {
            'codigo': codigo,
            'nombre': denominacion,
            'cuenta_presupuestaria': cuenta
        }
    return None

def determinar_nivel(codigo):
    """Determina el nivel jerárquico del código"""
    if '-' not in codigo:
        # Código de 5 dígitos: categoría principal (nivel 0)
        return 0, None
    else:
        partes = codigo.split('-')
        base = partes[0]  # Los primeros 5 dígitos
        subcodigo = partes[1]  # Los últimos 4 dígitos
        
        if subcodigo == '0000':
            # Subcategoría (nivel 1)
            return 1, base
        else:
            # Item específico (nivel 2)
            return 2, f"{base}-0000"

def generar_sql_inserts(datos_texto):
    """Genera los statements SQL INSERT"""
    
    registros = []
    ids_generados = {}  # Mapeo de código -> id
    id_counter = 1
    
    # Primera pasada: crear todos los registros y asignar IDs
    for linea in datos_texto.strip().split('\n'):
        datos = parse_linea(linea)
        if datos:
            nivel, padre_codigo = determinar_nivel(datos['codigo'])
            
            registro = {
                'id': id_counter,
                'nombre': datos['nombre'],
                'codigo': datos['codigo'],
                'descripcion': f"Cuenta Presupuestaria: {datos['cuenta_presupuestaria']}",
                'nivel': nivel,
                'padre_codigo': padre_codigo,
                'codigo_raw': datos['codigo']
            }
            
            ids_generados[datos['codigo']] = id_counter
            registros.append(registro)
            id_counter += 1
    
    # Segunda pasada: asignar categoria_padre_id
    for reg in registros:
        if reg['padre_codigo'] and reg['padre_codigo'] in ids_generados:
            reg['categoria_padre_id'] = ids_generados[reg['padre_codigo']]
        else:
            reg['categoria_padre_id'] = 'NULL'
    
    # Generar SQL
    sql_statements = []
    sql_statements.append("-- Script SQL generado automáticamente desde Catálogo SIGECOF")
    sql_statements.append(f"-- Fecha: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    sql_statements.append("")
    sql_statements.append("SET FOREIGN_KEY_CHECKS = 0;")
    sql_statements.append("TRUNCATE TABLE `categorias`;")
    sql_statements.append("SET FOREIGN_KEY_CHECKS = 1;")
    sql_statements.append("")
    
    # Insertar primero los de nivel 0, luego nivel 1, luego nivel 2
    for nivel in [0, 1, 2]:
        for reg in registros:
            if reg['nivel'] == nivel:
                nombre_escaped = reg['nombre'].replace("'", "''")
                descripcion_escaped = reg['descripcion'].replace("'", "''")
                
                sql = f"""INSERT INTO `categorias` (`id`, `nombre`, `codigo`, `descripcion`, `categoria_padre_id`, `activo`, `fecha_creacion`, `fecha_actualizacion`) 
VALUES ({reg['id']}, '{nombre_escaped}', '{reg['codigo']}', '{descripcion_escaped}', {reg['categoria_padre_id']}, 1, NOW(), NOW());"""
                
                sql_statements.append(sql)
    
    sql_statements.append("")
    sql_statements.append(f"-- Total registros insertados: {len(registros)}")
    
    return '\n'.join(sql_statements), registros

# Ejecutar
sql_output, registros_procesados = generar_sql_inserts(datos_pdf)

# Mostrar resumen
print(f"Total de registros procesados: {len(registros_procesados)}")
print(f"\nPrimeros 10 registros:")
for reg in registros_procesados[:10]:
    padre = reg['categoria_padre_id'] if reg['categoria_padre_id'] != 'NULL' else 'NULL'
    print(f"ID: {reg['id']:3} | Código: {reg['codigo']:12} | Nivel: {reg['nivel']} | Padre: {padre:4} | {reg['nombre'][:50]}")

print(f"\n{'='*80}")
print("SQL GENERADO (primeras 20 líneas):")
print(f"{'='*80}")
print('\n'.join(sql_output.split('\n')[:20]))
print("\n... [continúa con más inserts] ...")
