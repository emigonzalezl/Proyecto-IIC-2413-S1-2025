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