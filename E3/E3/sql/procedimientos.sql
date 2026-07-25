-- Procedimiento para crear/actualizar el plan de pagos 2026.
-- Lo usa la página de cuotas y también los triggers.

CREATE OR REPLACE FUNCTION sp_generar_plan_pagos_2026(p_id_socio_titular integer)
RETURNS void AS $$
DECLARE
    v_precio_socio integer := 0;
    v_precio_adicional integer := 0;
    v_cantidad_dependientes integer := 0;
    v_monto_base integer := 0;
    v_monto_adicional integer := 0;
    v_cuota integer;
BEGIN
    SELECT COALESCE(suc.precio_socio, 0), COALESCE(suc.precio_adicional, 0)
    INTO v_precio_socio, v_precio_adicional
    FROM socio s
    LEFT JOIN sucursal suc ON suc.codigo_sucursal = s.codigo_sucursal_base
    WHERE s.id_socio = p_id_socio_titular
      AND replace(lower(s.tipo_socio), ' ', '_') = 'socio_titular';

    IF NOT FOUND THEN
        RAISE EXCEPTION 'El socio titular % no existe', p_id_socio_titular;
    END IF;

    SELECT COUNT(*)
    INTO v_cantidad_dependientes
    FROM relacion_socio r
    JOIN socio sd ON sd.id_socio = r.id_socio_dependiente
    WHERE r.id_socio_titular = p_id_socio_titular
      AND replace(lower(sd.tipo_socio), ' ', '_') IN ('beneficiario', 'adicional')
      AND (sd.fecha_fin IS NULL OR sd.fecha_fin >= DATE '2026-01-01');

    v_monto_base := v_precio_socio;
    v_monto_adicional := v_cantidad_dependientes * v_precio_adicional;

    UPDATE pago_cuota
    SET monto_base = v_monto_base,
        monto_adicional = v_monto_adicional
    WHERE id_socio = p_id_socio_titular
      AND cuota_numero BETWEEN 1 AND 12
      AND (fecha_pago IS NULL OR monto_pagado = 0);

    FOR v_cuota IN 1..12 LOOP
        INSERT INTO pago_cuota
            (cuota_numero, fecha_pago, monto_pagado, medio_pago, id_socio, monto_base, monto_adicional)
        SELECT
            v_cuota,
            NULL,
            0,
            NULL,
            p_id_socio_titular,
            v_monto_base,
            v_monto_adicional
        WHERE NOT EXISTS (
            SELECT 1
            FROM pago_cuota pc
            WHERE pc.id_socio = p_id_socio_titular
              AND pc.cuota_numero = v_cuota
        );
    END LOOP;
END;
$$ LANGUAGE plpgsql;
