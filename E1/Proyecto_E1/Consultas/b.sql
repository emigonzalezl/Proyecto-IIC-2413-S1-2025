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