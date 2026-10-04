-- ============================================================
-- BASE DE DATOS
-- FARMACIA FUENTE DE VIDA
-- ============================================================

CREATE DATABASE IF NOT EXISTS farmacia_fuente_vida
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE farmacia_fuente_vida;



CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    correo VARCHAR(150),
    rol VARCHAR(30) NOT NULL DEFAULT 'Administrador',
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ============================================================
-- 2. TABLA: CATEGORIAS
-- Clasificación de productos
-- ============================================================

CREATE TABLE IF NOT EXISTS categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    estado BOOLEAN NOT NULL DEFAULT TRUE
);


-- ============================================================
-- 3. TABLA: PROVEEDORES
-- REQ4 - Registro y gestión de proveedores
-- ============================================================

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


-- ============================================================
-- TABLA: CLIENTES
-- ============================================================

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


-- ============================================================
-- TABLA: CONFIGURACION
-- Preferencias generales del sistema
-- ============================================================

CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(100) PRIMARY KEY,
    valor TEXT NOT NULL,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- Seguimiento de atención de alertas calculadas desde inventario
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

    CONSTRAINT fk_producto_categoria
        FOREIGN KEY (id_categoria)
        REFERENCES categorias(id_categoria)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);


-- ============================================================
-- 5. TABLA: LOTES
-- Control de existencias y fechas de vencimiento
-- ============================================================

CREATE TABLE IF NOT EXISTS lotes (
    id_lote INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    numero_lote VARCHAR(50) NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    cantidad INT NOT NULL DEFAULT 0,
    costo_unitario DECIMAL(10,2) NOT NULL,
    estado BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT fk_lote_producto
        FOREIGN KEY (id_producto)
        REFERENCES productos(id_producto)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT uk_producto_lote
        UNIQUE (id_producto, numero_lote)
);


-- ============================================================
-- 6. TABLA: COMPRAS
-- REQ3 - Registro de compras
-- ============================================================

CREATE TABLE IF NOT EXISTS compras (
    id_compra INT AUTO_INCREMENT PRIMARY KEY,
    id_proveedor INT NOT NULL,
    id_usuario INT NOT NULL,
    fecha_compra DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    numero_factura VARCHAR(50),
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estado VARCHAR(20) NOT NULL DEFAULT 'Completada',

    CONSTRAINT fk_compra_proveedor
        FOREIGN KEY (id_proveedor)
        REFERENCES proveedores(id_proveedor)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_compra_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);


-- ============================================================
-- 7. TABLA: DETALLE_COMPRAS
-- Productos que forman parte de cada compra
-- ============================================================

CREATE TABLE IF NOT EXISTS detalle_compras (
    id_detalle_compra INT AUTO_INCREMENT PRIMARY KEY,
    id_compra INT NOT NULL,
    id_producto INT NOT NULL,
    id_lote INT NOT NULL,
    cantidad INT NOT NULL,
    costo_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) GENERATED ALWAYS AS
        (cantidad * costo_unitario) STORED,

    CONSTRAINT fk_detalle_compra
        FOREIGN KEY (id_compra)
        REFERENCES compras(id_compra)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_detalle_producto_compra
        FOREIGN KEY (id_producto)
        REFERENCES productos(id_producto)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_detalle_lote_compra
        FOREIGN KEY (id_lote)
        REFERENCES lotes(id_lote)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);


-- ============================================================
-- 8. TABLA: VENTAS
-- REQ2 - Registro de ventas
-- ============================================================

CREATE TABLE IF NOT EXISTS ventas (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_cliente INT NULL,
    fecha_venta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    metodo_pago VARCHAR(30) NOT NULL DEFAULT 'Efectivo',
    estado VARCHAR(20) NOT NULL DEFAULT 'Completada',

    CONSTRAINT fk_venta_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_venta_cliente
        FOREIGN KEY (id_cliente)
        REFERENCES clientes(id_cliente)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);


-- ============================================================
-- 9. TABLA: DETALLE_VENTAS
-- Productos vendidos en cada venta
-- ============================================================

CREATE TABLE IF NOT EXISTS detalle_ventas (
    id_detalle_venta INT AUTO_INCREMENT PRIMARY KEY,
    id_venta INT NOT NULL,
    id_producto INT NOT NULL,
    id_lote INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) GENERATED ALWAYS AS
        (cantidad * precio_unitario) STORED,

    CONSTRAINT fk_detalle_venta
        FOREIGN KEY (id_venta)
        REFERENCES ventas(id_venta)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_detalle_producto_venta
        FOREIGN KEY (id_producto)
        REFERENCES productos(id_producto)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_detalle_lote_venta
        FOREIGN KEY (id_lote)
        REFERENCES lotes(id_lote)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);


-- ============================================================
-- 10. TRIGGER: ACTUALIZAR INVENTARIO AL COMPRAR
-- ============================================================

DELIMITER $$

DROP TRIGGER IF EXISTS trg_entrada_inventario$$
CREATE TRIGGER trg_entrada_inventario
AFTER INSERT ON detalle_compras
FOR EACH ROW
BEGIN
    UPDATE lotes
    SET cantidad = cantidad + NEW.cantidad
    WHERE id_lote = NEW.id_lote;
END$$

DELIMITER ;


-- ============================================================
-- 11. TRIGGER: ACTUALIZAR INVENTARIO AL VENDER
-- ============================================================

DELIMITER $$

DROP TRIGGER IF EXISTS trg_salida_inventario$$
CREATE TRIGGER trg_salida_inventario
AFTER INSERT ON detalle_ventas
FOR EACH ROW
BEGIN
    UPDATE lotes
    SET cantidad = cantidad - NEW.cantidad
    WHERE id_lote = NEW.id_lote;
END$$

DELIMITER ;


-- ============================================================
-- 12. VISTA: INVENTARIO ACTUAL
-- Muestra las existencias de cada producto
-- ============================================================

CREATE OR REPLACE VIEW vista_inventario AS
SELECT
    p.id_producto,
    p.codigo,
    p.nombre,
    c.nombre AS categoria,
    p.presentacion,
    p.precio_venta,
    p.stock_minimo,
    COALESCE(SUM(l.cantidad), 0) AS stock_actual
FROM productos p
INNER JOIN categorias c
    ON p.id_categoria = c.id_categoria
LEFT JOIN lotes l
    ON p.id_producto = l.id_producto
    AND l.estado = TRUE
GROUP BY
    p.id_producto,
    p.codigo,
    p.nombre,
    c.nombre,
    p.presentacion,
    p.precio_venta,
    p.stock_minimo;


-- ============================================================
-- 13. VISTA: PRODUCTOS CON STOCK BAJO
-- REQ6 - Alertas de inventario
-- ============================================================

CREATE OR REPLACE VIEW vista_stock_bajo AS
SELECT
    id_producto,
    codigo,
    nombre,
    categoria,
    stock_actual,
    stock_minimo
FROM vista_inventario
WHERE stock_actual <= stock_minimo;


-- ============================================================
-- 14. VISTA: PRODUCTOS PRÓXIMOS A VENCER
-- REQ7 - Alertas de vencimiento
-- ============================================================

CREATE OR REPLACE VIEW vista_productos_por_vencer AS
SELECT
    p.id_producto,
    p.codigo,
    p.nombre,
    l.numero_lote,
    l.fecha_vencimiento,
    l.cantidad,
    DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_para_vencer
FROM productos p
INNER JOIN lotes l
    ON p.id_producto = l.id_producto
WHERE l.estado = TRUE
AND l.fecha_vencimiento >= CURDATE()
AND l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY);


-- ============================================================
-- 15. VISTA: REPORTE DE VENTAS
-- REQ8 - Reportes de ventas
-- ============================================================

CREATE OR REPLACE VIEW vista_reporte_ventas AS
SELECT
    v.id_venta,
    v.fecha_venta,
    u.nombre AS usuario,
    v.metodo_pago,
    p.codigo,
    p.nombre AS producto,
    dv.cantidad,
    dv.precio_unitario,
    dv.subtotal
FROM ventas v
INNER JOIN usuarios u
    ON v.id_usuario = u.id_usuario
INNER JOIN detalle_ventas dv
    ON v.id_venta = dv.id_venta
INNER JOIN productos p
    ON dv.id_producto = p.id_producto;


-- ============================================================
-- 16. VISTA: RESUMEN DE VENTAS DIARIAS
-- ============================================================

CREATE OR REPLACE VIEW vista_ventas_diarias AS
SELECT
    DATE(fecha_venta) AS fecha,
    COUNT(id_venta) AS cantidad_ventas,
    SUM(total) AS total_vendido
FROM ventas
WHERE estado = 'Completada'
GROUP BY DATE(fecha_venta)
ORDER BY fecha DESC;


-- ============================================================
-- 17. VISTA: RESUMEN DE VENTAS SEMANALES
-- ============================================================

CREATE OR REPLACE VIEW vista_ventas_semanales AS
SELECT
    YEAR(fecha_venta) AS anio,
    WEEK(fecha_venta, 1) AS semana,
    COUNT(id_venta) AS cantidad_ventas,
    SUM(total) AS total_vendido
FROM ventas
WHERE estado = 'Completada'
GROUP BY
    YEAR(fecha_venta),
    WEEK(fecha_venta, 1)
ORDER BY anio DESC, semana DESC;


-- ============================================================
-- 18. DATOS INICIALES
-- ============================================================

INSERT IGNORE INTO categorias (nombre, descripcion) VALUES
('Analgésicos', 'Medicamentos utilizados para aliviar el dolor'),
('Antibióticos', 'Medicamentos utilizados para tratar infecciones'),
('Antigripales', 'Medicamentos para síntomas de gripe y resfriado'),
('Vitaminas', 'Suplementos y vitaminas'),
('Otros', 'Otros productos farmacéuticos');

-- Catalogo recibido hasta FV0067; faltan FV0068 a FV0172.
SET @cat_otros = (SELECT id_categoria FROM categorias WHERE nombre = 'Otros' LIMIT 1);

INSERT IGNORE INTO productos
    (codigo, nombre, descripcion, presentacion, id_categoria, precio_venta)
VALUES
    ('FV0001', 'Metformina', NULL, '1000', @cat_otros, 0.00),
    ('FV0002', 'Senosidos', 'Laxante', NULL, @cat_otros, 0.00),
    ('FV0003', 'Craspovidona', 'Antidiarreico', NULL, @cat_otros, 0.00),
    ('FV0004', 'Codeypina', 'Tos', 'Jarabe', @cat_otros, 0.00),
    ('FV0005', 'Codeypina', 'Tos', 'Pastilla', @cat_otros, 0.00),
    ('FV0006', 'Ketorolaco', 'Analgésico', NULL, @cat_otros, 0.00),
    ('FV0007', 'Bromuro de otilonio', 'Problemas de colon', NULL, @cat_otros, 0.00),
    ('FV0008', 'Ibuprofeno + metocarbamol', 'Analgésico', NULL, @cat_otros, 0.00),
    ('FV0009', 'Spasmo clor', 'Analgésico', NULL, @cat_otros, 0.00),
    ('FV0010', 'Lanzoprazol', NULL, NULL, @cat_otros, 0.00),
    ('FV0011', 'Loratadina', 'Alergia', 'Tableta', @cat_otros, 0.00),
    ('FV0012', 'Loratadina', 'Alergia', 'Jarabe', @cat_otros, 0.00),
    ('FV0013', 'Ojo de águila', NULL, 'Colirio', @cat_otros, 0.00),
    ('FV0014', 'Piroxican', NULL, 'Tableta', @cat_otros, 0.00),
    ('FV0015', 'Piroxican', NULL, 'Inyectado', @cat_otros, 0.00),
    ('FV0016', 'Recolector de heces', NULL, NULL, @cat_otros, 0.00),
    ('FV0017', 'Recolector de orina', NULL, NULL, @cat_otros, 0.00),
    ('FV0018', 'Azitromicina', NULL, 'Tableta', @cat_otros, 0.00),
    ('FV0019', 'Azitromicina', NULL, 'Suspensión', @cat_otros, 0.00),
    ('FV0020', 'Vitaflenaco', NULL, NULL, @cat_otros, 0.00),
    ('FV0021', 'Ciprofloxacina', NULL, 'Tableta', @cat_otros, 0.00),
    ('FV0022', 'Esomeprazol', NULL, 'Cápsula', @cat_otros, 0.00),
    ('FV0023', 'Levecilin AC', NULL, '120 ml suspensión', @cat_otros, 0.00),
    ('FV0024', 'Ibesartán + hidroclorotiazida', NULL, NULL, @cat_otros, 0.00),
    ('FV0025', 'Aciclovir', NULL, 'Tabletas', @cat_otros, 0.00),
    ('FV0026', 'Aciclovir', NULL, 'Suspensión', @cat_otros, 0.00),
    ('FV0027', 'Ibuprofeno', NULL, '800', @cat_otros, 0.00),
    ('FV0028', 'Ibuprofeno', NULL, '600', @cat_otros, 0.00),
    ('FV0029', 'Ibuprofeno', NULL, '400', @cat_otros, 0.00),
    ('FV0030', 'Ibuprofeno', NULL, 'Jarabe', @cat_otros, 0.00),
    ('FV0031', 'Sulfabac', 'Trimetoprim', 'Suspensión', @cat_otros, 0.00),
    ('FV0032', 'Clorfeniramina', NULL, 'Tabletas', @cat_otros, 0.00),
    ('FV0033', 'Clorfeniramina', NULL, 'Jarabe', @cat_otros, 0.00),
    ('FV0034', 'Nitazel', 'Desparasitante', 'Tabletas', @cat_otros, 0.00),
    ('FV0035', 'Exiflem', 'Bromexina', 'Jarabe', @cat_otros, 0.00),
    ('FV0036', 'Nitazel', NULL, 'Suspensión', @cat_otros, 0.00),
    ('FV0037', 'Metronidazol', NULL, 'Suspensión', @cat_otros, 0.00),
    ('FV0038', 'Metronidazol', NULL, 'Tableta', @cat_otros, 0.00),
    ('FV0039', 'Virogrip', NULL, 'Día gel', @cat_otros, 0.00),
    ('FV0040', 'Virogrip', NULL, 'Noche gel', @cat_otros, 0.00),
    ('FV0041', 'Virogrip', NULL, 'Día té', @cat_otros, 0.00),
    ('FV0042', 'Virogrip', NULL, 'Noche té', @cat_otros, 0.00),
    ('FV0043', 'Cardio vital', NULL, NULL, @cat_otros, 0.00),
    ('FV0044', 'Citrasel', NULL, 'Spray', @cat_otros, 0.00),
    ('FV0045', 'Citrasel', NULL, 'Crema', @cat_otros, 0.00),
    ('FV0046', 'Vitaplex', NULL, 'Inyectado', @cat_otros, 0.00),
    ('FV0047', 'Dolovitaplex', NULL, 'Inyectado', @cat_otros, 0.00),
    ('FV0048', 'Levusol', NULL, 'Suero', @cat_otros, 0.00),
    ('FV0049', 'Hidrocortisona 1%', NULL, 'Crema', @cat_otros, 0.00),
    ('FV0050', 'Gencioben', NULL, 'Crema', @cat_otros, 0.00),
    ('FV0051', 'Nitazoxanida', NULL, 'Tabletas', @cat_otros, 0.00),
    ('FV0052', 'Clotrimazol', NULL, 'Crema', @cat_otros, 0.00),
    ('FV0053', 'Omega 3', NULL, '1000', @cat_otros, 0.00),
    ('FV0054', 'Clotriplex', NULL, NULL, @cat_otros, 0.00),
    ('FV0055', 'Ácido fólico', NULL, NULL, @cat_otros, 0.00),
    ('FV0056', 'Tramadol', NULL, NULL, @cat_otros, 0.00),
    ('FV0057', 'Amoxicilina', NULL, 'Suspensión', @cat_otros, 0.00),
    ('FV0058', 'Amoxicilina', NULL, 'Cápsula', @cat_otros, 0.00),
    ('FV0059', 'Jeringa', NULL, '10 ml', @cat_otros, 0.00),
    ('FV0060', 'Jeringa', NULL, '5 ml', @cat_otros, 0.00),
    ('FV0061', 'Jeringa', NULL, '3 ml', @cat_otros, 0.00),
    ('FV0062', 'Ceftriaxona', NULL, 'Inyectado', @cat_otros, 0.00),
    ('FV0063', 'Acetaminofen', NULL, 'Tableta', @cat_otros, 0.00),
    ('FV0064', 'Acetaminofen', NULL, 'Jarabe', @cat_otros, 0.00),
    ('FV0065', 'EX flu', NULL, 'Inyectado', @cat_otros, 0.00),
    ('FV0066', 'EX flu', NULL, 'Tabletas', @cat_otros, 0.00),
    ('FV0067', 'Olmesartan + hidroclorotiazida', NULL, NULL, @cat_otros, 0.00);


INSERT INTO proveedores (nombre, nit, telefono, correo, direccion)
SELECT 'Proveedor Farmacéutico 1', '123456-7', '5555-1111',
       'proveedor1@email.com', 'Ciudad de Guatemala'
WHERE NOT EXISTS (
    SELECT 1 FROM proveedores WHERE nit = '123456-7'
);


INSERT INTO usuarios (nombre, usuario, password, correo, rol, estado)
SELECT 'Administrador de Farmacia', 'admin_fuente_vida', '$2y$10$uWgzGVgiy1rAbN4gUWWsbOE4HPwAxbSYmwPUAbIs6w7T28BrAgg0e', NULL, 'Administrador', TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE usuario = 'admin_fuente_vida'
);


-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================