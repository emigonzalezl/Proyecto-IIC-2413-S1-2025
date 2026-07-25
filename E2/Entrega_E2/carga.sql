-- Para evitar errores de carga
\set ON_ERROR_STOP on

-- Iniciamos una transacción para que la creación y carga de datos se trate como un solo proceso
BEGIN;

-- Primero borramos las tablas anteriores para poder ejecutar carga.sql más de una vez.
-- Se borran primero las tablas más dependientes y después las tablas base
DROP TABLE IF EXISTS asistente_evento CASCADE;
DROP TABLE IF EXISTS pago_evento CASCADE;
DROP TABLE IF EXISTS pago_reserva CASCADE;
DROP TABLE IF EXISTS pago_cuota CASCADE;
DROP TABLE IF EXISTS cuota CASCADE;
DROP TABLE IF EXISTS membresia CASCADE;
DROP TABLE IF EXISTS relacion_socio CASCADE;
DROP TABLE IF EXISTS persona_cargo CASCADE;
DROP TABLE IF EXISTS usuario CASCADE;
DROP TABLE IF EXISTS socio CASCADE;
DROP TABLE IF EXISTS reserva CASCADE;
DROP TABLE IF EXISTS evento CASCADE;
DROP TABLE IF EXISTS precio_lugar CASCADE;
DROP TABLE IF EXISTS lugar CASCADE;
DROP TABLE IF EXISTS contacto_empresa CASCADE;
DROP TABLE IF EXISTS empresa CASCADE;
DROP TABLE IF EXISTS persona CASCADE;
DROP TABLE IF EXISTS sucursal CASCADE;
DROP TABLE IF EXISTS cargo CASCADE;
DROP TABLE IF EXISTS comuna CASCADE;
DROP TABLE IF EXISTS region CASCADE;
DROP TABLE IF EXISTS nacimiento CASCADE;

-- borramos las secuencias usadas para generar códigos internos
DROP SEQUENCE IF EXISTS codigo_sucursal_seq CASCADE;
DROP SEQUENCE IF EXISTS codigo_lugar_seq CASCADE;
DROP SEQUENCE IF EXISTS codigo_reserva_seq CASCADE;

-- Borramos la función auxiliar
DROP FUNCTION IF EXISTS lugar_key(text);

-- Creamos secuencias para generar códigos internos que no vienen en los CSV
CREATE SEQUENCE codigo_sucursal_seq START 1;
CREATE SEQUENCE codigo_lugar_seq START 1;
CREATE SEQUENCE codigo_reserva_seq START 1;

-- normalizar nombres de lugares para poder compararlos aunque vengan escritos distinto
-- elimina una X final cuando aparece como marca operacional
CREATE OR REPLACE FUNCTION lugar_key(txt text)
RETURNS text AS $$
    SELECT regexp_replace(
        lower(
            translate(
                regexp_replace(coalesce($1, ''), '[[:space:]]+[Xx]$', ''),
                'áéíóúÁÉÍÓÚñÑ',
                'aeiouAEIOUnN'
            )
        ),
        '[^a-z0-9]',
        '',
        'g'
    );
$$ LANGUAGE SQL IMMUTABLE;

-- Tabla base de regiones
-- Se carga desde regiones_comunasOK.csv y se usa como referencia para comuna
CREATE TABLE region (
    codigo_region integer NOT NULL,
    nombre varchar(100) NOT NULL,
    CONSTRAINT region_pkey PRIMARY KEY (codigo_region),
    CONSTRAINT region_codigo_check CHECK (codigo_region BETWEEN 1 AND 16),
    CONSTRAINT region_nombre_unique UNIQUE (nombre)
);

-- Tabla base de comunas.
-- Cada comuna pertenece a una región asi que FK hacia region.
CREATE TABLE comuna (
    codigo_comuna integer NOT NULL,
    nombre varchar(100) NOT NULL,
    codigo_region integer NOT NULL,
    CONSTRAINT comuna_pkey PRIMARY KEY (codigo_comuna),
    CONSTRAINT comuna_codigo_region_fkey FOREIGN KEY (codigo_region) REFERENCES region(codigo_region),
    CONSTRAINT comuna_nombre_region_unique UNIQUE (nombre, codigo_region)
);

-- Tabla de tipos de cargos administrativos.
-- Se separa para no repetir el nombre del cargo en persona_cargo.
CREATE TABLE cargo (
    id_cargo serial NOT NULL,
    nombre varchar(100) NOT NULL,
    CONSTRAINT cargo_pkey PRIMARY KEY (id_cargo),
    CONSTRAINT cargo_nombre_unique UNIQUE (nombre)
);

-- Tabla de empresas clientes de eventos.
-- El RUT de empresa se usa como identificador principal.
CREATE TABLE empresa (
    rut_empresa varchar(15) NOT NULL,
    nombre varchar(150) NOT NULL,
    CONSTRAINT empresa_pkey PRIMARY KEY (rut_empresa)
);

-- Tabla central de personas.
-- Aquí quedan socios, beneficiarios, adicionales, invitados, administrativos, reservantes y contactos si corresponde.
-- Se mantiene RUN como PK
CREATE TABLE persona (
    run varchar(12) NOT NULL,
    nombre_completo varchar(150) NOT NULL,
    email varchar(150),
    telefono_celular varchar(20),
    telefono_alternativo varchar(20),
    direccion_calle varchar(150),
    codigo_comuna integer,
    fecha_nacimiento date,
    CONSTRAINT persona_pkey PRIMARY KEY (run),
    CONSTRAINT persona_codigo_comuna_fkey FOREIGN KEY (codigo_comuna) REFERENCES comuna(codigo_comuna),
    CONSTRAINT persona_email_unique UNIQUE (email),
    CONSTRAINT persona_telefono_celular_check CHECK (telefono_celular IS NULL OR length(telefono_celular) = 9),
    CONSTRAINT persona_telefono_alternativo_check CHECK (telefono_alternativo IS NULL OR length(telefono_alternativo) = 9)
);

-- Tabla de sucursales
-- El código interno se genera con secuencia porque el CSV trae nombres de sucursal no códigos
-- El nombre queda UNIQUE porque nombres de sucursales son únicos
CREATE TABLE sucursal (
    codigo_sucursal varchar(10) NOT NULL DEFAULT ('SUC' || lpad(nextval('codigo_sucursal_seq')::text, 3, '0')),
    nombre varchar(100) NOT NULL,
    direccion varchar(150) NOT NULL,
    codigo_comuna integer,
    CONSTRAINT sucursal_pkey PRIMARY KEY (codigo_sucursal),
    CONSTRAINT sucursal_codigo_comuna_fkey FOREIGN KEY (codigo_comuna) REFERENCES comuna(codigo_comuna),
    CONSTRAINT sucursal_nombre_unique UNIQUE (nombre)
);

-- Tabla de socios del club
-- Solo entran los tipos de persona que efectivamente son socios: socio titular, beneficiario y adicional
CREATE TABLE socio (
    id_socio serial NOT NULL,
    run_persona varchar(12),
    tipo_socio varchar(30) NOT NULL,
    fecha_inicio date NOT NULL,
    fecha_fin date,
    codigo_sucursal_base varchar(10),
    CONSTRAINT socio_pkey PRIMARY KEY (id_socio),
    CONSTRAINT socio_run_persona_fkey FOREIGN KEY (run_persona) REFERENCES persona(run),
    CONSTRAINT socio_codigo_sucursal_base_fkey FOREIGN KEY (codigo_sucursal_base) REFERENCES sucursal(codigo_sucursal),
    CONSTRAINT socio_run_persona_unique UNIQUE (run_persona),
    CONSTRAINT socio_tipo_socio_check CHECK (tipo_socio IN ('socio titular', 'beneficiario', 'adicional')),
    CONSTRAINT socio_fechas_check CHECK (fecha_fin IS NULL OR fecha_inicio <= fecha_fin)
);

-- Tabla de lugares de cada sucursal
-- El código interno se genera con secuencia porque los CSV identifican lugares por nombre
-- La combinación nombre + sucursal es única, ya que el mismo nombre puede repetirse en sucursales distintas
CREATE TABLE lugar (
    codigo_lugar varchar(15) NOT NULL DEFAULT ('LUG' || lpad(nextval('codigo_lugar_seq')::text, 6, '0')),
    nombre varchar(100) NOT NULL,
    capacidad integer NOT NULL,
    codigo_sucursal varchar(10) NOT NULL,
    tipo_lugar varchar(30) NOT NULL,
    CONSTRAINT lugar_pkey PRIMARY KEY (codigo_lugar),
    CONSTRAINT lugar_codigo_sucursal_fkey FOREIGN KEY (codigo_sucursal) REFERENCES sucursal(codigo_sucursal),
    CONSTRAINT lugar_capacidad_check CHECK (capacidad > 0),
    CONSTRAINT lugar_nombre_sucursal_unique UNIQUE (nombre, codigo_sucursal)
);

-- Tabla de usuarios del sistema
-- Se carga desde personas_sociosOK cuando es_usuario_sistema = SI
CREATE TABLE usuario (
    id_usuario serial NOT NULL,
    run_persona varchar(12),
    email_login varchar(150) NOT NULL,
    clave_encriptada varchar(255) NOT NULL,
    tipo_usuario varchar(30) NOT NULL,
    CONSTRAINT usuario_pkey PRIMARY KEY (id_usuario),
    CONSTRAINT usuario_run_persona_fkey FOREIGN KEY (run_persona) REFERENCES persona(run),
    CONSTRAINT usuario_email_login_unique UNIQUE (email_login),
    CONSTRAINT usuario_run_persona_unique UNIQUE (run_persona),
    CONSTRAINT usuario_tipo_usuario_check CHECK (tipo_usuario IN ('admin', 'administrativo', 'socio'))
);

-- Tabla auxiliar de nacimientos
-- Se usa el nombre nacimiento en vez de naci
CREATE TABLE nacimiento (
    run varchar(12),
    fecha_nacimiento date
);

-- Tabla intermedia entre persona, cargo y sucursal
-- Permite guardar cargos administrativos históricos y vigentes
CREATE TABLE persona_cargo (
    id_persona_cargo serial NOT NULL,
    run_persona varchar(12),
    id_cargo integer,
    codigo_sucursal varchar(10),
    fecha_inicio date NOT NULL,
    fecha_termino date,
    CONSTRAINT persona_cargo_pkey PRIMARY KEY (id_persona_cargo),
    CONSTRAINT persona_cargo_run_persona_fkey FOREIGN KEY (run_persona) REFERENCES persona(run),
    CONSTRAINT persona_cargo_id_cargo_fkey FOREIGN KEY (id_cargo) REFERENCES cargo(id_cargo),
    CONSTRAINT persona_cargo_codigo_sucursal_fkey FOREIGN KEY (codigo_sucursal) REFERENCES sucursal(codigo_sucursal),
    CONSTRAINT persona_cargo_fechas_check CHECK (fecha_termino IS NULL OR fecha_inicio <= fecha_termino)
);

-- Tabla que relaciona socios dependientes con su socio titular
-- Sirve para beneficiarios y adicionales
CREATE TABLE relacion_socio (
    id_relacion serial NOT NULL,
    id_socio_titular integer,
    id_socio_dependiente integer,
    parentesco varchar(30) NOT NULL,
    CONSTRAINT relacion_socio_pkey PRIMARY KEY (id_relacion),
    CONSTRAINT relacion_socio_id_socio_titular_fkey FOREIGN KEY (id_socio_titular) REFERENCES socio(id_socio),
    CONSTRAINT relacion_socio_id_socio_dependiente_fkey FOREIGN KEY (id_socio_dependiente) REFERENCES socio(id_socio),
    CONSTRAINT relacion_socio_no_mismo_socio_check CHECK (id_socio_titular IS NULL OR id_socio_dependiente IS NULL OR id_socio_titular <> id_socio_dependiente),
    CONSTRAINT relacion_socio_unique UNIQUE (id_socio_titular, id_socio_dependiente)
);

-- Tabla de membresías por socio titular y año
-- Se usa para las cuotas y para consultas de morosidad e ingresos por membresía
CREATE TABLE membresia (
    id_socio serial NOT NULL,
    id_socio_titular integer,
    anio integer NOT NULL,
    fecha_inicio date NOT NULL,
    fecha_fin date NOT NULL,
    monto_base integer NOT NULL,
    CONSTRAINT membresia_pkey PRIMARY KEY (id_socio),
    CONSTRAINT membresia_id_socio_titular_fkey FOREIGN KEY (id_socio_titular) REFERENCES socio(id_socio),
    CONSTRAINT membresia_anio_check CHECK (anio BETWEEN 1950 AND 2050),
    CONSTRAINT membresia_monto_base_check CHECK (monto_base >= 0),
    CONSTRAINT membresia_fechas_check CHECK (fecha_inicio <= fecha_fin),
    CONSTRAINT membresia_socio_anio_unique UNIQUE (id_socio_titular, anio)
);

-- Tabla de cuotas mensuales de una membresia
-- El estado acepta pagado y atrasado porque así quedó normalizado en pagos_membresiasOK.csv
CREATE TABLE cuota (
    id_cuota serial NOT NULL,
    id_membresia integer,
    mes integer NOT NULL,
    fecha_vencimiento date NOT NULL,
    monto_total integer NOT NULL,
    estado varchar(20) NOT NULL,
    CONSTRAINT cuota_pkey PRIMARY KEY (id_cuota),
    CONSTRAINT cuota_id_membresia_fkey FOREIGN KEY (id_membresia) REFERENCES membresia(id_socio),
    CONSTRAINT cuota_mes_check CHECK (mes BETWEEN 1 AND 12),
    CONSTRAINT cuota_monto_total_check CHECK (monto_total >= 0),
    CONSTRAINT cuota_estado_check CHECK (estado IN ('pagado', 'atrasado')),
    CONSTRAINT cuota_membresia_mes_unique UNIQUE (id_membresia, mes)
);

-- Tabla de precios históricos de lugares
-- Permite precios por día u hora, con vigencia y día de semana opcional
CREATE TABLE precio_lugar (
    id_precio serial NOT NULL,
    codigo_lugar varchar(15),
    tipo_precio varchar(10) NOT NULL,
    dia_semana varchar(15),
    hora_inicio time,
    hora_termino time,
    fecha_inicio date,
    fecha_fin date,
    monto integer NOT NULL,
    CONSTRAINT precio_lugar_pkey PRIMARY KEY (id_precio),
    CONSTRAINT precio_lugar_codigo_lugar_fkey FOREIGN KEY (codigo_lugar) REFERENCES lugar(codigo_lugar),
    CONSTRAINT precio_lugar_monto_check CHECK (monto >= 0),
    CONSTRAINT precio_lugar_tipo_precio_check CHECK (tipo_precio IN ('dia', 'hora')),
    CONSTRAINT precio_lugar_dia_semana_check CHECK (dia_semana IS NULL OR dia_semana IN ('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo')),
    CONSTRAINT precio_lugar_horas_check CHECK (hora_inicio IS NULL OR hora_termino IS NULL OR hora_inicio < hora_termino),
    CONSTRAINT precio_lugar_fechas_check CHECK (fecha_inicio IS NULL OR fecha_fin IS NULL OR fecha_inicio <= fecha_fin)
);

-- Tabla de reservas/arriendos
-- codigo_reserva se genera en SQL porque en reservas_arriendosOK.csv viene vacío.
-- Se usa timestamp porque el enunciado pide fecha con hora cuando corresponde.
CREATE TABLE reserva (
    codigo_reserva varchar(20) NOT NULL DEFAULT ('RES' || lpad(nextval('codigo_reserva_seq')::text, 6, '0')),
    codigo_lugar varchar(15),
    run_reservante varchar(12),
    fecha_inicio timestamp NOT NULL,
    fecha_fin timestamp NOT NULL,
    estado varchar(20) NOT NULL,
    monto_total integer NOT NULL,
    CONSTRAINT reserva_pkey PRIMARY KEY (codigo_reserva),
    CONSTRAINT reserva_codigo_lugar_fkey FOREIGN KEY (codigo_lugar) REFERENCES lugar(codigo_lugar),
    CONSTRAINT reserva_run_reservante_fkey FOREIGN KEY (run_reservante) REFERENCES persona(run),
    CONSTRAINT reserva_fechas_check CHECK (fecha_inicio < fecha_fin),
    CONSTRAINT reserva_monto_total_check CHECK (monto_total >= 0),
    CONSTRAINT reserva_estado_check CHECK (estado IN ('reservada', 'ejecutada', 'cancelada'))
);

-- Tabla de eventos
-- El codigo de evento se construye desde evento_id del CSV
CREATE TABLE evento (
    codigo_evento varchar(20) NOT NULL,
    nombre varchar(150) NOT NULL,
    fecha_evento date NOT NULL,
    codigo_lugar varchar(15),
    codigo_sucursal varchar(10),
    tipo_cliente varchar(20) NOT NULL,
    identificador_cliente varchar(20) NOT NULL,
    monto_total integer NOT NULL,
    CONSTRAINT evento_pkey PRIMARY KEY (codigo_evento),
    CONSTRAINT evento_codigo_lugar_fkey FOREIGN KEY (codigo_lugar) REFERENCES lugar(codigo_lugar),
    CONSTRAINT evento_codigo_sucursal_fkey FOREIGN KEY (codigo_sucursal) REFERENCES sucursal(codigo_sucursal),
    CONSTRAINT evento_tipo_cliente_check CHECK (tipo_cliente IN ('socio', 'persona', 'empresa')),
    CONSTRAINT evento_monto_total_check CHECK (monto_total >= 0)
);

-- Tabla de contactos asociados a empresas
-- Los contactos vienen desde eventosOK cuando el cliente es empresa
CREATE TABLE contacto_empresa (
    id_contacto serial NOT NULL,
    rut_empresa varchar(15),
    run_persona varchar(12),
    nombre varchar(150),
    cargo varchar(100),
    CONSTRAINT contacto_empresa_pkey PRIMARY KEY (id_contacto),
    CONSTRAINT contacto_empresa_rut_empresa_fkey FOREIGN KEY (rut_empresa) REFERENCES empresa(rut_empresa)
);

-- Tabla de asistentes de eventos
CREATE TABLE asistente_evento (
    id_asistente serial NOT NULL,
    codigo_evento varchar(20),
    run_asistente varchar(12),
    nombre_asistente varchar(150),
    CONSTRAINT asistente_evento_pkey PRIMARY KEY (id_asistente),
    CONSTRAINT asistente_evento_codigo_evento_fkey FOREIGN KEY (codigo_evento) REFERENCES evento(codigo_evento)
);

-- Tabla de pagos efectivos de cuotas de membresia
CREATE TABLE pago_cuota (
    id_pago_cuota serial NOT NULL,
    cuota_numero integer,
    fecha_pago date NOT NULL,
    monto_pagado integer NOT NULL,
    medio_pago varchar(30),
    id_socio integer,
    CONSTRAINT pago_cuota_pkey PRIMARY KEY (id_pago_cuota),
    CONSTRAINT pago_cuota_id_cuota_fkey FOREIGN KEY (cuota_numero) REFERENCES cuota(id_cuota),
    CONSTRAINT pago_cuota_monto_pagado_check CHECK (monto_pagado >= 0),
    CONSTRAINT pago_cuota_medio_pago_check CHECK (medio_pago IS NULL OR medio_pago IN ('efectivo', 'transferencia', 'tarjeta', 'tarjeta_credito'))
);

-- Tabla de pagos asociados a eventos.
CREATE TABLE pago_evento (
    id_pago_evento serial NOT NULL,
    codigo_evento varchar(20),
    fecha_pago date NOT NULL,
    monto integer NOT NULL,
    tipo_pago varchar(20) NOT NULL,
    CONSTRAINT pago_evento_pkey PRIMARY KEY (id_pago_evento),
    CONSTRAINT pago_evento_codigo_evento_fkey FOREIGN KEY (codigo_evento) REFERENCES evento(codigo_evento),
    CONSTRAINT pago_evento_monto_check CHECK (monto >= 0),
    CONSTRAINT pago_evento_tipo_pago_check CHECK (tipo_pago IN ('reserva', 'ejecucion', 'total'))
);

-- Tabla de pagos asociados a reservas.
CREATE TABLE pago_reserva (
    id_pago_reserva serial NOT NULL,
    codigo_reserva varchar(20),
    fecha_pago date NOT NULL,
    monto integer NOT NULL,
    medio_pago varchar(30),
    CONSTRAINT pago_reserva_pkey PRIMARY KEY (id_pago_reserva),
    CONSTRAINT pago_reserva_codigo_reserva_fkey FOREIGN KEY (codigo_reserva) REFERENCES reserva(codigo_reserva),
    CONSTRAINT pago_reserva_monto_check CHECK (monto >= 0),
    CONSTRAINT pago_reserva_medio_pago_check CHECK (medio_pago IS NULL OR medio_pago IN ('efectivo', 'transferencia', 'tarjeta', 'tarjeta_credito'))
);

-- Tabla temporal para registrar acciones realizadas durante la carga SQL.
-- Al final se exporta como cargaLOG.txt.
CREATE TEMP TABLE carga_log (
    linea text,
    archivo text,
    identificador text,
    accion text
);

-- Tabla temporal para registrar filas que no pudieron cargarse por problemas de FK o correspondencia.
-- Al final se exporta como cargaERR.csv.
CREATE TEMP TABLE carga_err (
    archivo text,
    identificador text,
    motivo text,
    fila text
);

-- Tablas temporales con la misma forma de los archivos OK
CREATE TEMP TABLE tmp_regiones_comunas (
    codigo_comuna text,
    comuna_nombre text,
    region_codigo text,
    region_nombre text
);

-- Temporal de personas_sociosOK.csv.
CREATE TEMP TABLE tmp_personas_socios (
    run_persona text,
    nombre_completo text,
    email text,
    telefono_celular text,
    telefono_alternativo text,
    direccion_calle text,
    comuna_nombre text,
    region_codigo text,
    region_nombre text,
    tipo_persona text,
    run_socio_titular text,
    parentesco text,
    fecha_nacimiento text,
    fecha_inicio_membresia text,
    fecha_fin_membresia text,
    es_usuario_sistema text,
    tipo_usuario text,
    clave_en_texto_plano text,
    sucursal_base_nombre text
);

-- Temporal de sucursales_lugaresOK.csv
CREATE TEMP TABLE tmp_sucursales_lugares (
    sucursal_nombre text,
    direccion_sucursal text,
    comuna_nombre text,
    lugar_nombre text,
    tipo_lugar text,
    capacidad_personas text,
    precio text,
    descuento_socio_evento text,
    tipo_precio text,
    dia_semana text,
    hora_inicio text,
    hora_termino text,
    fecha_inicio_vigencia text,
    fecha_fin_vigencia text
);

-- Temporal de reservas_arriendosOK.csv
CREATE TEMP TABLE tmp_reservas_arriendos (
    id_tmp serial,
    codigo_reserva text,
    fecha_reserva text,
    fecha_inicio text,
    fecha_fin text,
    estado_reserva text,
    run_reservante text,
    nombre_reservante text,
    es_socio text,
    lugar_nombre text,
    sucursal_nombre text,
    monto_total text,
    monto_pagado text,
    medio_pago text,
    fecha_pago text
);

-- Temporal de eventosOK.csv
-- Contiene evento, cliente, contacto de empresa, asistentes, montos
CREATE TEMP TABLE tmp_eventos (
    evento_id text,
    nombre_evento text,
    fecha_contratacion text,
    fecha_evento text,
    lugar_nombre text,
    sucursal_nombre text,
    tipo_cliente text,
    run_cliente text,
    nombre_cliente text,
    rut_contacto_empresa text,
    nombre_contacto_empresa text,
    cargo_contacto text,
    lista_asistentes text,
    monto_total_evento text,
    monto_pagado_reserva text,
    monto_pagado_ejecucion text
);

-- Temporal de pagos_membresiasOK.csv
-- Se usa para crear membresías, cuotas y pagos de cuota
CREATE TEMP TABLE tmp_pagos_membresias (
    pago_id text,
    run_socio_titular text,
    nombre_socio_titular text,
    anio_membresia text,
    mes_cuota text,
    fecha_vencimiento text,
    monto_membresia text,
    monto_adicionales text,
    monto_total text,
    estado_pago text,
    fecha_pago text,
    medio_pago text
);

-- Temporal de cargos_administrativosOK.csv
-- Se usa para crear cargos y asignarlos a personas por sucursal
CREATE TEMP TABLE tmp_cargos_administrativos (
    run_persona text,
    sucursal_nombre text,
    nombre_cargo text,
    fecha_inicio_cargo text,
    fecha_termino_cargo text
);

-- Cargamos los CSV limpios generados por main.php en las tablas temporales
\copy tmp_regiones_comunas(codigo_comuna, comuna_nombre, region_codigo, region_nombre) FROM 'archivos_generados/regiones_comunasOK.csv' DELIMITER ';' CSV HEADER;
\copy tmp_personas_socios(run_persona, nombre_completo, email, telefono_celular, telefono_alternativo, direccion_calle, comuna_nombre, region_codigo, region_nombre, tipo_persona, run_socio_titular, parentesco, fecha_nacimiento, fecha_inicio_membresia, fecha_fin_membresia, es_usuario_sistema, tipo_usuario, clave_en_texto_plano, sucursal_base_nombre) FROM 'archivos_generados/personas_sociosOK.csv' DELIMITER ';' CSV HEADER;
\copy tmp_sucursales_lugares(sucursal_nombre, direccion_sucursal, comuna_nombre, lugar_nombre, tipo_lugar, capacidad_personas, precio, descuento_socio_evento, tipo_precio, dia_semana, hora_inicio, hora_termino, fecha_inicio_vigencia, fecha_fin_vigencia) FROM 'archivos_generados/sucursales_lugaresOK.csv' DELIMITER ';' CSV HEADER;
\copy tmp_reservas_arriendos(codigo_reserva, fecha_reserva, fecha_inicio, fecha_fin, estado_reserva, run_reservante, nombre_reservante, es_socio, lugar_nombre, sucursal_nombre, monto_total, monto_pagado, medio_pago, fecha_pago) FROM 'archivos_generados/reservas_arriendosOK.csv' DELIMITER ';' CSV HEADER;
\copy tmp_eventos(evento_id, nombre_evento, fecha_contratacion, fecha_evento, lugar_nombre, sucursal_nombre, tipo_cliente, run_cliente, nombre_cliente, rut_contacto_empresa, nombre_contacto_empresa, cargo_contacto, lista_asistentes, monto_total_evento, monto_pagado_reserva, monto_pagado_ejecucion) FROM 'archivos_generados/eventosOK.csv' DELIMITER ';' CSV HEADER;
\copy tmp_pagos_membresias(pago_id, run_socio_titular, nombre_socio_titular, anio_membresia, mes_cuota, fecha_vencimiento, monto_membresia, monto_adicionales, monto_total, estado_pago, fecha_pago, medio_pago) FROM 'archivos_generados/pagos_membresiasOK.csv' DELIMITER ';' CSV HEADER;
\copy tmp_cargos_administrativos(run_persona, sucursal_nombre, nombre_cargo, fecha_inicio_cargo, fecha_termino_cargo) FROM 'archivos_generados/cargos_administrativosOK.csv' DELIMITER ';' CSV HEADER;

-- Insertamos regiones distintas desde regiones_comunasOK.csv
-- ON CONFLICT evita duplicados si el archivo tiene varias comunas de la misma region
INSERT INTO region (codigo_region, nombre)
SELECT DISTINCT region_codigo::integer, region_nombre
FROM tmp_regiones_comunas
WHERE region_codigo IS NOT NULL AND region_nombre IS NOT NULL
ON CONFLICT (codigo_region) DO NOTHING;

-- Insertamos comunas distintas y las conectamos con su region
INSERT INTO comuna (codigo_comuna, nombre, codigo_region)
SELECT DISTINCT codigo_comuna::integer, comuna_nombre, region_codigo::integer
FROM tmp_regiones_comunas
WHERE codigo_comuna IS NOT NULL AND comuna_nombre IS NOT NULL AND region_codigo IS NOT NULL
ON CONFLICT (codigo_comuna) DO NOTHING;

-- Creamos una fila por sucursal unica
-- Como el CSV identifica sucursales por nombre el código interno se genera automáticamente
INSERT INTO sucursal (nombre, direccion, codigo_comuna)
SELECT DISTINCT ON (s.sucursal_nombre)
    s.sucursal_nombre,
    s.direccion_sucursal,
    c.codigo_comuna
FROM tmp_sucursales_lugares s
JOIN comuna c ON c.nombre = s.comuna_nombre
WHERE s.sucursal_nombre IS NOT NULL AND s.direccion_sucursal IS NOT NULL
ORDER BY s.sucursal_nombre
ON CONFLICT (nombre) DO NOTHING;

INSERT INTO carga_log VALUES (NULL, 'sucursales_lugaresOK.csv', NULL, 'Se crearon sucursales únicas desde los nombres de negocio del archivo OK.');

-- Insertamos personas desde personas_sociosOK.csv
-- ROW_NUMBER se usa para manejar correos duplicados: conservamos el primero y dejamos los repetidos como NULL
WITH personas_rank AS (
    SELECT p.*,
           ROW_NUMBER() OVER (PARTITION BY NULLIF(p.email, '') ORDER BY p.run_persona) AS rn_email
    FROM tmp_personas_socios p
)
INSERT INTO persona (run, nombre_completo, email, telefono_celular, telefono_alternativo, direccion_calle, codigo_comuna, fecha_nacimiento)
SELECT
    p.run_persona,
    p.nombre_completo,
    CASE
        WHEN NULLIF(p.email, '') IS NULL THEN NULL
        WHEN p.rn_email = 1 THEN p.email
        ELSE NULL
    END,
    NULLIF(p.telefono_celular, ''),
    NULLIF(p.telefono_alternativo, ''),
    NULLIF(p.direccion_calle, ''),
    c.codigo_comuna,
    NULLIF(p.fecha_nacimiento, '')::date
FROM personas_rank p
JOIN comuna c ON c.nombre = p.comuna_nombre AND c.codigo_region = p.region_codigo::integer
WHERE p.run_persona IS NOT NULL
ON CONFLICT (run) DO NOTHING;

INSERT INTO carga_log
SELECT NULL, 'personas_sociosOK.csv', run_persona, 'Email duplicado cargado como NULL para respetar UNIQUE(email).'
FROM (
    SELECT p.*, ROW_NUMBER() OVER (PARTITION BY NULLIF(p.email, '') ORDER BY p.run_persona) AS rn_email
    FROM tmp_personas_socios p
) p
WHERE NULLIF(p.email, '') IS NOT NULL AND rn_email > 1;

-- Creamos personas minimas desde reservas si el RUN no venía en personas_sociosOK.csv
-- Esto evita perder reservas por FK hacia persona
INSERT INTO persona (
    run,
    nombre_completo,
    email,
    telefono_celular,
    telefono_alternativo,
    direccion_calle,
    codigo_comuna,
    fecha_nacimiento
)
SELECT DISTINCT
    r.run_reservante,
    r.nombre_reservante,
    NULL::varchar(150),
    NULL::varchar(20),
    NULL::varchar(20),
    NULL::varchar(150),
    NULL::integer,
    NULL::date
FROM tmp_reservas_arriendos r
WHERE r.run_reservante IS NOT NULL
ON CONFLICT (run) DO NOTHING;

INSERT INTO carga_log
SELECT DISTINCT
    NULL,
    'reservas_arriendosOK.csv',
    r.run_reservante,
    'Persona reservante no venía en personas_sociosOK; se creó con datos mínimos para mantener la reserva.'
FROM tmp_reservas_arriendos r
LEFT JOIN tmp_personas_socios p ON p.run_persona = r.run_reservante
WHERE p.run_persona IS NULL;


-- Creamos personas minimas desde eventos para clientes tipo socio o persona.
INSERT INTO persona (
    run,
    nombre_completo,
    email,
    telefono_celular,
    telefono_alternativo,
    direccion_calle,
    codigo_comuna,
    fecha_nacimiento
)
SELECT DISTINCT
    e.run_cliente,
    COALESCE(NULLIF(e.nombre_cliente, ''), 'Nombre desconocido'),
    NULL::varchar(150),
    NULL::varchar(20),
    NULL::varchar(20),
    NULL::varchar(150),
    NULL::integer,
    NULL::date
FROM tmp_eventos e
WHERE e.tipo_cliente IN ('socio', 'persona')
  AND e.run_cliente IS NOT NULL
ON CONFLICT (run) DO NOTHING;


-- Creamos personas minimas para contactos de empresa cuando tienen run informado
INSERT INTO persona (
    run,
    nombre_completo,
    email,
    telefono_celular,
    telefono_alternativo,
    direccion_calle,
    codigo_comuna,
    fecha_nacimiento
)
SELECT DISTINCT
    e.rut_contacto_empresa,
    COALESCE(NULLIF(e.nombre_contacto_empresa, ''), 'Contacto empresa'),
    NULL::varchar(150),
    NULL::varchar(20),
    NULL::varchar(20),
    NULL::varchar(150),
    NULL::integer,
    NULL::date
FROM tmp_eventos e
WHERE e.tipo_cliente = 'empresa'
  AND NULLIF(e.rut_contacto_empresa, '') IS NOT NULL
ON CONFLICT (run) DO NOTHING;

-- Copiamos fechas de nacimiento no nulas a la tabla nacimiento.
INSERT INTO nacimiento (run, fecha_nacimiento)
SELECT run, fecha_nacimiento
FROM persona
WHERE fecha_nacimiento IS NOT NULL;

-- Creamos socios desde personas_sociosOK.csv.
-- Solo se cargan socio_titular beneficiario y adicional
INSERT INTO socio (run_persona, tipo_socio, fecha_inicio, fecha_fin, codigo_sucursal_base)
SELECT
    p.run_persona,
    p.tipo_persona,
    NULLIF(p.fecha_inicio_membresia, '')::date,
    NULLIF(p.fecha_fin_membresia, '')::date,
    s.codigo_sucursal
FROM tmp_personas_socios p
LEFT JOIN sucursal s ON s.nombre = p.sucursal_base_nombre
WHERE p.tipo_persona IN ('socio titular', 'beneficiario', 'adicional')
  AND NULLIF(p.fecha_inicio_membresia, '') IS NOT NULL
ON CONFLICT (run_persona) DO NOTHING;

-- Creamos socios minimos para reservantes que no estaban registrados como socios
INSERT INTO socio (run_persona, tipo_socio, fecha_inicio, fecha_fin, codigo_sucursal_base)
SELECT DISTINCT ON (r.run_reservante)
    r.run_reservante,
    'socio titular',
    r.fecha_inicio::timestamp::date,
    NULL,
    s.codigo_sucursal
FROM tmp_reservas_arriendos r
JOIN sucursal s ON s.nombre = r.sucursal_nombre
LEFT JOIN socio so ON so.run_persona = r.run_reservante
WHERE so.run_persona IS NULL
ORDER BY r.run_reservante, r.fecha_inicio
ON CONFLICT (run_persona) DO NOTHING;

INSERT INTO carga_log
SELECT DISTINCT NULL, 'reservas_arriendosOK.csv', r.run_reservante, 'Reservante sin registro de socio previo fue creado como socio titular mínimo para conservar la reserva.'
FROM tmp_reservas_arriendos r
LEFT JOIN tmp_personas_socios p ON p.run_persona = r.run_reservante AND p.tipo_persona IN ('socio titular', 'beneficiario', 'adicional')
WHERE p.run_persona IS NULL;

-- Creamos usuarios del sistema solo para personas marcadas como SI en es_usuario_sistema
INSERT INTO usuario (run_persona, email_login, clave_encriptada, tipo_usuario)
SELECT p.run_persona, p.email, p.clave_en_texto_plano, p.tipo_usuario
FROM tmp_personas_socios p
WHERE p.es_usuario_sistema = 'SI'
  AND p.email IS NOT NULL
  AND p.clave_en_texto_plano IS NOT NULL
  AND p.tipo_usuario IS NOT NULL
ON CONFLICT (email_login) DO NOTHING;

-- Creamos la relacion entre titular y beneficiario/adicional
-- Para adicionales se guarda parentesco 'adicional' porque parentesco solo aplica a beneficiarios.
INSERT INTO relacion_socio (id_socio_titular, id_socio_dependiente, parentesco)
SELECT st.id_socio, sd.id_socio,
       CASE
           WHEN p.tipo_persona = 'adicional' THEN 'adicional'
           WHEN lugar_key(p.parentesco) = 'conyuge' THEN 'conyuge'
           ELSE p.parentesco
       END
FROM tmp_personas_socios p
JOIN socio sd ON sd.run_persona = p.run_persona
JOIN socio st ON st.run_persona = p.run_socio_titular
WHERE p.tipo_persona IN ('beneficiario', 'adicional')
ON CONFLICT (id_socio_titular, id_socio_dependiente) DO NOTHING;

-- Creamos membresías anuales a partir de pagos_membresiasOK.csv
-- Se usa una membresía por socio titular y año
INSERT INTO membresia (id_socio_titular, anio, fecha_inicio, fecha_fin, monto_base)
SELECT DISTINCT ON (s.id_socio, m.anio_membresia)
    s.id_socio,
    m.anio_membresia::integer,
    (m.anio_membresia || '-01-01')::date,
    (m.anio_membresia || '-12-31')::date,
    m.monto_membresia::integer
FROM tmp_pagos_membresias m
JOIN socio s ON s.run_persona = m.run_socio_titular
ORDER BY s.id_socio, m.anio_membresia, m.pago_id
ON CONFLICT (id_socio_titular, anio) DO NOTHING;

-- Creamos cuotas mensuales asociadas a cada membresia
INSERT INTO cuota (id_membresia, mes, fecha_vencimiento, monto_total, estado)
SELECT mem.id_socio,
       p.mes_cuota::integer,
       p.fecha_vencimiento::date,
       p.monto_total::integer,
       p.estado_pago
FROM tmp_pagos_membresias p
JOIN socio s ON s.run_persona = p.run_socio_titular
JOIN membresia mem ON mem.id_socio_titular = s.id_socio AND mem.anio = p.anio_membresia::integer
ON CONFLICT (id_membresia, mes) DO NOTHING;

-- Insertamos pagos efectivos de cuotas
-- Si una cuota está atrasada no entra a pago_cuota porque no hubo pago recibido
INSERT INTO pago_cuota (cuota_numero, fecha_pago, monto_pagado, medio_pago, id_socio)
SELECT c.id_cuota,
       NULLIF(p.fecha_pago, '')::date,
       p.monto_total::integer,
       NULLIF(p.medio_pago, ''),
       s.id_socio
FROM tmp_pagos_membresias p
JOIN socio s ON s.run_persona = p.run_socio_titular
JOIN membresia mem ON mem.id_socio_titular = s.id_socio AND mem.anio = p.anio_membresia::integer
JOIN cuota c ON c.id_membresia = mem.id_socio AND c.mes = p.mes_cuota::integer
WHERE p.estado_pago = 'pagado'
  AND NULLIF(p.fecha_pago, '') IS NOT NULL;

-- Insertamos los nombres de cargos administrativos sin repetir
INSERT INTO cargo (nombre)
SELECT DISTINCT nombre_cargo
FROM tmp_cargos_administrativos
WHERE nombre_cargo IS NOT NULL
ON CONFLICT (nombre) DO NOTHING;

-- Asignamos cada cargo administrativo a una persona y una sucursal
INSERT INTO persona_cargo (run_persona, id_cargo, codigo_sucursal, fecha_inicio, fecha_termino)
SELECT c.run_persona,
       ca.id_cargo,
       s.codigo_sucursal,
       c.fecha_inicio_cargo::date,
       NULLIF(c.fecha_termino_cargo, '')::date
FROM tmp_cargos_administrativos c
JOIN persona p ON p.run = c.run_persona
JOIN cargo ca ON ca.nombre = c.nombre_cargo
JOIN sucursal s ON s.nombre = c.sucursal_nombre;

-- Creamos empresas desde eventosOK.csv cuando tipo_cliente es = empresa.
INSERT INTO empresa (rut_empresa, nombre)
SELECT DISTINCT ON (run_cliente)
    run_cliente,
    COALESCE(NULLIF(nombre_cliente, ''), 'Empresa sin nombre')
FROM tmp_eventos
WHERE tipo_cliente = 'empresa'
ORDER BY run_cliente, nombre_cliente
ON CONFLICT (rut_empresa) DO NOTHING;

-- Creamos contactos de empresa asociados a su empresa.
INSERT INTO contacto_empresa (rut_empresa, run_persona, nombre, cargo)
SELECT DISTINCT e.run_cliente,
       NULLIF(e.rut_contacto_empresa, ''),
       NULLIF(e.nombre_contacto_empresa, ''),
       NULLIF(e.cargo_contacto, '')
FROM tmp_eventos e
JOIN empresa emp ON emp.rut_empresa = e.run_cliente
WHERE e.tipo_cliente = 'empresa'
  AND (NULLIF(e.rut_contacto_empresa, '') IS NOT NULL OR NULLIF(e.nombre_contacto_empresa, '') IS NOT NULL);

-- Creamos lugares base desde sucursales_lugaresOK.csv.
-- Se usa lugar_key para evitar duplicados por diferencias de espacios tildes formato.
WITH base_lugares AS (
    SELECT DISTINCT ON (s.codigo_sucursal, lugar_key(t.lugar_nombre))
        t.lugar_nombre AS nombre,
        GREATEST(t.capacidad_personas::integer, 1) AS capacidad,
        s.codigo_sucursal,
        t.tipo_lugar
    FROM tmp_sucursales_lugares t
    JOIN sucursal s ON s.nombre = t.sucursal_nombre
    WHERE t.lugar_nombre IS NOT NULL
    ORDER BY s.codigo_sucursal, lugar_key(t.lugar_nombre), t.lugar_nombre
)
INSERT INTO lugar (nombre, capacidad, codigo_sucursal, tipo_lugar)
SELECT nombre, capacidad, codigo_sucursal, tipo_lugar
FROM base_lugares
ON CONFLICT (nombre, codigo_sucursal) DO NOTHING;

-- Creamos lugares faltantes que aparecen en reservas pero no estaban en sucursales_lugaresOK.csv con capacidad 1
WITH reservas_lugares AS (
    SELECT DISTINCT
        regexp_replace(r.lugar_nombre, '[[:space:]]+[Xx]$', '') AS nombre_reserva,
        lugar_key(r.lugar_nombre) AS key_reserva,
        s.codigo_sucursal,
        CASE
            WHEN lugar_key(r.lugar_nombre) LIKE 'restaurant%' THEN 'restaurant'
            WHEN lugar_key(r.lugar_nombre) LIKE 'piscina%' THEN 'piscina'
            WHEN lugar_key(r.lugar_nombre) LIKE 'cabana%' THEN 'cabana'
            WHEN lugar_key(r.lugar_nombre) LIKE 'saloneventos%' THEN 'salon_eventos'
            WHEN lugar_key(r.lugar_nombre) LIKE '%techada%' THEN 'cancha_techada'
            WHEN lugar_key(r.lugar_nombre) LIKE '%airelibre%' THEN 'cancha_aire_libre'
            ELSE 'otro'
        END AS tipo_lugar
    FROM tmp_reservas_arriendos r
    JOIN sucursal s ON s.nombre = r.sucursal_nombre
), faltantes AS (
    SELECT rl.*
    FROM reservas_lugares rl
    LEFT JOIN lugar l ON l.codigo_sucursal = rl.codigo_sucursal AND lugar_key(l.nombre) = rl.key_reserva
    WHERE l.codigo_lugar IS NULL
)
INSERT INTO lugar (nombre, capacidad, codigo_sucursal, tipo_lugar)
SELECT nombre_reserva, 1, codigo_sucursal, tipo_lugar
FROM faltantes
ON CONFLICT (nombre, codigo_sucursal) DO NOTHING;

INSERT INTO carga_log
SELECT DISTINCT NULL, 'reservas_arriendosOK.csv', r.lugar_nombre, 'Lugar no venía en sucursales_lugaresOK; se creó con capacidad 1 para conservar reservas.'
FROM tmp_reservas_arriendos r
JOIN sucursal s ON s.nombre = r.sucursal_nombre
LEFT JOIN tmp_sucursales_lugares sl ON sl.sucursal_nombre = r.sucursal_nombre AND lugar_key(sl.lugar_nombre) = lugar_key(r.lugar_nombre)
WHERE sl.lugar_nombre IS NULL;

-- Insertamos los precios de cada lugar.
INSERT INTO precio_lugar (codigo_lugar, tipo_precio, dia_semana, hora_inicio, hora_termino, fecha_inicio, fecha_fin, monto)
SELECT DISTINCT
       l.codigo_lugar,
       CASE
           WHEN t.tipo_precio IN ('dia', 'hora') THEN t.tipo_precio
           WHEN NULLIF(t.hora_inicio, '') IS NOT NULL OR NULLIF(t.hora_termino, '') IS NOT NULL THEN 'hora'
           ELSE 'dia'
       END,
       NULLIF(t.dia_semana, ''),
       NULLIF(t.hora_inicio, '')::time,
       NULLIF(t.hora_termino, '')::time,
       NULLIF(t.fecha_inicio_vigencia, '')::date,
       NULLIF(t.fecha_fin_vigencia, '')::date,
       t.precio::integer
FROM tmp_sucursales_lugares t
JOIN sucursal s ON s.nombre = t.sucursal_nombre
JOIN lugar l ON l.codigo_sucursal = s.codigo_sucursal AND lugar_key(l.nombre) = lugar_key(t.lugar_nombre);

-- Registramos reservas que no se puedan cargar por falta de sucursal, lugar o persona
INSERT INTO carga_err
SELECT 'reservas_arriendosOK.csv', r.id_tmp::text, 'No se encontró sucursal, lugar o persona para cargar la reserva.', row_to_json(r)::text
FROM tmp_reservas_arriendos r
LEFT JOIN sucursal s ON s.nombre = r.sucursal_nombre
LEFT JOIN lugar l ON l.codigo_sucursal = s.codigo_sucursal AND lugar_key(l.nombre) = lugar_key(r.lugar_nombre)
LEFT JOIN persona p ON p.run = r.run_reservante
WHERE s.codigo_sucursal IS NULL OR l.codigo_lugar IS NULL OR p.run IS NULL;


-- El codigo_reserva se genera desde id_tmp 
INSERT INTO reserva (codigo_reserva, codigo_lugar, run_reservante, fecha_inicio, fecha_fin, estado, monto_total)
SELECT 'RES' || lpad(r.id_tmp::text, 6, '0'),
       l.codigo_lugar,
       r.run_reservante,
       r.fecha_inicio::timestamp,
       r.fecha_fin::timestamp,
       r.estado_reserva,
       r.monto_total::integer
FROM tmp_reservas_arriendos r
JOIN sucursal s ON s.nombre = r.sucursal_nombre
JOIN lugar l ON l.codigo_sucursal = s.codigo_sucursal AND lugar_key(l.nombre) = lugar_key(r.lugar_nombre)
JOIN persona p ON p.run = r.run_reservante
ON CONFLICT (codigo_reserva) DO NOTHING;

-- Insertamos pagos de reserva cuando monto pagado > 0
INSERT INTO pago_reserva (codigo_reserva, fecha_pago, monto, medio_pago)
SELECT 'RES' || lpad(r.id_tmp::text, 6, '0'),
       NULLIF(r.fecha_pago, '')::date,
       NULLIF(r.monto_pagado, '')::integer,
       NULLIF(r.medio_pago, '')
FROM tmp_reservas_arriendos r
JOIN reserva re ON re.codigo_reserva = 'RES' || lpad(r.id_tmp::text, 6, '0')
WHERE NULLIF(r.monto_pagado, '') IS NOT NULL
  AND NULLIF(r.monto_pagado, '')::integer > 0
  AND NULLIF(r.fecha_pago, '') IS NOT NULL;

-- Registramos eventos que no se puedan cargar por falta de sucursal o lugar
INSERT INTO carga_err
SELECT 'eventosOK.csv', e.evento_id, 'No se encontró sucursal o lugar para cargar el evento.', row_to_json(e)::text
FROM tmp_eventos e
LEFT JOIN sucursal s ON s.nombre = e.sucursal_nombre
LEFT JOIN lugar l ON l.codigo_sucursal = s.codigo_sucursal AND lugar_key(l.nombre) = lugar_key(e.lugar_nombre)
WHERE s.codigo_sucursal IS NULL OR l.codigo_lugar IS NULL;

-- Insertamos eventos finales
INSERT INTO evento (codigo_evento, nombre, fecha_evento, codigo_lugar, codigo_sucursal, tipo_cliente, identificador_cliente, monto_total)
SELECT 'EVT' || e.evento_id,
       e.nombre_evento,
       e.fecha_evento::date,
       l.codigo_lugar,
       s.codigo_sucursal,
       e.tipo_cliente,
       e.run_cliente,
       e.monto_total_evento::integer
FROM tmp_eventos e
JOIN sucursal s ON s.nombre = e.sucursal_nombre
JOIN lugar l ON l.codigo_sucursal = s.codigo_sucursal AND lugar_key(l.nombre) = lugar_key(e.lugar_nombre)
ON CONFLICT (codigo_evento) DO NOTHING;

-- Separamos la lista de asistentes por ';'
INSERT INTO asistente_evento (codigo_evento, run_asistente, nombre_asistente)
SELECT 'EVT' || e.evento_id,
       NULL,
       trim(a.nombre_asistente)
FROM tmp_eventos e
JOIN evento ev ON ev.codigo_evento = 'EVT' || e.evento_id
CROSS JOIN LATERAL unnest(string_to_array(COALESCE(e.lista_asistentes, ''), ';')) AS a(nombre_asistente)
WHERE trim(a.nombre_asistente) <> '';

-- Insertamos el pago de reserva del evento
INSERT INTO pago_evento (codigo_evento, fecha_pago, monto, tipo_pago)
SELECT 'EVT' || evento_id,
       NULLIF(fecha_contratacion, '')::date,
       NULLIF(monto_pagado_reserva, '')::integer,
       'reserva'
FROM tmp_eventos
WHERE NULLIF(monto_pagado_reserva, '') IS NOT NULL
  AND NULLIF(monto_pagado_reserva, '')::integer > 0
  AND NULLIF(fecha_contratacion, '') IS NOT NULL;

-- Insertamos el pago de ejecución del evento
INSERT INTO pago_evento (codigo_evento, fecha_pago, monto, tipo_pago)
SELECT 'EVT' || evento_id,
       NULLIF(fecha_evento, '')::date,
       NULLIF(monto_pagado_ejecucion, '')::integer,
       'ejecucion'
FROM tmp_eventos
WHERE NULLIF(monto_pagado_ejecucion, '') IS NOT NULL
  AND NULLIF(monto_pagado_ejecucion, '')::integer > 0
  AND NULLIF(fecha_evento, '') IS NOT NULL;

-- Dejamos una línea final en el log para indicar que la carga termino
INSERT INTO carga_log VALUES (NULL, 'carga.sql', NULL, 'Carga SQL finalizada. Las PK internas de sucursal, lugar y reserva fueron generadas durante la carga.');

-- Exportamos los errores y acciones de la carga SQL a los archivos pedidos
\copy carga_err TO 'archivos_generados/cargaERR.csv' DELIMITER ';' CSV HEADER;
\copy carga_log TO 'archivos_generados/cargaLOG.txt' DELIMITER ';' CSV HEADER;


COMMIT;
