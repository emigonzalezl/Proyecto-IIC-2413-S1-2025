SELECT
    pb.RUN AS run_beneficiario,
    pb.nombre_completo AS nombre_beneficiario,
    pb.correo AS correo_beneficiario,
    pb.telefono_celular AS telefono_beneficiario,

    pt.RUN AS run_titular,
    pt.nombre_completo AS nombre_titular,
    pt.correo AS correo_titular,
    pt.telefono_celular AS telefono_titular

FROM Familiar f
JOIN Persona pb ON f.run_persona = pb.RUN
JOIN Socio s ON f.run_titular = s.RUN
JOIN Persona pt ON s.RUN = pt.RUN

WHERE f.tipo = 'beneficiario'
  AND pb.fecha_nacimiento >= '1997-01-01'
  AND pb.fecha_nacimiento <= '1997-12-31';