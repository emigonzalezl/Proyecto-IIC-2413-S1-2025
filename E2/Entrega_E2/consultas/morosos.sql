\pset border 2
\pset null '0'
\o consultas/morosos.txt

SELECT
    p.nombre_completo,
    p.run,
    s.nombre AS sucursal,
    SUM(c.monto_total) AS monto_atrasado,
    COUNT(c.id_cuota) AS numero_cuotas
FROM cuota c
JOIN membresia m
    ON m.id_socio = c.id_membresia
JOIN socio so
    ON so.id_socio = m.id_socio_titular
JOIN persona p
    ON p.run = so.run_persona
LEFT JOIN sucursal s
    ON s.codigo_sucursal = so.codigo_sucursal_base
WHERE c.estado = 'atrasado'
GROUP BY
    p.nombre_completo,
    p.run,
    s.nombre
ORDER BY
    monto_atrasado DESC,
    numero_cuotas DESC,
    p.nombre_completo;

\o