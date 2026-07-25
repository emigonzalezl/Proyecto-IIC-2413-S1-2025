\pset border 2
\pset null '0'
\o consultas/ingresomensual.txt

-----------------------------------------
-- BLOQUE A CAMBIAR para probar con distintas fechas. 
WITH parametros AS (
    SELECT
        DATE_TRUNC('month', CURRENT_DATE)::date AS mes_inicio,
        (DATE_TRUNC('month', CURRENT_DATE) + INTERVAL '1 month')::date AS mes_fin,
        'Santa Cruz'::text AS sucursal_objetivo
),
-----------------------------------------
pagos_reserva_total AS (
    SELECT
        codigo_reserva,
        SUM(monto) AS total_pagado
    FROM pago_reserva
    GROUP BY codigo_reserva
),

pagos_evento_total AS (
    SELECT
        codigo_evento,
        SUM(monto) AS total_pagado
    FROM pago_evento
    GROUP BY codigo_evento
),

ingresos AS (

    SELECT
        'membresias' AS concepto,
        'efectivamente recibido' AS tipo_ingreso,
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
    CROSS JOIN parametros p
    WHERE s.nombre = p.sucursal_objetivo
      AND pc.fecha_pago >= p.mes_inicio
      AND pc.fecha_pago < p.mes_fin

    UNION ALL

    SELECT
        'membresias' AS concepto,
        'futuro esperado' AS tipo_ingreso,
        SUM(c.monto_total) AS monto
    FROM cuota c
    JOIN membresia m
        ON m.id_socio = c.id_membresia
    JOIN socio so
        ON so.id_socio = m.id_socio_titular
    JOIN sucursal s
        ON s.codigo_sucursal = so.codigo_sucursal_base
    CROSS JOIN parametros p
    WHERE s.nombre = p.sucursal_objetivo
      AND c.fecha_vencimiento >= p.mes_inicio
      AND c.fecha_vencimiento < p.mes_fin
      AND c.estado = 'atrasado'

    UNION ALL

    SELECT
        'reservas ejecutadas' AS concepto,
        'efectivamente recibido' AS tipo_ingreso,
        SUM(pr.monto) AS monto
    FROM pago_reserva pr
    JOIN reserva r
        ON r.codigo_reserva = pr.codigo_reserva
    JOIN lugar l
        ON l.codigo_lugar = r.codigo_lugar
    JOIN sucursal s
        ON s.codigo_sucursal = l.codigo_sucursal
    CROSS JOIN parametros p
    WHERE s.nombre = p.sucursal_objetivo
      AND r.estado = 'ejecutada'
      AND pr.fecha_pago >= p.mes_inicio
      AND pr.fecha_pago < p.mes_fin

    UNION ALL

    SELECT
        'reservas ejecutadas' AS concepto,
        'futuro esperado' AS tipo_ingreso,
        SUM(GREATEST(r.monto_total - COALESCE(prt.total_pagado, 0), 0)) AS monto
    FROM reserva r
    JOIN lugar l
        ON l.codigo_lugar = r.codigo_lugar
    JOIN sucursal s
        ON s.codigo_sucursal = l.codigo_sucursal
    LEFT JOIN pagos_reserva_total prt
        ON prt.codigo_reserva = r.codigo_reserva
    CROSS JOIN parametros p
    WHERE s.nombre = p.sucursal_objetivo
      AND r.estado = 'ejecutada'
      AND r.fecha_inicio::date >= p.mes_inicio
      AND r.fecha_inicio::date < p.mes_fin

    UNION ALL

    SELECT
        'eventos' AS concepto,
        'efectivamente recibido' AS tipo_ingreso,
        SUM(pe.monto) AS monto
    FROM pago_evento pe
    JOIN evento e
        ON e.codigo_evento = pe.codigo_evento
    JOIN sucursal s
        ON s.codigo_sucursal = e.codigo_sucursal
    CROSS JOIN parametros p
    WHERE s.nombre = p.sucursal_objetivo
      AND pe.fecha_pago >= p.mes_inicio
      AND pe.fecha_pago < p.mes_fin

    UNION ALL

    SELECT
        'eventos' AS concepto,
        'futuro esperado' AS tipo_ingreso,
        SUM(GREATEST(e.monto_total - COALESCE(pet.total_pagado, 0), 0)) AS monto
    FROM evento e
    JOIN sucursal s
        ON s.codigo_sucursal = e.codigo_sucursal
    LEFT JOIN pagos_evento_total pet
        ON pet.codigo_evento = e.codigo_evento
    CROSS JOIN parametros p
    WHERE s.nombre = p.sucursal_objetivo
      AND e.fecha_evento >= p.mes_inicio
      AND e.fecha_evento < p.mes_fin
)

SELECT
    concepto,
    tipo_ingreso,
    COALESCE(SUM(monto), 0) AS ingreso_mensual
FROM ingresos
GROUP BY concepto, tipo_ingreso
ORDER BY concepto, tipo_ingreso;

\o