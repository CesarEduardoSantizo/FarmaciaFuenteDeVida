-- ============================================================
-- BASE DE DATOS
-- FARMACIA FUENTE DE VIDA
-- ============================================================

CREATE DATABASE IF NOT EXISTS farmacia_fuente_vida
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE farmacia_fuente_vida;

CREATE TABLE IF NOT EXISTS roles (
	nombre VARCHAR(30) NOT NULL PRIMARY KEY,
	creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO roles (nombre) VALUES ('Administrador'), ('Vendedor');

CREATE TABLE IF NOT EXISTS usuarios (
	id_usuario INT AUTO_INCREMENT PRIMARY KEY,
	nombre VARCHAR(100) NOT NULL,
	usuario VARCHAR(50) NOT NULL UNIQUE,
	password VARCHAR(255) NOT NULL,
	correo VARCHAR(150),
	rol VARCHAR(30) NOT NULL DEFAULT 'Administrador',
	estado BOOLEAN NOT NULL DEFAULT TRUE,
	fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol)
		REFERENCES roles(nombre)
		ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS permisos_modulos (
	rol VARCHAR(30) NOT NULL,
	modulo VARCHAR(50) NOT NULL,
	puede_acceder BOOLEAN NOT NULL DEFAULT FALSE,
	actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (rol, modulo)
);

CREATE TABLE IF NOT EXISTS alertas_atendidas (
	tipo VARCHAR(30) NOT NULL,
	referencia INT NOT NULL,
	atendida_por INT NOT NULL,
	atendida_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (tipo, referencia),
	CONSTRAINT fk_alerta_usuario FOREIGN KEY (atendida_por)
		REFERENCES usuarios(id_usuario)
		ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS categorias (
	id_categoria INT AUTO_INCREMENT PRIMARY KEY,
	nombre VARCHAR(100) NOT NULL UNIQUE,
	descripcion VARCHAR(255),
	estado BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS proveedores (
	id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
	nombre VARCHAR(150) NOT NULL,
	contacto VARCHAR(150),
	nit VARCHAR(30),
	telefono VARCHAR(20),
	correo VARCHAR(100),
	direccion VARCHAR(200),
	estado BOOLEAN NOT NULL DEFAULT TRUE,
	fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clientes (
	id_cliente INT AUTO_INCREMENT PRIMARY KEY,
	nombre VARCHAR(150) NOT NULL,
	nit VARCHAR(30),
	telefono VARCHAR(20),
	correo VARCHAR(100),
	direccion VARCHAR(200),
	estado BOOLEAN NOT NULL DEFAULT TRUE,
	fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS configuracion (
	clave VARCHAR(100) PRIMARY KEY,
	valor TEXT NOT NULL,
	actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS catalogo_presentaciones (
	id_catalogo_presentacion INT AUTO_INCREMENT PRIMARY KEY,
	nombre VARCHAR(100) NOT NULL UNIQUE,
	nivel SMALLINT UNSIGNED NOT NULL,
	estado BOOLEAN NOT NULL DEFAULT TRUE,
	fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT chk_presentacion_nivel CHECK (nivel >= 1)
);

CREATE TABLE IF NOT EXISTS productos (
	id_producto INT AUTO_INCREMENT PRIMARY KEY,
	codigo VARCHAR(50) NOT NULL UNIQUE,
	nombre VARCHAR(150) NOT NULL,
	descripcion VARCHAR(255),
	principio_activo VARCHAR(150),
	presentacion VARCHAR(100),
	id_categoria INT NOT NULL,
	precio_venta DECIMAL(10,2) NOT NULL,
	stock_minimo INT NOT NULL DEFAULT 5,
	estado BOOLEAN NOT NULL DEFAULT TRUE,
	fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria)
		REFERENCES categorias(id_categoria)
		ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS producto_presentaciones (
	id_producto_presentacion INT AUTO_INCREMENT PRIMARY KEY,
	id_producto INT NOT NULL,
	id_catalogo_presentacion INT NOT NULL,
	unidades_base BIGINT UNSIGNED NOT NULL,
	precio_venta DECIMAL(10,2) NOT NULL,
	estado BOOLEAN NOT NULL DEFAULT TRUE,
	UNIQUE KEY uk_producto_presentacion (id_producto, id_catalogo_presentacion),
	CONSTRAINT chk_presentacion_unidades CHECK (unidades_base > 0),
	CONSTRAINT fk_presentacion_producto FOREIGN KEY (id_producto)
		REFERENCES productos(id_producto)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT fk_presentacion_catalogo FOREIGN KEY (id_catalogo_presentacion)
		REFERENCES catalogo_presentaciones(id_catalogo_presentacion)
		ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS lotes (
	id_lote INT AUTO_INCREMENT PRIMARY KEY,
	id_producto INT NOT NULL,
	numero_lote VARCHAR(50) NOT NULL,
	fecha_vencimiento DATE NOT NULL,
	cantidad BIGINT UNSIGNED NOT NULL DEFAULT 0,
	costo_unitario DECIMAL(10,2) NOT NULL,
	estado BOOLEAN NOT NULL DEFAULT TRUE,
	CONSTRAINT fk_lote_producto FOREIGN KEY (id_producto)
		REFERENCES productos(id_producto)
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT uk_producto_lote UNIQUE (id_producto, numero_lote)
);

CREATE TABLE IF NOT EXISTS compras (
	id_compra INT AUTO_INCREMENT PRIMARY KEY,
	id_proveedor INT NOT NULL,
	id_usuario INT NOT NULL,
	fecha_compra DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	numero_factura VARCHAR(50),
	total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	estado VARCHAR(20) NOT NULL DEFAULT 'Completada',
	CONSTRAINT fk_compra_proveedor FOREIGN KEY (id_proveedor)
		REFERENCES proveedores(id_proveedor)
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT fk_compra_usuario FOREIGN KEY (id_usuario)
		REFERENCES usuarios(id_usuario)
		ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS detalle_compras (
	id_detalle_compra INT AUTO_INCREMENT PRIMARY KEY,
	id_compra INT NOT NULL,
	id_producto INT NOT NULL,
	id_lote INT NOT NULL,
	id_producto_presentacion INT NULL,
	cantidad INT NOT NULL,
	cantidad_base BIGINT UNSIGNED NULL,
	costo_unitario DECIMAL(10,2) NOT NULL,
	subtotal DECIMAL(10,2) GENERATED ALWAYS AS (cantidad * costo_unitario) STORED,
	CONSTRAINT fk_detalle_compra FOREIGN KEY (id_compra)
		REFERENCES compras(id_compra)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT fk_detalle_producto_compra FOREIGN KEY (id_producto)
		REFERENCES productos(id_producto)
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT fk_detalle_lote_compra FOREIGN KEY (id_lote)
		REFERENCES lotes(id_lote)
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT fk_detalle_presentacion_compra FOREIGN KEY (id_producto_presentacion)
		REFERENCES producto_presentaciones(id_producto_presentacion)
		ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS ventas (
	id_venta INT AUTO_INCREMENT PRIMARY KEY,
	id_usuario INT NOT NULL,
	id_cliente INT NULL,
	fecha_venta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
	metodo_pago VARCHAR(30) NOT NULL DEFAULT 'Efectivo',
	estado VARCHAR(20) NOT NULL DEFAULT 'Completada',
	CONSTRAINT fk_venta_usuario FOREIGN KEY (id_usuario)
		REFERENCES usuarios(id_usuario)
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT fk_venta_cliente FOREIGN KEY (id_cliente)
		REFERENCES clientes(id_cliente)
		ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS detalle_ventas (
	id_detalle_venta INT AUTO_INCREMENT PRIMARY KEY,
	id_venta INT NOT NULL,
	id_producto INT NOT NULL,
	id_lote INT NOT NULL,
	id_producto_presentacion INT NULL,
	cantidad INT NOT NULL,
	cantidad_base BIGINT UNSIGNED NULL,
	precio_unitario DECIMAL(10,2) NOT NULL,
	subtotal DECIMAL(10,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
	CONSTRAINT fk_detalle_venta FOREIGN KEY (id_venta)
		REFERENCES ventas(id_venta)
		ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT fk_detalle_producto_venta FOREIGN KEY (id_producto)
		REFERENCES productos(id_producto)
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT fk_detalle_lote_venta FOREIGN KEY (id_lote)
		REFERENCES lotes(id_lote)
		ON UPDATE CASCADE ON DELETE RESTRICT,
	CONSTRAINT fk_detalle_presentacion_venta FOREIGN KEY (id_producto_presentacion)
		REFERENCES producto_presentaciones(id_producto_presentacion)
		ON UPDATE CASCADE ON DELETE SET NULL
);

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
DROP TRIGGER IF EXISTS trg_entrada_inventario$$
CREATE TRIGGER trg_entrada_inventario
AFTER INSERT ON detalle_compras
FOR EACH ROW
BEGIN
	UPDATE lotes SET cantidad = cantidad + COALESCE(NEW.cantidad_base, NEW.cantidad)
	WHERE id_lote = NEW.id_lote;
END$$
DROP TRIGGER IF EXISTS trg_salida_inventario$$
-- Las salidas se aplican en la transacción de ventas y se asignan entre lotes vigentes.
DELIMITER ;

CREATE OR REPLACE VIEW vista_inventario AS
SELECT p.id_producto, p.codigo, p.nombre, c.nombre AS categoria,
	   p.presentacion, p.precio_venta, p.stock_minimo,
	   COALESCE(SUM(l.cantidad), 0) AS stock_actual
FROM productos p
INNER JOIN categorias c ON p.id_categoria = c.id_categoria
LEFT JOIN lotes l ON p.id_producto = l.id_producto AND l.estado = TRUE
GROUP BY p.id_producto, p.codigo, p.nombre, c.nombre, p.presentacion,
		 p.precio_venta, p.stock_minimo;

CREATE OR REPLACE VIEW vista_stock_bajo AS
SELECT id_producto, codigo, nombre, categoria, stock_actual, stock_minimo
FROM vista_inventario
WHERE stock_actual <= stock_minimo;

CREATE OR REPLACE VIEW vista_productos_por_vencer AS
SELECT p.id_producto, p.codigo, p.nombre, l.numero_lote, l.fecha_vencimiento,
	   l.cantidad, DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_para_vencer
FROM productos p
INNER JOIN lotes l ON p.id_producto = l.id_producto
WHERE l.estado = TRUE AND l.fecha_vencimiento >= CURDATE()
  AND l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY);

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

CREATE OR REPLACE VIEW vista_ventas_diarias AS
SELECT DATE(fecha_venta) AS fecha, COUNT(id_venta) AS cantidad_ventas,
	   SUM(total) AS total_vendido
FROM ventas WHERE estado = 'Completada'
GROUP BY DATE(fecha_venta)
ORDER BY fecha DESC;

CREATE OR REPLACE VIEW vista_ventas_semanales AS
SELECT YEAR(fecha_venta) AS anio, WEEK(fecha_venta, 1) AS semana,
	   COUNT(id_venta) AS cantidad_ventas, SUM(total) AS total_vendido
FROM ventas WHERE estado = 'Completada'
GROUP BY YEAR(fecha_venta), WEEK(fecha_venta, 1)
ORDER BY anio DESC, semana DESC;

INSERT IGNORE INTO categorias (nombre, descripcion) VALUES
('Analgésicos', 'Medicamentos utilizados para aliviar el dolor'),
('Antibióticos', 'Medicamentos utilizados para tratar infecciones'),
('Antigripales', 'Medicamentos para síntomas de gripe y resfriado'),
('Vitaminas', 'Suplementos y vitaminas'),
('Otros', 'Otros productos farmacéuticos');

INSERT IGNORE INTO catalogo_presentaciones (nombre, nivel) VALUES
('Unidad', 1),
('Blíster', 2),
('Cajita', 3),
('Caja', 4);

-- Catálogo de medicamentos FDV-001 a FDV-172.
SET @id_categoria_otros = (SELECT id_categoria FROM categorias WHERE nombre = 'Otros' LIMIT 1);

START TRANSACTION;

-- 1. Metformina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-001', 'Metformina', 'Dosis/concentración: 1000', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 2. Senosidos
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-002', 'Senosidos', 'Nota: Laxante', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 3. Craspovidona
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-003', 'Craspovidona', 'Nota: Antidiarreico', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 4. Codeypina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-004', 'Codeypina', 'Nota: Tos', NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 5. Codeypina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-005', 'Codeypina', 'Nota: Tos', NULL, 'Pastilla',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 6. Ketorolaco
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-006', 'Ketorolaco', 'Nota: Analgésico', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 7. Bromuro de otilonio
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-007', 'Bromuro de otilonio', 'Nota: Problemas de colon', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 8. Ibuprofen + metocarbamol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-008', 'Ibuprofen + metocarbamol', 'Nota: Analgésico', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 9. Spasmo clor
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-009', 'Spasmo clor', 'Nota: Analgésico', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 10. Lanzoprazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-010', 'Lanzoprazol', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 11. Loratadina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-011', 'Loratadina', 'Nota: Alergia', NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 12. Loratadina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-012', 'Loratadina', 'Nota: Alergia', NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 13. Ojo de águila
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-013', 'Ojo de águila', NULL, NULL, 'Colirio',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 14. Piroxican
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-014', 'Piroxican', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 15. Piroxican
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-015', 'Piroxican', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 16. Recolector
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-016', 'Recolector', NULL, NULL, 'Heces',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 17. Recolector
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-017', 'Recolector', NULL, NULL, 'Orina',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 18. Azitromicina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-018', 'Azitromicina', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 19. Azitromicina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-019', 'Azitromicina', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 20. Vitaflenaco
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-020', 'Vitaflenaco', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 21. Ciprofloxacina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-021', 'Ciprofloxacina', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 22. Esomeprazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-022', 'Esomeprazol', NULL, NULL, 'Cápsula',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 23. Levecilin AC
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-023', 'Levecilin AC', 'Dosis/concentración: 120 ml', NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 24. Ibesartán + Hidroclorotiazida
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-024', 'Ibesartán + Hidroclorotiazida', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 25. Aciclovir
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-025', 'Aciclovir', NULL, NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 26. Aciclovir
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-026', 'Aciclovir', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 27. Ibuprofeno
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-027', 'Ibuprofeno', 'Dosis/concentración: 800', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 28. Ibuprofeno
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-028', 'Ibuprofeno', 'Dosis/concentración: 600', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 29. Ibuprofeno
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-029', 'Ibuprofeno', 'Dosis/concentración: 400', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 30. Ibuprofeno
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-030', 'Ibuprofeno', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 31. Sulfabac
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-031', 'Sulfabac', 'Nota: Trimetoprim', NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 32. Clorfeniramina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-032', 'Clorfeniramina', NULL, NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 33. Clorfeniramina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-033', 'Clorfeniramina', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 34. Nitazel
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-034', 'Nitazel', 'Nota: Desparasitante', NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 35. Exiflem
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-035', 'Exiflem', 'Nota: Bromexina', NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 36. Nitazel
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-036', 'Nitazel', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 37. Metronidazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-037', 'Metronidazol', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 38. Metronidazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-038', 'Metronidazol', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 39. Virogrip día
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-039', 'Virogrip día', NULL, NULL, 'Gel',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 40. Virogrip noche
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-040', 'Virogrip noche', NULL, NULL, 'Gel',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 41. Virogrip día
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-041', 'Virogrip día', NULL, NULL, 'Té',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 42. Virogrip noche
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-042', 'Virogrip noche', NULL, NULL, 'Té',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 43. Cardio vital
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-043', 'Cardio vital', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 44. Citrasel
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-044', 'Citrasel', NULL, NULL, 'Spray',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 45. Citrasel
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-045', 'Citrasel', NULL, NULL, 'Crema',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 46. Vitaplex
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-046', 'Vitaplex', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 47. Dolovitaplex
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-047', 'Dolovitaplex', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 48. Levusol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-048', 'Levusol', NULL, NULL, 'Suero',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 49. Hidrocortizona
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-049', 'Hidrocortizona', 'Dosis/concentración: 1%', NULL, 'Crema',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 50. Gencloben
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-050', 'Gencloben', NULL, NULL, 'Crema',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 51. Nitazoxanida
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-051', 'Nitazoxanida', NULL, NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 52. Clotrimazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-052', 'Clotrimazol', NULL, NULL, 'Crema',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 53. Omega 3
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-053', 'Omega 3', 'Dosis/concentración: 1,000', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 54. Clotriplex
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-054', 'Clotriplex', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 55. Ácido fólico
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-055', 'Ácido fólico', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 56. Tramadol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-056', 'Tramadol', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 57. Amoxicilina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-057', 'Amoxicilina', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 58. Amoxicilina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-058', 'Amoxicilina', NULL, NULL, 'Cápsula',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 59. Jeringa
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-059', 'Jeringa', 'Dosis/concentración: 10 ml', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 60. Jeringa
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-060', 'Jeringa', 'Dosis/concentración: 5 ml', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 61. Jeringa
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-061', 'Jeringa', 'Dosis/concentración: 3 ml', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 62. Ceftriaxona
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-062', 'Ceftriaxona', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 63. Acetaminofen
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-063', 'Acetaminofen', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 64. Acetaminofen
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-064', 'Acetaminofen', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 65. Ex flu
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-065', 'Ex flu', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 66. Ex flu
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-066', 'Ex flu', NULL, NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 67. Olmesartan + hidroclorotiazida
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-067', 'Olmesartan + hidroclorotiazida', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 68. Simeticona
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-068', 'Simeticona', NULL, NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 69. Simeticona
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-069', 'Simeticona', NULL, NULL, 'Gotas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 70. Diclofenaco potásico
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-070', 'Diclofenaco potásico', 'Dosis/concentración: 50 mg', NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 71. Diclofenaco potásico
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-071', 'Diclofenaco potásico', 'Dosis/concentración: 100 mg', NULL, 'Gel',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 72. Diclofenaco potásico
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-072', 'Diclofenaco potásico', NULL, NULL, 'Tópico',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 73. Neofebrina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-073', 'Neofebrina', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 74. Neofebrina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-074', 'Neofebrina', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 75. Lágrimas oculares
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-075', 'Lágrimas oculares', NULL, NULL, 'Gotas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 76. Cloranfenicol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-076', 'Cloranfenicol', NULL, NULL, 'Gotas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 77. Cloranfenicol + dexa + nafasolin
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-077', 'Cloranfenicol + dexa + nafasolin', NULL, NULL, 'Gotas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 78. Ciprofloxacina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-078', 'Ciprofloxacina', NULL, NULL, 'Gotas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 79. Nafasolina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-079', 'Nafasolina', NULL, NULL, 'Gotas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 80. Otik
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-080', 'Otik', NULL, NULL, 'Gotas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 81. Relax plus
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-081', 'Relax plus', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 82. Agua oxigenada
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-082', 'Agua oxigenada', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 83. Ex flu tos
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-083', 'Ex flu tos', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 84. Ex flu
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-084', 'Ex flu', 'Nota: Multisíntomas', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 85. Viro grip
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-085', 'Viro grip', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 86. Naproxeno
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-086', 'Naproxeno', NULL, NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 87. Saluprim
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-087', 'Saluprim', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 88. Saluprim
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-088', 'Saluprim', NULL, NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 89. Aguja
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-089', 'Aguja', 'Dosis/concentración: N. 24', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 90. Aguja
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-090', 'Aguja', 'Dosis/concentración: N. 23', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 91. Aguja
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-091', 'Aguja', 'Dosis/concentración: N. 22', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 92. Aguja
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-092', 'Aguja', 'Dosis/concentración: N. 21', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 93. Nistatina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-093', 'Nistatina', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 94. Zonitone
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-094', 'Zonitone', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 95. Zonitone
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-095', 'Zonitone', NULL, NULL, 'Caramelos',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 96. Multiplex prenatal
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-096', 'Multiplex prenatal', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 97. Nervisel
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-097', 'Nervisel', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 98. Dolonervisel
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-098', 'Dolonervisel', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 99. Nervisel
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-099', 'Nervisel', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 100. Dolonervisel
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-100', 'Dolonervisel', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 101. Dolovitanervo
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-101', 'Dolovitanervo', NULL, NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 102. Dolovitanervo
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-102', 'Dolovitanervo', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 103. Vitanervo
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-103', 'Vitanervo', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 104. Sanirenal
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-104', 'Sanirenal', NULL, NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 105. Guayatos
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-105', 'Guayatos', NULL, NULL, 'Jarabes',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 106. Trimetose
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-106', 'Trimetose', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 107. Trilox
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-107', 'Trilox', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 108. Glicitos
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-108', 'Glicitos', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 109. Fluconazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-109', 'Fluconazol', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 110. Ibesartan
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-110', 'Ibesartan', 'Dosis/concentración: 150', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 111. Ibesartan
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-111', 'Ibesartan', 'Dosis/concentración: 300', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 112. Unal
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-112', 'Unal', 'Nota: Anticonceptivo', NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 113. Complejo B
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-113', 'Complejo B', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 114. Kalivia
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-114', 'Kalivia', 'Nota: Analgésico', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 115. Tresbiot
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-115', 'Tresbiot', NULL, NULL, 'Crema',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 116. Valsartan
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-116', 'Valsartan', 'Dosis/concentración: 160 mg', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 117. Betoapan
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-117', 'Betoapan', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 118. Vitamina C
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-118', 'Vitamina C', 'Dosis/concentración: 500 mg', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 119. Desketoprofeno
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-119', 'Desketoprofeno', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 120. Diclofenaco
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-120', 'Diclofenaco', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 121. Meloxican
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-121', 'Meloxican', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 122. Meloxican
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-122', 'Meloxican', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 123. Diclofenaco
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-123', 'Diclofenaco', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 124. Levadura de cerveza
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-124', 'Levadura de cerveza', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 125. Secnidazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-125', 'Secnidazol', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 126. Secnidazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-126', 'Secnidazol', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 127. Bicarbonato
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-127', 'Bicarbonato', NULL, NULL, 'Bolsita',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 128. Bicarbonato
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-128', 'Bicarbonato', 'Dosis/concentración: 1/2 libra', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 129. Bicarbonato
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-129', 'Bicarbonato', 'Dosis/concentración: 1 libra', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 130. Polvo óxido de zinc
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-130', 'Polvo óxido de zinc', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 131. Albendazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-131', 'Albendazol', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 132. Albendazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-132', 'Albendazol', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 133. Tosiflem
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-133', 'Tosiflem', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 134. Dipirona
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-134', 'Dipirona', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 135. Dexametazona
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-135', 'Dexametazona', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 136. Diclofenaco + neurotropas
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-136', 'Diclofenaco + neurotropas', NULL, NULL, 'Cápsula',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 137. Vive sensitivo
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-137', 'Vive sensitivo', NULL, NULL, 'Preservativo',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 138. Metformina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-138', 'Metformina', 'Dosis/concentración: 500', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 139. Vitanervo
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-139', 'Vitanervo', NULL, NULL, 'Pastilla',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 140. Sostengo
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-140', 'Sostengo', 'Nota: Sildenafil', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 141. Tossadios
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-141', 'Tossadios', NULL, NULL, 'Caramelo',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 142. Metformina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-142', 'Metformina', 'Dosis/concentración: 850; Nota: Combinada', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 143. Salbutamol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-143', 'Salbutamol', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 144. Salbutamol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-144', 'Salbutamol', NULL, NULL, 'Inhalador',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 145. Salbutamol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-145', 'Salbutamol', NULL, NULL, 'Tabletas',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 146. Tetraciclina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-146', 'Tetraciclina', 'Dosis/concentración: 500 mg', NULL, 'Cápsula',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 147. Fosfolipidos
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-147', 'Fosfolipidos', NULL, NULL, 'Gel',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 148. Flufin alergia
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-148', 'Flufin alergia', NULL, NULL, 'Gel',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 149. Dolodent
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-149', 'Dolodent', 'Nota: Anestecia', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 150. Napioliv
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-150', 'Napioliv', 'Dosis/concentración: 550 mg', NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 151. Complejo B plus
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-151', 'Complejo B plus', NULL, NULL, 'Cápsula gel',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 152. Clotrimazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-152', 'Clotrimazol', 'Dosis/concentración: 2%', NULL, 'Crema',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 153. Cuerpo amarillo
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-153', 'Cuerpo amarillo', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 154. Losartan + hidroclorotiazida
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-154', 'Losartan + hidroclorotiazida', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 155. Ketoconazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-155', 'Ketoconazol', NULL, NULL, 'Crema',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 156. Loratadina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-156', 'Loratadina', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 157. Loratadina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-157', 'Loratadina', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 158. Desloratadina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-158', 'Desloratadina', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 159. Gingo biloba
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-159', 'Gingo biloba', NULL, NULL, 'Cápsula gel',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 160. Dolfix
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-160', 'Dolfix', NULL, NULL, 'Cápsula',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 161. Esomeprazol
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-161', 'Esomeprazol', NULL, NULL, 'Cápsula',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 162. Enerhit
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-162', 'Enerhit', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 163. Prenatales plus
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-163', 'Prenatales plus', NULL, NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 164. Ferridoce
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-164', 'Ferridoce', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 165. Ferridoce
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-165', 'Ferridoce', NULL, NULL, 'Jarabe',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 166. Metrizol V
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-166', 'Metrizol V', NULL, NULL, 'Óvulos',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 167. Cefadroxilo
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-167', 'Cefadroxilo', NULL, NULL, 'Tableta',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 168. Cefadroxilo
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-168', 'Cefadroxilo', NULL, NULL, 'Suspensión',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 169. Gabapentina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-169', 'Gabapentina', 'Dosis/concentración: 400 mg', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 170. Gabapentina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-170', 'Gabapentina', 'Dosis/concentración: 300 mg', NULL, NULL,
     @id_categoria_otros, 0.00, 5, TRUE);

-- 171. Lidocaina
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-171', 'Lidocaina', NULL, NULL, 'Inyectado',
     @id_categoria_otros, 0.00, 5, TRUE);

-- 172. Seven "S"
INSERT INTO productos
    (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo, estado)
VALUES
    ('FDV-172', 'Seven "S"', NULL, NULL, 'Cápsulas',
     @id_categoria_otros, 0.00, 5, TRUE);

COMMIT;

INSERT IGNORE INTO producto_presentaciones
	(id_producto, id_catalogo_presentacion, unidades_base, precio_venta)
SELECT p.id_producto, cp.id_catalogo_presentacion, 1, p.precio_venta
FROM productos p
INNER JOIN catalogo_presentaciones cp ON cp.nombre = 'Unidad';

INSERT INTO proveedores (nombre, nit, telefono, correo, direccion)
SELECT 'Proveedor Farmacéutico 1', '123456-7', '5555-1111',
	   'proveedor1@email.com', 'Ciudad de Guatemala'
WHERE NOT EXISTS (SELECT 1 FROM proveedores WHERE nit = '123456-7');

INSERT INTO usuarios (nombre, usuario, password, correo, rol, estado)
SELECT 'Administrador de Farmacia', 'admin_fuente_vida', '$2y$10$uWgzGVgiy1rAbN4gUWWsbOE4HPwAxbSYmwPUAbIs6w7T28BrAgg0e', NULL, 'Administrador', TRUE
WHERE NOT EXISTS (
	SELECT 1 FROM usuarios WHERE usuario = 'admin_fuente_vida'
);

INSERT IGNORE INTO roles (nombre)
SELECT DISTINCT rol FROM usuarios WHERE rol IS NOT NULL AND rol <> '';

INSERT IGNORE INTO roles (nombre) VALUES ('Administrador'), ('Vendedor');

INSERT IGNORE INTO permisos_modulos (rol, modulo, puede_acceder)
SELECT roles.nombre, modulos.modulo,
	   CASE WHEN roles.nombre = 'Administrador' THEN TRUE
	        WHEN roles.nombre = 'Vendedor' AND modulos.modulo <> 'usuarios' THEN TRUE
	        ELSE FALSE END
FROM roles
CROSS JOIN (
	SELECT 'inicio' AS modulo
	UNION ALL SELECT 'inventario'
	UNION ALL SELECT 'productos'
	UNION ALL SELECT 'catalogo_presentaciones'
	UNION ALL SELECT 'ventas'
	UNION ALL SELECT 'clientes'
	UNION ALL SELECT 'compras'
	UNION ALL SELECT 'proveedores'
	UNION ALL SELECT 'reportes'
	UNION ALL SELECT 'alertas'
	UNION ALL SELECT 'configuracion'
	UNION ALL SELECT 'usuarios'
) AS modulos;
