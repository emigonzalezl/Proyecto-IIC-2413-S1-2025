\pset border 2
\pset null '0'
\o consultas/ingresoporsucursal.txt

WITH gerente AS (
    SELECT DISTINCT ON (pc.codigo_sucursal)
        pc.codigo_sucursal,
        p.nombre_completo AS gerente_a_cargo
    FROM persona_cargo pc
    JOIN persona p
        ON p.run = pc.run_persona
    JOIN cargo c
        ON c.id_cargo = pc.id_cargo
    WHERE lugar_key(c.nombre) LIKE '%gerente%'
      AND pc.fecha_inicio <= DATE '2025-12-31'
      AND (
            pc.fecha_termino IS NULL
            OR pc.fecha_termino >= DATE '2025-01-01'
          )
    ORDER BY pc.codigo_sucursal, pc.fecha_inicio DESC
),

ingresos_membresias AS (
    SELECT
        s.codigo_sucursal,
        SUM(pc.monto_pagado) AS monto
    FROM pago_cuota pc
    JOIN cuota c
        ON c.id_cuota = pc.cuota_numero
    JOIN membresia m
        ON m.id_socio = c.id_membresia
    JOIN socio so
        ON so.id_socio = m.id_socio_titular
    JOIN sucursal s
        ON s.codigo_sucursal = so.codigo_sucursal_base
    WHERE pc.fecha_pago >= DATE '2025-01-01'
      AND pc.fecha_pago < DATE '2026-01-01'
    GROUP BY s.codigo_sucursal
),

ingresos_reservas AS (
    SELECT
        s.codigo_sucursal,
        SUM(pr.monto) AS monto
    FROM pago_reserva pr
    JOIN reserva r
        ON r.codigo_reserva = pr.codigo_reserva
    JOIN lugar l
        ON l.codigo_lugar = r.codigo_lugar
    JOIN sucursal s
        ON s.codigo_sucursal = l.codigo_sucursal
    WHERE pr.fecha_pago >= DATE '2025-01-01'
      AND pr.fecha_pago < DATE '2026-01-01'
    GROUP BY s.codigo_sucursal
),

ingresos_eventos AS (
    SELECT
        s.codigo_sucursal,
        SUM(pe.monto) AS monto
    FROM pago_evento pe
    JOIN evento e
        ON e.codigo_evento = pe.codigo_evento
    JOIN sucursal s
        ON s.codigo_sucursal = e.codigo_sucursal
    WHERE pe.fecha_pago >= DATE '2025-01-01'
      AND pe.fecha_pago < DATE '2026-01-01'
    GROUP BY s.codigo_sucursal
),

ingresos_totales AS (
    SELECT
        s.codigo_sucursal,
        s.nombre AS sucursal,
        COALESCE(im.monto, 0)
        + COALESCE(ir.monto, 0)
        + COALESCE(ie.monto, 0) AS ingresos_totales
    FROM sucursal s
    LEFT JOIN ingresos_membresias im
        ON im.codigo_sucursal = s.codigo_sucursal
    LEFT JOIN ingresos_reservas ir
        ON ir.codigo_sucursal = s.codigo_sucursal
    LEFT JOIN ingresos_eventos ie
        ON ie.codigo_sucursal = s.codigo_sucursal
),

total_club AS (
    SELECT
        SUM(ingresos_totales) AS total
    FROM ingresos_totales
)

SELECT
    it.sucursal,
    COALESCE(g.gerente_a_cargo, 'Sin gerente registrado') AS gerente_a_cargo,
    it.ingresos_totales,
    CASE
        WHEN tc.total = 0 THEN 0
        ELSE ROUND((it.ingresos_totales::numeric * 100) / tc.total, 2)
    END AS porcentaje_total_club
FROM ingresos_totales it
LEFT JOIN gerente g
    ON g.codigo_sucursal = it.codigo_sucursal
CROSS JOIN total_club tc
ORDER BY it.ingresos_totales DESC, it.sucursal;

\o