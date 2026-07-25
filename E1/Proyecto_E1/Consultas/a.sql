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