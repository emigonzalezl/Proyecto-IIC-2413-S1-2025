\pset border 2
\pset null 'NULL'
\o consultas/finbeneficiario.txt

WITH parametros AS (
    SELECT
        (DATE_TRUNC('year', CURRENT_DATE) + INTERVAL '1 year')::date AS renovacion_inicio,
        (DATE_TRUNC('year', CURRENT_DATE) + INTERVAL '2 years')::date AS renovacion_fin
)

SELECT
    pb.run AS run_beneficiario,
    pb.nombre_completo AS nombre_beneficiario,
    pb.email AS correo_beneficiario,
    pb.telefono_celular AS telefono_beneficiario,

    pt.run AS run_socio_titular,
    pt.nombre_completo AS nombre_socio_titular,
    pt.email AS correo_socio_titular,
    pt.telefono_celular AS telefono_socio_titular

FROM relacion_socio rs
JOIN socio sb
    ON sb.id_socio = rs.id_socio_dependiente
JOIN persona pb
    ON pb.run = sb.run_persona
JOIN socio st
    ON st.id_socio = rs.id_socio_titular
JOIN persona pt
    ON pt.run = st.run_persona
CROSS JOIN parametros p

WHERE sb.tipo_socio = 'beneficiario'
  AND lugar_key(rs.parentesco) LIKE 'hij%'
  AND pb.fecha_nacimiento IS NOT NULL
  AND pb.fecha_nacimiento + INTERVAL '29 years' >= p.renovacion_inicio
  AND pb.fecha_nacimiento + INTERVAL '29 years' < p.renovacion_fin

ORDER BY pb.nombre_completo;

\o