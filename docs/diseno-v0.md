# Asistente de foros con IA para Moodle: diseño v0

Borrador del 2026-10-08. Nombre: **AI Forum Assistant** (`local_aiforumassist`), definitivo. Sigue el patrón de AI Grader Pro (IA + lo que hace) y no lleva "Pro", que en un plugin gratuito hace pensar en una versión de pago. No hay ningún plugin con ese nombre; el más parecido es "Forum AI" de Datacurso.

**Tiko es la mascota de los dos plugins.** Une a AI Forum Assistant con AI Grader Pro sin cambiar el nombre del primero:

- Presenta los vídeos.
- Es el nombre y la foto del asistente en los foros (apartado 3.2).
- Aparece en el icono, del mismo estilo que el de AI Grader Pro (morado, con el destello).

La descripción del Marketplace dirá "From the author of AI Grader Pro".

## 1. Qué es

Un plugin local que ayuda al profesor a atender los foros de su curso:

1. **Responder dudas.** Cuando un alumno pregunta algo en un foro, la IA prepara una respuesta basada en los materiales y fechas del curso. Por defecto es un borrador que el profesor revisa y publica con un clic.
2. **Recordatorios.** Una vez por semana publica (o deja como borrador) un mensaje con las entregas y cierres de los próximos días, sacados del calendario de Moodle.
3. **Dinamizar** (fase 3). Propone mensajes para animar la discusión sobre el tema de la semana. Siempre con aprobación del profesor.

Lema, coherente con AI Grader Pro: **la IA propone, el profesor decide.**

### Qué no hace (a propósito)

- **No califica la participación.** Evaluar a los alumnos convierte el sistema en "alto riesgo" según el Reglamento europeo de IA (anexo III, punto 3) y exige mucha más documentación y controles. Si algún día se quiere, sería otro plugin.
- **No resuelve tareas evaluables.** Si un alumno pide la solución de una práctica o de un examen, orienta pero no da la respuesta.
- **No inventa.** Si la respuesta no está en los materiales del curso, no contesta: avisa al profesor.

## 2. Competencia

| Plugin | Qué hace | Limitación |
|---|---|---|
| [Forum AI (Datacurso)](https://marketplace.moodle.com/plugins/local_forum_ai) | Borradores de respuesta revisados por el profesor; también califica la participación | Solo funciona con el proveedor de IA de Datacurso, que requiere licencia. 111 instalaciones, 487 descargas en 90 días |
| Chatbots (AI Chat, Raison…) | Un chat aparte en el curso | No trabajan en los foros; algunos dependen de un servicio externo |

Nuestro hueco: **gratuito, con cualquier proveedor del subsistema de IA de Moodle, el profesor decide, los nombres no salen de Moodle y sin calificar** (sin la carga de alto riesgo).

## 3. Cómo funciona

### 3.1 Responder dudas

1. Un alumno publica en un foro activado → el observador de `\mod_forum\event\post_created` crea una tarea en segundo plano (adhoc task) que se ejecuta pasado el **tiempo de espera** configurado (por defecto 2 horas), para dar opción a que contesten el profesor o los compañeros.
2. La tarea comprueba que siga mereciendo respuesta: que no haya contestado ya un profesor, que el foro y el curso sigan activados y que no se haya superado el límite diario de llamadas.
3. Filtro barato antes de llamar a la IA: el mensaje tiene pinta de pregunta (signos de interrogación, palabras interrogativas o foro de tipo pregunta y respuesta).
4. Se prepara el contexto:
   - **Ficha del curso, sin IA:** nombre, secciones y actividades, y fechas de entrega y cierre del calendario (`\core_calendar\local\api::get_action_events_by_course`). Las preguntas de logística ("¿cuándo se entrega…?") se responden con datos exactos.
   - **Fragmentos de los materiales** más relacionados con la pregunta (ver 3.4).
   - **El hilo:** los mensajes anteriores de la discusión.
   - Nombres, correos y usuarios de los alumnos sustituidos por `[STUDENT]`, como en AI Grader Pro.
5. Una sola llamada a `generate_text` del subsistema de IA, que devuelve JSON:
   `{"is_question": bool, "answerable": bool, "answer": "...", "sources": [cmid, ...], "graded_task_request": bool}`.
6. Según el resultado:
   - **Se puede responder:** se guarda el borrador con sus fuentes y se avisa al profesor (notificación de Moodle, agrupada).
   - **No está en los materiales, o pide resolver una tarea evaluable:** no se responde y se avisa al profesor de que hay una pregunta pendiente.
   - **No es una pregunta:** se ignora.
7. El profesor, en el panel "Asistente IA" del curso, ve la pregunta, el borrador y las fuentes, y puede **publicar**, **editar y publicar** o **descartar**.
8. **Modo automático (v0.2; opcional, por foro, desactivado por defecto):** la respuesta se publica sin revisión, solo si `answerable` es verdadero. El profesor puede borrarla después como cualquier mensaje. En la v0.1 todas las respuestas pasan por el profesor.

### 3.2 Quién firma las respuestas

- **Respuestas revisadas por el profesor:** se publican **con el nombre del profesor**, como cualquier mensaje suyo. La IA ha sido una herramienta de redacción y el profesor responde de lo que publica. La nota "Preparada con ayuda de IA" es **opcional**: un ajuste del profesor, desactivado por defecto, que el administrador puede forzar si la política de la institución lo pide.
- **Respuestas automáticas (v0.2) y recordatorios automáticos (v0.1):** se publican con un usuario que crea el plugin (sin acceso para iniciar sesión): **Tiko, el asistente de IA**, el zorro mascota de los vídeos de AI Grader Pro. Así los dos plugins comparten personaje.
  - Nombre mostrado: "Tiko (asistente de IA)" (en inglés, "Tiko (AI assistant)"), según el idioma del sitio al instalarlo.
  - Foto de perfil: la cara de Tiko, exportada del mismo dibujo de los vídeos.
  - Perfil: explica que es una IA, que sus respuestas pueden contener errores y que el profesor las ve.
  - Nota fija al pie de cada mensaje, que no se puede quitar: "Soy Tiko, un asistente de IA. Esta respuesta se ha generado automáticamente y puede contener errores; tu profesor la verá." (en los recordatorios: "Soy Tiko, un asistente de IA. Las fechas salen del calendario del curso.")
  - El administrador puede cambiar el nombre y la foto si la institución prefiere su propio asistente, pero el nombre tiene que seguir indicando que es una IA.

  Aquí el alumno sí habla directamente con la IA, y el artículo 50 del Reglamento europeo de IA obliga a decírselo.
- **Recordatorios:** si el profesor los aprueba, van con su nombre; en modo automático, los firma Tiko con la misma nota fija.

Para que la revisión sea real, el panel muestra el borrador junto a sus fuentes, y publicar exige abrirlo (no hay "publicar todo" para las respuestas a dudas).

### 3.3 Recordatorios semanales

- Tarea programada (scheduled task) que se ejecuta cada hora y, para cada curso con recordatorios activados, comprueba si toca (día y hora elegidos por el profesor, por defecto lunes a las 8:00).
- Recoge del calendario las entregas, cierres y eventos de los próximos 7 días (configurable).
- La IA solo redacta un mensaje breve y amable en el idioma del curso; las fechas y enlaces los pone el plugin, no la IA, para que no haya errores. Sin eventos esa semana, no se publica nada.
- Se publica en el foro que elija el profesor (por defecto el de Avisos del curso), en modo borrador (el profesor lo aprueba y sale con su nombre) o automático (lo firma Tiko). El modo automático de los recordatorios sí entra en la v0.1: las fechas no dependen de la IA y aprobar un aviso cada semana restaría utilidad.

### 3.4 Índice de los materiales del curso

El subsistema de IA de Moodle (4.5–5.3) no ofrece embeddings, así que la búsqueda es **léxica y local**:

- Se extrae el texto de páginas, libros, etiquetas, descripciones de actividades, resumen del curso y archivos (PDF, Word, PowerPoint, OpenDocument, notebooks), reutilizando los extractores de AI Grader Pro.
- Se trocea en fragmentos de unas 300 palabras y se guarda en una tabla del plugin con su actividad de origen.
- Se actualiza cuando se crea o modifica una actividad (eventos `course_module_created` y `course_module_updated`) y con una tarea nocturna.
- Para cada pregunta, se puntúan los fragmentos con BM25 (sin acentos, con palabras vacías en español e inglés) y se mandan los 6 mejores (unos 6.000 tokens como máximo).
- Las respuestas citan las actividades de origen con enlace, para que el alumno vaya a la fuente.

Más adelante, si algún proveedor de Moodle ofrece embeddings, se puede añadir búsqueda semántica sin cambiar el resto.

### 3.5 Dinamizar (fase 3)

Una vez por semana, propuesta de un mensaje que abra debate sobre la sección actual del curso (una pregunta, un caso, un enlace a un material). Siempre como borrador para el profesor. Límite de un mensaje por semana y foro, para no generar ruido.

## 4. Configuración

**Administrador del sitio:**
- Activar o desactivar el plugin y en qué categorías o cursos está disponible (como en AI Grader Pro).
- Límite de llamadas a la IA por curso y día (por defecto 50).
- Sustituir los nombres de los alumnos (activado por defecto).
- Instrucción institucional opcional para todas las respuestas.

**Profesor, en su curso:**
- Foros en los que actúa y modo en cada uno: desactivado o borrador (por defecto); el modo automático llega en la v0.2.
- Tiempo de espera antes de responder (por defecto 2 horas).
- Nivel de ayuda en dudas sobre tareas: solo orientar (por defecto) o explicar con más detalle.
- Recordatorios: activados o no, día y hora, foro y modo.

## 5. Datos, privacidad y registro

Tablas:

| Tabla | Para qué |
|---|---|
| `local_aiforumassist_config` | Configuración por curso y por foro |
| `local_aiforumassist_draft` | Borradores: mensaje original, texto propuesto, fuentes, estado (pendiente, publicado, descartado), quién decidió y cuándo |
| `local_aiforumassist_chunk` | Índice de los materiales (no son datos personales) |
| `local_aiforumassist_log` | Registro de cada llamada: hash del prompt, modelo, tokens, resultado, decisión del profesor |

- Proveedor de privacidad completo (exportación y borrado de los datos de cada alumno).
- Los borradores descartados y los registros se borran pasados 180 días (configurable).
- Lo que se manda a la IA: el hilo con los nombres sustituidos y fragmentos de los materiales del curso. Nada más.

## 6. Reglamento europeo de IA

- **Alto riesgo:** no aplica mientras el plugin no califique ni evalúe a los alumnos (anexo III, punto 3, que además se ha aplazado al 2 de diciembre de 2027).
- **Transparencia (artículo 50, en vigor desde el 2 de agosto de 2026):** obliga a avisar cuando el alumno interactúa directamente con una IA, es decir, en el modo automático. Se cumple con el usuario "Tiko (asistente de IA)", la nota fija y el perfil. Las respuestas que el profesor revisa y publica con su nombre son mensajes del profesor redactados con ayuda; el propio artículo 50.4 exime de etiquetar el texto generado por IA cuando una persona lo revisa y asume la responsabilidad. Aun así, la nota opcional existe porque muchas instituciones piden declarar el uso de IA.
- **Alfabetización en IA (artículo 4):** es responsabilidad de la institución que lo usa. El plugin incluirá una página de ayuda para el profesorado sobre cómo funciona y sus límites.

(No es asesoramiento jurídico; antes de publicar conviene que lo revise alguien que conozca el Reglamento.)

## 7. Qué se reutiliza de AI Grader Pro

Extractores de archivos, sustitución de nombres, clasificación de errores del proveedor (límite de peticiones, autenticación…), disponibilidad por categoría y curso, patrones del proveedor de privacidad, generador de datos de prueba con respuestas de IA simuladas y el flujo de CI con moodle-plugin-ci. Se copia el código (no se crea una dependencia entre plugins).

## 8. Fases

| Versión | Contenido |
|---|---|
| **v0.1 (MVP)** | Índice de materiales, borradores de respuesta que el profesor publica con su nombre (nota de IA opcional), panel del profesor, recordatorios semanales (borrador o automático, este último firmado por Tiko), usuario Tiko con su foto, configuración, privacidad, tests |
| v0.2 | Modo automático por foro, resumen semanal para el profesor de preguntas sin responder |
| v0.3 | Dinamizar: propuestas de debate semanales |

Tamaño estimado del MVP: algo menos que el MVP de AI Grader Pro, porque reutiliza buena parte de su base.

Moodle 4.5 a 5.3, PHP 8.1 a 8.4, gratuito y GPL, como AI Grader Pro.

## 9. Decisiones pendientes

1. ~~Nombre~~ Decidido el 2026-10-08: AI Forum Assistant, con Tiko como mascota de los dos plugins.
2. ~~Quién firma~~ Decidido el 2026-10-08: las respuestas revisadas van con el nombre del profesor (nota de IA opcional, desactivada por defecto); las automáticas las firma "Tiko (asistente de IA)" con nota fija.
3. ~~Modo automático~~ Decidido el 2026-10-08: las respuestas automáticas se dejan para la v0.2; en la v0.1 todas las respuestas las aprueba el profesor. Los recordatorios automáticos (firmados por Tiko) sí están en la v0.1.
4. Curso de pruebas: **"Microcredencial en IA · Demo"** (`MICRO-IA-DEMO`, curso 4 del Moodle local). Tiene 8 secciones con el temario de Deep Learning (del perceptrón a los agentes), 21 PDF (30 MB), 22 etiquetas y 55 enlaces: material real con el que comprobar si las respuestas aciertan. Le faltan un foro de dudas, alumnos y preguntas: se añaden alumnos ficticios y una batería de preguntas variadas (resueltas en los PDF, de fechas, que piden la solución de la práctica final y que no tienen nada que ver con el curso). El piloto con alumnos reales vendría después, en una edición real de la microcredencial.
