-- Índices de apoyo.
-- No cambian los datos, solo ayudan a que algunas búsquedas sean más rápidas.

CREATE INDEX IF NOT EXISTS idx_reserva_lugar_fecha ON reserva(codigo_lugar, fecha_inicio, fecha_fin);
CREATE INDEX IF NOT EXISTS idx_reserva_run_reservante ON reserva(run_reservante);
CREATE INDEX IF NOT EXISTS idx_lugar_sucursal ON lugar(codigo_sucursal);
CREATE INDEX IF NOT EXISTS idx_socio_run_tipo ON socio(run_persona, tipo_socio);
CREATE INDEX IF NOT EXISTS idx_socio_sucursal ON socio(codigo_sucursal_base);
CREATE INDEX IF NOT EXISTS idx_pago_cuota_socio_cuota ON pago_cuota(id_socio, cuota_numero, fecha_pago);
CREATE INDEX IF NOT EXISTS idx_pago_reserva_codigo_fecha ON pago_reserva(codigo_reserva, fecha_pago);
CREATE INDEX IF NOT EXISTS idx_pago_evento_codigo_fecha ON pago_evento(codigo_evento, fecha_pago);
CREATE INDEX IF NOT EXISTS idx_evento_sucursal_fecha ON evento(codigo_sucursal, fecha_evento);
CREATE INDEX IF NOT EXISTS idx_relacion_socio_titular ON relacion_socio(id_socio_titular);
CREATE INDEX IF NOT EXISTS idx_asistente_evento_codigo ON asistente_evento(codigo_evento);
CREATE INDEX IF NOT EXISTS idx_contacto_empresa_rut ON contacto_empresa(rut_empresa);
