import logging
import mysql.connector
from datetime import datetime, timedelta
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, ContextTypes, ConversationHandler, MessageHandler, filters, CallbackQueryHandler
import pytz

# Configuración de logging
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

# Estados de la conversación
SELECTING_ACTION, SELECTING_IDENTIFICATION, SELECTING_DATE_RANGE = range(3)

# 🔧 CONFIGURACIÓN DE LA BASE DE DATOS LOCAL
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'bd_inti'
}

# IDs de Telegram permitidos
ALLOWED_USER_IDS = [
    1796586571, #IRVING COELLO
    5533587155, #RICHARD MOLINA
    1994641948, #DIXON VELIZ
    5482898999


    ]

# Conexión a la base de datos
def get_db_connection():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except mysql.connector.Error as err:
        logger.error(f"Error de conexión a MySQL: {err}")
        return None

# Obtener la hora actual de Venezuela
def get_venezuela_time():
    tz = pytz.timezone('America/Caracas')
    return datetime.now(tz)

# Obtener la última actualización de la base de datos
def get_last_db_update():
    conn = get_db_connection()
    if conn is None:
        return get_venezuela_time()

    try:
        cursor = conn.cursor(dictionary=True)

        queries = [
            "SELECT MAX(creado_en) as ultima FROM personas_naturales",
            "SELECT MAX(creado_en) as ultima FROM personas_juridicas",
            "SELECT MAX(creado_en) as ultima FROM colectivos",
            "SELECT MAX(creado_en) as ultima FROM solicitudes",
            "SELECT MAX(fecha_accion) as ultima FROM bitacora"
        ]

        last_update = None
        for query in queries:
            cursor.execute(query)
            result = cursor.fetchone()
            if result and result['ultima']:
                if last_update is None or result['ultima'] > last_update:
                    last_update = result['ultima']

        if last_update is None:
            last_update = get_venezuela_time()

        return last_update

    except Exception as e:
        logger.error(f"Error al obtener última actualización: {e}")
        return get_venezuela_time()
    finally:
        cursor.close()
        conn.close()

# Agregar información de actualización al mensaje
def add_update_info(message):
    last_update = get_last_db_update()
    if isinstance(last_update, datetime):
        update_str = last_update.strftime('%d/%m/%Y %H:%M:%S')
    else:
        update_str = str(last_update)

    return f"{message}\n\n🕒 Base de datos actualizada hasta: {update_str} (Hora Venezuela)"

# Verificar si el usuario está permitido
def is_user_allowed(user_id):
    return user_id in ALLOWED_USER_IDS

# Comando /start
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user = update.effective_user

    if not is_user_allowed(user.id):
        await update.message.reply_text("❌ No tienes permiso para usar este bot.")
        return

    welcome_message = (
        f"Hola {user.first_name}! 👋\n\n"
        "Soy un bot para consultar información del sistema INTI.\n\n"
        "Selecciona una opción:"
    )

    keyboard = [
        [InlineKeyboardButton("🔍 Buscar Solicitante", callback_data='solicitantes')],
        [InlineKeyboardButton("📋 Consultar Solicitudes", callback_data='solicitudes')],
        [InlineKeyboardButton("📊 Generar Reportes", callback_data='reportes')],
        [InlineKeyboardButton("ℹ️ Información del Sistema", callback_data='info')]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    await update.message.reply_text(add_update_info(welcome_message), reply_markup=reply_markup)

# Manejar botones inline
async def handle_button(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer()

    if not is_user_allowed(query.from_user.id):
        await query.edit_message_text("❌ No tienes permiso para usar este bot.")
        return

    if query.data == 'solicitantes':
        await query.edit_message_text(add_update_info("🔍 Por favor, ingresa la cédula o RIF del solicitante que deseas buscar:"))
        context.user_data['action'] = 'solicitantes'
        return SELECTING_IDENTIFICATION
    elif query.data == 'solicitudes':
        await query.edit_message_text(add_update_info("📋 Por favor, ingresa la cédula o RIF para consultar sus solicitudes:"))
        context.user_data['action'] = 'solicitudes'
        return SELECTING_IDENTIFICATION
    elif query.data == 'reportes':
        hoy = datetime.now()
        hace_30_dias = hoy - timedelta(days=30)
        rango_fechas = f"{hace_30_dias.strftime('%d/%m/%Y')} - {hoy.strftime('%d/%m/%Y')}"

        await query.edit_message_text(
            add_update_info(
                f"📊 Por favor, ingresa el rango de fechas para generar el reporte.\n"
                f"Formato: DD/MM/YYYY - DD/MM/YYYY\n\n"
                f"Ejemplo: {rango_fechas}\n\n"
                f"Puedes copiar y pegar este rango: {rango_fechas}"
            )
        )
        context.user_data['action'] = 'reportes'
        return SELECTING_DATE_RANGE
    elif query.data == 'info':
        await info_callback(update, context)
    elif query.data == 'menu':
        await volver_menu(update, context)

# Procesar identificación para búsqueda de solicitantes o solicitudes
async def process_identification(update: Update, context: ContextTypes.DEFAULT_TYPE):
    identificacion = update.message.text.strip()
    action = context.user_data.get('action', '')

    if not is_user_allowed(update.effective_user.id):
        await update.message.reply_text("❌ No tienes permiso para usar este bot.")
        return ConversationHandler.END

    if action == 'solicitantes':
        await buscar_solicitante(update, identificacion)
    elif action == 'solicitudes':
        await consultar_solicitudes(update, identificacion)

    return ConversationHandler.END

# Buscar solicitante por cédula/RIF
async def buscar_solicitante(update: Update, identificacion: str):
    conn = get_db_connection()
    if conn is None:
        await update.message.reply_text(add_update_info("❌ Error de conexión a la base de datos."))
        return

    try:
        cursor = conn.cursor(dictionary=True)

        response = f"🔍 Resultados para: {identificacion}\n\n"
        encontrado = False

        # Persona Natural
        cursor.execute("""
            SELECT pn.*, r.primer_nombre as rep_nombre, r.primer_apellido as rep_apellido,
                   r.telefono as rep_telefono, r.email as rep_email
            FROM personas_naturales pn
            LEFT JOIN representantes r ON pn.id_representante = r.id_representante
            WHERE pn.cedula = %s AND pn.activo = 1
        """, (identificacion,))
        persona_natural = cursor.fetchone()
        if persona_natural:
            encontrado = True
            response += "👤 Persona Natural:\n"
            response += f"   📝 Nombre: {persona_natural['primer_nombre']} {persona_natural.get('segundo_nombre', '')} {persona_natural['primer_apellido']} {persona_natural.get('segundo_apellido', '')}\n"
            response += f"   🆔 Cédula: {persona_natural['cedula']}\n"
            response += f"   📞 Teléfono: {persona_natural['telefono']}\n"
            response += f"   🏠 Dirección: {persona_natural['direccion_habitacion']}\n"
            response += f"   👫 Estado Civil: {persona_natural['estado_civil']}\n"
            response += f"   👶 N° Hijos: {persona_natural['numero_hijos']}\n"
            response += f"   🎓 Grado Instrucción: {persona_natural['grado_instruccion'].replace('_', ' ').title()}\n"
            response += f"   📖 Sabe Leer: {persona_natural['sabe_leer']}\n"
            response += f"   💰 Ayuda Económica: {persona_natural['posee_ayuda_economica']}\n"
            response += f"   💼 Trabaja: {persona_natural['trabaja_actualmente']}\n"
            response += f"   🏘️ Pertenece a Comuna: {persona_natural['pertenece_comuna']}\n"
            response += f"   🏥 Enfermedades: {persona_natural['enfermedades'] or 'Ninguna'}\n"

            if persona_natural['rep_nombre']:
                response += f"   👔 Representante: {persona_natural['rep_nombre']} {persona_natural['rep_apellido']}\n"
                response += f"   📞 Teléfono Representante: {persona_natural['rep_telefono']}\n"
                response += f"   📧 Email Representante: {persona_natural['rep_email']}\n"

            response += "\n"

        # Persona Jurídica
        cursor.execute("""
            SELECT pj.*, r.primer_nombre as rep_nombre, r.primer_apellido as rep_apellido,
                   r.telefono as rep_telefono, r.email as rep_email, r.profesion as rep_profesion
            FROM personas_juridicas pj
            LEFT JOIN representantes r ON pj.id_representante = r.id_representante
            WHERE pj.rif = %s AND pj.activo = 1
        """, (identificacion,))
        persona_juridica = cursor.fetchone()
        if persona_juridica:
            encontrado = True
            response += "🏢 Persona Jurídica:\n"
            response += f"   📝 Razón Social: {persona_juridica['razon_social']}\n"
            response += f"   🆔 RIF: {persona_juridica['rif']}\n"
            response += f"   📞 Teléfono: {persona_juridica['telefono']}\n"
            response += f"   🏠 Dirección: {persona_juridica['direccion_habitacion']}\n"
            response += f"   👫 Estado Civil: {persona_juridica['estado_civil']}\n"
            response += f"   👶 N° Hijos: {persona_juridica['numero_hijos']}\n"
            response += f"   🎓 Grado Instrucción: {persona_juridica['grado_instruccion'].replace('_', ' ').title()}\n"
            response += f"   📖 Sabe Leer: {persona_juridica['sabe_leer']}\n"
            response += f"   💰 Ayuda Económica: {persona_juridica['posee_ayuda_economica']}\n"
            response += f"   💼 Trabaja: {persona_juridica['trabaja_actualmente']}\n"
            response += f"   🏘️ Pertenece a Comuna: {persona_juridica['pertenece_comuna']}\n"
            response += f"   🏥 Enfermedades: {persona_juridica['enfermedades'] or 'Ninguna'}\n"

            if persona_juridica['rep_nombre']:
                response += f"   👔 Representante Legal: {persona_juridica['rep_nombre']} {persona_juridica['rep_apellido']}\n"
                response += f"   📞 Teléfono Representante: {persona_juridica['rep_telefono']}\n"
                response += f"   📧 Email Representante: {persona_juridica['rep_email']}\n"
                response += f"   🎓 Profesión Representante: {persona_juridica['rep_profesion']}\n"

            response += "\n"

        # Colectivo
        cursor.execute("SELECT * FROM colectivos WHERE rif_o_ci_referente = %s AND activo = 1", (identificacion,))
        colectivo = cursor.fetchone()
        if colectivo:
            encontrado = True
            response += "👥 Colectivo:\n"
            response += f"   📝 Nombre: {colectivo['nombre_colectivo']}\n"
            response += f"   🆔 Referente: {colectivo['rif_o_ci_referente']}\n"
            response += f"   📞 Teléfono: {colectivo['telefono']}\n"
            response += f"   👥 Integrantes: {colectivo['numero_integrantes']}\n"
            response += f"   🏠 Dirección: {colectivo['direccion_habitacion']}\n\n"

            cursor.execute("SELECT * FROM colectivo_integrantes WHERE rif_o_ci_colectivo = %s AND activo = 1", (identificacion,))
            integrantes = cursor.fetchall()
            if integrantes:
                response += "   👤 Integrantes:\n"
                for i, integrante in enumerate(integrantes, 1):
                    response += f"      {i}. {integrante['primer_nombre']} {integrante.get('segundo_nombre', '')} {integrante['primer_apellido']} {integrante.get('segundo_apellido', '')}\n"
                    response += f"         🆔 Cédula: {integrante['cedula']}\n"
                    response += f"         📞 Teléfono: {integrante['telefono']}\n"
                    response += f"         👫 Sexo: {'Masculino' if integrante['sexo'] == 'M' else 'Femenino' if integrante['sexo'] == 'F' else 'Otro'}\n"
                    response += f"         🎂 Fecha Nacimiento: {integrante['fecha_nacimiento']}\n"
                    if integrante['es_referente']:
                        response += f"         👑 Referente del Colectivo\n"
                    response += "\n"

        # Representante
        cursor.execute("SELECT * FROM representantes WHERE id_representante = %s AND activo = 1", (identificacion,))
        representante = cursor.fetchone()
        if representante:
            encontrado = True
            response += "👔 Representante:\n"
            response += f"   📝 Nombre: {representante['primer_nombre']} {representante.get('segundo_nombre', '')} {representante['primer_apellido']} {representante.get('segundo_apellido', '')}\n"
            response += f"   🆔 ID: {representante['id_representante']}\n"
            response += f"   📞 Teléfono: {representante['telefono']}\n"
            response += f"   📧 Email: {representante['email']}\n"
            response += f"   🎓 Profesión: {representante['profesion']}\n"
            response += f"   🏠 Dirección: {representante['direccion']}\n"
            response += f"   👫 Tipo: {representante['tipo'].replace('_', ' ').title() if representante['tipo'] else 'No especificado'}\n\n"

        if not encontrado:
            response = add_update_info(f"❌ No se encontraron resultados para: {identificacion}\n\n"
                                      "⚠️ Posibles causas:\n"
                                      "• El solicitante no está registrado en el sistema\n"
                                      "• La cédula/RIF puede tener un formato incorrecto\n"
                                      "• El solicitante puede estar marcado como inactivo")

            keyboard = [
                [InlineKeyboardButton("🔄 Intentar con otro ID", callback_data='solicitantes')],
                [InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]
            ]
            reply_markup = InlineKeyboardMarkup(keyboard)
            await update.message.reply_text(response, reply_markup=reply_markup)
            return

        else:
            response = add_update_info(response)

        keyboard = [[InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(response, reply_markup=reply_markup)

    except Exception as e:
        error_response = add_update_info(f"❌ Error en la búsqueda: {str(e)}\n\n"
                                        "⚠️ Por favor, verifica el formato de la cédula/RIF e intenta nuevamente.")

        keyboard = [
            [InlineKeyboardButton("🔄 Intentar nuevamente", callback_data='solicitantes')],
            [InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(error_response, reply_markup=reply_markup)
    finally:
        if 'cursor' in locals(): cursor.close()
        if 'conn' in locals(): conn.close()

# Consultar solicitudes por cédula/RIF
async def consultar_solicitudes(update: Update, identificacion: str):
    conn = get_db_connection()
    if conn is None:
        await update.message.reply_text(add_update_info("❌ Error de conexión a la base de datos."))
        return

    try:
        cursor = conn.cursor(dictionary=True)

        query = """
        SELECT s.*,
               CASE
                   WHEN s.tipo_solicitante = 'N' THEN CONCAT(pn.primer_nombre, ' ', pn.primer_apellido)
                   WHEN s.tipo_solicitante = 'J' THEN pj.razon_social
                   WHEN s.tipo_solicitante = 'C' THEN c.nombre_colectivo
               END AS nombre_solicitante,
               tp.nombre_procedimiento,
               p.nombre_predio
        FROM solicitudes s
        LEFT JOIN personas_naturales pn ON s.cedula_solicitante_n = pn.cedula
        LEFT JOIN personas_juridicas pj ON s.rif_solicitante_j = pj.rif
        LEFT JOIN colectivos c ON s.rif_ci_solicitante_c = c.rif_o_ci_referente
        LEFT JOIN tipo_procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
        LEFT JOIN predios p ON s.id_predio = p.id_predio
        WHERE s.cedula_solicitante_n = %s OR s.rif_solicitante_j = %s OR s.rif_ci_solicitante_c = %s
        ORDER BY s.fecha_solicitud DESC
        """
        cursor.execute(query, (identificacion, identificacion, identificacion))
        solicitudes = cursor.fetchall()

        if not solicitudes:
            response = add_update_info(f"❌ No se encontraron solicitudes para: {identificacion}\n\n"
                                      "⚠️ Posibles causas:\n"
                                      "• El solicitante no tiene solicitudes registradas\n"
                                      "• La cédula/RIF puede tener un formato incorrecto\n"
                                      "• Las solicitudes pueden estar en otro estado")

            keyboard = [
                [InlineKeyboardButton("🔍 Buscar otro solicitante", callback_data='solicitudes')],
                [InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]
            ]
            reply_markup = InlineKeyboardMarkup(keyboard)
            await update.message.reply_text(response, reply_markup=reply_markup)
            return

        else:
            response = f"📋 Solicitudes para: {identificacion}\n\n"
            for i, sol in enumerate(solicitudes, 1):
                response += f"{i}. 📋 {sol['numero_solicitud']}\n"
                response += f"   👤 Solicitante: {sol['nombre_solicitante']}\n"
                response += f"   📝 Procedimiento: {sol['nombre_procedimiento']}\n"
                response += f"   🏞️ Predio: {sol['nombre_predio']}\n"
                response += f"   📅 Fecha: {sol['fecha_solicitud'].strftime('%d/%m/%Y')}\n"
                response += f"   🟢 Estado: {sol['estatus'].replace('_', ' ').title()}\n"
                response += f"   📝 Observaciones: {sol['observaciones'] or 'Ninguna'}\n\n"

            response = add_update_info(response)

        keyboard = [[InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(response, reply_markup=reply_markup)

    except Exception as e:
        error_response = add_update_info(f"❌ Error en la consulta: {str(e)}\n\n"
                                        "⚠️ Por favor, verifica el formato de la cédula/RIF e intenta nuevamente.")

        keyboard = [
            [InlineKeyboardButton("🔄 Intentar nuevamente", callback_data='solicitudes')],
            [InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(error_response, reply_markup=reply_markup)
    finally:
        if 'cursor' in locals(): cursor.close()
        if 'conn' in locals(): conn.close()

# Procesar rango de fechas para reportes
async def process_date_range(update: Update, context: ContextTypes.DEFAULT_TYPE):
    user_input = update.message.text
    try:
        fecha_inicio_str, fecha_fin_str = user_input.split('-')
        fecha_inicio = datetime.strptime(fecha_inicio_str.strip(), '%d/%m/%Y')
        fecha_fin = datetime.strptime(fecha_fin_str.strip(), '%d/%m/%Y')

        conn = get_db_connection()
        if conn is None:
            await update.message.reply_text(add_update_info("❌ Error de conexión a la base de datos."))
            return ConversationHandler.END

        cursor = conn.cursor(dictionary=True)

        # Solicitudes en rango
        query_solicitudes = """
        SELECT s.*,
               CASE
                   WHEN s.tipo_solicitante = 'N' THEN CONCAT(pn.primer_nombre, ' ', pn.primer_apellido)
                   WHEN s.tipo_solicitante = 'J' THEN pj.razon_social
                   WHEN s.tipo_solicitante = 'C' THEN c.nombre_colectivo
               END AS nombre_solicitante,
               tp.nombre_procedimiento,
               p.nombre_predio
        FROM solicitudes s
        LEFT JOIN personas_naturales pn ON s.cedula_solicitante_n = pn.cedula
        LEFT JOIN personas_juridicas pj ON s.rif_solicitante_j = pj.rif
        LEFT JOIN colectivos c ON s.rif_ci_solicitante_c = c.rif_o_ci_referente
        LEFT JOIN tipo_procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
        LEFT JOIN predios p ON s.id_predio = p.id_predio
        WHERE s.fecha_solicitud BETWEEN %s AND %s
        ORDER BY s.fecha_solicitud DESC
        """
        cursor.execute(query_solicitudes, (fecha_inicio.date(), fecha_fin.date()))
        solicitudes = cursor.fetchall()

        # Estadísticas por estado
        query_estado = """
        SELECT estatus, COUNT(*) as cantidad
        FROM solicitudes
        WHERE fecha_solicitud BETWEEN %s AND %s
        GROUP BY estatus
        """
        cursor.execute(query_estado, (fecha_inicio.date(), fecha_fin.date()))
        reporte_estado = cursor.fetchall()

        # Estadísticas por tipo de solicitante
        query_tipo = """
        SELECT tipo_solicitante, COUNT(*) as cantidad
        FROM solicitudes
        WHERE fecha_solicitud BETWEEN %s AND %s
        GROUP BY tipo_solicitante
        """
        cursor.execute(query_tipo, (fecha_inicio.date(), fecha_fin.date()))
        reporte_tipo = cursor.fetchall()

        # Estadísticas por procedimiento
        query_procedimiento = """
        SELECT tp.nombre_procedimiento, COUNT(*) as cantidad
        FROM solicitudes s
        JOIN tipo_procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
        WHERE s.fecha_solicitud BETWEEN %s AND %s
        GROUP BY tp.nombre_procedimiento
        """
        cursor.execute(query_procedimiento, (fecha_inicio.date(), fecha_fin.date()))
        reporte_procedimiento = cursor.fetchall()

        response = f"📊 Reporte del {fecha_inicio_str.strip()} al {fecha_fin_str.strip()}:\n\n"

        if solicitudes:
            response += "📋 Todas las solicitudes:\n"
            for i, sol in enumerate(solicitudes, 1):
                response += f"{i}. {sol['numero_solicitud']} - {sol['nombre_solicitante']} - {sol['estatus'].replace('_', ' ').title()}\n"
            response += "\n"

        response += "📈 Estadísticas por Estado:\n"
        for item in reporte_estado:
            estado = item['estatus'].replace('_', ' ').title()
            response += f"   {estado}: {item['cantidad']}\n"

        response += "\n📈 Por Tipo de Solicitante:\n"
        tipo_map = {'N': 'Natural', 'J': 'Jurídica', 'C': 'Colectivo'}
        for item in reporte_tipo:
            tipo = tipo_map.get(item['tipo_solicitante'], item['tipo_solicitante'])
            response += f"   {tipo}: {item['cantidad']}\n"

        response += "\n📈 Por Procedimiento:\n"
        for item in reporte_procedimiento:
            response += f"   {item['nombre_procedimiento']}: {item['cantidad']}\n"

        total = sum(item['cantidad'] for item in reporte_estado)
        response += f"\n📦 Total de solicitudes: {total}"

        response = add_update_info(response)

        keyboard = [[InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(response, reply_markup=reply_markup)

    except ValueError:
        error_response = add_update_info(
            "❌ Formato de fecha incorrecto. Por favor, usa el formato: DD/MM/YYYY - DD/MM/YYYY\n\n"
            "Ejemplo: 01/09/2024 - 15/09/2024\n\n"
            "⚠️ Asegúrate de usar el formato correcto con guiones y sin espacios adicionales."
        )

        keyboard = [
            [InlineKeyboardButton("🔄 Intentar nuevamente", callback_data='reportes')],
            [InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(error_response, reply_markup=reply_markup)
        return SELECTING_DATE_RANGE
    except Exception as e:
        error_response = add_update_info(f"❌ Error al generar el reporte: {str(e)}\n\n"
                                        "⚠️ Por favor, verifica el formato de las fechas e intenta nuevamente.")

        keyboard = [
            [InlineKeyboardButton("🔄 Intentar nuevamente", callback_data='reportes')],
            [InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(error_response, reply_markup=reply_markup)
    finally:
        if 'cursor' in locals(): cursor.close()
        if 'conn' in locals(): conn.close()

    return ConversationHandler.END

# Información del sistema
async def info_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer("ℹ️ Obteniendo información del sistema...")

    conn = get_db_connection()
    if conn is None:
        await query.edit_message_text(add_update_info("❌ Error de conexión a la base de datos."))
        return

    try:
        cursor = conn.cursor(dictionary=True)

        cursor.execute("SELECT COUNT(*) as total FROM personas_naturales WHERE activo = 1")
        total_naturales = cursor.fetchone()['total']

        cursor.execute("SELECT COUNT(*) as total FROM personas_juridicas WHERE activo = 1")
        total_juridicas = cursor.fetchone()['total']

        cursor.execute("SELECT COUNT(*) as total FROM colectivos WHERE activo = 1")
        total_colectivos = cursor.fetchone()['total']

        cursor.execute("SELECT COUNT(*) as total FROM solicitudes")
        total_solicitudes = cursor.fetchone()['total']

        cursor.execute("SELECT MAX(fecha_solicitud) as ultima FROM solicitudes")
        ultima_solicitud = cursor.fetchone()['ultima']

        response = (
            "ℹ️ Información del Sistema INTI:\n\n"
            f"👤 Personas naturales: {total_naturales}\n"
            f"🏢 Personas jurídicas: {total_juridicas}\n"
            f"👥 Colectivos: {total_colectivos}\n"
            f"📋 Total de solicitudes: {total_solicitudes}\n"
            f"📅 Última solicitud registrada: {ultima_solicitud.strftime('%d/%m/%Y') if ultima_solicitud else 'N/A'}\n"
        )

        response = add_update_info(response)

        keyboard = [[InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await query.edit_message_text(response, reply_markup=reply_markup)

    except Exception as e:
        await query.edit_message_text(add_update_info(f"❌ Error: {str(e)}"))
    finally:
        if 'cursor' in locals(): cursor.close()
        if 'conn' in locals(): conn.close()

# Volver al menú principal
async def volver_menu(update: Update, context: ContextTypes.DEFAULT_TYPE):
    query = update.callback_query
    await query.answer("🔙 Volviendo al menú principal...")

    if not is_user_allowed(query.from_user.id):
        await query.edit_message_text("❌ No tienes permiso para usar este bot.")
        return

    welcome_message = (
        f"Hola {query.from_user.first_name}! 👋\n\n"
        "Soy un bot para consultar información del sistema INTI.\n\n"
        "Selecciona una opción:"
    )

    keyboard = [
        [InlineKeyboardButton("🔍 Buscar Solicitante", callback_data='solicitantes')],
        [InlineKeyboardButton("📋 Consultar Solicitudes", callback_data='solicitudes')],
        [InlineKeyboardButton("📊 Generar Reportes", callback_data='reportes')],
        [InlineKeyboardButton("ℹ️ Información del Sistema", callback_data='info')]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)

    await query.edit_message_text(add_update_info(welcome_message), reply_markup=reply_markup)

# Cancelar conversación
async def cancel(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if update.message:
        await update.message.reply_text(add_update_info("Operación cancelada."))
    return ConversationHandler.END

# Manejar mensajes no reconocidos
async def unknown(update: Update, context: ContextTypes.DEFAULT_TYPE):
    if not is_user_allowed(update.effective_user.id):
        return

    response = add_update_info("❌ Comando no reconocido.\n\n"
                              "Por favor, usa los botones del menú o escribe /start para volver al menú principal.")

    keyboard = [[InlineKeyboardButton("🔙 Volver al Menú Principal", callback_data='menu')]]
    reply_markup = InlineKeyboardMarkup(keyboard)

    await update.message.reply_text(response, reply_markup=reply_markup)

# Función principal
# ... (todo el código anterior permanece igual hasta aquí) ...

def main():
    application = Application.builder().token('8439056768:AAFfXBOB8Vxz-lQ2MVzJCnYu8_UxmKav4OY').build()

    conv_handler = ConversationHandler(
        entry_points=[CallbackQueryHandler(handle_button)],
        states={
            SELECTING_IDENTIFICATION: [MessageHandler(filters.TEXT & ~filters.COMMAND, process_identification)],
            SELECTING_DATE_RANGE: [MessageHandler(filters.TEXT & ~filters.COMMAND, process_date_range)]
        },
        fallbacks=[
            CommandHandler('cancel', cancel),
            CallbackQueryHandler(volver_menu, pattern='^menu$'),
            CallbackQueryHandler(handle_button)
        ],
        per_user=True,
        conversation_timeout=300,  # 👈 ¡CORREGIDO! (sin 'a' extra)
    )

    application.add_handler(CommandHandler("start", start))
    application.add_handler(conv_handler)
    application.add_handler(CallbackQueryHandler(volver_menu, pattern='^menu$'))
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, unknown))

    logger.info("Bot iniciado. Esperando comandos...")
    application.run_polling()

if __name__ == '__main__':
    main()