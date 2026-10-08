<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish strings for AI Forum Assistant.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aiforumassist:manage'] = 'Configurar AI Forum Assistant en un curso y revisar lo que prepara';
$string['ainote'] = 'Preparada con ayuda de IA.';
$string['answer_sources'] = 'Más información en:';
$string['draft_alreadydecided'] = 'Esto ya se publicó o se descartó.';
$string['draft_askedby'] = 'Pregunta de {$a->name}, {$a->time}';
$string['draft_discard'] = 'Descartar';
$string['draft_discarded'] = 'Descartado.';
$string['draft_intro_answer'] = 'Comprueba la respuesta y sus fuentes antes de publicarla: saldrá con tu nombre.';
$string['draft_intro_reminder'] = 'Las fechas y los enlaces salen del calendario del curso. Se publicará con tu nombre.';
$string['draft_message'] = 'Mensaje';
$string['draft_opendiscussion'] = 'Abrir la discusión';
$string['draft_postgone'] = 'El mensaje del alumno ya no existe.';
$string['draft_publish'] = 'Publicar con mi nombre';
$string['draft_published'] = 'Publicado en el foro.';
$string['draft_title'] = 'Revisar';
$string['draft_writeyourself'] = 'Puedes escribir aquí tu respuesta, o contestar en el foro y descartar esto.';
$string['error_retry'] = 'El proveedor de IA no está disponible ahora mismo; la pregunta del mensaje {$a} se volverá a intentar más tarde.';
$string['kind_answer'] = 'Respuesta preparada';
$string['kind_question'] = 'Pregunta para ti';
$string['kind_reminder'] = 'Recordatorio semanal';
$string['messageprovider:pending'] = 'Borradores y preguntas que te esperan en AI Forum Assistant';
$string['notavailable'] = 'AI Forum Assistant no está disponible en este curso. Consulta con la administración del sitio.';
$string['notify_answer_body'] = 'Un alumno ha preguntado algo en {$a->course} y la IA ha preparado una respuesta. Revísala antes de publicarla.';
$string['notify_answer_subject'] = 'Tiko ha preparado una respuesta en {$a->course}';
$string['notify_question_body'] = 'Un alumno ha preguntado algo en {$a->course} que el asistente no ha podido responder.';
$string['notify_question_subject'] = 'Una pregunta para ti en {$a->course}';
$string['notify_reminder_body'] = 'El recordatorio semanal de {$a->course} espera tu aprobación.';
$string['notify_reminder_subject'] = 'El recordatorio semanal de {$a->course} está listo';
$string['panel'] = 'Panel de AI Forum Assistant';
$string['panel_empty'] = 'No hay nada aquí.';
$string['panel_from'] = 'Alumno';
$string['panel_index'] = 'Materiales del curso indexados: {$a->fragments} fragmentos ({$a->time}).';
$string['panel_index_never'] = 'Los materiales del curso aún no se han indexado.';
$string['panel_reindex'] = 'Indexar ahora';
$string['panel_reindexed'] = 'Materiales del curso indexados: {$a->fragments} fragmentos de {$a->sources} fuentes.';
$string['panel_review'] = 'Revisar';
$string['panel_settings'] = 'Ajustes';
$string['panel_show_all'] = 'Todo';
$string['panel_show_pending'] = 'Pendiente de ti';
$string['panel_state_off'] = 'El asistente no prepara respuestas en este curso.';
$string['panel_state_on'] = 'El asistente prepara respuestas en {$a} foro(s) de este curso.';
$string['panel_what'] = 'Qué';
$string['panel_when'] = 'Cuándo';
$string['pluginname'] = 'AI Forum Assistant';
$string['privacy:metadata:ai_provider'] = 'Para preparar una respuesta se envían la discusión del foro y fragmentos de los materiales del curso al proveedor de IA configurado en el subsistema de IA de Moodle.';
$string['privacy:metadata:ai_provider:posttext'] = 'El texto de los mensajes de la discusión, con los nombres de los alumnos sustituidos si el ajuste del sitio está activado.';
$string['privacy:metadata:course'] = 'Ajustes del asistente en cada curso.';
$string['privacy:metadata:course:usermodified'] = 'El profesor que cambió los ajustes por última vez.';
$string['privacy:metadata:draft'] = 'Lo que el asistente prepara para el profesorado a partir de las preguntas de los alumnos.';
$string['privacy:metadata:draft:authorid'] = 'El alumno que hizo la pregunta.';
$string['privacy:metadata:draft:decidedby'] = 'El profesor que publicó o descartó el borrador.';
$string['privacy:metadata:draft:message'] = 'La respuesta preparada.';
$string['privacy:metadata:draft:timecreated'] = 'Cuándo se preparó el borrador.';
$string['privacy:metadata:log'] = 'Registro de las llamadas del asistente a la IA y de las decisiones del profesorado.';
$string['privacy:metadata:log:action'] = 'Qué ocurrió (publicar, descartar...).';
$string['privacy:metadata:log:prompt'] = 'El texto enviado a la IA, que cita la pregunta del alumno (con los nombres sustituidos si el ajuste del sitio está activado).';
$string['privacy:metadata:log:timecreated'] = 'Cuándo ocurrió.';
$string['privacy:metadata:log:userid'] = 'El profesor que decidió.';
$string['reason_ai_error'] = 'El proveedor de IA no respondió.';
$string['reason_daily_limit'] = 'Se alcanzó el límite diario de llamadas a la IA.';
$string['reason_graded_task'] = 'Pide la solución de un trabajo evaluable.';
$string['reason_not_in_materials'] = 'Los materiales del curso no la responden.';
$string['reminder_intro_fallback'] = 'Esto es lo que vence en los próximos días. ¡Organiza tu semana!';
$string['reminder_subject'] = 'Recordatorio: lo que vence esta semana';
$string['remindertype_close'] = 'cierre';
$string['remindertype_due'] = 'entrega';
$string['remindertype_expectcompletionon'] = 'fecha prevista para completarla';
$string['remindertype_submissionsclose'] = 'cierre de envíos';
$string['setting_allowedcategories'] = 'Categorías';
$string['setting_allowedcategories_desc'] = 'Los cursos de estas categorías (y sus subcategorías) pueden usarlo.';
$string['setting_allowedcourses'] = 'Cursos';
$string['setting_allowedcourses_desc'] = 'Nombres cortos de los cursos que pueden usarlo, uno por línea o separados por comas.';
$string['setting_availability_heading'] = 'Dónde está disponible';
$string['setting_availability_heading_desc'] = 'Limita AI Forum Assistant a algunas categorías y/o cursos. Deja ambos ajustes vacíos para que esté disponible en todos.';
$string['setting_dailylimit'] = 'Llamadas a la IA por curso y día';
$string['setting_dailylimit_desc'] = 'Máximo de preguntas que se preparan por curso en 24 horas, para controlar el coste. Por encima, las preguntas llegan al profesor sin borrador. 0 = sin límite.';
$string['setting_enabled'] = 'Activar AI Forum Assistant';
$string['setting_enabled_desc'] = 'Interruptor general. Desactivado, ningún curso prepara respuestas ni recordatorios.';
$string['setting_forcenote'] = 'Marcar siempre las respuestas preparadas con IA';
$string['setting_forcenote_desc'] = 'Añade "Preparada con ayuda de IA" a todas las respuestas que el profesorado publique desde un borrador, sin opción a quitarlo. Úsalo si tu institución pide declarar el uso de la IA.';
$string['setting_instruction'] = 'Instrucción de la institución';
$string['setting_instruction_desc'] = 'Texto opcional que se añade a todas las peticiones; por ejemplo, un tono o una política que deben seguir todas las respuestas.';
$string['setting_redactnames'] = 'No enviar los nombres de los alumnos a la IA';
$string['setting_redactnames_desc'] = 'Antes de enviar una discusión del foro al proveedor de IA, se sustituyen el nombre, el correo, el nombre de usuario y el número de ID de los alumnos por [STUDENT].';
$string['settings_ainote'] = 'Añadir "Preparada con ayuda de IA"';
$string['settings_ainote_help'] = 'Añade esa línea a las respuestas que publiques desde un borrador. No es obligatoria cuando revisas tú la respuesta, pero algunas instituciones la piden.';
$string['settings_answers'] = 'Respuestas a las dudas';
$string['settings_enabled'] = 'Preparar respuestas a las dudas de los alumnos';
$string['settings_enabled_help'] = 'Cuando un alumno pregunta algo en uno de los foros de abajo, la IA prepara una respuesta con los materiales y las fechas del curso. Tú la revisas y la publicas con tu nombre; no se publica nada sin ti.';
$string['settings_forums'] = 'Foros';
$string['settings_forums_help'] = 'Los foros en los que el asistente prepara respuestas.';
$string['settings_helplevel'] = 'Ayuda en trabajos evaluables';
$string['settings_helplevel_explain'] = 'Explicar';
$string['settings_helplevel_guide'] = 'Orientar';
$string['settings_helplevel_help'] = 'Las actividades evaluables nunca se resuelven. "Orientar" da respuestas breves y señala la parte exacta de los materiales; "Explicar" permite explicaciones más largas, con ejemplos, cuando los materiales las respaldan.';
$string['settings_noforums'] = 'Este curso aún no tiene ningún foro en el que puedan escribir los alumnos.';
$string['settings_reminderday'] = 'Día';
$string['settings_reminderdays'] = 'Días que abarca';
$string['settings_reminderforum'] = 'Foro';
$string['settings_reminderforum_news'] = 'Avisos';
$string['settings_reminderhour'] = 'Hora';
$string['settings_remindermode'] = 'Publicación';
$string['settings_remindermode_auto'] = 'Automática (lo publica Tiko)';
$string['settings_remindermode_draft'] = 'Lo apruebo yo';
$string['settings_remindermode_help'] = '"Lo apruebo yo": el recordatorio espera en tu panel y se publica con tu nombre. "Automática": lo publica Tiko, el asistente de IA, con una nota que dice que es una IA.';
$string['settings_reminders'] = 'Recordatorios semanales';
$string['settings_reminders_enabled'] = 'Recordatorio semanal de las próximas entregas';
$string['settings_reminders_enabled_help'] = 'Una vez a la semana, un mensaje con las entregas y cierres de los próximos días, sacados del calendario del curso. Las fechas y enlaces los pone el plugin, no la IA, así que siempre son correctos; la IA solo escribe una frase de introducción.';
$string['settings_saved'] = 'Ajustes guardados.';
$string['settings_title'] = 'Ajustes de AI Forum Assistant';
$string['settings_wait'] = 'Esperar antes de preparar la respuesta';
$string['settings_wait_help'] = 'Tiempo que se espera tras una pregunta, para que puedas contestar tú o un compañero. Si alguien del profesorado responde mientras tanto, no se prepara nada.';
$string['settings_wait_none'] = 'Prepararla enseguida';
$string['status_discarded'] = 'Descartada';
$string['status_pending'] = 'Pendiente';
$string['status_published'] = 'Publicada';
$string['task_refresh_indexes'] = 'Actualizar los índices de los materiales de los cursos';
$string['task_send_reminders'] = 'Preparar los recordatorios semanales';
$string['tiko_description'] = '<p>Soy Tiko, un asistente de IA. Publico recordatorios automáticos en algunos foros de los cursos. Lo que escribo puede contener errores, y tu profesor lo ve todo.</p>';
$string['tiko_lastname'] = '(asistente de IA)';
$string['tikonote_reminder'] = 'Soy Tiko, un asistente de IA. Las fechas salen del calendario del curso.';
