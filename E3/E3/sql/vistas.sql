-- Vistas del item 2.4.
-- La página consultas.php solo ejecuta estas vistas.

DROP VIEW IF EXISTS vista_agenda_sucursal;
DROP VIEW IF EXISTS vista_ingreso_mensual_sucursal;
DROP VIEW IF EXISTS vista_cuotas_atrasadas;
DROP VIEW IF EXISTS vista_beneficiarios_hijos_29;
DROP VIEW IF EXISTS vista_reporte_sucursales_2025;

CREATE OR REPLACE VIEW vista_agenda_sucursal AS
SELECT
    suc.codigo_sucursal,
    suc.nombre AS sucursal,
    l.codigo_lugar,
    l.nombre AS lugar,
    INITCAP(TRIM(TO_CHAR(r.fecha_inicio::date, 'TMDay'))) AS dia,
    r.fecha_inicio::date AS fecha,
    r.fecha_inicio::time AS hora,
    r.fecha_inicio AS inicio,
    r.fecha_fin AS termino,
    'Reserva'::text AS tipo_registro,
    COALESCE(p.nombre_completo, r.run_reservante, 'Sin reservante') AS reservado_por,
    r.codigo_reserva AS codigo_referencia,
    r.estado
FROM reserva r
JOIN lugar l ON l.codigo_lugar = r.codigo_lugar
JOIN sucursal suc ON suc.codigo_sucursal = l.codigo_sucursal
LEFT JOIN persona p ON p.run = r.run_reservante
WHERE lower(COALESCE(r.estado, '')) <> 'cancelada'
  AND lower(COALESCE(r.estado, '')) <> 'reservada_evento'
  AND NOT EXISTS (SELECT 1 FROM evento e WHERE e.codigo_evento = r.codigo_reserva)

UNION ALL

SELECT
    suc.codigo_sucursal,
    suc.nombre AS sucursal,
    l.codigo_lugar,
    l.nombre AS lugar,
    INITCAP(TRIM(TO_CHAR(e.fecha_evento, 'TMDay'))) AS dia,
    e.fecha_evento AS fecha,
    TIME '00:00' AS hora,
    e.fecha_evento::timestamp AS inicio,
    (e.fecha_evento::timestamp + INTERVAL '1 day') AS termino,
    'Evento'::text AS tipo_registro,
    COALESCE(p_cliente.nombre_completo, p_socio.nombre_completo, emp.nombre, e.identificador_cliente, 'Sin cliente') AS reservado_por,
    e.codigo_evento AS codigo_referencia,
    'programado'::text AS estado
FROM evento e
LEFT JOIN lugar l ON l.codigo_lugar = e.codigo_lugar
JOIN sucursal suc ON suc.codigo_sucursal = COALESCE(e.codigo_sucursal, l.codigo_sucursal)
LEFT JOIN persona p_cliente ON p_cliente.run = e.identificador_cliente
LEFT JOIN socio so_cliente ON so_cliente.id_socio::text = e.identificador_cliente OR so_cliente.run_persona = e.identificador_cliente
LEFT JOIN persona p_socio ON p_socio.run = so_cliente.run_persona
LEFT JOIN empresa emp ON emp.rut_empresa = e.identificador_cliente;

CREATE OR REPLACE VIEW vista_ingreso_mensual_sucursal AS
WITH ingresos AS (
    SELECT
        suc.codigo_sucursal,
        suc.nombre AS sucursal,
        'Membresías'::text AS concepto,
        CASE WHEN pc.fecha_pago IS NOT NULL THEN 'Efectivamente recibido' ELSE 'Futuro esperado' END AS tipo_ingreso,
        COALESCE(pc.monto_pagado, 0) AS monto
    FROM pago_cuota pc
    JOIN socio s ON s.id_socio = pc.id_socio
    JOIN sucursal suc ON suc.codigo_sucursal = s.codigo_sucursal_base
    WHERE (
        pc.fecha_pago IS NOT NULL
        AND date_trunc('month', pc.fecha_pago)::date = date_trunc('month', CURRENT_DATE)::date
    ) OR (
        pc.fecha_pago IS NULL
        AND pc.cuota_numero = EXTRACT(MONTH FROM CURRENT_DATE)::int
    )

    UNION ALL

    SELECT
        suc.codigo_sucursal,
        suc.nombre AS sucursal,
        'Reservas ejecutadas'::text AS concepto,
        CASE WHEN pr.fecha_pago IS NOT NULL THEN 'Efectivamente recibido' ELSE 'Futuro esperado' END AS tipo_ingreso,
        COALESCE(pr.monto, 0) AS monto
    FROM pago_reserva pr
    JOIN reserva r ON r.codigo_reserva = pr.codigo_reserva
    JOIN lugar l ON l.codigo_lugar = r.codigo_lugar
    JOIN sucursal suc ON suc.codigo_sucursal = l.codigo_sucursal
    WHERE (
        pr.fecha_pago IS NOT NULL
        AND date_trunc('month', pr.fecha_pago)::date = date_trunc('month', CURRENT_DATE)::date
    ) OR (
        pr.fecha_pago IS NULL
        AND date_trunc('month', r.fecha_inicio)::date = date_trunc('month', CURRENT_DATE)::date
    )

    UNION ALL

    SELECT
        suc.codigo_sucursal,
        suc.nombre AS sucursal,
        'Eventos'::text AS concepto,
        CASE WHEN pe.fecha_pago IS NOT NULL THEN 'Efectivamente recibido' ELSE 'Futuro esperado' END AS tipo_ingreso,
        COALESCE(pe.monto, 0) AS monto
    FROM pago_evento pe
    JOIN evento e ON e.codigo_evento = pe.codigo_evento
    JOIN sucursal suc ON suc.codigo_sucursal = e.codigo_sucursal
    WHERE date_trunc('month', pe.fecha_pago)::date = date_trunc('month', CURRENT_DATE)::date
)
SELECT
    codigo_sucursal,
    sucursal,
    concepto,
    tipo_ingreso,
    SUM(monto)::integer AS monto_total
FROM ingresos
GROUP BY codigo_sucursal, sucursal, concepto, tipo_ingreso;

CREATE OR REPLACE VIEW vista_cuotas_atrasadas AS
SELECT
    s.id_socio,
    p.run,
    p.nombre_completo,
    suc.nombre AS sucursal,
    SUM(COALESCE(pc.monto_pagado, pc.monto_base, 0) + COALESCE(pc.monto_adicional, 0))::integer AS monto_atrasado,
    COUNT(*)::integer AS numero_cuotas_atrasadas
FROM pago_cuota pc
JOIN socio s ON s.id_socio = pc.id_socio
JOIN persona p ON p.run = s.run_persona
LEFT JOIN sucursal suc ON suc.codigo_sucursal = s.codigo_sucursal_base
WHERE pc.fecha_pago IS NULL
  AND pc.cuota_numero IS NOT NULL
  AND pc.cuota_numero <= EXTRACT(MONTH FROM CURRENT_DATE)::int
GROUP BY s.id_socio, p.run, p.nombre_completo, suc.nombre;

CREATE OR REPLACE VIEW vista_beneficiarios_hijos_29 AS
SELECT
    p_dep.run AS run_beneficiario,
    p_dep.nombre_completo AS beneficiario,
    p_dep.email AS correo_beneficiario,
    p_dep.telefono_celular AS telefono_beneficiario,
    p_tit.run AS run_titular,
    p_tit.nombre_completo AS socio_titular,
    p_tit.email AS correo_titular,
    p_tit.telefono_celular AS telefono_titular,
    suc.nombre AS sucursal,
    p_dep.fecha_nacimiento,
    rs.parentesco
FROM relacion_socio rs
JOIN socio s_tit ON s_tit.id_socio = rs.id_socio_titular
JOIN persona p_tit ON p_tit.run = s_tit.run_persona
JOIN socio s_dep ON s_dep.id_socio = rs.id_socio_dependiente
JOIN persona p_dep ON p_dep.run = s_dep.run_persona
LEFT JOIN sucursal suc ON suc.codigo_sucursal = s_tit.codigo_sucursal_base
WHERE lower(rs.parentesco) LIKE 'hij%'
  AND p_dep.fecha_nacimiento IS NOT NULL
  AND p_dep.fecha_nacimiento >= DATE '1997-01-01'
  AND p_dep.fecha_nacimiento < DATE '1998-01-01';

CREATE OR REPLACE VIEW vista_reporte_sucursales_2025 AS
WITH ingresos AS (
    SELECT
        suc.codigo_sucursal,
        SUM(pc.monto_pagado)::numeric AS monto
    FROM pago_cuota pc
    JOIN socio s ON s.id_socio = pc.id_socio
    JOIN sucursal suc ON suc.codigo_sucursal = s.codigo_sucursal_base
    WHERE pc.fecha_pago >= DATE '2025-01-01'
      AND pc.fecha_pago < DATE '2026-01-01'
    GROUP BY suc.codigo_sucursal

    UNION ALL

    SELECT
        suc.codigo_sucursal,
        SUM(pr.monto)::numeric AS monto
    FROM pago_reserva pr
    JOIN reserva r ON r.codigo_reserva = pr.codigo_reserva
    JOIN lugar l ON l.codigo_lugar = r.codigo_lugar
    JOIN sucursal suc ON suc.codigo_sucursal = l.codigo_sucursal
    WHERE pr.fecha_pago >= DATE '2025-01-01'
      AND pr.fecha_pago < DATE '2026-01-01'
    GROUP BY suc.codigo_sucursal

    UNION ALL

    SELECT
        suc.codigo_sucursal,
        SUM(pe.monto)::numeric AS monto
    FROM pago_evento pe
    JOIN evento e ON e.codigo_evento = pe.codigo_evento
    JOIN sucursal suc ON suc.codigo_sucursal = e.codigo_sucursal
    WHERE pe.fecha_pago >= DATE '2025-01-01'
      AND pe.fecha_pago < DATE '2026-01-01'
    GROUP BY suc.codigo_sucursal
), ingresos_sucursal AS (
    SELECT codigo_sucursal, SUM(monto) AS ingresos_totales
    FROM ingresos
    GROUP BY codigo_sucursal
), gerentes AS (
    SELECT DISTINCT ON (pc.codigo_sucursal)
        pc.codigo_sucursal,
        p.nombre_completo AS gerente_a_cargo
    FROM persona_cargo pc
    JOIN cargo c ON c.id_cargo = pc.id_cargo
    JOIN persona p ON p.run = pc.run_persona
    WHERE lower(c.nombre) LIKE '%gerente%'
      AND pc.fecha_inicio <= DATE '2025-12-31'
      AND (pc.fecha_termino IS NULL OR pc.fecha_termino >= DATE '2025-01-01')
    ORDER BY pc.codigo_sucursal, pc.fecha_inicio DESC
), total_club AS (
    SELECT SUM(ingresos_totales) AS total FROM ingresos_sucursal
)
SELECT
    suc.codigo_sucursal,
    suc.nombre AS sucursal,
    COALESCE(g.gerente_a_cargo, 'Sin gerente registrado') AS gerente_a_cargo,
    COALESCE(i.ingresos_totales, 0)::integer AS ingresos_totales,
    CASE
        WHEN COALESCE(t.total, 0) = 0 THEN 0::numeric
        ELSE ROUND((COALESCE(i.ingresos_totales, 0) * 100.0 / t.total), 2)
    END AS porcentaje_total_club
FROM sucursal suc
LEFT JOIN ingresos_sucursal i ON i.codigo_sucursal = suc.codigo_sucursal
LEFT JOIN gerentes g ON g.codigo_sucursal = suc.codigo_sucursal
CROSS JOIN total_club t
ORDER BY ingresos_totales DESC, suc.nombre;
