-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3307
-- Tiempo de generación: 10-10-2026 a las 04:10:23
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `farmacia_fuente_vida`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas_atendidas`
--

CREATE TABLE `alertas_atendidas` (
  `tipo` varchar(30) NOT NULL,
  `referencia` int(11) NOT NULL,
  `atendida_por` int(11) NOT NULL,
  `atendida_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `alertas_atendidas`
--

INSERT INTO `alertas_atendidas` (`tipo`, `referencia`, `atendida_por`, `atendida_en`) VALUES
('stock', 4, 1, '2026-10-06 01:51:27'),
('stock', 30, 1, '2026-10-06 01:51:29'),
('stock', 46, 1, '2026-10-04 23:27:51'),
('stock', 95, 1, '2026-10-06 01:51:32'),
('vencimiento', 1, 1, '2026-10-04 05:04:15'),
('vencimiento', 2, 1, '2026-10-04 05:04:23'),
('vencimiento', 3, 1, '2026-10-04 19:01:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_presentaciones`
--

CREATE TABLE `catalogo_presentaciones` (
  `id_catalogo_presentacion` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `nivel` smallint(5) UNSIGNED NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `catalogo_presentaciones`
--

INSERT INTO `catalogo_presentaciones` (`id_catalogo_presentacion`, `nombre`, `nivel`, `estado`, `fecha_registro`) VALUES
(1, 'Unidad', 1, 1, '2026-10-04 04:02:25'),
(2, 'Bl├¡ster', 2, 1, '2026-10-04 04:02:25'),
(3, 'Cajita', 3, 1, '2026-10-04 04:02:25'),
(4, 'Caja', 4, 1, '2026-10-04 04:02:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Analgésicos', 'Medicamentos utilizados para aliviar el dolor', 1),
(2, 'Antibióticos', 'Medicamentos utilizados para tratar infecciones', 1),
(3, 'Antigripales', 'Medicamentos para síntomas de gripe y resfriado', 1),
(4, 'Vitaminas', 'Suplementos y vitaminas', 1),
(5, 'Otros', 'Otros productos farmacéuticos', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `nit` varchar(30) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras`
--

CREATE TABLE `compras` (
  `id_compra` int(11) NOT NULL,
  `id_proveedor` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_compra` datetime NOT NULL DEFAULT current_timestamp(),
  `numero_factura` varchar(50) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` varchar(20) NOT NULL DEFAULT 'Completada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `compras`
--

INSERT INTO `compras` (`id_compra`, `id_proveedor`, `id_usuario`, `fecha_compra`, `numero_factura`, `total`, `estado`) VALUES
(1, 1, 1, '2026-10-03 22:50:41', '111', 1000.00, 'Completada'),
(2, 1, 1, '2026-10-03 23:15:53', '122', 120.00, 'Completada'),
(3, 2, 1, '2026-10-04 13:45:00', '111', 50.00, 'Completada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `clave` varchar(100) NOT NULL,
  `valor` text NOT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`clave`, `valor`, `actualizado_en`) VALUES
('alertas_stock_minimo', '5', '2026-10-04 23:30:14'),
('alertas_vencimiento_dias', '30', '2026-10-04 23:30:14'),
('confirmar_eliminaciones', '1', '2026-10-04 23:30:14'),
('moneda', 'GTQ', '2026-10-04 23:30:28'),
('nombre_farmacia', 'Farmacia Fuente de Vida', '2026-10-04 23:30:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_compras`
--

CREATE TABLE `detalle_compras` (
  `id_detalle_compra` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_lote` int(11) NOT NULL,
  `id_producto_presentacion` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `cantidad_base` bigint(20) UNSIGNED DEFAULT NULL,
  `costo_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`cantidad` * `costo_unitario`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `detalle_compras`
--

INSERT INTO `detalle_compras` (`id_detalle_compra`, `id_compra`, `id_producto`, `id_lote`, `id_producto_presentacion`, `cantidad`, `cantidad_base`, `costo_unitario`) VALUES
(1, 1, 173, 2, 257, 1, 480, 1000.00),
(2, 2, 174, 3, 261, 2, 100, 60.00),
(3, 3, 64, 4, 265, 2, 5760, 25.00);

--
-- Disparadores `detalle_compras`
--
DELIMITER $$
CREATE TRIGGER `trg_entrada_inventario` AFTER INSERT ON `detalle_compras` FOR EACH ROW BEGIN
	UPDATE lotes
	SET cantidad = cantidad + COALESCE(NEW.cantidad_base, NEW.cantidad)
	WHERE id_lote = NEW.id_lote;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ventas`
--

CREATE TABLE `detalle_ventas` (
  `id_detalle_venta` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_lote` int(11) NOT NULL,
  `id_producto_presentacion` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `cantidad_base` bigint(20) UNSIGNED DEFAULT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `detalle_ventas`
--

INSERT INTO `detalle_ventas` (`id_detalle_venta`, `id_venta`, `id_producto`, `id_lote`, `id_producto_presentacion`, `cantidad`, `cantidad_base`, `precio_unitario`) VALUES
(1, 1, 173, 1, 260, 3, 3, 2.08),
(2, 2, 174, 3, 262, 11, 55, 10.00),
(3, 3, 173, 2, 258, 3, 60, 41.67),
(4, 4, 64, 4, 267, 3, 72, 1.25),
(5, 5, 174, 3, 264, 4, 4, 2.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ventas_lotes`
--

CREATE TABLE `detalle_ventas_lotes` (
  `id_detalle_venta_lote` int(11) NOT NULL,
  `id_detalle_venta` int(11) NOT NULL,
  `id_lote` int(11) NOT NULL,
  `cantidad_base` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `detalle_ventas_lotes`
--

INSERT INTO `detalle_ventas_lotes` (`id_detalle_venta_lote`, `id_detalle_venta`, `id_lote`, `cantidad_base`) VALUES
(1, 1, 1, 3),
(2, 2, 3, 55),
(3, 3, 2, 60),
(4, 4, 4, 72),
(5, 5, 3, 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lotes`
--

CREATE TABLE `lotes` (
  `id_lote` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `numero_lote` varchar(50) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `cantidad` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `costo_unitario` decimal(10,2) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `lotes`
--

INSERT INTO `lotes` (`id_lote`, `id_producto`, `numero_lote`, `fecha_vencimiento`, `cantidad`, `costo_unitario`, `estado`) VALUES
(1, 173, '2', '2026-10-03', 2397, 2.08, 1),
(2, 173, '3', '2026-10-10', 420, 2.08, 1),
(3, 174, '3', '2026-10-05', 41, 1.20, 1),
(4, 64, '2', '2026-10-28', 5688, 0.01, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos_modulos`
--

CREATE TABLE `permisos_modulos` (
  `rol` varchar(30) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `puede_acceder` tinyint(1) NOT NULL DEFAULT 0,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `permisos_modulos`
--

INSERT INTO `permisos_modulos` (`rol`, `modulo`, `puede_acceder`, `actualizado_en`) VALUES
('Administrador', 'alertas', 1, '2026-10-04 19:10:16'),
('Administrador', 'catalogo_presentaciones', 1, '2026-10-04 19:10:16'),
('Administrador', 'clientes', 1, '2026-10-04 19:10:16'),
('Administrador', 'compras', 1, '2026-10-04 19:10:16'),
('Administrador', 'configuracion', 1, '2026-10-04 19:10:16'),
('Administrador', 'inicio', 1, '2026-10-04 19:10:16'),
('Administrador', 'inventario', 1, '2026-10-04 19:10:16'),
('Administrador', 'productos', 1, '2026-10-04 19:10:16'),
('Administrador', 'proveedores', 1, '2026-10-04 19:10:16'),
('Administrador', 'reportes', 1, '2026-10-04 19:10:16'),
('Administrador', 'usuarios', 1, '2026-10-04 19:10:16'),
('Administrador', 'ventas', 1, '2026-10-04 19:10:16'),
('Vendedor', 'alertas', 1, '2026-10-04 19:10:16'),
('Vendedor', 'catalogo_presentaciones', 1, '2026-10-04 19:10:16'),
('Vendedor', 'clientes', 1, '2026-10-04 19:10:16'),
('Vendedor', 'compras', 1, '2026-10-04 19:10:16'),
('Vendedor', 'configuracion', 1, '2026-10-04 19:10:16'),
('Vendedor', 'inicio', 1, '2026-10-04 19:10:16'),
('Vendedor', 'inventario', 1, '2026-10-04 19:10:16'),
('Vendedor', 'productos', 1, '2026-10-04 19:10:16'),
('Vendedor', 'proveedores', 1, '2026-10-04 19:10:16'),
('Vendedor', 'reportes', 1, '2026-10-04 19:10:16'),
('Vendedor', 'usuarios', 0, '2026-10-04 19:10:16'),
('Vendedor', 'ventas', 1, '2026-10-04 19:10:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `principio_activo` varchar(150) DEFAULT NULL,
  `presentacion` varchar(100) DEFAULT NULL,
  `id_categoria` int(11) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `stock_minimo` int(11) NOT NULL DEFAULT 5,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `codigo`, `nombre`, `descripcion`, `principio_activo`, `presentacion`, `id_categoria`, `precio_venta`, `stock_minimo`, `estado`, `fecha_registro`) VALUES
(1, 'FDV-001', 'Metformina', 'Dosis/concentración: 1000', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(2, 'FDV-002', 'Senosidos', 'Nota: Laxante', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(3, 'FDV-003', 'Craspovidona', 'Nota: Antidiarreico', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(4, 'FDV-004', 'Codeypina', 'Nota: Tos', NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(5, 'FDV-005', 'Codeypina', 'Nota: Tos', NULL, 'Pastilla', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(6, 'FDV-006', 'Ketorolaco', 'Nota: Analgésico', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(7, 'FDV-007', 'Bromuro de otilonio', 'Nota: Problemas de colon', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(8, 'FDV-008', 'Ibuprofen + metocarbamol', 'Nota: Analgésico', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(9, 'FDV-009', 'Spasmo clor', 'Nota: Analgésico', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(10, 'FDV-010', 'Lanzoprazol', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(11, 'FDV-011', 'Loratadina', 'Nota: Alergia', NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(12, 'FDV-012', 'Loratadina', 'Nota: Alergia', NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(13, 'FDV-013', 'Ojo de águila', NULL, NULL, 'Colirio', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(14, 'FDV-014', 'Piroxican', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(15, 'FDV-015', 'Piroxican', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(16, 'FDV-016', 'Recolector', NULL, NULL, 'Heces', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(17, 'FDV-017', 'Recolector', NULL, NULL, 'Orina', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(18, 'FDV-018', 'Azitromicina', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(19, 'FDV-019', 'Azitromicina', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(20, 'FDV-020', 'Vitaflenaco', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(21, 'FDV-021', 'Ciprofloxacina', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(22, 'FDV-022', 'Esomeprazol', NULL, NULL, 'Cápsula', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(23, 'FDV-023', 'Levecilin AC', 'Dosis/concentración: 120 ml', NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(24, 'FDV-024', 'Ibesartán + Hidroclorotiazida', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(25, 'FDV-025', 'Aciclovir', NULL, NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(26, 'FDV-026', 'Aciclovir', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(27, 'FDV-027', 'Ibuprofeno', 'Dosis/concentración: 800', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(28, 'FDV-028', 'Ibuprofeno', 'Dosis/concentración: 600', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(29, 'FDV-029', 'Ibuprofeno', 'Dosis/concentración: 400', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(30, 'FDV-030', 'Ibuprofeno', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(31, 'FDV-031', 'Sulfabac', 'Nota: Trimetoprim', NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(32, 'FDV-032', 'Clorfeniramina', NULL, NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(33, 'FDV-033', 'Clorfeniramina', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(34, 'FDV-034', 'Nitazel', 'Nota: Desparasitante', NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(35, 'FDV-035', 'Exiflem', 'Nota: Bromexina', NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(36, 'FDV-036', 'Nitazel', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(37, 'FDV-037', 'Metronidazol', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(38, 'FDV-038', 'Metronidazol', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(39, 'FDV-039', 'Virogrip día', NULL, NULL, 'Gel', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(40, 'FDV-040', 'Virogrip noche', NULL, NULL, 'Gel', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(41, 'FDV-041', 'Virogrip día', NULL, NULL, 'Té', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(42, 'FDV-042', 'Virogrip noche', NULL, NULL, 'Té', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(43, 'FDV-043', 'Cardio vital', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(44, 'FDV-044', 'Citrasel', NULL, NULL, 'Spray', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(45, 'FDV-045', 'Citrasel', NULL, NULL, 'Crema', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(46, 'FDV-046', 'Vitaplex', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(47, 'FDV-047', 'Dolovitaplex', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(48, 'FDV-048', 'Levusol', NULL, NULL, 'Suero', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(49, 'FDV-049', 'Hidrocortizona', 'Dosis/concentración: 1%', NULL, 'Crema', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(50, 'FDV-050', 'Gencloben', NULL, NULL, 'Crema', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(51, 'FDV-051', 'Nitazoxanida', NULL, NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(52, 'FDV-052', 'Clotrimazol', NULL, NULL, 'Crema', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(53, 'FDV-053', 'Omega 3', 'Dosis/concentración: 1,000', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(54, 'FDV-054', 'Clotriplex', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(55, 'FDV-055', 'Ácido fólico', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(56, 'FDV-056', 'Tramadol', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(57, 'FDV-057', 'Amoxicilina', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(58, 'FDV-058', 'Amoxicilina', NULL, NULL, 'Cápsula', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(59, 'FDV-059', 'Jeringa', 'Dosis/concentración: 10 ml', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(60, 'FDV-060', 'Jeringa', 'Dosis/concentración: 5 ml', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(61, 'FDV-061', 'Jeringa', 'Dosis/concentración: 3 ml', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(62, 'FDV-062', 'Ceftriaxona', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(63, 'FDV-063', 'Acetaminofen', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(64, 'FDV-064', 'Acetaminofen', '', '', 'Jarabe', 3, 100.00, 5, 1, '2026-10-04 02:53:56'),
(65, 'FDV-065', 'Ex flu', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(66, 'FDV-066', 'Ex flu', NULL, NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(67, 'FDV-067', 'Olmesartan + hidroclorotiazida', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(68, 'FDV-068', 'Simeticona', NULL, NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(69, 'FDV-069', 'Simeticona', NULL, NULL, 'Gotas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(70, 'FDV-070', 'Diclofenaco potásico', 'Dosis/concentración: 50 mg', NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(71, 'FDV-071', 'Diclofenaco potásico', 'Dosis/concentración: 100 mg', NULL, 'Gel', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(72, 'FDV-072', 'Diclofenaco potásico', NULL, NULL, 'Tópico', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(73, 'FDV-073', 'Neofebrina', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(74, 'FDV-074', 'Neofebrina', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(75, 'FDV-075', 'Lágrimas oculares', NULL, NULL, 'Gotas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(76, 'FDV-076', 'Cloranfenicol', NULL, NULL, 'Gotas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(77, 'FDV-077', 'Cloranfenicol + dexa + nafasolin', NULL, NULL, 'Gotas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(78, 'FDV-078', 'Ciprofloxacina', NULL, NULL, 'Gotas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(79, 'FDV-079', 'Nafasolina', NULL, NULL, 'Gotas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(80, 'FDV-080', 'Otik', NULL, NULL, 'Gotas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(81, 'FDV-081', 'Relax plus', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(82, 'FDV-082', 'Agua oxigenada', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(83, 'FDV-083', 'Ex flu tos', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(84, 'FDV-084', 'Ex flu', 'Nota: Multisíntomas', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(85, 'FDV-085', 'Viro grip', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(86, 'FDV-086', 'Naproxeno', NULL, NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(87, 'FDV-087', 'Saluprim', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(88, 'FDV-088', 'Saluprim', NULL, NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(89, 'FDV-089', 'Aguja', 'Dosis/concentración: N. 24', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(90, 'FDV-090', 'Aguja', 'Dosis/concentración: N. 23', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(91, 'FDV-091', 'Aguja', 'Dosis/concentración: N. 22', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(92, 'FDV-092', 'Aguja', 'Dosis/concentración: N. 21', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(93, 'FDV-093', 'Nistatina', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(94, 'FDV-094', 'Zonitone', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(95, 'FDV-095', 'Zonitone', NULL, NULL, 'Caramelos', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(96, 'FDV-096', 'Multiplex prenatal', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(97, 'FDV-097', 'Nervisel', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(98, 'FDV-098', 'Dolonervisel', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(99, 'FDV-099', 'Nervisel', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(100, 'FDV-100', 'Dolonervisel', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(101, 'FDV-101', 'Dolovitanervo', NULL, NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(102, 'FDV-102', 'Dolovitanervo', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(103, 'FDV-103', 'Vitanervo', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(104, 'FDV-104', 'Sanirenal', NULL, NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(105, 'FDV-105', 'Guayatos', NULL, NULL, 'Jarabes', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(106, 'FDV-106', 'Trimetose', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(107, 'FDV-107', 'Trilox', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(108, 'FDV-108', 'Glicitos', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(109, 'FDV-109', 'Fluconazol', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(110, 'FDV-110', 'Ibesartan', 'Dosis/concentración: 150', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(111, 'FDV-111', 'Ibesartan', 'Dosis/concentración: 300', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(112, 'FDV-112', 'Unal', 'Nota: Anticonceptivo', NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(113, 'FDV-113', 'Complejo B', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(114, 'FDV-114', 'Kalivia', 'Nota: Analgésico', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(115, 'FDV-115', 'Tresbiot', NULL, NULL, 'Crema', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(116, 'FDV-116', 'Valsartan', 'Dosis/concentración: 160 mg', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(117, 'FDV-117', 'Betoapan', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(118, 'FDV-118', 'Vitamina C', 'Dosis/concentración: 500 mg', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(119, 'FDV-119', 'Desketoprofeno', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(120, 'FDV-120', 'Diclofenaco', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(121, 'FDV-121', 'Meloxican', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(122, 'FDV-122', 'Meloxican', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(123, 'FDV-123', 'Diclofenaco', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(124, 'FDV-124', 'Levadura de cerveza', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(125, 'FDV-125', 'Secnidazol', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(126, 'FDV-126', 'Secnidazol', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(127, 'FDV-127', 'Bicarbonato', NULL, NULL, 'Bolsita', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(128, 'FDV-128', 'Bicarbonato', 'Dosis/concentración: 1/2 libra', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(129, 'FDV-129', 'Bicarbonato', 'Dosis/concentración: 1 libra', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(130, 'FDV-130', 'Polvo óxido de zinc', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(131, 'FDV-131', 'Albendazol', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(132, 'FDV-132', 'Albendazol', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(133, 'FDV-133', 'Tosiflem', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(134, 'FDV-134', 'Dipirona', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(135, 'FDV-135', 'Dexametazona', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(136, 'FDV-136', 'Diclofenaco + neurotropas', NULL, NULL, 'Cápsula', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(137, 'FDV-137', 'Vive sensitivo', NULL, NULL, 'Preservativo', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(138, 'FDV-138', 'Metformina', 'Dosis/concentración: 500', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(139, 'FDV-139', 'Vitanervo', NULL, NULL, 'Pastilla', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(140, 'FDV-140', 'Sostengo', 'Nota: Sildenafil', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(141, 'FDV-141', 'Tossadios', NULL, NULL, 'Caramelo', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(142, 'FDV-142', 'Metformina', 'Dosis/concentración: 850; Nota: Combinada', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(143, 'FDV-143', 'Salbutamol', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(144, 'FDV-144', 'Salbutamol', NULL, NULL, 'Inhalador', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(145, 'FDV-145', 'Salbutamol', NULL, NULL, 'Tabletas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(146, 'FDV-146', 'Tetraciclina', 'Dosis/concentración: 500 mg', NULL, 'Cápsula', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(147, 'FDV-147', 'Fosfolipidos', NULL, NULL, 'Gel', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(148, 'FDV-148', 'Flufin alergia', NULL, NULL, 'Gel', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(149, 'FDV-149', 'Dolodent', 'Nota: Anestecia', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(150, 'FDV-150', 'Napioliv', 'Dosis/concentración: 550 mg', NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(151, 'FDV-151', 'Complejo B plus', NULL, NULL, 'Cápsula gel', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(152, 'FDV-152', 'Clotrimazol', 'Dosis/concentración: 2%', NULL, 'Crema', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(153, 'FDV-153', 'Cuerpo amarillo', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(154, 'FDV-154', 'Losartan + hidroclorotiazida', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(155, 'FDV-155', 'Ketoconazol', NULL, NULL, 'Crema', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(156, 'FDV-156', 'Loratadina', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(157, 'FDV-157', 'Loratadina', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(158, 'FDV-158', 'Desloratadina', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(159, 'FDV-159', 'Gingo biloba', NULL, NULL, 'Cápsula gel', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(160, 'FDV-160', 'Dolfix', NULL, NULL, 'Cápsula', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(161, 'FDV-161', 'Esomeprazol', NULL, NULL, 'Cápsula', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(162, 'FDV-162', 'Enerhit', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(163, 'FDV-163', 'Prenatales plus', NULL, NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(164, 'FDV-164', 'Ferridoce', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(165, 'FDV-165', 'Ferridoce', NULL, NULL, 'Jarabe', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(166, 'FDV-166', 'Metrizol V', NULL, NULL, 'Óvulos', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(167, 'FDV-167', 'Cefadroxilo', NULL, NULL, 'Tableta', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(168, 'FDV-168', 'Cefadroxilo', NULL, NULL, 'Suspensión', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(169, 'FDV-169', 'Gabapentina', 'Dosis/concentración: 400 mg', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(170, 'FDV-170', 'Gabapentina', 'Dosis/concentración: 300 mg', NULL, NULL, 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(171, 'FDV-171', 'Lidocaina', NULL, NULL, 'Inyectado', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(172, 'FDV-172', 'Seven \"S\"', NULL, NULL, 'Cápsulas', 5, 0.00, 5, 1, '2026-10-04 02:53:56'),
(173, 'FDV-173', 'SUKROL', 'vitaminas para el cerebro y nervios', 'Vitamina', 'frasco 100 tabletas', 4, 1500.00, 5, 1, '2026-10-04 04:40:21'),
(174, 'FVD-174', 'aspirina', '', 'acido', '100 mg tabletas', 4, 100.00, 5, 1, '2026-10-04 05:12:22'),
(175, 'FDV-175', 'Vitamina D P', 'Vitamina D, prueba', 'prueba', '400 gramos', 4, 25.00, 5, 1, '2026-10-04 23:17:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_presentaciones`
--

CREATE TABLE `producto_presentaciones` (
  `id_producto_presentacion` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_catalogo_presentacion` int(11) NOT NULL,
  `unidades_base` bigint(20) UNSIGNED NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `producto_presentaciones`
--

INSERT INTO `producto_presentaciones` (`id_producto_presentacion`, `id_producto`, `id_catalogo_presentacion`, `unidades_base`, `precio_venta`, `estado`) VALUES
(1, 1, 1, 1, 0.00, 1),
(2, 2, 1, 1, 0.00, 1),
(3, 3, 1, 1, 0.00, 1),
(4, 4, 1, 1, 0.00, 1),
(5, 5, 1, 1, 0.00, 1),
(6, 6, 1, 1, 0.00, 1),
(7, 7, 1, 1, 0.00, 1),
(8, 8, 1, 1, 0.00, 1),
(9, 9, 1, 1, 0.00, 1),
(10, 10, 1, 1, 0.00, 1),
(11, 11, 1, 1, 0.00, 1),
(12, 12, 1, 1, 0.00, 1),
(13, 13, 1, 1, 0.00, 1),
(14, 14, 1, 1, 0.00, 1),
(15, 15, 1, 1, 0.00, 1),
(16, 16, 1, 1, 0.00, 1),
(17, 17, 1, 1, 0.00, 1),
(18, 18, 1, 1, 0.00, 1),
(19, 19, 1, 1, 0.00, 1),
(20, 20, 1, 1, 0.00, 1),
(21, 21, 1, 1, 0.00, 1),
(22, 22, 1, 1, 0.00, 1),
(23, 23, 1, 1, 0.00, 1),
(24, 24, 1, 1, 0.00, 1),
(25, 25, 1, 1, 0.00, 1),
(26, 26, 1, 1, 0.00, 1),
(27, 27, 1, 1, 0.00, 1),
(28, 28, 1, 1, 0.00, 1),
(29, 29, 1, 1, 0.00, 1),
(30, 30, 1, 1, 0.00, 1),
(31, 31, 1, 1, 0.00, 1),
(32, 32, 1, 1, 0.00, 1),
(33, 33, 1, 1, 0.00, 1),
(34, 34, 1, 1, 0.00, 1),
(35, 35, 1, 1, 0.00, 1),
(36, 36, 1, 1, 0.00, 1),
(37, 37, 1, 1, 0.00, 1),
(38, 38, 1, 1, 0.00, 1),
(39, 39, 1, 1, 0.00, 1),
(40, 40, 1, 1, 0.00, 1),
(41, 41, 1, 1, 0.00, 1),
(42, 42, 1, 1, 0.00, 1),
(43, 43, 1, 1, 0.00, 1),
(44, 44, 1, 1, 0.00, 1),
(45, 45, 1, 1, 0.00, 1),
(46, 46, 1, 1, 0.00, 1),
(47, 47, 1, 1, 0.00, 1),
(48, 48, 1, 1, 0.00, 1),
(49, 49, 1, 1, 0.00, 1),
(50, 50, 1, 1, 0.00, 1),
(51, 51, 1, 1, 0.00, 1),
(52, 52, 1, 1, 0.00, 1),
(53, 53, 1, 1, 0.00, 1),
(54, 54, 1, 1, 0.00, 1),
(55, 55, 1, 1, 0.00, 1),
(56, 56, 1, 1, 0.00, 1),
(57, 57, 1, 1, 0.00, 1),
(58, 58, 1, 1, 0.00, 1),
(59, 59, 1, 1, 0.00, 1),
(60, 60, 1, 1, 0.00, 1),
(61, 61, 1, 1, 0.00, 1),
(62, 62, 1, 1, 0.00, 1),
(63, 63, 1, 1, 0.00, 1),
(64, 64, 1, 1, 0.05, 1),
(65, 65, 1, 1, 0.00, 1),
(66, 66, 1, 1, 0.00, 1),
(67, 67, 1, 1, 0.00, 1),
(68, 68, 1, 1, 0.00, 1),
(69, 69, 1, 1, 0.00, 1),
(70, 70, 1, 1, 0.00, 1),
(71, 71, 1, 1, 0.00, 1),
(72, 72, 1, 1, 0.00, 1),
(73, 73, 1, 1, 0.00, 1),
(74, 74, 1, 1, 0.00, 1),
(75, 75, 1, 1, 0.00, 1),
(76, 76, 1, 1, 0.00, 1),
(77, 77, 1, 1, 0.00, 1),
(78, 78, 1, 1, 0.00, 1),
(79, 79, 1, 1, 0.00, 1),
(80, 80, 1, 1, 0.00, 1),
(81, 81, 1, 1, 0.00, 1),
(82, 82, 1, 1, 0.00, 1),
(83, 83, 1, 1, 0.00, 1),
(84, 84, 1, 1, 0.00, 1),
(85, 85, 1, 1, 0.00, 1),
(86, 86, 1, 1, 0.00, 1),
(87, 87, 1, 1, 0.00, 1),
(88, 88, 1, 1, 0.00, 1),
(89, 89, 1, 1, 0.00, 1),
(90, 90, 1, 1, 0.00, 1),
(91, 91, 1, 1, 0.00, 1),
(92, 92, 1, 1, 0.00, 1),
(93, 93, 1, 1, 0.00, 1),
(94, 94, 1, 1, 0.00, 1),
(95, 95, 1, 1, 0.00, 1),
(96, 96, 1, 1, 0.00, 1),
(97, 97, 1, 1, 0.00, 1),
(98, 98, 1, 1, 0.00, 1),
(99, 99, 1, 1, 0.00, 1),
(100, 100, 1, 1, 0.00, 1),
(101, 101, 1, 1, 0.00, 1),
(102, 102, 1, 1, 0.00, 1),
(103, 103, 1, 1, 0.00, 1),
(104, 104, 1, 1, 0.00, 1),
(105, 105, 1, 1, 0.00, 1),
(106, 106, 1, 1, 0.00, 1),
(107, 107, 1, 1, 0.00, 1),
(108, 108, 1, 1, 0.00, 1),
(109, 109, 1, 1, 0.00, 1),
(110, 110, 1, 1, 0.00, 1),
(111, 111, 1, 1, 0.00, 1),
(112, 112, 1, 1, 0.00, 1),
(113, 113, 1, 1, 0.00, 1),
(114, 114, 1, 1, 0.00, 1),
(115, 115, 1, 1, 0.00, 1),
(116, 116, 1, 1, 0.00, 1),
(117, 117, 1, 1, 0.00, 1),
(118, 118, 1, 1, 0.00, 1),
(119, 119, 1, 1, 0.00, 1),
(120, 120, 1, 1, 0.00, 1),
(121, 121, 1, 1, 0.00, 1),
(122, 122, 1, 1, 0.00, 1),
(123, 123, 1, 1, 0.00, 1),
(124, 124, 1, 1, 0.00, 1),
(125, 125, 1, 1, 0.00, 1),
(126, 126, 1, 1, 0.00, 1),
(127, 127, 1, 1, 0.00, 1),
(128, 128, 1, 1, 0.00, 1),
(129, 129, 1, 1, 0.00, 1),
(130, 130, 1, 1, 0.00, 1),
(131, 131, 1, 1, 0.00, 1),
(132, 132, 1, 1, 0.00, 1),
(133, 133, 1, 1, 0.00, 1),
(134, 134, 1, 1, 0.00, 1),
(135, 135, 1, 1, 0.00, 1),
(136, 136, 1, 1, 0.00, 1),
(137, 137, 1, 1, 0.00, 1),
(138, 138, 1, 1, 0.00, 1),
(139, 139, 1, 1, 0.00, 1),
(140, 140, 1, 1, 0.00, 1),
(141, 141, 1, 1, 0.00, 1),
(142, 142, 1, 1, 0.00, 1),
(143, 143, 1, 1, 0.00, 1),
(144, 144, 1, 1, 0.00, 1),
(145, 145, 1, 1, 0.00, 1),
(146, 146, 1, 1, 0.00, 1),
(147, 147, 1, 1, 0.00, 1),
(148, 148, 1, 1, 0.00, 1),
(149, 149, 1, 1, 0.00, 1),
(150, 150, 1, 1, 0.00, 1),
(151, 151, 1, 1, 0.00, 1),
(152, 152, 1, 1, 0.00, 1),
(153, 153, 1, 1, 0.00, 1),
(154, 154, 1, 1, 0.00, 1),
(155, 155, 1, 1, 0.00, 1),
(156, 156, 1, 1, 0.00, 1),
(157, 157, 1, 1, 0.00, 1),
(158, 158, 1, 1, 0.00, 1),
(159, 159, 1, 1, 0.00, 1),
(160, 160, 1, 1, 0.00, 1),
(161, 161, 1, 1, 0.00, 1),
(162, 162, 1, 1, 0.00, 1),
(163, 163, 1, 1, 0.00, 1),
(164, 164, 1, 1, 0.00, 1),
(165, 165, 1, 1, 0.00, 1),
(166, 166, 1, 1, 0.00, 1),
(167, 167, 1, 1, 0.00, 1),
(168, 168, 1, 1, 0.00, 1),
(169, 169, 1, 1, 0.00, 1),
(170, 170, 1, 1, 0.00, 1),
(171, 171, 1, 1, 0.00, 1),
(172, 172, 1, 1, 0.00, 1),
(257, 173, 4, 480, 1500.00, 1),
(258, 173, 3, 20, 41.67, 1),
(259, 173, 2, 10, 20.83, 1),
(260, 173, 1, 1, 2.08, 1),
(261, 174, 4, 50, 100.00, 1),
(262, 174, 3, 5, 10.00, 1),
(263, 174, 2, 5, 10.00, 1),
(264, 174, 1, 1, 2.00, 1),
(265, 64, 4, 2880, 100.00, 1),
(266, 64, 3, 240, 12.50, 1),
(267, 64, 2, 24, 1.25, 1),
(269, 175, 3, 12, 25.00, 1),
(270, 175, 2, 6, 12.50, 1),
(271, 175, 1, 1, 2.08, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `contacto` varchar(150) DEFAULT NULL,
  `nit` varchar(30) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id_proveedor`, `nombre`, `contacto`, `nit`, `telefono`, `correo`, `direccion`, `estado`, `fecha_registro`) VALUES
(1, 'Proveedor Farmacéutico 1', NULL, '123456-7', '5555-1111', 'proveedor1@email.com', 'Ciudad de Guatemala', 1, '2026-10-04 02:53:56'),
(2, 'Galeno', 'Miguel santizo', '125888', '47434353', 'santizosantosf37@gmail.com', 'zona 11', 1, '2026-10-04 18:57:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `nombre` varchar(30) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`nombre`, `creado_en`) VALUES
('Administrador', '2026-10-04 19:17:35'),
('Vendedor', '2026-10-04 19:17:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `rol` varchar(30) NOT NULL DEFAULT 'Administrador',
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `usuario`, `password`, `correo`, `rol`, `estado`, `fecha_creacion`) VALUES
(1, 'Administrador de Farmacia', 'admin_fuente_vida', '$2y$10$uWgzGVgiy1rAbN4gUWWsbOE4HPwAxbSYmwPUAbIs6w7T28BrAgg0e', NULL, 'Administrador', 1, '2026-10-04 02:53:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `fecha_venta` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` varchar(30) NOT NULL DEFAULT 'Efectivo',
  `estado` varchar(20) NOT NULL DEFAULT 'Completada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id_venta`, `id_usuario`, `id_cliente`, `fecha_venta`, `total`, `metodo_pago`, `estado`) VALUES
(1, 1, NULL, '2026-10-03 23:05:02', 6.24, 'Transferencia', 'Completada'),
(2, 1, NULL, '2026-10-03 23:17:14', 110.00, 'Efectivo', 'Completada'),
(3, 1, NULL, '2026-10-04 12:03:44', 125.01, 'Efectivo', 'Completada'),
(4, 1, NULL, '2026-10-04 17:21:28', 3.75, 'Efectivo', 'Completada'),
(5, 1, NULL, '2026-10-05 19:49:27', 8.00, 'Efectivo', 'Completada');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_inventario`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_inventario` (
`id_producto` int(11)
,`codigo` varchar(50)
,`nombre` varchar(150)
,`categoria` varchar(100)
,`presentacion` varchar(100)
,`precio_venta` decimal(10,2)
,`stock_minimo` int(11)
,`stock_actual` decimal(42,0)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_productos_por_vencer`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_productos_por_vencer` (
`id_producto` int(11)
,`codigo` varchar(50)
,`nombre` varchar(150)
,`numero_lote` varchar(50)
,`fecha_vencimiento` date
,`cantidad` bigint(20) unsigned
,`dias_para_vencer` int(7)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_reporte_ventas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_reporte_ventas` (
`id_venta` int(11)
,`fecha_venta` datetime
,`usuario` varchar(100)
,`metodo_pago` varchar(30)
,`codigo` varchar(50)
,`producto` varchar(150)
,`presentacion` varchar(100)
,`cantidad` int(11)
,`cantidad_base` bigint(20) unsigned
,`precio_unitario` decimal(10,2)
,`subtotal` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_stock_bajo`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_stock_bajo` (
`id_producto` int(11)
,`codigo` varchar(50)
,`nombre` varchar(150)
,`categoria` varchar(100)
,`stock_actual` decimal(42,0)
,`stock_minimo` int(11)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_ventas_diarias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_ventas_diarias` (
`fecha` date
,`cantidad_ventas` bigint(21)
,`total_vendido` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_ventas_semanales`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_ventas_semanales` (
`anio` int(4)
,`semana` int(2)
,`cantidad_ventas` bigint(21)
,`total_vendido` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_inventario`
--
DROP TABLE IF EXISTS `vista_inventario`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_inventario`  AS SELECT `p`.`id_producto` AS `id_producto`, `p`.`codigo` AS `codigo`, `p`.`nombre` AS `nombre`, `c`.`nombre` AS `categoria`, `p`.`presentacion` AS `presentacion`, `p`.`precio_venta` AS `precio_venta`, `p`.`stock_minimo` AS `stock_minimo`, coalesce(sum(`l`.`cantidad`),0) AS `stock_actual` FROM ((`productos` `p` join `categorias` `c` on(`p`.`id_categoria` = `c`.`id_categoria`)) left join `lotes` `l` on(`p`.`id_producto` = `l`.`id_producto` and `l`.`estado` = 1)) GROUP BY `p`.`id_producto`, `p`.`codigo`, `p`.`nombre`, `c`.`nombre`, `p`.`presentacion`, `p`.`precio_venta`, `p`.`stock_minimo` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_productos_por_vencer`
--
DROP TABLE IF EXISTS `vista_productos_por_vencer`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_productos_por_vencer`  AS SELECT `p`.`id_producto` AS `id_producto`, `p`.`codigo` AS `codigo`, `p`.`nombre` AS `nombre`, `l`.`numero_lote` AS `numero_lote`, `l`.`fecha_vencimiento` AS `fecha_vencimiento`, `l`.`cantidad` AS `cantidad`, to_days(`l`.`fecha_vencimiento`) - to_days(curdate()) AS `dias_para_vencer` FROM (`productos` `p` join `lotes` `l` on(`p`.`id_producto` = `l`.`id_producto`)) WHERE `l`.`estado` = 1 AND `l`.`fecha_vencimiento` >= curdate() AND `l`.`fecha_vencimiento` <= curdate() + interval 30 day ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_reporte_ventas`
--
DROP TABLE IF EXISTS `vista_reporte_ventas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_reporte_ventas`  AS SELECT `v`.`id_venta` AS `id_venta`, `v`.`fecha_venta` AS `fecha_venta`, `u`.`nombre` AS `usuario`, `v`.`metodo_pago` AS `metodo_pago`, `p`.`codigo` AS `codigo`, `p`.`nombre` AS `producto`, `cp`.`nombre` AS `presentacion`, `dv`.`cantidad` AS `cantidad`, `dv`.`cantidad_base` AS `cantidad_base`, `dv`.`precio_unitario` AS `precio_unitario`, `dv`.`subtotal` AS `subtotal` FROM (((((`ventas` `v` join `usuarios` `u` on(`v`.`id_usuario` = `u`.`id_usuario`)) join `detalle_ventas` `dv` on(`v`.`id_venta` = `dv`.`id_venta`)) join `productos` `p` on(`dv`.`id_producto` = `p`.`id_producto`)) left join `producto_presentaciones` `pp` on(`pp`.`id_producto_presentacion` = `dv`.`id_producto_presentacion`)) left join `catalogo_presentaciones` `cp` on(`cp`.`id_catalogo_presentacion` = `pp`.`id_catalogo_presentacion`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_stock_bajo`
--
DROP TABLE IF EXISTS `vista_stock_bajo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_stock_bajo`  AS SELECT `vista_inventario`.`id_producto` AS `id_producto`, `vista_inventario`.`codigo` AS `codigo`, `vista_inventario`.`nombre` AS `nombre`, `vista_inventario`.`categoria` AS `categoria`, `vista_inventario`.`stock_actual` AS `stock_actual`, `vista_inventario`.`stock_minimo` AS `stock_minimo` FROM `vista_inventario` WHERE `vista_inventario`.`stock_actual` <= `vista_inventario`.`stock_minimo` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ventas_diarias`
--
DROP TABLE IF EXISTS `vista_ventas_diarias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ventas_diarias`  AS SELECT cast(`ventas`.`fecha_venta` as date) AS `fecha`, count(`ventas`.`id_venta`) AS `cantidad_ventas`, sum(`ventas`.`total`) AS `total_vendido` FROM `ventas` WHERE `ventas`.`estado` = 'Completada' GROUP BY cast(`ventas`.`fecha_venta` as date) ORDER BY cast(`ventas`.`fecha_venta` as date) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ventas_semanales`
--
DROP TABLE IF EXISTS `vista_ventas_semanales`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ventas_semanales`  AS SELECT year(`ventas`.`fecha_venta`) AS `anio`, week(`ventas`.`fecha_venta`,1) AS `semana`, count(`ventas`.`id_venta`) AS `cantidad_ventas`, sum(`ventas`.`total`) AS `total_vendido` FROM `ventas` WHERE `ventas`.`estado` = 'Completada' GROUP BY year(`ventas`.`fecha_venta`), week(`ventas`.`fecha_venta`,1) ORDER BY year(`ventas`.`fecha_venta`) DESC, week(`ventas`.`fecha_venta`,1) DESC ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertas_atendidas`
--
ALTER TABLE `alertas_atendidas`
  ADD PRIMARY KEY (`tipo`,`referencia`),
  ADD KEY `fk_alerta_usuario` (`atendida_por`);

--
-- Indices de la tabla `catalogo_presentaciones`
--
ALTER TABLE `catalogo_presentaciones`
  ADD PRIMARY KEY (`id_catalogo_presentacion`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`);

--
-- Indices de la tabla `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `fk_compra_proveedor` (`id_proveedor`),
  ADD KEY `fk_compra_usuario` (`id_usuario`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`clave`);

--
-- Indices de la tabla `detalle_compras`
--
ALTER TABLE `detalle_compras`
  ADD PRIMARY KEY (`id_detalle_compra`),
  ADD KEY `fk_detalle_compra` (`id_compra`),
  ADD KEY `fk_detalle_producto_compra` (`id_producto`),
  ADD KEY `fk_detalle_lote_compra` (`id_lote`),
  ADD KEY `fk_detalle_presentacion_compra` (`id_producto_presentacion`);

--
-- Indices de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id_detalle_venta`),
  ADD KEY `fk_detalle_venta` (`id_venta`),
  ADD KEY `fk_detalle_producto_venta` (`id_producto`),
  ADD KEY `fk_detalle_lote_venta` (`id_lote`),
  ADD KEY `fk_detalle_presentacion_venta` (`id_producto_presentacion`);

--
-- Indices de la tabla `detalle_ventas_lotes`
--
ALTER TABLE `detalle_ventas_lotes`
  ADD PRIMARY KEY (`id_detalle_venta_lote`),
  ADD KEY `fk_asignacion_venta_detalle` (`id_detalle_venta`),
  ADD KEY `fk_asignacion_venta_lote` (`id_lote`);

--
-- Indices de la tabla `lotes`
--
ALTER TABLE `lotes`
  ADD PRIMARY KEY (`id_lote`),
  ADD UNIQUE KEY `uk_producto_lote` (`id_producto`,`numero_lote`);

--
-- Indices de la tabla `permisos_modulos`
--
ALTER TABLE `permisos_modulos`
  ADD PRIMARY KEY (`rol`,`modulo`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `fk_producto_categoria` (`id_categoria`);

--
-- Indices de la tabla `producto_presentaciones`
--
ALTER TABLE `producto_presentaciones`
  ADD PRIMARY KEY (`id_producto_presentacion`),
  ADD UNIQUE KEY `uk_producto_presentacion` (`id_producto`,`id_catalogo_presentacion`),
  ADD KEY `fk_presentacion_catalogo` (`id_catalogo_presentacion`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id_proveedor`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`nombre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `fk_usuarios_rol` (`rol`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`),
  ADD KEY `fk_venta_usuario` (`id_usuario`),
  ADD KEY `fk_venta_cliente` (`id_cliente`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `catalogo_presentaciones`
--
ALTER TABLE `catalogo_presentaciones`
  MODIFY `id_catalogo_presentacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `compras`
--
ALTER TABLE `compras`
  MODIFY `id_compra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `detalle_compras`
--
ALTER TABLE `detalle_compras`
  MODIFY `id_detalle_compra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id_detalle_venta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas_lotes`
--
ALTER TABLE `detalle_ventas_lotes`
  MODIFY `id_detalle_venta_lote` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `lotes`
--
ALTER TABLE `lotes`
  MODIFY `id_lote` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

--
-- AUTO_INCREMENT de la tabla `producto_presentaciones`
--
ALTER TABLE `producto_presentaciones`
  MODIFY `id_producto_presentacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=276;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alertas_atendidas`
--
ALTER TABLE `alertas_atendidas`
  ADD CONSTRAINT `fk_alerta_usuario` FOREIGN KEY (`atendida_por`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `fk_compra_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`id_proveedor`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_compra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_compras`
--
ALTER TABLE `detalle_compras`
  ADD CONSTRAINT `fk_detalle_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id_compra`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_lote_compra` FOREIGN KEY (`id_lote`) REFERENCES `lotes` (`id_lote`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_presentacion_compra` FOREIGN KEY (`id_producto_presentacion`) REFERENCES `producto_presentaciones` (`id_producto_presentacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_producto_compra` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `fk_detalle_lote_venta` FOREIGN KEY (`id_lote`) REFERENCES `lotes` (`id_lote`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_presentacion_venta` FOREIGN KEY (`id_producto_presentacion`) REFERENCES `producto_presentaciones` (`id_producto_presentacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_producto_venta` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_venta` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_ventas_lotes`
--
ALTER TABLE `detalle_ventas_lotes`
  ADD CONSTRAINT `fk_asignacion_venta_detalle` FOREIGN KEY (`id_detalle_venta`) REFERENCES `detalle_ventas` (`id_detalle_venta`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asignacion_venta_lote` FOREIGN KEY (`id_lote`) REFERENCES `lotes` (`id_lote`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `lotes`
--
ALTER TABLE `lotes`
  ADD CONSTRAINT `fk_lote_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `producto_presentaciones`
--
ALTER TABLE `producto_presentaciones`
  ADD CONSTRAINT `fk_presentacion_catalogo` FOREIGN KEY (`id_catalogo_presentacion`) REFERENCES `catalogo_presentaciones` (`id_catalogo_presentacion`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_presentacion_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol`) REFERENCES `roles` (`nombre`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
