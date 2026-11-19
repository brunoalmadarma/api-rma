ALTER TABLE notas_remision MODIFY id_caso_insumo INT NULL, MODIFY id_stock INT NULL;
ALTER TABLE notas_remision MODIFY estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE';
