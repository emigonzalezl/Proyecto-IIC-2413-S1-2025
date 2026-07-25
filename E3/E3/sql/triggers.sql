-- Triggers para actualizar el plan de pagos cuando cambian beneficiarios/adicionales.
-- Se carga después del procedimiento.

CREATE OR REPLACE FUNCTION trg_actualizar_plan_pagos_dependientes()
RETURNS trigger AS $$
DECLARE
    v_id_titular integer;
BEGIN
    IF TG_OP = 'DELETE' THEN
        v_id_titular := OLD.id_socio_titular;
    ELSE
        v_id_titular := NEW.id_socio_titular;
    END IF;

    IF v_id_titular IS NOT NULL THEN
        PERFORM sp_generar_plan_pagos_2026(v_id_titular);
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_relacion_socio_plan_pagos ON relacion_socio;

CREATE TRIGGER trg_relacion_socio_plan_pagos
AFTER INSERT OR UPDATE OR DELETE ON relacion_socio
FOR EACH ROW
EXECUTE FUNCTION trg_actualizar_plan_pagos_dependientes();

CREATE OR REPLACE FUNCTION trg_actualizar_plan_pagos_socio_dependiente()
RETURNS trigger AS $$
DECLARE
    v_id_titular integer;
BEGIN
    SELECT r.id_socio_titular
    INTO v_id_titular
    FROM relacion_socio r
    WHERE r.id_socio_dependiente = NEW.id_socio
    LIMIT 1;

    IF v_id_titular IS NOT NULL THEN
        PERFORM sp_generar_plan_pagos_2026(v_id_titular);
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_socio_dependiente_plan_pagos ON socio;

CREATE TRIGGER trg_socio_dependiente_plan_pagos
AFTER UPDATE OF tipo_socio, fecha_fin ON socio
FOR EACH ROW
WHEN (OLD.tipo_socio IS DISTINCT FROM NEW.tipo_socio OR OLD.fecha_fin IS DISTINCT FROM NEW.fecha_fin)
EXECUTE FUNCTION trg_actualizar_plan_pagos_socio_dependiente();
