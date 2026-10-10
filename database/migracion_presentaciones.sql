USE farmacia_fuente_vida;

CREATE TABLE IF NOT EXISTS catalogo_presentaciones (
	id_catalogo_presentacion INT AUTO_INCREMENT PRIMARY KEY,
	nombre VARCHAR(100) NOT NULL UNIQUE,
	nivel SMALLINT UNSIGNED NOT NULL,
	estado BOOLEAN NOT NULL DEFAULT TRUE,
	fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS producto_presentaciones (
	id_producto_presentacion INT AUTO_INCREMENT PRIMARY KEY,
	id_producto INT NOT NULL,
	id_catalogo_presentacion INT NOT NULL,
	unidades_base BIGINT UNSIGNED NOT NULL,
	precio_venta DECIMAL(10,2) NOT NULL,
	estado BOOLEAN NOT NULL DEFAULT TRUE,
	UNIQUE KEY uk_producto_presentacion (id_producto, id_catalogo_presentacion),
	CONSTRAINT fk_presentacion_producto FOREIGN KEY (id_producto)
		REFERENCES productos(id_producto)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT fk_presentacion_catalogo FOREIGN KEY (id_catalogo_presentacion)
		REFERENCES catalogo_presentaciones(id_catalogo_presentacion)
		ON UPDATE CASCADE ON DELETE RESTRICT
);

INSERT IGNORE INTO catalogo_presentaciones (nombre, nivel) VALUES
	('Unidad', 1),
	('Blíster', 2),
	('Cajita', 3),
	('Caja', 4);

INSERT IGNORE INTO producto_presentaciones
	(id_producto, id_catalogo_presentacion, unidades_base, precio_venta)
SELECT p.id_producto, cp.id_catalogo_presentacion, 1, p.precio_venta
FROM productos p
INNER JOIN catalogo_presentaciones cp ON cp.nombre = 'Unidad'
WHERE p.precio_venta >= 0;

ALTER TABLE lotes MODIFY cantidad BIGINT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS detalle_ventas_lotes (
	id_detalle_venta_lote INT AUTO_INCREMENT PRIMARY KEY,
	id_detalle_venta INT NOT NULL,
	id_lote INT NOT NULL,
	cantidad_base BIGINT UNSIGNED NOT NULL,
	CONSTRAINT fk_asignacion_venta_detalle FOREIGN KEY (id_detalle_venta)
		REFERENCES detalle_ventas(id_detalle_venta)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT fk_asignacion_venta_lote FOREIGN KEY (id_lote)
		REFERENCES lotes(id_lote)
		ON UPDATE CASCADE ON DELETE RESTRICT
);

DELIMITER $$
DROP PROCEDURE IF EXISTS migrar_columnas_presentaciones$$
CREATE PROCEDURE migrar_columnas_presentaciones()
BEGIN
	IF NOT EXISTS (
		SELECT 1 FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_compras'
			AND COLUMN_NAME = 'id_producto_presentacion'
	) THEN
		ALTER TABLE detalle_compras
			ADD COLUMN id_producto_presentacion INT NULL AFTER id_lote;
	END IF;

	IF NOT EXISTS (
		SELECT 1 FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_compras'
			AND COLUMN_NAME = 'cantidad_base'
	) THEN
		ALTER TABLE detalle_compras
			ADD COLUMN cantidad_base BIGINT UNSIGNED NULL AFTER cantidad;
	END IF;

	IF NOT EXISTS (
		SELECT 1 FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_ventas'
			AND COLUMN_NAME = 'id_producto_presentacion'
	) THEN
		ALTER TABLE detalle_ventas
			ADD COLUMN id_producto_presentacion INT NULL AFTER id_lote;
	END IF;

	IF NOT EXISTS (
		SELECT 1 FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_ventas'
			AND COLUMN_NAME = 'cantidad_base'
	) THEN
		ALTER TABLE detalle_ventas
			ADD COLUMN cantidad_base BIGINT UNSIGNED NULL AFTER cantidad;
	END IF;

	IF NOT EXISTS (
		SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
		WHERE CONSTRAINT_SCHEMA = DATABASE()
			AND TABLE_NAME = 'detalle_compras'
			AND CONSTRAINT_NAME = 'fk_detalle_presentacion_compra'
	) THEN
		ALTER TABLE detalle_compras
			ADD CONSTRAINT fk_detalle_presentacion_compra
			FOREIGN KEY (id_producto_presentacion)
			REFERENCES producto_presentaciones(id_producto_presentacion)
			ON UPDATE CASCADE ON DELETE SET NULL;
	END IF;

	IF NOT EXISTS (
		SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
		WHERE CONSTRAINT_SCHEMA = DATABASE()
			AND TABLE_NAME = 'detalle_ventas'
			AND CONSTRAINT_NAME = 'fk_detalle_presentacion_venta'
	) THEN
		ALTER TABLE detalle_ventas
			ADD CONSTRAINT fk_detalle_presentacion_venta
			FOREIGN KEY (id_producto_presentacion)
			REFERENCES producto_presentaciones(id_producto_presentacion)
			ON UPDATE CASCADE ON DELETE SET NULL;
	END IF;
END$$
CALL migrar_columnas_presentaciones()$$
DROP PROCEDURE migrar_columnas_presentaciones$$
DELIMITER ;

UPDATE detalle_compras SET cantidad_base = cantidad WHERE cantidad_base IS NULL;
UPDATE detalle_ventas SET cantidad_base = cantidad WHERE cantidad_base IS NULL;

DELIMITER $$
DROP TRIGGER IF EXISTS trg_entrada_inventario$$
CREATE TRIGGER trg_entrada_inventario
AFTER INSERT ON detalle_compras
FOR EACH ROW
BEGIN
	UPDATE lotes
	SET cantidad = cantidad + COALESCE(NEW.cantidad_base, NEW.cantidad)
	WHERE id_lote = NEW.id_lote;
END$$
DROP TRIGGER IF EXISTS trg_salida_inventario$$
DELIMITER ;

CREATE OR REPLACE VIEW vista_reporte_ventas AS
SELECT v.id_venta, v.fecha_venta, u.nombre AS usuario, v.metodo_pago,
	   p.codigo, p.nombre AS producto, cp.nombre AS presentacion,
	   dv.cantidad, dv.cantidad_base, dv.precio_unitario, dv.subtotal
FROM ventas v
INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
INNER JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
INNER JOIN productos p ON dv.id_producto = p.id_producto
LEFT JOIN producto_presentaciones pp ON pp.id_producto_presentacion = dv.id_producto_presentacion
LEFT JOIN catalogo_presentaciones cp ON cp.id_catalogo_presentacion = pp.id_catalogo_presentacion;
