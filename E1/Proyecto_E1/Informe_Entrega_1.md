# Informe Entrega 1 - Bases de datos IIC2413

## Datos del Alumno
| **Apellidos**       | **Nombres**          | **Número de Alumno** |
|---------------------|----------------------|----------------------|
|   González López    |   Emilia Isidora     |     24626473         |


## 1. Descripción y análisis del problema
 

El problema planteado en el enucnciado trata de diseñar un modelo de datos que represente el "Club Social y Deportivo DCColo", para louego poder implementarlo en un sistema relacional que se encuentre formalizado para poder ejecutar consultas sobre este en SQL.
En esta primera entrega, el enfoque estuvo en realizar el modelo Entidad/Relación, diagramarlo y realizar consultas simples sobre este. 

### Analisis del problema

El problema planteado consiste en un modelo complejo que incorpora distintos aspectos del funcionamiento, cobro, integrantes, y administración del club deportivo. A partir de enunciado podemos identificar los siguientes dominios de información.

Primero, existe un dominio asociado a las personas. La base del sistema son las acciones realizadas por los distintos roles de personas dentro del club deportivo, entre ellos socios, invitados, contactos, clientes, usuarios y familiares. Es importante recordar luego los aspectos específicos de cada rol, por ejemplo los distintos tipos de socios, sus familaires, las reservas que estos pueden realizar y los cobros que se les realizan a estos. También existen invitados por los socios, o asistentes eventos, algunos de los cuales se consideran como personas dentro del club, y otros como entidades más "temporales", y por ende el sistema cuenta con menos información sobre ellos. Adicionalmente, es importante distinguir entre los socios y los familiares de socios, y también entre los socios en distintos rangos etareos, ya que cada uno cuenta con reglas específicas.

También es importante identificar el dominio de la infraestructura del club, que incluye sucursales, lugares, y precio asociados a estos. Una sucursal puede contener varios lugares, como canchas, salones, cabañas, etc. Estos lugares pueden ser reservados por socios, o puede ser agendado en ellos un evento por una empresa mediante un contacto. Es importante tambien considerar que el valor de arriendo de los lugares varia con la fehca y horario. Las actividades también son un dominio, dentro de los cuales están contenidos los eventos y las reservas. Es importante diferenciarlo de la infraestructura y que como mencionamos el uso de lugares en fechas específicas cuentan con diferencias importantes en torno a la contratación y a la gestión de pagos.

Finalmente se establece el dominio relacionado con los pagos, membresías y las cuotas. Los socios pagan una membresía anual, divisible en cuotas, también pueden pagar reservas o eventos. Terceros también pueden pagar por eventos mediante un contacto con el club. También hay reglas importantes que establecen condiciones sobre los pagos atrasados y los estados de vigencia de los socios.

En conclusión, para la solución aplicada del problema es importante integrar todos estos dominios en un modelo consistente y coherente, que cumpla con las reglas establecidas, tratando de mantenerlo lo más simple posible, pero también que se encuentre completo y pueda permitir todas las consultas necesarias, y que estas sean resueltas de manera eficiente.



## 2. Solución aplicada

El primer aspecto de la solución fue el de las Personas que aprticipan e interactúan con el Club. 
Para las personas, estas pueden tener distintos roles, cargos, e interactuar con las distintas sucursales del Club deportivo, realizando reservas, invitando a otras personas, etc. Entre los roles de personas existentes se encuentran; "Socios", "Invitados", "Contactos", "Usuarios", Familiares de socios beneficiarios, y "Contactos" de empresas. Todos estos roles pertenecen a personas, y la información bse que constituye a una persona es; nombre, RUN, dirección, datos de contacto como telefono principal/alternativo, correo, calle y comuna. Adicionalmente se incorporo el atributo de fecha de nacimiento apra poder realizar el calculo de la edad y asi poder mantener actualizado el estados de socio y los cobros correspondientes.

Para diferenciar entre los socios y sus familiares, se creó la entidad "Familiar" (familiar_socio en diagrama) para poder incorporar atributos que nos permitan saber su tipo y vigencia. Esto fue necesario ya que la entidad necesitaba tener atributos propios que no podían representarse de otro modo.

Los clientes fueron representados con la entidad "Cliente", definido según lo propuesto en el enunciado, permitiéndonos manejar de forma unificada a las personas y empresas.

La infraestructura se modelo creando las identidades de "Sucursal", "Lugar", y "PrecioLugar". DE esta manera, se separaran los distintos lugares contenidos dentro de las sucursales con los precios especificos de cada uno. 

Luego, se modelaron los eventos y las reservas como entidades independientes "Evento" y "Reserva", comprendiendo que existen reservas de otro tipo, y diferencias en reglas asociadas. Tanto las reservas como eventos se relacionan con un lugar, lo que nos ayuda también a determinar la sucursal.

La entidad de "Pago" nos permite registrar distintos tipos de pagos (reserva, cuota o evento), y establecer su monto y fecha. Asi con una misma entidad podemos cubrir las transacciones de diversos pagos dentro del club.

Finalemnte, se modelo la entidad de "Cargo" para definir el sistema administrativo del club, definiendo fehca de inicio y termino, representando la gerencia por sucursal.


### 2.1 Modelo Entidad Relación

El archivo PDF conteniendo mi modelo se encuentra adjunto. La justificación de mi modelo es la siguiente.

En primer lugar, la entidad de persona es la base de todo el modelo. Se define RUN como llave primaria, permitiendo identificar de forma única a cada individuo, siendo los otros atributos correspondientes a información pedida por el enunciado. La jerarquía de la entidad "Persona" es tal debido a que múltiples roles comparten atributos (que los hacen personas), pero que tienen distintos comportamientos y funciones. Por esto se modelan socio, invitado, contacto y usuario como subtipos. En particular la entidad de socio es la más completa, ya que también es la más compleja.

La entidad de "Cliente" es necesaria para representar las personas y a las empresas que contratan eventos, nos ayuda a simplificar el diseño del modelo.

"Familiar" es una entidad que se usa para representar relaciones con atributos propios, como tipo y vigencia. Su cardinal es de 1 a N, ya que un socio puede tener múltiples familiares asociados a si mismo.


La empresa se define con RUT como llave primaria. La relación de empresa y contacto es de cardinal uno a muchos, ya que una empresa puede tener muchos contactos.
Sucursal representa las distintas sedes del club y se relaciona con una comuna específica, su relación también es de N a 1, de qué varias sucursales pueden pertenecer a una misma comuna.

Luego se encuentran los lugares, relacionados a la sucursales en 1 a N, ya que cada sucursal tiene múltiples instalaciones o lugares dentro de sí misma. PrecioLugarn se relaciona con lugar en relación 1 a N, lo que nos permite representar distintos precios según su fecha y horario específico.

Reserva también se considera una entidad independiente, relacionando a un socio con un lugar en una fecha y hora específicos. La cardinal también es de 1 a N llega un socio puede poner una reservar múltiples veces.

El evento se modela de manera separada a la reserva ya que tienen diferencias conceptuales. "Evento" se relaciona con "Lugar" en 1 a N ya qué múltiples eventos pueden ocurrir en un mismo lugar. Se relaciona "Evento" con "Cliente" tambien en N a 1, ya que un cliente puede contratar múltiples eventos.

"AsistenteEventos" se modela como entidad debil, ya que es dependiente de un "Evento". Su llave primaria es compuesta, ya que utiliza id_evento y el identificador parcial del asistente.

"Membresía" se relaciona con "Socio" en 1 a N, ya que un socio puede tener múltiples membresías a lo largo del tiempo, variando con la edad, un socio también puede no contratar y volver a contratar una membresía. Las curas se relacionan con membresía en 1 a N tambien, ya que una membresía puede tener asociado múltiples pagos por cuotas. 

"Cargo" se modela como entidad independiente debido a la necesidad de representar que un cargo tiene una vigencia temporal. La relacion entre un cargo y una persona es de 1 a N, ya que una persona puede tener multiples cargos a la vez. Se relaciona tambien con sucursal en N a 1, ya que cada cargo peretence a unas sucursal especifica pero hay multiples cargos.

De esta manera, el modelo propuesto logra representar de manera completa y estructurada el problema propuesto del Club DCColo.

### 2.2 Modelo Entidad Relación normalizado

La normalización de mi modelo Entidad-Relación es la siguiente:

	Persona (RUN PK, nombre_completo, correo, direccion_calle, telefono_celular, telefono_alternativo, fecha_nacimiento, id_comuna FK)

	Socio (RUN PK FK, fecha_incorporacion, estado, id_sucursal FK)

	Invitado (RUN PK FK)

	Contacto (RUN PK FK)

	Usuario (id_usuario PK, RUN FK, correo, clave, tipo_usuario)

	Familiar (id_familiar PK, run_titular FK, run_persona FK, tipo, fecha_inicio, fecha_fin)

	Cargo (id_cargo PK, nombre, fecha_inicio, fecha_fin, RUN FK, id_sucursal FK)

	Empresa (RUT_empresa PK, nombre)

	Cliente (id_cliente PK)

	Sucursal (id_sucursal PK, nombre, id_comuna FK)

	Lugar (id_lugar PK, tipo, capacidad, direccion_real, id_sucursal FK)

	PrecioLugar (id_precio PK, valor, fecha_inicio, fecha_fin, hora_inicio, hora_fin, tipo_cobro, id_lugar FK)

	Reserva (id_reserva PK, fecha, hora_inicio, hora_fin, estado, id_socio FK, id_lugar FK)

	Evento (id_evento PK, nombre, fecha, id_lugar FK)

	AsistenteEvento (id_evento FK, identificador, nombre, PRIMARY KEY(id_evento, identificador))

	Membresia (id_membresia PK, fecha_inicio, fecha_fin, id_socio FK)

	Cuota (id_cuota PK, mes, año, monto, fecha_limite, id_membresia FK)

	Pago (id_pago PK, monto, fecha, tipo, id_cuota FK, id_reserva FK, id_evento FK)

	Comuna (id_comuna PK, nombre, id_region FK)

	Region (id_region PK, nombre)

### 2.3 Consultas SQL

En esta sección se encuentran las consultas en SQL desarrolladas a partir del modelo propuesto. Es importante considerar que estas consultas asumen las tablas ya hechas, se encuentra adjunto a la entrega las 5 consultas por separado y un archivo llamado tablas.sql donde esta la definición de las tablas a partir de las entidades creadas y sus atributos, el cual no incluyo aqui por orden.

**Consulta A**

En esta primera consulta, se busca mostrar la agenda de una sucursal específica para una semana determinada, incluyendo las reservas realizadas por socios y eventos. Para esto se utilizaron las tablas de las reservas asociadas a los socios y de los eventos organizados. 

En la primera parte, de las reservas de los socios, se obtienen las reservas uniendo las tablas Reserva, Lugar, Sucursal, Socio y Persona. En base de estos podemos obtener el nombre del socio asociado a cada reserva.

Para la segunda parte, en los eventos uniendo Evento con Lugar y Sucursal. 

Entonces, para la resolución de esta consulta, se obtiene la sucursal a través de Lugar, se realiza respetando la diferencia entre reservas y eventos, y se logra combinar ambas actividades en un solo listado visible.

```sql
-- Reservas de socios
SELECT
    r.fecha,
    r.hora_inicio,
    l.id_lugar,
    p.nombre_completo AS nombre
FROM Reserva r
JOIN Lugar l ON r.id_lugar = l.id_lugar
JOIN Sucursal s ON l.id_sucursal = s.id_sucursal
JOIN Socio so ON r.id_socio = so.RUN
JOIN Persona p ON so.RUN = p.RUN
WHERE s.nombre = 'Santa Cruz'
  AND r.fecha >= '2026-04-06'
  AND r.fecha <= '2026-04-12'

UNION

-- Eventos
SELECT
    e.fecha,
    NULL AS hora_inicio,
    l.id_lugar,
    e.nombre AS nombre
FROM Evento e
JOIN Lugar l ON e.id_lugar = l.id_lugar
JOIN Sucursal s ON l.id_sucursal = s.id_sucursal
WHERE s.nombre = 'Santa Cruz'
  AND e.fecha >= '2026-04-06'
  AND e.fecha <= '2026-04-12'

ORDER BY fecha, hora_inicio, id_lugar;
```

**Consulta B**

Para realizar esta consulta y encontrar el monto del ingreso mensual, separamos el desarrollo en los ingresos que ya fueron recibidos, y los ingresos por recibir, por reservas que aún no son ejecutadas.

Para los ingresos que ya fueron recibidos, se utiliza la tabla de Pago, que representa transacciones ya realizadas, filtrando los pagos dentro del rango de fechas correspondientes al mes.

Luego para los ingresos futuros, aún no existe un pago registrado. Para resolver esto, se utiliza el valor del arriendo asociado a un lugar, con la ayuda de la tabla PrecioLugar, el cual incluye una vigencia temporal. Por esto, la consulta también incorpora la fecha de inicio y fecha de fin para asegurar que el precio utilizado en la fecha de la reserva sigue siendo válido.

De esta manera combinamos los ingresos presentes, y futuros, lo que nos permite representar la información de manera simple.


```sql
-- Ingresos ya recibidos
SELECT
    'recibido' AS tipo_ingreso,
    SUM(p.monto) AS total
FROM Pago p
WHERE p.fecha >= '2026-04-01'
  AND p.fecha <= '2026-04-30'

UNION ALL

-- Ingresos de reservas aun no ejecutadas
SELECT
    'futuro' AS tipo_ingreso,
    SUM(pl.valor) AS total
FROM Reserva r
JOIN Lugar l ON r.id_lugar = l.id_lugar
JOIN PrecioLugar pl ON pl.id_lugar = l.id_lugar
WHERE r.estado <> 'ejecutado'
  AND r.fecha >= '2026-04-01'
  AND r.fecha <= '2026-04-30'
  AND pl.fecha_inicio <= r.fecha
  AND pl.fecha_fin >= r.fecha;
```

**Consulta C**

Para esta consulta, buscamos obtener un reporte de todos los socios con sus cuotas atrasadas. Para esto, se recorre la relación entre socio, membresía, cuota y pago. Con un LEFT JOIN entre Cuota y Pago identificamos las cuotas que aun no tienen un pago asociado. 

Luego, buscamso donde los pagos son NULL, es decir no existentes, que es justo la información que buscamos. Adicionalmente también consideramos la fecha límite para verificar que la cuota está efectivamente atrasada, y no solamente por pagar dentro del plazo.

Agrupamos los resultados, permitiendonos calcular el monto total debido y la cantidad de cuotas pendientes.


```sql
SELECT
    p.nombre_completo,
    s.RUN,
    su.nombre AS sucursal,
    SUM(c.monto) AS monto_total,
    COUNT(c.id_cuota) AS numero_cuotas
FROM Socio s
JOIN Persona p ON s.RUN = p.RUN
JOIN Sucursal su ON s.id_sucursal = su.id_sucursal
JOIN Membresia m ON m.id_socio = s.RUN
JOIN Cuota c ON c.id_membresia = m.id_membresia
LEFT JOIN Pago pa ON pa.id_cuota = c.id_cuota
WHERE pa.id_pago IS NULL
  AND c.fecha_limite < '2026-04-30'
GROUP BY p.nombre_completo, s.RUN, su.nombre
ORDER BY monto_total DESC;
```

**Consulta D**

En esta consulta, buscamos generar un listado de todos los beneficiarios-hijos y datos de su socio titular que deberán pagar un costo adicional en la próxima renovación de la membresía.

Para esto, se utilizó la entidad Familiar. Se seleccionan aquellos registros donde el tipo sea "beneficiario", y luego se filtran las personas cuya fecha de nacimiento indique que cumplen 29 años, debiendo incurrir en un costo adicional. El atributo de fecha de nacimiento fue añadido específicamente para facilitar la realización de esta consulta.

Esta consulta también nos permite obtener información del socio titular asociado al beneficiario, uniendo dos veces la tabla persona, mostrando los datos completos.

```sql
SELECT
    pb.RUN AS run_beneficiario,
    pb.nombre_completo AS nombre_beneficiario,
    pb.correo AS correo_beneficiario,
    pb.telefono_celular AS telefono_beneficiario,

    pt.RUN AS run_titular,
    pt.nombre_completo AS nombre_titular,
    pt.correo AS correo_titular,
    pt.telefono_celular AS telefono_titular

FROM Familiar f
JOIN Persona pb ON f.run_persona = pb.RUN
JOIN Socio s ON f.run_titular = s.RUN
JOIN Persona pt ON s.RUN = pt.RUN

WHERE f.tipo = 'beneficiario'
  AND pb.fecha_nacimiento >= '1997-01-01'
  AND pb.fecha_nacimiento <= '1997-12-31';
```

**Consulta E**

En esta consulta se nos pide generar un reporte para el año 2025 de todas las sucursales y sus ingresos, ordenados de mayor a menor. 

En esta consulta se vuelve complejo evitar que se dupliquen los datos al combinar los distintos pagos de reservas y eventos. Para esto, ambos ingresos fueron separados en consultas distintas, que luego se combinaron con UNION.

Para cada subconsumo Ulta, se agruparon los ingresos por sucursal utilizando la relación entre el lugar y sucursal.

Luego, se agruparon estos resultados para obtener el ingreso total por sucursal. También es posible obtener el gerente de cada sucursal a partir de la entidad de Cargo, filtrando por el nombre correspondiente.

Para que esta información esté ordenada de mayor a menor hacemos un ORDER BY en DESC, lo que nos proporciona la información solicitada.

```sql
SELECT
    s.nombre AS sucursal,
    per.nombre_completo AS gerente,
    SUM(t.ingreso) AS ingreso_total,

    (SUM(t.ingreso) * 100.0) / (
        SELECT SUM(monto)
        FROM Pago
        WHERE fecha >= '2025-01-01'
          AND fecha <= '2025-12-31'
    ) AS porcentaje

FROM Sucursal s
JOIN Cargo c ON c.id_sucursal = s.id_sucursal
JOIN Persona per ON per.RUN = c.RUN

JOIN (
    -- Reservas
    SELECT
        l.id_sucursal,
        SUM(p.monto) AS ingreso
    FROM Lugar l
    JOIN Reserva r ON r.id_lugar = l.id_lugar
    JOIN Pago p ON p.id_reserva = r.id_reserva
    WHERE p.fecha >= '2025-01-01'
      AND p.fecha <= '2025-12-31'
    GROUP BY l.id_sucursal

    UNION ALL

    -- Eventos
    SELECT
        l.id_sucursal,
        SUM(p.monto) AS ingreso
    FROM Lugar l
    JOIN Evento e ON e.id_lugar = l.id_lugar
    JOIN Pago p ON p.id_evento = e.id_evento
    WHERE p.fecha >= '2025-01-01'
      AND p.fecha <= '2025-12-31'
    GROUP BY l.id_sucursal
) t ON t.id_sucursal = s.id_sucursal

WHERE c.nombre = 'Gerente'

GROUP BY s.nombre, per.nombre_completo
ORDER BY ingreso_total DESC;
```



## 3. Referencias y bibliografía externa

Material utilizado principalemnte consiste de las clases y ayudantias de SQL como base principal del codigo y planteamiento de resolución del problema.

En algunos aspectos de este proyecto se utilizó la IA ChatGpt, para consultas simples como "Ayúdame con la redacción de esta sección", o para revisar correctitud en las consultas planteadas, ya que al no tener una base de datos concretas las consultas podían sólo ser chequeadas a mano, con prompts como "¿Hay algún error en esta parte de mi codigo de SQL?". El planteamiento inicial del problema, solución, diagrama, modelo y consultas fueron realizados sin uso de IA.
