-- Esto lo corro en local después de cargar el dump.
-- Sirve para que los ids automáticos no partan de 1 si ya hay datos.

SELECT setval('public.usuario_id_usuario_seq', COALESCE((SELECT MAX(id_usuario) FROM public.usuario), 1), true);
SELECT setval('public.persona_cargo_id_persona_cargo_seq', COALESCE((SELECT MAX(id_persona_cargo) FROM public.persona_cargo), 1), true);
SELECT setval('public.socio_id_socio_seq', COALESCE((SELECT MAX(id_socio) FROM public.socio), 1), true);
SELECT setval('public.membresia_id_membresia_seq', COALESCE((SELECT MAX(id_socio) FROM public.membresia), 1), true);
SELECT setval('public.relacion_socio_id_relacion_seq', COALESCE((SELECT MAX(id_relacion) FROM public.relacion_socio), 1), true);
SELECT setval('public.pago_cuota_id_pago_cuota_seq', COALESCE((SELECT MAX(id_pago_cuota) FROM public.pago_cuota), 1), true);
SELECT setval('public.asistente_evento_id_asistente_seq', COALESCE((SELECT MAX(id_asistente) FROM public.asistente_evento), 1), true);
SELECT setval('public.contacto_empresa_id_contacto_seq', COALESCE((SELECT MAX(id_contacto) FROM public.contacto_empresa), 1), true);
SELECT setval('public.pago_evento_id_pago_evento_seq', COALESCE((SELECT MAX(id_pago_evento) FROM public.pago_evento), 1), true);
SELECT setval('public.precio_lugar_id_precio_seq', COALESCE((SELECT MAX(id_precio) FROM public.precio_lugar), 1), true);
SELECT setval('public.pago_reserva_id_pago_reserva_seq', COALESCE((SELECT MAX(id_pago_reserva) FROM public.pago_reserva), 1), true);
