CREATE TABLE usuarios (
    -- IDENTIFICADORES DEL SISTEMA
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_usuario VARCHAR(20) UNIQUE NOT NULL, 
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- DATOS DE IDENTIFICACIÓN DEL TITULAR
    tipo_persona ENUM('NATURAL', 'JURIDICA') DEFAULT 'NATURAL',
    dni_ruc VARCHAR(11) NOT NULL,
    nombres VARCHAR(100),
    apellidos VARCHAR(100),
    razon_social VARCHAR(200),
    estado_civil ENUM('SOLTERO', 'CASADO', 'VIUDO', 'DIVORCIADO', 'CONVIVIENTE') NULL,
    
    -- VINCULACIÓN CON LA ZONA (SECTOR / AA.HH)
    -- Aquí está la magia: Con este ID sabemos si es AA.HH, Urb, y a qué Sector pertenece.
    zona_id INT NOT NULL, 

    -- DIRECCIÓN EXACTA (Dentro de esa Zona)
    direccion_tipo_via ENUM('CALLE', 'AVENIDA', 'JIRON', 'PASAJE', 'CARRETERA'),
    direccion_nombre VARCHAR(100),
    direccion_numero VARCHAR(20),
    direccion_manzana VARCHAR(10), 
    direccion_lote VARCHAR(10),    
    
    -- UBICACIÓN GEOGRÁFICA (Fijos por defecto)
    distrito VARCHAR(50) DEFAULT 'Amarilis',
    provincia VARCHAR(50) DEFAULT 'Huánuco',
    departamento VARCHAR(50) DEFAULT 'Huánuco',
    referencia_domicilio TEXT,
    
    -- CARACTERÍSTICAS DEL PREDIO
    numero_pisos INT DEFAULT 1,
    tipo_predio ENUM('DOMESTICO', 'COMERCIAL', 'INDUSTRIAL', 'MIXTO') NOT NULL,
    detalle_negocio VARCHAR(150),
    
    -- DATOS DE CONTACTO
    telefono_fijo VARCHAR(15),
    celular_wsp VARCHAR(15), 
    contacto_referencia VARCHAR(100),
    email VARCHAR(100),
    clave_web VARCHAR(255), 
    
    -- INFORMACIÓN DEL SERVICIO
    tipo_servicio ENUM('AGUA', 'DESAGUE', 'AMBOS') NOT NULL,
    condicion_titular ENUM('PROPIETARIO', 'INQUILINO', 'CUIDADOR', 'OTRO') NOT NULL,
    observaciones_servicio TEXT,
    
    -- OBSERVACIONES TÉCNICAS
    incidencias_detectadas TEXT, 
    observaciones_tecnico TEXT,
    
    -- ARCHIVOS
    foto_predio_url VARCHAR(255),
    
    -- ESTADO DEL REGISTRO
    estado_usuario ENUM('ACTIVO', 'SUSPENDIDO', 'EN_REVISION') DEFAULT 'EN_REVISION',

    latitud DECIMAL(10, 8) NULL,
    longitud DECIMAL(11, 8) NULL,


    -- CONEXIÓN (LLAVE FORÁNEA)
    -- Esto asegura que solo puedas registrar usuarios en Zonas que existen realmente
    CONSTRAINT fk_usuario_zona FOREIGN KEY (zona_id) REFERENCES zonas(id)

    
);

CREATE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    rol ENUM('ADMIN', 'USER') DEFAULT 'USER',
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
);

CREATE TABLE sectores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL, -- Ej: "Sector 1", "Sector 2 - Amarilis"
    descripcion VARCHAR(200),    -- Ej: "Abastecido por Reservorio R1"
    estado TINYINT DEFAULT 1
);

CREATE TABLE zonas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sector_id INT NOT NULL,      -- Relación: Pertenece a un Sector
    nombre VARCHAR(150) NOT NULL,-- Ej: "AA.HH. Los Girasoles"
    tipo ENUM('AA.HH', 'URBANIZACION', 'ASOCIACION', 'JIRON', 'OTRO') DEFAULT 'AA.HH',
    
    FOREIGN KEY (sector_id) REFERENCES sectores(id) ON DELETE RESTRICT
);




