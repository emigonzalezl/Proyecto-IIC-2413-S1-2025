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