-- DUMP DE ESTRUCTURA PARA DER - 2026-11-16 20:31:16

-- ==========================================
-- BASE DE DATOS: dosisma_rma_bd (rma)
-- ==========================================

CREATE TABLE `bajas_stock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_stock` int NOT NULL,
  `id_caso` int DEFAULT NULL,
  `cantidad` int NOT NULL,
  `stock_anterior` int NOT NULL,
  `stock_nuevo` int NOT NULL,
  `id_usuario` int NOT NULL,
  `causa` varchar(30) NOT NULL,
  `motivo` text NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bajas_stock` (`id_stock`),
  KEY `fk_bajas_caso` (`id_caso`),
  KEY `fk_bajas_usuario` (`id_usuario`),
  CONSTRAINT `fk_bajas_caso` FOREIGN KEY (`id_caso`) REFERENCES `casos` (`id`),
  CONSTRAINT `fk_bajas_insumo` FOREIGN KEY (`id_stock`) REFERENCES `stock` (`id`),
  CONSTRAINT `fk_bajas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `caso_fotos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_caso` int NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_caso_foto` (`id_caso`,`archivo`),
  CONSTRAINT `fk_fotos_caso` FOREIGN KEY (`id_caso`) REFERENCES `casos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `caso_insumos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_caso` int NOT NULL,
  `id_stock` int NOT NULL,
  `id_tecnico` int NOT NULL COMMENT 'Técnico que aplicó o gastó el insumo',
  `cantidad` int NOT NULL DEFAULT '1',
  `costo_aplicado` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Costo histórico al momento del consumo',
  `fecha_aplicacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `observacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_casoinsumos_caso` (`id_caso`),
  KEY `fk_casoinsumos_stock` (`id_stock`),
  KEY `fk_casoinsumos_tecnico` (`id_tecnico`),
  CONSTRAINT `fk_casoinsumos_caso` FOREIGN KEY (`id_caso`) REFERENCES `casos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_casoinsumos_stock` FOREIGN KEY (`id_stock`) REFERENCES `stock` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_casoinsumos_tecnico` FOREIGN KEY (`id_tecnico`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=311 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `casos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_caso` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `id_cliente` int NOT NULL,
  `id_tecnico` int NOT NULL,
  `id_tipo_caso` int NOT NULL,
  `id_estado_actual` int NOT NULL,
  `id_proveedor` int DEFAULT NULL,
  `equipo` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `marca` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `modelo` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numero_serie` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion_problema` text COLLATE utf8mb4_general_ci NOT NULL,
  `diagnostico_final` text COLLATE utf8mb4_general_ci,
  `foto_archivo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `qr_archivo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_ingreso` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_cierre` datetime DEFAULT NULL,
  `fecha_envio_proveedor` datetime DEFAULT NULL,
  `referencia_proveedor` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_caso` (`numero_caso`),
  KEY `fk_casos_proveedores` (`id_proveedor`),
  KEY `idx_casos_numero_caso` (`numero_caso`),
  KEY `idx_casos_cliente` (`id_cliente`),
  KEY `idx_casos_tecnico` (`id_tecnico`),
  KEY `idx_casos_tipo` (`id_tipo_caso`),
  KEY `idx_casos_estado_actual` (`id_estado_actual`),
  CONSTRAINT `fk_casos_clientes` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_casos_estado_actual` FOREIGN KEY (`id_estado_actual`) REFERENCES `estados_caso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_casos_proveedores` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_casos_tecnico` FOREIGN KEY (`id_tecnico`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_casos_tipos` FOREIGN KEY (`id_tipo_caso`) REFERENCES `tipos_caso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=734 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `catalogo_equipos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `catalogo_marcas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `categorias_stock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `cedula` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `celular` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula` (`cedula`),
  KEY `idx_clientes_cedula` (`cedula`)
) ENGINE=InnoDB AUTO_INCREMENT=630 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `estados_caso` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `historial_estados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_caso` int NOT NULL,
  `id_estado` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `observacion` text COLLATE utf8mb4_general_ci,
  `id_usuario` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_historial_estados` (`id_estado`),
  KEY `fk_historial_usuarios` (`id_usuario`),
  KEY `idx_historial_caso_fecha` (`id_caso`,`fecha`),
  CONSTRAINT `fk_historial_casos` FOREIGN KEY (`id_caso`) REFERENCES `casos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_historial_estados` FOREIGN KEY (`id_estado`) REFERENCES `estados_caso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_historial_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2305 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `movimientos_stock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_stock` int NOT NULL,
  `tipo_movimiento` enum('ENTRADA','SALIDA_RMA','AJUSTE_INVENTARIO','DEVOLUCION') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` int NOT NULL,
  `stock_anterior` int NOT NULL,
  `stock_nuevo` int NOT NULL,
  `id_caso` int DEFAULT NULL COMMENT 'Solo si el movimiento proviene de una reparación',
  `id_usuario` int NOT NULL COMMENT 'Usuario que registró el movimiento',
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mov_stock` (`id_stock`),
  KEY `fk_mov_caso` (`id_caso`),
  KEY `fk_mov_usuario` (`id_usuario`),
  CONSTRAINT `fk_mov_caso` FOREIGN KEY (`id_caso`) REFERENCES `casos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_stock` FOREIGN KEY (`id_stock`) REFERENCES `stock` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=317 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notas_remision` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_caso` int NOT NULL,
  `numero_caso` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_caso_insumo` int DEFAULT NULL,
  `id_stock` int DEFAULT NULL,
  `codigo_sku` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insumo_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_tecnico` int NOT NULL,
  `id_usuario_creador` int NOT NULL,
  `cantidad` int NOT NULL,
  `costo_unitario` decimal(14,2) NOT NULL DEFAULT '0.00',
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_cierre` datetime DEFAULT NULL,
  `id_usuario_cierre` int DEFAULT NULL,
  `observacion_cierre` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_remision_consumo` (`id_caso_insumo`),
  KEY `idx_remisiones_estado_fecha` (`estado`,`fecha_creacion`),
  KEY `idx_remisiones_caso` (`id_caso`),
  KEY `idx_remisiones_tecnico` (`id_tecnico`)
) ENGINE=InnoDB AUTO_INCREMENT=530 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proveedores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `contacto` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=308 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `estado` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `stock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo_sku` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Código interno o de barras del insumo',
  `id_categoria` int DEFAULT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: Pasta Térmica Arctic MX-4 4g',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `id_proveedor_habitual` int DEFAULT NULL COMMENT 'Vínculo opcional a la tabla proveedores',
  `cantidad` int NOT NULL DEFAULT '0' COMMENT 'Stock físico disponible actual',
  `stock_minimo` int NOT NULL DEFAULT '2' COMMENT 'Umbral para disparar Alertas de Stock Crítico',
  `costo_unitario` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Costo en Guaraníes / Moneda local',
  `precio_venta` decimal(12,2) DEFAULT '0.00' COMMENT 'Precio al cliente si aplica cobro',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_sku` (`codigo_sku`),
  KEY `fk_stock_categoria` (`id_categoria`),
  KEY `fk_stock_proveedor` (`id_proveedor_habitual`),
  CONSTRAINT `fk_stock_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias_stock` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_proveedor` FOREIGN KEY (`id_proveedor_habitual`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=407 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tipos_caso` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_rol` int NOT NULL,
  `usuario` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `contrasena_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_usuarios_rol` (`id_rol`),
  CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ==========================================
-- BASE DE DATOS: dosisma_inventario_db (inventario)
-- ==========================================

CREATE TABLE `movimientos_inventario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `producto_id` int NOT NULL,
  `tipo_movimiento` enum('ENTREGA_TECNICO','REPOSICION','AJUSTE','INGRESO_RMA','SALIDA_RMA') COLLATE utf8mb4_general_ci NOT NULL,
  `cantidad` int NOT NULL,
  `stock_anterior` int NOT NULL,
  `stock_nuevo` int NOT NULL,
  `usuario_id` int NOT NULL,
  `referencia` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observacion` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `movimientos_inventario_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notas_remision` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_remision` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tipo_remision` enum('ENTRADA','SALIDA') COLLATE utf8mb4_general_ci NOT NULL,
  `destinatario_proveedor` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `estado` enum('PENDIENTE','COMPLETADO','ANULADO') COLLATE utf8mb4_general_ci DEFAULT 'PENDIENTE',
  `fecha_emision` date NOT NULL,
  `usuario_id` int NOT NULL,
  `observaciones` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_remision` (`numero_remision`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `operaciones_rma` (
  `id` int NOT NULL AUTO_INCREMENT,
  `producto_id` int NOT NULL,
  `id_caso` int DEFAULT NULL,
  `numero_caso` varchar(30) DEFAULT NULL,
  `id_tecnico` int DEFAULT NULL,
  `tipo` varchar(30) NOT NULL,
  `cantidad` int NOT NULL,
  `stock_anterior` int NOT NULL,
  `stock_nuevo` int NOT NULL,
  `rma_anterior` int NOT NULL,
  `rma_nuevo` int NOT NULL,
  `id_usuario` int NOT NULL,
  `motivo` text NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_operaciones_caso` (`producto_id`,`id_caso`,`tipo`),
  CONSTRAINT `fk_operaciones_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=314 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `productos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `categoria` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `marca` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `stock_actual` int NOT NULL DEFAULT '0',
  `stock_rma` int NOT NULL DEFAULT '0',
  `stock_minimo` int NOT NULL DEFAULT '5',
  `estado_alerta` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'OK',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=416 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `remision_detalles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `remision_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `remision_id` (`remision_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `remision_detalles_ibfk_1` FOREIGN KEY (`remision_id`) REFERENCES `notas_remision` (`id`) ON DELETE CASCADE,
  CONSTRAINT `remision_detalles_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

