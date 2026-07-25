# Informe Entrega 3 - Bases de Datos IIC2413

## Datos del Alumno

| **Apellidos**       | **Nombres**          | **Número de Alumno** |
|---------------------|----------------------|----------------------|
| González López      | Emilia González      | 24626473             |

---

## 1. Descripción y análisis del problema

El problema de esta entrega consiste en construir una aplicación web funcional para el sistema del Club Social y Deportivo DCColo. A diferencia de la Entrega 2, en esta etapa la base de datos ya existe y se encuentra poblada, por lo que el foco está en crear una página web que permita interactuar con ella usando PHP, HTML, CSS y SQL.

La aplicación debía permitir iniciar sesión, registrar usuarios administrativos, administrar socios titulares, beneficiarios y adicionales, generar y pagar cuotas, arrendar canchas, ejecutar vistas de consulta, realizar una consulta inestructurada y crear eventos con lista de invitados. Además, varias acciones debían ejecutarse como transacciones, para evitar que quedaran datos incompletos si una parte fallaba.

Una dificultad importante fue que varias funcionalidades dependían entre sí. Por ejemplo, para arrendar una cancha no basta con insertar una reserva: también hay que revisar permisos, vigencia del socio, deuda, sucursal, cancha y disponibilidad horaria. Algo parecido ocurre con los eventos, donde se deben guardar datos del evento, cliente, lugar, pago e invitados en una sola operación.

También se tomaron decisiones sobre validación de datos. Una de las más importantes fue normalizar los RUN, ya que en la base podían aparecer escritos con puntos, sin puntos o sin guion. Esto evita registrar a la misma persona más de una vez por diferencias de formato.

Finalmente, se separaron los permisos según tipo de usuario. Administrativos y administradores pueden acceder a las acciones de administración, mientras que los socios titulares solo tienen acceso a las funcionalidades que les corresponden, como el arriendo de canchas.

---

## 2. Solución aplicada

La solución se organizó en páginas PHP separadas por funcionalidad y en archivos SQL para procedimientos, triggers, vistas e índices.

La aplicación usa `index.php` como login y `dashboard.php` como página principal. Las principales páginas implementadas son:

- `registrar_usuario.php`
- `socios.php`
- `beneficiarios.php`
- `cuotas.php`
- `arriendo_canchas.php`
- `consultas.php`
- `consulta_sql.php`
- `eventos.php`

El archivo `utils.php` contiene las funciones comunes: conexión a PostgreSQL, manejo de sesión, permisos, log, validación de RUN, consultas reutilizables y transacciones. Esto permite no repetir la misma lógica en todas las páginas.

La carpeta `sql/` contiene:

- `procedimientos.sql`: procedimiento para generar plan de pagos 2026.
- `triggers.sql`: triggers asociados a beneficiarios y adicionales.
- `vistas.sql`: vistas de la sección Vista de Consultas.
- `indices.sql`: índices de apoyo.
- `ajustar_secuencias_local.sql`: ajuste de secuencias para pruebas locales.

Además, la carpeta `logs/` contiene `dccolo.log`, donde se registran accesos y acciones importantes.

---

## 2.1 Manejo de usuarios

El login se implementó en `index.php`. El usuario ingresa correo y clave, y PHP valida esos datos usando las tablas `usuario` y `persona`, como pide el enunciado.

Se aceptan para iniciar sesión:

- `administrativo`
- `administrador`
- `admin`
- `socio_titular`

Cada intento de acceso queda en el log con el formato:

```text
YYYY-MM-DD HH:MM:SS ACCESO usuario Exitoso/Fallido
```

La página `registrar_usuario.php` permite crear nuevos usuarios administrativos o administradores. Esta acción inserta datos en:

- `persona`
- `usuario`
- `persona_cargo`

Todo se hace dentro de una transacción. Si algo falla, se hace `rollBack()` y no queda información parcial. Si funciona, se hace `commit()` y se registra la acción en el log.

---

## 2.2 Socios, beneficiarios, adicionales y cuotas

Esta parte se implementó con `socios.php`, `beneficiarios.php`, `cuotas.php`, `procedimientos.sql` y `triggers.sql`.

### Socios titulares

`socios.php` permite registrar socios titulares. La función principal inserta o actualiza datos en:

- `persona`
- `socio`
- `membresia`

Se decidió permitir que una persona ya existente pueda convertirse en socio titular. Por ejemplo, si una persona ya existe como usuario, no se vuelve a insertar en `persona`; solo se actualizan sus datos y se crea su registro como socio.

También se normalizan RUN para evitar duplicados. Por ejemplo (con mi rut):

```text
21.557.645-0
21557645-0
215576450
```

se interpretan como el mismo RUN.

### Beneficiarios y adicionales

`beneficiarios.php` permite seleccionar un socio titular y agregar un beneficiario o adicional. Se insertan datos en:

- `persona`
- `socio`
- `relacion_socio`

Se valida que el titular exista y sea socio titular. En esta parte no se exige vigencia actual, porque el enunciado solo pide incorporar beneficiarios o adicionales. La vigencia sí se exige para arriendo de canchas.

### Procedimiento y trigger

En `procedimientos.sql` se creó:

```sql
sp_generar_plan_pagos_2026(p_id_socio_titular integer)
```

Este procedimiento genera las 12 cuotas del año 2026 considerando el precio base del socio y los costos adicionales por beneficiarios/adicionales.

En `triggers.sql` se creó un trigger sobre `relacion_socio`, para que el plan se actualice cuando cambian los beneficiarios o adicionales de un socio titular.

### Pago de cuotas

`cuotas.php` permite generar el plan 2026 y pagar la cuota impaga más antigua. Pagar cuotas no renueva automáticamente la membresía, porque el enunciado pide registrar pagos, pero no una renovación automática.

---

## 2.3 Arriendo de canchas

`arriendo_canchas.php` permite arrendar canchas. Pueden acceder:

- administrativos
- administradores
- socios titulares

La página permite seleccionar sucursal, cancha, fecha y horario. Para crear una reserva se valida que:

- el lugar exista;
- la cancha pertenezca a la sucursal;
- el horario no esté ocupado;
- el socio esté vigente;
- el socio no tenga deuda vencida.

Si entra un socio titular, se usa automáticamente su RUN. Si entra un administrativo o administrador, se muestra una lista de socios titulares habilitados.

El arriendo se guarda en `reserva` con estado:

```text
reservada
```

La inserción se hace dentro de una transacción y queda registrada en el log.

---

## 2.4 Vista de Consultas

`consultas.php` implementa las consultas similares a E2, pero dentro de la página web. En el menú se muestra como **Vista de Consultas**.

Las vistas están en `sql/vistas.sql`:

- `vista_agenda_sucursal`
- `vista_ingreso_mensual_sucursal`
- `vista_cuotas_atrasadas`
- `vista_beneficiarios_hijos_29`
- `vista_reporte_sucursales_2025`

Cada vista se ejecuta desde la interfaz y muestra sus resultados en una tabla. Esta sección solo está disponible para administrativos o administradores.

---

## 2.5 Consulta inestructurada

La consulta inestructurada se implementó en `consulta_sql.php`. La página tiene tres campos:

- `A`: columnas.
- `B`: tabla o vista.
- `C`: condición.

Con eso se arma una consulta del tipo:

```sql
SELECT A FROM B WHERE C LIMIT 100;
```

Para evitar inyección SQL, se validan las tablas, columnas y condiciones. También se bloquean palabras peligrosas como `DROP`, `DELETE`, `UPDATE`, `INSERT`, `ALTER`, `TRUNCATE` y `UNION`.

Además, los valores se ejecutan con `prepare()` y parámetros. Esta funcionalidad no busca aceptar cualquier consulta SQL posible, sino permitir consultas simples y seguras.

---

## 2.6 Creación de eventos e invitados

`eventos.php` permite crear un evento completo en una sola transacción. El formulario pide evento, cliente, sucursal, lugar, fecha, horario, valor, primera cuota e invitados.

El cliente puede ser:

- socio titular;
- empresa o institución.

Si el cliente es socio, se exige que esté vigente y sin deuda vencida. Si es empresa, se registra o actualiza en `empresa`, y opcionalmente se guarda un contacto en `contacto_empresa`.

El selector de lugares se filtra por sucursal, para evitar que al elegir una sucursal aparezcan lugares de otra. Además, PHP vuelve a validar que el lugar pertenezca realmente a esa sucursal.

Como `evento` no guarda hora exacta, también se crea una reserva técnica en `reserva` con estado:

```text
reservada_evento
```

Esto permite bloquear el lugar y horario del evento.

Para el pago inicial, si no se ingresa monto, se usa el 50% del valor total. Si se ingresa manualmente, se valida que no sea mayor que el valor total del evento.

Los invitados se ingresan en un textarea y se guardan en `asistente_evento`. Si falla cualquier parte del proceso, se hace rollback completo.

---

## 2.7 Permisos y seguridad básica

Se implementaron permisos tanto en el menú como en cada página. Las páginas administrativas están protegidas para:

```text
administrativo
administrador
admin
```

La página de arriendo también permite `socio_titular`.

Además, se usan sesiones para mantener el usuario conectado, `prepare()` para consultas con datos variables y `escaparHTML()` para mostrar datos en HTML sin imprimir directamente valores ingresados por usuarios.

---

## 2.8 Logs

El log principal está en:

```text
logs/dccolo.log
```

Se registran accesos y acciones:

```text
YYYY-MM-DD HH:MM:SS ACCESO usuario Exitoso/Fallido
YYYY-MM-DD HH:MM:SS ACCION usuario NOMBRE_ACCION Exitoso/Fallido detalle
```

Algunas acciones registradas son:

- `REGISTRO_USUARIO`
- `REGISTRO_SOCIO_TITULAR`
- `REGISTRO_BENEFICIARIO_ADICIONAL`
- `GENERAR_PLAN_PAGOS_2026`
- `PAGO_CUOTA`
- `ARRIENDO_CANCHA`
- `VISTA_CONSULTAS`
- `CONSULTA_INESTRUCTURADA`
- `CREACION_EVENTO`

El log fue útil para revisar errores durante las pruebas, como secuencias desfasadas, socios no vigentes o intentos de login fallidos.

---

## 3. Archivos principales

- `index.php`: inicio de sesión.
- `dashboard.php`: página principal.
- `registrar_usuario.php`: registro de administrativos/administradores.
- `socios.php`: registro de socios titulares.
- `beneficiarios.php`: registro de beneficiarios y adicionales.
- `cuotas.php`: plan de pagos y pago de cuotas.
- `arriendo_canchas.php`: arriendo de canchas.
- `consultas.php`: Vista de Consultas.
- `consulta_sql.php`: consulta inestructurada.
- `eventos.php`: creación de eventos.
- `logout.php`: cierre de sesión.
- `test_db.php`: prueba de conexión.
- `utils.php`: funciones comunes.
- `styles.css`: estilos.
- `partials/`: partes reutilizables.
- `sql/`: procedimientos, triggers, vistas, índices y secuencias.
- `logs/dccolo.log`: archivo de log.

---

## 4. Pruebas realizadas

Se probó el login exitoso y fallido, verificando que ambos quedaran en el log. También se probó registrar usuarios nuevos, cerrar sesión e iniciar sesión con ellos.

Para socios, se probó registrar socios titulares nuevos, convertir una persona existente en socio titular y evitar duplicados por formato de RUN.

En beneficiarios y adicionales se probó insertar dependientes y revisar que quedaran en `persona`, `socio` y `relacion_socio`.

En cuotas se probó cargar el procedimiento y trigger, generar cuotas 2026 y pagar la cuota más antigua.

En arriendo de canchas se probó crear reservas válidas, bloquear horarios ocupados y filtrar socios vigentes sin deuda.

En Vista de Consultas se probaron las cinco vistas desde la página.

En consulta inestructurada se probaron consultas válidas y también intentos peligrosos con `DROP`, `DELETE` o `;`, verificando que fueran rechazados.

En eventos se probaron eventos con socio y con empresa. También se probaron casos borde como fecha pasada, lugar de otra sucursal, choque de horario, primera cuota mayor al total e invitados inválidos.

---


## 5. Instrucciones para Stonebraker
Para que el site corra sin problemas ejecutar en orden:

1. Preparamos los logs:

mkdir -p logs
touch logs/dccolo.log
chmod 775 logs
chmod 666 logs/dccolo.log

2. Cargamos sql:

psql -f sql/procedimientos.sql
psql -f sql/triggers.sql
psql -f sql/vistas.sql
psql -f sql/indices.sql
psql -f sql/ajustar_secuencias_local.sql


3. Limpiar el log antes de partir:
> logs/dccolo.log


4. Tail logs:
tail -f logs/dccolo.log

luego Ctrl + c para salir


## 6. Referencias, bibliografía y uso de IA

Para esta entrega se usaron principalmente los materiales del curso, los enunciados de E1 y E2 como contexto, las ayudantías de PostgreSQL, PHP, vistas, triggers, stored procedures y transacciones, además de documentación oficial de PHP/PostgreSQL para dudas puntuales.

No se usaron repositorios externos ni código de otras personas. La aplicación se desarrolló sobre la base de lo visto en el curso, con apoyo de IA para dudas de implementación, revisión de errores y simplificación de algunas partes.

Uso aproximado de IA:

```text

Estructura visual inicial de páginas PHP y CSS: 60% IA

No soy tan experta con el uso de CSS y tenia una foto de inspo que encontre en pinterest:
https://cl.pinterest.com/pin/402016704260961212/
Que me gusto mucho asi que le fui pidiendo a chat ayuda para que me quedara lo mas parecido posible.

Manejo de Usuarios: 30% IA
Administración de Socios, Beneficiarios, Adicionales y Pago de Cuotas : 40% IA
Arriendo de Canchas: 25% IA
Consultas similares E2: 20% IA
Consultas Intraestructuradas: 15% IA
Redacción del informe: 30% (para estructurar y ordenar mejor la info)
```

La IA utilizada fue ChatGPT plan pagado básico. Se usó como apoyo para resolver errores, ordenar ideas y revisar código, pero las ideas de implementación y conceptuales, las decisiones de reglas de negocio, la esttructura, y la creación general del codigo son de mi autoría.

Ejemplos de prompts usados:

```text
Me aparece un error de duplicate key en socio_pkey. ¿Qué significa y cómo reviso la secuencia?
```

```text
¿Cómo evito registrar dos veces el mismo RUN si viene con puntos, sin puntos o sin guion?
```

```text
¿Cómo filtro lugares por sucursal en un formulario y además lo valido en PHP?
```

```text
¿Cómo hago un stored procedure simple que genere 12 cuotas para 2026?
```

```text
¿Cómo valido una consulta SELECT A FROM B WHERE C para evitar DROP o DELETE?
```

```text
¿Qué pruebas debería hacer para revisar login, socios, cuotas, canchas, vistas y eventos?
```

---

## 8. Conclusión

La entrega implementa una aplicación web funcional para DCColo. Permite conectarse a PostgreSQL, iniciar sesión, registrar acciones en log, proteger páginas según tipo de usuario y ejecutar las funcionalidades pedidas en la E3.

Se usaron transacciones para operaciones que modifican varias tablas, como registro de usuarios, socios, beneficiarios, arriendo de canchas y eventos. También se agregaron procedimientos almacenados, triggers y vistas mediante archivos SQL.

Durante las pruebas se corrigieron problemas como RUN duplicados por formato, secuencias desfasadas, socios vencidos en arriendos, lugares de evento mal filtrados y pagos de evento mayores al total. Con esto, la versión final queda funcional y documentada para ejecutarse de forma local o en Stonebraker.
