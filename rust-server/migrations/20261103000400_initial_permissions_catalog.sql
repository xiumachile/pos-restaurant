-- Fase 04: catálogo inicial de permisos POS Edge.
--
-- Los permisos son globales.
-- Las asignaciones a roles se realizan por establecimiento.
-- No se conceden permisos automáticamente.

INSERT INTO permisos (id, codigo, descripcion)
VALUES
    -- Pedidos
    (gen_random_uuid(), 'pedidos.ver', 'Consultar pedidos'),
    (gen_random_uuid(), 'pedidos.crear', 'Crear pedidos'),
    (gen_random_uuid(), 'pedidos.editar', 'Modificar pedidos'),
    (gen_random_uuid(), 'pedidos.cancelar', 'Cancelar pedidos'),

    -- Mesas
    (gen_random_uuid(), 'mesas.ver', 'Consultar mesas'),
    (gen_random_uuid(), 'mesas.abrir', 'Abrir mesas'),
    (gen_random_uuid(), 'mesas.cerrar', 'Cerrar mesas'),
    (gen_random_uuid(), 'mesas.configurar', 'Configurar mesas y salones'),

    -- Caja
    (gen_random_uuid(), 'caja.abrir', 'Abrir caja'),
    (gen_random_uuid(), 'caja.cobrar', 'Registrar cobros'),
    (gen_random_uuid(), 'caja.cerrar', 'Cerrar caja'),
    (gen_random_uuid(), 'caja.anular', 'Anular operaciones de caja'),

    -- Productos
    (gen_random_uuid(), 'productos.ver', 'Consultar productos'),
    (gen_random_uuid(), 'productos.gestionar', 'Gestionar productos y carta'),

    -- Inventario
    (gen_random_uuid(), 'inventario.ver', 'Consultar inventario'),
    (gen_random_uuid(), 'inventario.gestionar', 'Gestionar inventario'),
    (gen_random_uuid(), 'inventario.ajustar', 'Realizar ajustes de inventario'),

    -- Compras
    (gen_random_uuid(), 'compras.ver', 'Consultar compras'),
    (gen_random_uuid(), 'compras.gestionar', 'Gestionar compras'),
    (gen_random_uuid(), 'compras.pagar', 'Registrar pagos de compras'),

    -- Delivery
    (gen_random_uuid(), 'delivery.ver', 'Consultar pedidos delivery'),
    (gen_random_uuid(), 'delivery.gestionar', 'Gestionar pedidos delivery'),
    (gen_random_uuid(), 'delivery.asignar', 'Asignar repartidores'),

    -- Cocina
    (gen_random_uuid(), 'cocina.ver', 'Consultar pedidos de cocina'),
    (gen_random_uuid(), 'cocina.actualizar', 'Actualizar estados de cocina'),

    -- Reportes
    (gen_random_uuid(), 'reportes.ver', 'Consultar reportes'),
    (gen_random_uuid(), 'reportes.exportar', 'Exportar reportes'),

    -- Administración
    (gen_random_uuid(), 'usuarios.gestionar', 'Gestionar usuarios'),
    (gen_random_uuid(), 'roles.gestionar', 'Gestionar roles y permisos'),
    (gen_random_uuid(), 'configuracion.gestionar', 'Gestionar configuración')
ON CONFLICT (codigo) DO NOTHING;
