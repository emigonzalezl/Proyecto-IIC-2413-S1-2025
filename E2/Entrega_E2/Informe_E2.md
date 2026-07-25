# Informe Entrega 2 - Bases de datos IIC2413

## Datos del Alumno
| **Apellidos**       | **Nombres**          | **Número de Alumno** |
|---------------------|----------------------|----------------------|
| Gonzalez Lopez      | Emilia Isidora       |    24626473          |


## 1. Descripción y análisis del problema
 
El problema consiste en tomar una serie de archivos CSV entregados con información del sistema DCColo y transformarlos en una base de datos relacional funcional, limpia y consistente. Los archivos originales contienen datos de personas, socios, sucursales, lugares, reservas, eventos, pagos de membresías, cargos administrativos y regiones/comunas.
Para esto, es necesario realizar una limpieza de datos con php antes de ejecutar la carga de los datos y su sposetrior envio a PostgreSQL, ya que los archivos vienen con múltiples errores de formato, datos nódulos, valores mal escritos, fecha de distintos formatos, reunión inválidos, textos con problemas de codificación o datos que no calzan entre archivos.

# 2. Solución aplicada

La solucion aplicada del problema se divide en tres partes; limpieza con PHP, carga con SQL, y la creación de consultas. 

Primero se implementó el archivo main.php, encargado de leer los CSV originales desde la carpeta archivos. Para cada archivo se aplicó una función de revisión específica, según sus columnas y reglas. El programa genera tres salidas por archivo: un archivo OK con las filas limpias que pueden cargarse, un archivo ERR con las filas rechazadas y un archivo LOG con las acciones realizadas durante la limpieza. De esta manera, el proceso queda trazable y se puede revisar qué datos fueron corregidos o descartados.
En esta etapa se normalizaron textos, se corrigieron espacios extra, se validaron RUN, fechas, horas, números y opciones cerradas. También se tomaron algunas decisiones de limpieza, como completar reservas con fecha pero sin hora usando 00:00 para el inicio y 23:59 para el término, interpretar monto_adicionales vacío como 0, o dejar nulos campos que no aplicaban según el tipo de persona. Los errores irreparables, como RUN inválidos o campos obligatorios imposibles de recuperar, se enviaron a ERR.

Luego se implementó carga.sql. Este archivo crea el esquema relacional final, usando DROP TABLE IF EXISTS ... CASCADE para poder ejecutarlo más de una vez. También crea secuencias para generar códigos internos de sucursal, lugar y reserva, ya que esos identificadores no venían siempre en los CSV. Además, se crea la función lugar_key, que permite comparar nombres de lugares aunque vengan escritos con diferencias de tildes, espacios o formato.
Después se crean las tablas finales con sus claves primarias, claves foráneas y restricciones. Se agregaron CHECK para validar estados, tipos, montos, fechas y rangos válidos. También se agregaron UNIQUE en casos donde era necesario evitar duplicados, como correos, nombres de sucursal o lugares dentro de una misma sucursal. Las tablas se crearon en un orden que respeta las dependencias, partiendo por tablas base como region y comuna, y luego avanzando hacia persona, sucursal, socio, lugar, reserva, evento y pagos.
Para cargar los datos, carga.sql primero usa tablas temporales con la misma forma de los OK.csv. Los archivos se cargan con \copy y luego se reparten a las tablas finales mediante INSERT INTO ... SELECT. Este método permite separar la forma original del CSV del modelo relacional final. Por ejemplo, personas_sociosOK.csv se reparte entre persona, socio, usuario, nacimiento y relacion_socio; mientras que sucursales_lugaresOK.csv se reparte entre sucursal, lugar y precio_lugar.
Durante la carga se tomaron decisiones para conservar la mayor cantidad de información posible. Por ejemplo, si una reserva tenía un reservante que no estaba en personas_sociosOK.csv, se creó una persona mínima con RUN y nombre. Si una reserva mencionaba un lugar que no existía en sucursales_lugaresOK.csv, se creó un lugar mínimo con capacidad 1. Estas acciones se registraron en cargaLOG.txt. Además, se genera cargaERR.csv para registrar filas que no pudieron cargarse por problemas de correspondencia, aunque en la ejecución final no quedaron errores de carga.

Finalmente se crearon las consultas SQL pedidas por el enunciado. Cada consulta se guardó en la carpeta consultas y genera su resultado en archivos txt dentro de consultas_generadas. Las consultas desarrolladas fueron agenda.sql, ingresomensual.sql, morosos.sql, finbeneficiario.sql e ingresoporsucursal.sql. Para comprobar que funcionaran correctamente, se probaron algunas con fechas alternativas cuando los datos originales no tenían registros en el período exacto pedido. Por ejemplo, agenda.sql se probó con abril de 2025 porque la semana de 2026 no tenía datos; ingresomensual.sql se probó con abril de 2025 porque había ingresos en ese mes; y finbeneficiario.sql se probó con renovación 2031 para verificar que apareciera un beneficiario que cumplía 29 años.

En conjunto, la solución permite transformar los archivos originales en una base de datos relacional consistente, registrar las correcciones realizadas y generar los reportes solicitados. El proceso queda dividido de forma clara: PHP limpia los datos, SQL construye y carga el modelo, y las consultas obtienen la información final requerida.

## 2.1 Limpieza de datos con PHP

El archivo main.php tiene como objetivo limpiar los CSV originales antes de cargarlos a la base de datos. La idea fue separar el proceso en dos etapas: primero PHP revisa y corrige los errores más directos de formato y contenido, y luego carga.sql se encarga de insertar los datos en las tablas finales y resolver relaciones entre tablas.
Para cada archivo original se genera un archivo OK, un archivo ERR y un archivo LOG. El archivo OK contiene las filas que pudieron ser usadas después de la limpieza. El archivo ERR contiene las filas que tenían errores irreparables o datos obligatorios faltantes. El archivo LOG registra las acciones realizadas, como normalizaciones, cambios de formato o valores que se dejaron nulos.
Se decidió que una fila con errores irreparables no debía pasar al OK, porque después podría romper las restricciones de SQL. En cambio, si un dato se podía corregir, se corregía y se dejaba registro en el LOG. Por ejemplo, se podían reparar espacios extra, mayúsculas, minúsculas, tildes, formatos de fecha, formatos de hora o caracteres dañados puntuales.
También se asumió que PHP no debía resolver todas las relaciones entre tablas, porque eso corresponde mejor a SQL. Por ejemplo, PHP limpia nombres de sucursal, nombres de lugar, RUN, fechas y montos, pero no asigna códigos internos de sucursal, lugar o reserva. Eso se deja para carga.sql, donde se generan códigos y se hacen los JOIN entre tablas.

### Personas_Socios

Para personas_socios.csv se revisan los datos principales de cada persona: RUN, nombre, correo, teléfonos, dirección, comuna, región, tipo de persona, datos de socio titular, parentesco, fechas de membresía, usuario del sistema, clave y sucursal base.

Para el RUN se intenta normalizar el formato y luego se valida el dígito verificador. Si el RUN no puede arreglarse o no es válido, la fila se manda a ERR, porque el RUN es el identificador principal de una persona y después se usa como PK o FK en SQL.

Para nombre, comuna, región y sucursal se limpian espacios, mayúsculas/minúsculas y tildes cuando corresponde. En regiones y comunas no se encontraron muchos errores irreparables; principalmente se podían corregir normalizando texto. También se revisa que la región y la comuna sean coherentes con las listas oficiales cargadas desde regiones_comunas.csv. Si el nombre de región o comuna no corresponde a los datos oficiales, se considera un error.

En las direcciones se hizo una limpieza simple de espacios y caracteres. Se encontró un caso puntual de codificación dañada en “Vicuña”, por lo que se agregó una reparación específica para ese caso. No se intentó inventar direcciones ni corregirlas más allá de problemas claros de formato.

Para los códigos de región se asumió que solo debían usarse los códigos definidos en el archivo oficial de regiones y comunas. No se inventan regiones ni se cambian códigos de manera arbitraria, porque esos códigos se usan después para crear region y comuna en SQL.

Para las fechas se usó validación con checkdate de PHP. También se repararon formatos con año corto. Se asumió que, si el año corto era menor a 26, correspondía a los años 2000, y si era mayor o igual a 26, correspondía a los años 1900. Esta regla se aplicó para poder rescatar fechas como 02-05-25 o 10-08-98 sin descartarlas inmediatamente.

Para tipo_persona se normalizaron valores como socio_titular, socio titular, beneficiario, adicional, invitado y administrativo. Se asumió que solo los tipos socio titular, beneficiario y adicional se cargarían después como socios. En cambio, invitados y administrativos se mantienen como personas, pero no se insertan en la tabla socio.

Para run_socio_titular se aplicó la regla final de que solo beneficiarios y adicionales deben tener un socio titular asociado. En el caso de socio titular, invitado o administrativo, ese campo no aplica y se deja nulo si venía informado. Esta decisión evita crear relaciones incorrectas entre personas que no deberían depender de un socio titular.

Para parentesco se asumió que aplica solo a beneficiarios. Si una persona beneficiaria no tenía parentesco, la fila se manda a ERR, porque faltaría información necesaria para relacionarla con el socio titular. Para adicionales, invitados, administrativos y socios titulares, si venía parentesco informado se dejaba nulo, porque no correspondía usarlo para esas categorías.

Para usuario del sistema se asumió que el campo debía indicar SI o NO. Si una persona no es usuaria del sistema, se dejan nulos tipo_usuario y clave. Si sí es usuaria, entonces se revisa que tenga tipo_usuario válido y clave en texto plano. Esto permite que después SQL solo inserte en usuario a quienes realmente correspondan.

### Sucursales_Lugares

Para sucursales_lugares.csv se revisan datos de sucursal, dirección, comuna, lugar, tipo de lugar, capacidad, precio, tipo de precio, día de semana, horas y fechas de vigencia.

Se asumió que el nombre de la sucursal debe ser obligatorio, porque después se usa para crear sucursales y conectar reservas, eventos y lugares. También se revisa comuna para que pueda asociarse correctamente con la tabla comuna en SQL.

Para lugar_nombre se limpian espacios y caracteres raros, pero no se genera un código en PHP. El código del lugar se genera después en SQL. También se decidió que los problemas de coincidencia entre nombres de lugares de distintos archivos se resolverían principalmente en SQL usando lugar_key, porque ahí se pueden comparar lugares ya cargados por sucursal.

Para capacidad y precio se exige que sean numéricos cuando son obligatorios. Si el precio viene vacío o no se puede convertir a número, la fila se manda a ERR, porque esa fila no permite crear correctamente el precio del lugar.

Para día de semana se normaliza el texto y se acepta solo lunes, martes, miércoles, jueves, viernes, sábado o domingo. Si viene vacío, se deja nulo, porque no todos los precios tienen que depender de un día específico. Se corrigió que cuando dia_semana venía vacío la función no debía hacer return, porque eso impedía seguir revisando horas y fechas de vigencia de la misma fila.

Para horas y fechas de vigencia se revisa formato y coherencia. Si hay hora de inicio y hora de término, se valida que la hora de inicio no sea posterior a la de término. Para fechas, se valida que la fecha de inicio no sea posterior a la fecha de fin.

### Reservas_Arriendos

Para reservas_arriendos.csv se revisan fechas de reserva, fecha de inicio, fecha de fin, estado de reserva, RUN y nombre del reservante, lugar, sucursal, montos, medio de pago y fecha de pago.

Se asumió que codigo_reserva podía venir vacío, porque la idea era generarlo en SQL. Por eso PHP no manda a ERR las filas sin codigo_reserva. En carga.sql se genera un código único usando el id temporal de cada fila.

Para fecha_inicio y fecha_fin se decidió que todas debían quedar en formato fecha-hora. Cuando una fecha venía sin hora, se asumió que correspondía a una reserva de día completo. Por eso se asignó 00:00 como hora de inicio y 23:59 como hora de término. Esta decisión permite conservar reservas que de otra forma se habrían perdido, y se deja registrada en el LOG.

Para estado_reserva se aceptan los valores reservada, ejecutada y cancelada, que son los valores usados después en SQL. Si el estado no corresponde a uno de esos valores, la fila se considera inválida.

Para run_reservante se valida el RUN, porque después se usa para conectar la reserva con persona. Si el RUN no es válido, la fila no se puede cargar correctamente. Para nombre_reservante se revisa que no venga vacío y que tenga un formato razonable de nombre.

Para lugar_nombre y sucursal_nombre se limpian espacios y caracteres, pero no se fuerza una coincidencia exacta en PHP. Esa parte se deja para SQL, porque en carga.sql se usa lugar_key para relacionar nombres escritos de forma distinta entre reservas y sucursales_lugares.

Para monto_total y monto_pagado se revisa que sean numéricos cuando corresponde. monto_pagado puede ser cero o venir vacío dependiendo de si existe pago. Los pagos reales se insertan después en SQL solo cuando hay monto pagado mayor que cero y fecha de pago.

### Eventos

Para eventos.csv se revisan los datos de evento, fecha de contratación, fecha de evento, lugar, sucursal, tipo de cliente, datos del cliente, contacto de empresa, asistentes y montos.

Se corrigieron varios caracteres dañados frecuentes en nombres de eventos, clientes y contactos, especialmente problemas de codificación con tildes y ñ. Estas reparaciones se hicieron solo cuando el patrón era claro.

Para tipo_cliente se normalizó a socio, persona o empresa. Esta decisión se usó después en SQL para decidir si el identificador del cliente correspondía a una persona/socio o a una empresa.

Para clientes tipo socio o persona, se revisa RUN cuando viene informado. Para clientes tipo empresa, se interpreta el identificador como RUT de empresa en SQL. La creación de empresas y contactos se deja para carga.sql, porque depende de cómo se insertan eventos y empresas.

Para nombre_cliente se permitió que, cuando el tipo de cliente era socio, pudiera venir vacío si el RUN permitía identificarlo. En cambio, para otros casos se intentó conservar o limpiar el nombre cuando venía informado.

Para asistentes_texto se separan asistentes por punto y coma. Se limpian nombres vacíos y se eliminan asistentes que no tienen un formato mínimo de nombre y apellido. No se crean personas completas para asistentes en PHP, porque en SQL se guardan como asistentes del evento según el texto disponible.

Para montos de evento se revisa que sean numéricos. El monto_total_evento se conserva porque después se usa para calcular ingresos futuros esperados. Los montos pagados se usan en SQL para crear pagos de reserva y pagos de ejecución del evento.

### Pagos_Membresias

Para pagos_membresias.csv se revisan los pagos asociados a membresías, incluyendo RUN del socio titular, año de membresía, mes de cuota, fecha de vencimiento, montos, estado de pago, fecha de pago y medio de pago.

Para run_socio_titular se valida el RUN porque después se usa para conectar el pago con el socio y su membresía. Si no es válido, la fila no se puede cargar.

Para anio_membresia se dejó un rango amplio, pero razonable, para evitar años absurdos. Se decidió usar un rango como 1950 a 2050, porque era más flexible que limitarlo solo al año actual y permitía conservar datos históricos o futuros razonables.

Para mes_cuota se valida que sea un número entre 1 y 12. Para fecha_vencimiento y fecha_pago se revisa formato de fecha. La fecha de pago puede venir vacía si la cuota está atrasada, porque en ese caso todavía no existe pago efectivo.

Para montos se revisa que monto_membresia y monto_total sean numéricos. monto_adicionales no se consideró obligatorio; si venía vacío, se asumió como 0 para revisar la coherencia de montos. Esto permite validar que monto_total corresponda a monto_membresia más monto_adicionales sin rechazar filas solo porque el adicional no venía informado.

Para estado_pago se normaliza a pagado o atrasado. Si el estado es pagado, se espera que exista fecha de pago y medio de pago. Si está atrasado, se permite que la fecha de pago venga vacía, porque todavía no se ha recibido el pago.

### Cargos_Administrativos

Para cargos_administrativos.csv se revisan RUN de la persona, sucursal, nombre del cargo, fecha de inicio y fecha de término del cargo.

El RUN se valida porque se usa para relacionar el cargo con una persona. La sucursal se limpia y se deja como texto para que SQL la conecte después con la tabla sucursal.

El nombre del cargo se limpia y se conserva como texto. No se intenta clasificarlo completamente en PHP, porque SQL crea la tabla cargo con los nombres distintos y después la consulta ingresoporsucursal.sql busca cargos que contengan la palabra gerente.

Para fechas de cargo se revisa formato y coherencia. La fecha de inicio es obligatoria porque permite saber desde cuándo una persona ocupa el cargo. La fecha de término puede venir vacía, porque eso representa un cargo vigente. Si ambas fechas existen, se revisa que la fecha de término no sea anterior a la fecha de inicio.

### Criterios generales usados

En general, PHP se usó para limpiar errores de formato y descartar filas con errores irreparables. Se corrigieron espacios extra, mayúsculas/minúsculas, tildes, caracteres dañados, fechas, horas, RUN, números y opciones cerradas.

No se usó PHP para crear claves internas ni para resolver todas las relaciones entre entidades. Eso se dejó para SQL, porque ahí se pueden generar códigos, insertar tablas en orden y hacer JOIN entre temporales y tablas finales.

También se decidió que PHP no debía inventar información importante. Solo se completaron datos cuando había una regla clara, como asignar 00:00 y 23:59 a reservas con fecha pero sin hora, o asumir monto_adicionales como 0 cuando venía vacío. En los casos donde no había una reparación razonable, la fila se mandó a ERR.

De esta forma, main.php prepara los archivos para que carga.sql pueda trabajar sobre datos más limpios y consistentes, minimizando errores de carga y reduciendo la pérdida de información.

## 2.2 Carga de datos con Psql

El archivo carga.sql tiene como objetivo crear el esquema final de la base de datos, cargar los archivos limpios generados por mi main.php, Y armar las tablas finales usando los datos de los OK.csv obtenidos. Para esto se usaron las funciones de SQL; CREATE TABLE, CREATE SEQUENCE, CREATE FUNCTION, tablas temporales, \copy e INSERT INTO ... SELECT.
Al inicio se usa \set ON_ERROR_STOP on para que psql detenga la ejecución si ocurre un error. Esto es importante porque evita que la carga siga avanzando sobre una base incompleta o no coherente. También se usa BEGIN al inicio y COMMIT al final, para que la creación y carga de datos funcionen como un solo proceso.

Se usan DROP TABLE IF EXISTS ... CASCADE para poder ejecutar el archivo más de una vez. El CASCADE es necesario porque hay tablas que dependen de otras mediante claves foráneas. Por eso se borran primero las tablas más dependientes como pagos, asistentes y relaciones, y después las tablas base, como persona, sucursal, comuna y region.

También se crean secuencias para generar códigos internos de sucursal, lugar y reserva, ya que los CSV no siempre traen estos identificadores. Por ejemplo, las sucursales y lugares vienen por nombre, y el codigo_reserva venía vacío. Por eso SQL genera códigos como SUC001, LUG000001 o RES000001.

Además, se crea la función lugar_key(text), que sirve para normalizar nombres de lugares. Esto fue necesario porque un mismo lugar podía venir escrito de distintas formas entre archivos con espacios, tildes o formatos diferentes. Con esta función se pueden evitar errores al unir reservas, lugares y sucursales.

### Creación de tablas y restricciones

Lo principal en esta parte era crear las yablas en un esquema ordenado respetando las dependencias para evitar errores y facilitar el resto del proceso de carga. 
Las tablas region y comuna se crean primero porque son tablas base. Region tiene como PK codigo_region y se agrega un CHECK para que el código esté entre 1 y 16. Comuna tiene como PK codigo_comuna y una FK hacia region, porque cada comuna pertenece a una región.

La tabla persona guarda los datos comunes de todas las personas, como RUN, nombre, correo, teléfonos, dirección, comuna y fecha de nacimiento. Se usa run como PK, porque identifica a cada persona. También se agrega una FK hacia comuna y restricciones para que el email sea único y los teléfonos tengan largo 9 cuando no son nulos. Esto es coherente con la limpieza hecha en PHP.

La tabla sucursal guarda las sucursales del club. Como el CSV trae el nombre de la sucursal y no un código, el codigo_sucursal se genera automáticamente con una secuencia. Además, el nombre de la sucursal queda como UNIQUE para evitar duplicados, y se relaciona con comuna mediante una FK.

La tabla socio se separa de persona porque no todas las personas son socios. Solo se insertan como socios quienes tienen tipo socio titular, beneficiario o adicional. Se agrega una FK hacia persona y otra hacia sucursal, además de un CHECK para permitir solo esos tipos de socio. También se valida que la fecha de fin no sea anterior a la fecha de inicio.

La tabla lugar representa los espacios físicos de cada sucursal. El codigo_lugar se genera automáticamente, ya que los CSV identifican lugares por nombre. Cada lugar tiene una FK hacia sucursal. También se agrega una restricción para que la capacidad sea mayor que cero y un UNIQUE sobre nombre y sucursal, porque un mismo nombre no debería repetirse dentro de la misma sucursal.

La tabla usuario guarda las personas que son usuarios del sistema. Solo se carga cuando es_usuario_sistema es SI. Se agregan restricciones para que el email de login sea único, para que una persona no tenga más de un usuario y para que el tipo de usuario sea admin, administrativo o socio.

La tabla nacimiento se mantiene como tabla auxiliar para guardar RUN y fecha de nacimiento. Se usó el nombre nacimiento en vez de naci para que fuera más claro. Esta tabla sirve especialmente para consultas donde se necesita trabajar con la edad, como finbeneficiario.sql.

La tabla persona_cargo conecta una persona, un cargo y una sucursal. Sirve para registrar cargos administrativos y poder identificar, por ejemplo, el gerente de una sucursal. Se agregan FK hacia persona, cargo y sucursal, además de una restricción para que la fecha de término no sea anterior a la fecha de inicio.

La tabla relacion_socio conecta socios dependientes con su socio titular. Se usa para beneficiarios y adicionales. Tiene dos FK hacia socio: una para el titular y otra para el dependiente. También se agrega una restricción para evitar que un socio quede relacionado consigo mismo y un UNIQUE para no duplicar la misma relación.

La tabla membresia representa la membresía anual de un socio titular. Se conecta con socio y tiene restricciones para validar el año, los montos y las fechas. También se agrega UNIQUE sobre socio titular y año, para que un socio no tenga dos membresías para el mismo año.

La tabla cuota guarda las cuotas mensuales de una membresía. Tiene FK hacia membresia, y se agregan restricciones para que el mes esté entre 1 y 12, el monto no sea negativo y el estado sea pagado o atrasado, que son los valores que quedaron normalizados desde PHP.

La tabla precio_lugar guarda los precios de los lugares. Se relaciona con lugar y permite guardar tipo de precio, día, horas, fechas de vigencia y monto. Se agregan CHECK para validar montos no negativos, tipos de precio, días de la semana y coherencia entre horas y fechas.

La tabla reserva guarda las reservas o arriendos. El codigo_reserva se genera en SQL porque venía vacío en el CSV. Se usa timestamp para fecha_inicio y fecha_fin, porque las reservas requieren fecha y hora. Esto también es coherente con PHP, donde las fechas sin hora se completaron con 00:00 y 23:59. Además, se agrega monto_total para poder calcular ingresos futuros en las consultas.

La tabla evento guarda los eventos realizados en las sucursales. Tiene FK hacia lugar y sucursal, y guarda tipo de cliente, identificador del cliente y monto total. El monto_total se agregó porque era necesario para calcular ingresos futuros esperados en ingresomensual.sql.

Las tablas pago_cuota, pago_reserva y pago_evento guardan pagos efectivamente recibidos. Cada una tiene restricciones para que los montos no sean negativos y para controlar los medios o tipos de pago. En pago_evento se separan pagos de reserva y pagos de ejecución.

### Tablas temporales y carga con \copy

Después de crear las tablas finales, se crean tablas temporales con la misma forma que los archivos OK.csv. Esto se hace porque los CSV no vienen con la misma estructura del modelo final. Por ejemplo, personas_sociosOK.csv trae datos que después se reparten entre persona, socio, usuario, nacimiento y relacion_socio. Lo mismo ocurre con sucursales_lugaresOK.csv, que trae información mezclada de sucursales, lugares y precios.

Primero se cargan los CSV a las tablas temporales usando \copy. Se usa CSV HEADER porque la primera fila trae los nombres de las columnas. Luego, desde esas tablas temporales, se hacen los INSERT INTO ... SELECT hacia las tablas finales.

### INSERT INTO

Primero se insertan region y comuna desde regiones_comunasOK.csv. Se usa SELECT DISTINCT porque las regiones aparecen repetidas para distintas comunas. También se usa ON CONFLICT DO NOTHING para evitar errores por datos repetidos.

Luego se insertan las sucursales desde sucursales_lugaresOK.csv. Como cada sucursal aparece repetida en varias filas, se usa DISTINCT ON para dejar solo una fila por sucursal. El código de sucursal se genera automáticamente con la secuencia.

Después se insertan las personas desde personas_sociosOK.csv. Para manejar correos duplicados se usa ROW_NUMBER, conservando el primer correo y dejando los repetidos como NULL. Esto permite mantener UNIQUE(email) sin perder personas completas. También se usa NULLIF para transformar celdas vacías en NULL.

Además, se crean personas mínimas desde reservas y eventos cuando aparece un RUN que no estaba en personas_sociosOK.csv. Esto evita perder reservas o eventos por problemas de FK hacia persona. En esos casos se guarda el RUN y nombre, y el resto queda nulo.

Luego se insertan los socios desde personas_sociosOK.csv, pero solo para socio titular, beneficiario y adicional. También se crean socios mínimos para reservantes que no estaban registrados como socios, porque las reservas deben quedar asociadas a una persona/socio y no se quería perder esa información.

La tabla usuario se carga solo para personas marcadas como usuarios del sistema. Para eso se exige que tengan correo, clave y tipo de usuario.

La tabla relacion_socio se carga para conectar beneficiarios y adicionales con su socio titular. Para adicionales se guarda el parentesco como adicional, porque el campo parentesco aplica principalmente a beneficiarios.

Las membresías se crean desde pagos_membresiasOK.csv, usando una membresía por socio titular y año. Luego se crean las cuotas mensuales asociadas a cada membresía. Los pagos de cuota solo se insertan cuando el estado es pagado, porque una cuota atrasada representa deuda, no un pago recibido.

Los cargos se insertan desde cargos_administrativosOK.csv. Primero se crean los nombres de cargos sin repetir y luego se asigna cada cargo a una persona y sucursal en persona_cargo.

Las empresas y contactos se crean desde eventosOK.csv. Cuando tipo_cliente es empresa, el run_cliente se interpreta como RUT de la empresa. Luego se insertan los contactos asociados a esa empresa cuando vienen informados.

Los lugares se crean primero desde sucursales_lugaresOK.csv, usando lugar_key para evitar duplicados por diferencias de escritura. Después se crean lugares faltantes desde reservas_arriendosOK.csv cuando una reserva menciona un lugar que no existía en sucursales_lugaresOK.csv. Estos lugares se crean con capacidad 1 para no perder reservas.

Los precios se insertan desde sucursales_lugaresOK.csv y se asocian al lugar correspondiente usando lugar_key. Se usa SELECT DISTINCT para evitar duplicar precios iguales.

Las reservas se insertan desde reservas_arriendosOK.csv. Antes de insertarlas se registran en carga_err las que no puedan cargarse por falta de sucursal, lugar o persona. El codigo_reserva se genera con el id temporal de la fila, para que sea único y estable. También se cargan fechas, estado y monto total.

Los pagos de reserva solo se insertan cuando existe monto pagado mayor que cero y fecha de pago. Esto evita crear pagos vacíos.

Los eventos se insertan desde eventosOK.csv con un código generado a partir del evento_id. Antes de insertarlos, también se registran en carga_err los eventos que no puedan cargarse por falta de sucursal o lugar. Luego se insertan los asistentes separando la lista de asistentes por punto y coma.

Finalmente, pago_evento se carga separando pagos de reserva y pagos de ejecución. Solo se insertan pagos cuando el monto es mayor que cero y existe una fecha asociada.

Al final, carga.sql exporta cargaERR.csv y cargaLOG.txt. En la ejecución final, cargaERR.csv quedó sin filas, lo que indica que las filas de los OK.csv pudieron cargarse correctamente o resolverse con las reglas definidas. En cambio, cargaLOG.txt registra las acciones realizadas durante la carga, como creación de personas mínimas, socios mínimos o lugares faltantes.

En conclusión, carga.sql permite pasar desde archivos CSV limpios a un modelo relacional consistente. Las tablas finales mantienen PK, FK, UNIQUE y CHECK para asegurar integridad, mientras que las tablas temporales permiten cargar los CSV sin perder su forma original. Las decisiones principales fueron generar códigos internos, normalizar nombres de lugares con lugar_key, crear registros mínimos cuando era necesario y registrar esas acciones en el log, buscando minimizar la pérdida de información.



## 2.3 Consultas SQL

### agenda.sql

Para la consulta agenda.sql, se busca mostrar la agenda de la sucursal “Santa Cruz” para la semana que comienza el 6 de abril de 2026 hasta el 13 de abril de 2026. Para resolverla, se propone la consulta con una tabla temporal usando WITH agenda AS, dentro de la cual se combinan las reservas y los eventos, ya que la agenda de una sucursal no está formada por una sola tabla y hay que considerar tanto las reservas realizadas por los socios como los eventos realizados en los lugares de la sucursal.

Se seleccionaron las reservas, uniéndolas con las personas para obtener el nombre del reservante. También utilizamos lugar para saber qué espacio fue reservado y sucursal para filtrar solo las reservas correspondientes a “Santa Cruz”.

Luego agregamos los eventos con UNION ALL. Como los eventos tienen fecha_evento y no almacenan hora específica, se propone que la hora de inicio sea 00:00 para poder unirlos en una misma estructura con las reservas. Luego agrupamos por fecha, hora y lugar según lo pedido. También usamos string_agg() para manejar el caso en que hubiera más de una actividad asociada a un mismo día, hora y lugar, de modo que aparezcan en una sola línea. Luego se ordenó por fecha, hora y lugar para ordenar los datos cronológicamente.

De este modo, se crea una consulta funcional, ya que sigue las relaciones del modelo de manera directa: una reserva se asocia a un lugar, el lugar pertenece a una sucursal y el reservante se obtiene desde persona. Para los eventos ocurre lo mismo, ya que cada evento está asociado a una sucursal y a un lugar.

Para la ejecución de esta consulta, al no haber datos que cumplieran con lo solicitado entre los datos de los CSV dados, se probó la validez y funcionalidad de la consulta cambiando el año a 2025, pero manteniendo los días. Esto nos permitió obtener 11 filas en vez de una tabla nula, que luego pudimos verificar comparando con el CSV para ver que la ejecución de la consulta fuese correcta.

### ingresomensual.sql

Para la consulta ingresomensual.sql, se calcula el ingreso mensual de la sucursal Santa Cruz, separando los ingresos por membresías, reservas ejecutadas y eventos, dividiendo también los montos en efectivamente recibidos e ingresos futuros esperados. Para construir esta consulta se utilizó WITH parametros AS(), para poder definir el inicio y el fin del mes actual, del cual se utiliza el primer día del mes actual, y luego se le suma un mes para obtener el límite superior del período.

La consulta calcula ingresos a partir de tres fuentes distintas. Para las membresías se usan las tablas pago_cuota, cuota, membresia, socio y sucursal. Los ingresos recibidos corresponden a los pagos registrados en pago_cuota, los cuales luego filtramos por fecha_pago dentro del mismo mes. Los ingresos futuros esperados de membresía corresponden a todas las cuotas que tienen el estado de atrasado.

Para las reservas ejecutadas se utiliza pago_reserva, reserva, lugar y sucursal, donde se utilizan todos los pago_reserva que ya tengan el estado de ejecutado. Para los ingresos futuros luego se calcula la diferencia entre el monto total de la reserva y lo que ya está pagado.
Es por esto que, al hacer esta consulta, fue necesario agregar monto_total a la tabla de reserva durante la carga, para poder facilitar el cálculo de los saldos pendientes. Luego, para eventos, los ingresos efectivamente recibidos se toman desde pago_evento, mientras que los ingresos futuros esperados se calculan como la diferencia entre el monto total del evento y la suma de los pagos ya realizados. Por esto también fue necesario agregar monto_total a evento.

Luego hicimos un UNION ALL y agrupamos por concepto y tipo_ingreso con COALESCE.
Al igual que con la consulta anterior, al ejecutar esta consulta con la fecha actual, la tabla resultante podía aparecer nula, ya que la base de datos tenía datos disponibles principalmente en el año 2025. Por lo tanto, para probar que la consulta funcionaba, se cambió temporalmente el bloque de parámetros a abril de 2025. Luego, con esto, pudimos obtener los ingresos por las membresías y por las reservas ejecutadas, resultando en la siguiente tabla:

	+---------------------+------------------------+-----------------+
	|      concepto       |      tipo_ingreso      | ingreso_mensual |
	+---------------------+------------------------+-----------------+
	| eventos             | efectivamente recibido |               0 |
	| eventos             | futuro esperado        |               0 |
	| membresias          | efectivamente recibido |          270000 |
	| membresias          | futuro esperado        |               0 |
	| reservas ejecutadas | efectivamente recibido |         2765000 |
	| reservas ejecutadas | futuro esperado        |               0 |
	+---------------------+------------------------+-----------------+

Lo que nos permitió verificar que la consulta era ejecutable y correcta. 

### morosos.sql

Para la consulta morosos.sql, generamos un reporte de todos los socios que tienen cuotas atrasadas, con sus nombres completos, RUN, sucursal, monto total atrasado y número de cuotas atrasadas.

Partimos desde la tabla cuota, para poder solicitar el estado de cada cuota, filtrando por el estado “atrasado”. Luego, cada cuota se conecta con su membresía correspondiente, que luego se conecta con el socio titular. A partir de la tabla de socios se puede obtener el RUN de la persona asociada y, con ese RUN, podemos unirlo a la persona desde la tabla de personas, que luego también nos permite conectarlo con sucursal. La consulta agrupa por socio y sucursal.
Luego calculamos el monto atrasado usando la función de suma, y contamos el número de cuotas atrasadas con COUNT. Finalmente, se ordena de mayor a menor monto atrasado, y luego por número de cuotas, lo que permite que los socios con mayor deuda aparezcan primero.

Al ejecutar la consulta, se obtuvieron 46 socios morosos. Luego verificamos manualmente con el CSV de pagos_membresias y verificamos que la consulta efectivamente funcionaba, ya que cumplía con los montos y números de cuotas, y también había justo 46 socios morosos.

### finbeneficiario.sql

Para finbeneficiario.sql, se busca obtener todos los beneficiarios que son hijos de un socio titular y que, en la próxima renovación de membresía, cumplen 29 años.
Para esta consulta se parte de la tabla relacion_socio, ya que ahí podemos conectar con mayor facilidad cada socio dependiente con su socio titular.

También se une con la tabla persona dos veces: una para obtener los datos personales del beneficiario y otra para obtener los datos personales del socio titular mediante filtrado. Luego, para encontrar quiénes cumplen 29 años, se compara la fecha de nacimiento y se le suman 29 años con el rango de la próxima renovación. La versión final se calcula con la fecha actual, pero para revisar que la consulta estuviese correcta, se probó cambiando el rango de renovación a 2031, buscando que apareciera el beneficiario Mauricio Ortiz Barrera, asociado al socio titular Kevin Henríquez Duarte. Esto ocurre porque, siguiendo con la lógica de esta consulta, al ser Mauricio Ortiz Barrera nacido el 2002, al ingresar el año de renovación como 2031 debería aparecer como resultado en la consulta.

### ingresoporsucursal.sql

Para la consulta ingresoporsucursal.sql, generamos un reporte para el año 2025 de todas las sucursales y el porcentaje total del club correspondiente a esa sucursal, ordenando de mayor a menor ingreso. Para esta consulta se construyeron varias tablas comunes con WITH. Primero definimos una tabla de gerente, que busca el gerente vigente de cada sucursal durante el año 2025, usando persona_cargo, persona, cargo y sucursal. Luego filtramos por los cargos que contengan la palabra gerente.

Luego calculamos los ingresos por las tres vías de ingreso. La primera son los ingresos por membresías, tomados desde pago_cuota, conectando con cuota, membresia, socio y sucursal. Luego calculamos las reservas desde pago_reserva, conectando con reserva, lugar y sucursal. Finalmente, calculamos los ingresos por eventos tomados desde pago_evento, conectando con evento y sucursal.

Para cada una de las fuentes de ingresos se filtra por año, tomando como inicio el 1 de enero de 2025 y como fecha final el 1 de enero de 2026. Luego sumamos los ingresos, y calculamos el total del club sumando los ingresos de todas las sucursales y calculando el porcentaje de cada sucursal. El resultado obtenido para esta consulta fue:

	+-----------------+------------------------+------------------+-----------------------+
	|    sucursal     |    gerente_a_cargo     | ingresos_totales | porcentaje_total_club |
	+-----------------+------------------------+------------------+-----------------------+
	| Providencia     | Loreto Alvarez Barrera |       1062571000 |                 29.30 |
	| Santiago Centro | Sin gerente registrado |        999793000 |                 27.57 |
	| Santa Cruz      | Sin gerente registrado |        878951000 |                 24.23 |
	| La Florida      | Sin gerente registrado |        685474000 |                 18.90 |
	+-----------------+------------------------+------------------+-----------------------+

Lo que podemos ver es que se validó mediante verificación manual, y también chequeando que los porcentajes suman aproximadamente 100%. La consulta también nos muestra el gerente a cargo, si es que existe esa información. En nuestra consulta, el único gerente con nombre era el gerente encontrado en la sucursal de Providencia.



## 3. Referencias y bibliografía externa

<!-- en cada sección indica %IA, Tecnología y Prompt -->


Para el desarrollo de esta entrega se utilizaron principalmente los materiales del curso, las ayudantías entregadas, el enunciado de la E2, la documentación oficial de PHP y apoyo puntual de herramientas externas. También se usó IA como apoyo en algunas partes del proceso, pero la lógica general de limpieza, modelación y carga fue definida completamente por mi en el desarrollo de mi trabajo. Los porcentajes aproximados del uso de IA son:

	main.php --> 10% IA
	carga.sql --> 50% IA
	consultas.sql --> 30% IA

Y la IA usada fue ChatGPT plan pagado basico.

En main.php, la mayor parte del código fue desarrollada manualmente. La IA se usó principalmente como apoyo para la parte de abrir, leer, escribir y cerrar archivos CSV, además de revisar algunos CSV grandes para detectar posibles errores por columna. También se usó para revisar los archivos OK.csv después de la limpieza, como segunda verificación para ver que no quedaran errores evidentes de formato.

Tambien se uso IA para la generacion de algunas funciones especificas donde, al ser "nueva" en PHP, entendía la logica pero no conocia bien como implementarla en php. Por ejemplo, en el caso de la función de dirección, se pidió ayuda para separar letras y números cuando venían pegados, por ejemplo convertir Avenida123 en Avenida 123. Para eso se usó un prompt del estilo: “¿Cómo hago para separar letras y números en un string en PHP?”. De ahí se obtuvo la idea de usar preg_replace y otras funciones simples.

También se usó IA para revisar nombres de comunas y detectar versiones que podían necesitar tildes o normalización, pero las decisiones finales de limpieza y qué mandar a OK o ERR fueron tomadas manualmente.

En carga.sql, la IA se usó más como apoyo para escribir bloques largos y repetitivos de SQL, especialmente en CREATE TABLE, INSERT INTO ... SELECT, JOIN, ON CONFLICT, NULLIF, COALESCE y partes más complejas como ROW_NUMBER, DISTINCT ON, lugar_key, unnest y string_to_array. En algunas partes donde no sabia muy bien que hacer, pero entendia el flujo , dependencias, estructuras y constraints de las tablas y su implementación, le daba a la IA todo mi concepto para la carga e implementación de una tabla o proceso especifico y le pedia su versión de codigo, aseguranodme de entenderlo todo y poder recrearlo personalemente antes de implementarlo en mi codigo.

Algunos prompts usados fueron del estilo: “¿Cómo genero códigos únicos para reservas si el CSV viene sin codigo_reserva?”, “¿Cómo comparo nombres de lugares escritos distinto en SQL?” o “¿Cómo evito que un correo duplicado rompa un UNIQUE sin perder la persona?”.

En las consultas SQL, la IA se usó como apoyo para estructurar mejor los JOIN, agrupaciones y cálculos. La interpretación de lo pedido por cada consulta y la validación de los resultados se hizo manualmente, comparando con los CSV y con las tablas cargadas. También se usó IA para ayudar a explicar por qué algunas consultas daban vacío con las fechas originales y cómo probarlas con fechas donde sí había datos.

Como herramientas externas se usó la extensión de VS Code PHP de Devsense, con autocompletado e IntelliSense, para escribir código de manera más rápida. También se consultó la documentación oficial de PHP para la función checkdate, usada en la validación de fechas:

https://www.php.net/manual/en/function.checkdate.php


## 4. Instrucciones de ejecución

Desde cd Entrega_E2, los archivos se pueden ejecutar en orden de la siguiente manera:

	php main.php
	psql -d egonzalezl8.e2 -f carga.sql
	psql -d egonzalezl8.e2 -f consultas/agenda.sql
	psql -d egonzalezl8.e2 -f consultas/ingresomensual.sql
	psql -d egonzalezl8.e2 -f consultas/morosos.sql
	psql -d egonzalezl8.e2 -f consultas/finbeneficiario.sql
	psql -d egonzalezl8.e2 -f consultas/ingresoporsucursal.sql

Asumiendo siempre que uno se encuentre en Entrega_E2.