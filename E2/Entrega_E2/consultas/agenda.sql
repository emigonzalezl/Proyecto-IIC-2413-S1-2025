\pset border 2
\pset null 'NULL'
\o consultas/agenda.txt

WITH agenda AS (

    SELECT
        r.fecha_inicio::date AS fecha,
        r.fecha_inicio::time AS hora,
        l.nombre AS lugar,
        p.nombre_completo AS nombre,
        'reserva' AS tipo
    FROM reserva r
    JOIN persona p
        ON p.run = r.run_reservante
    JOIN lugar l
        ON l.codigo_lugar = r.codigo_lugar
    JOIN sucursal s
        ON s.codigo_sucursal = l.codigo_sucursal
    WHERE s.nombre = 'Santa Cruz'
      AND r.fecha_inicio::date >= DATE '2026-04-06'
      AND r.fecha_inicio::date < DATE '2026-04-13'

    UNION ALL

    SELECT
        e.fecha_evento AS fecha,
        TIME '00:00' AS hora,
        l.nombre AS lugar,
        e.nombre AS nombre,
        'evento' AS tipo
    FROM evento e
    JOIN lugar l
        ON l.codigo_lugar = e.codigo_lugar
    JOIN sucursal s
        ON s.codigo_sucursal = e.codigo_sucursal
    WHERE s.nombre = 'Santa Cruz'
      AND e.fecha_evento >= DATE '2026-04-06'
      AND e.fecha_evento < DATE '2026-04-13'
)

SELECT
    CASE EXTRACT(DOW FROM fecha)
        WHEN 0 THEN 'domingo'
        WHEN 1 THEN 'lunes'
        WHEN 2 THEN 'martes'
        WHEN 3 THEN 'miercoles'
        WHEN 4 THEN 'jueves'
        WHEN 5 THEN 'viernes'
        WHEN 6 THEN 'sabado'
    END AS dia,
    fecha,
    hora,
    lugar,
    string_agg(tipo || ': ' || nombre, ' / ' ORDER BY tipo, nombre) AS evento_o_socio
FROM agenda
GROUP BY fecha, hora, lugar
ORDER BY fecha, hora, lugar;

\o