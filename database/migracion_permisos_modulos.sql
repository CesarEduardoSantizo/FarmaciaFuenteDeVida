USE farmacia_fuente_vida;

CREATE TABLE IF NOT EXISTS permisos_modulos (
    rol VARCHAR(30) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    puede_acceder BOOLEAN NOT NULL DEFAULT FALSE,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rol, modulo)
);

CREATE TABLE IF NOT EXISTS roles (
    nombre VARCHAR(30) NOT NULL PRIMARY KEY,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO roles (nombre)
SELECT DISTINCT rol FROM usuarios WHERE rol IS NOT NULL AND rol <> '';

INSERT IGNORE INTO roles (nombre)
SELECT DISTINCT rol FROM permisos_modulos WHERE rol IS NOT NULL AND rol <> '';

INSERT IGNORE INTO roles (nombre) VALUES ('Administrador'), ('Vendedor');

SET @fk_usuarios_rol_existe = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND CONSTRAINT_NAME = 'fk_usuarios_rol'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_usuarios_rol = IF(
    @fk_usuarios_rol_existe = 0,
    'ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol) REFERENCES roles(nombre) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt_fk_usuarios_rol FROM @sql_fk_usuarios_rol;
EXECUTE stmt_fk_usuarios_rol;
DEALLOCATE PREPARE stmt_fk_usuarios_rol;

INSERT IGNORE INTO permisos_modulos (rol, modulo, puede_acceder)
SELECT roles.nombre, modulos.modulo,
       CASE
           WHEN roles.nombre = 'Administrador' THEN TRUE
           WHEN roles.nombre = 'Vendedor' AND modulos.modulo <> 'usuarios' THEN TRUE
           ELSE FALSE
       END
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
