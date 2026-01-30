<?php
// usuarios_api.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        handleCreate($conn);
        break;
    case 'PUT':
        handleUpdate($conn);
        break;
    case 'DELETE':
        handleDelete($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Método no permitido"]);
        break;
}

// --- 1. OBTENER (GET) ---
function handleGet($conn) {
    // Si piden un ID específico (?id=1)
    if (isset($_GET['id'])) {
        // Hacemos JOIN para traer el nombre de la ZONA y el SECTOR automáticamente
        $sql = "SELECT u.*, z.nombre as nombre_zona, s.nombre as nombre_sector 
                FROM usuarios u 
                LEFT JOIN zonas z ON u.zona_id = z.id 
                LEFT JOIN sectores s ON z.sector_id = s.id 
                WHERE u.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        echo json_encode($data ?: ["error" => "Usuario no encontrado"]);
    } 
    // Si piden buscar por DNI (?dni=12345678)
    elseif (isset($_GET['dni'])) {
        $sql = "SELECT * FROM usuarios WHERE dni_ruc = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $_GET['dni']);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_assoc() ?: ["error" => "DNI no encontrado"]);
    }
    // Si no, devolvemos TODOS (Limitado a los ultimos 100 para no saturar)
    else {
        $sql = "SELECT u.id, u.codigo_usuario, u.nombres, u.apellidos, u.dni_ruc, z.nombre as zona 
                FROM usuarios u 
                LEFT JOIN zonas z ON u.zona_id = z.id 
                ORDER BY u.id DESC LIMIT 100";
        $result = $conn->query($sql);
        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        echo json_encode($usuarios);
    }
}

// --- 2. CREAR (POST) ---
function handleCreate($conn) {
    $data = json_decode(file_get_contents("php://input"), true);

    // 1. Validaciones básicas obligatorias
    if (!isset($data['codigo_usuario']) || !isset($data['dni_ruc']) || !isset($data['zona_id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan datos obligatorios (codigo, dni, zona_id)"]);
        return;
    }

    // Preparamos la consulta gigante (Solo pondré los campos principales para el ejemplo, tú puedes agregar todos)
    $sql = "INSERT INTO usuarios (
        codigo_usuario, tipo_persona, dni_ruc, nombres, apellidos, razon_social, 
        zona_id, direccion_tipo_via, direccion_nombre, direccion_numero, direccion_manzana, direccion_lote,
        tipo_predio, tipo_servicio, condicion_titular, estado_usuario, latitud, longitud
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    // Asignar variables para manejar nulos (PHP 7+ null coalescing)
    $codigo = $data['codigo_usuario'];
    $tipo_p = $data['tipo_persona'] ?? 'NATURAL';
    $dni    = $data['dni_ruc'];
    $nombres = $data['nombres'] ?? null;
    $apellidos = $data['apellidos'] ?? null;
    $razon  = $data['razon_social'] ?? null;
    $zona   = $data['zona_id']; // ¡IMPORTANTE! Aquí recibimos el ID (número) de la tabla zonas
    $dir_via = $data['direccion_tipo_via'] ?? 'CALLE';
    $dir_nom = $data['direccion_nombre'] ?? '';
    $dir_num = $data['direccion_numero'] ?? '';
    $dir_mz  = $data['direccion_manzana'] ?? '';
    $dir_lt  = $data['direccion_lote'] ?? '';
    $t_predio = $data['tipo_predio'] ?? 'DOMESTICO';
    $t_serv   = $data['tipo_servicio'] ?? 'AGUA';
    $cond     = $data['condicion_titular'] ?? 'PROPIETARIO';
    $estado   = 'EN_REVISION';
    $lat      = $data['latitud'] ?? null;
    $lon      = $data['longitud'] ?? null;

    // "s" = string, "i" = integer, "d" = double (decimal)
    // El orden de las letras debe coincidir EXACTAMENTE con los ? de arriba
    // s s s s s s i s s s s s s s s s d d (18 variables)
    $stmt->bind_param("ssssssisssssssssdd", 
        $codigo, $tipo_p, $dni, $nombres, $apellidos, $razon, 
        $zona, $dir_via, $dir_nom, $dir_num, $dir_mz, $dir_lt,
        $t_predio, $t_serv, $cond, $estado, $lat, $lon
    );

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "Usuario registrado", "id" => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error SQL: " . $stmt->error]);
    }
}

// --- 3. ACTUALIZAR (PUT) ---
function handleUpdate($conn) {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Falta ID"]);
        return;
    }

    // Ejemplo: Actualizar solo el estado y observaciones técnicas
    $sql = "UPDATE usuarios SET estado_usuario = ?, observaciones_tecnico = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $data['estado_usuario'], $data['observaciones_tecnico'], $data['id']);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Usuario actualizado"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error al actualizar"]);
    }
}

// --- 4. ELIMINAR (DELETE) ---
function handleDelete($conn) {
    $id = $_GET['id'] ?? null;
    if (!$id) { 
        http_response_code(400); echo json_encode(["error" => "Falta ID"]); return; 
    }

    $sql = "DELETE FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(["message" => "Usuario eliminado"]);
    } else {
        echo json_encode(["error" => "Error al eliminar"]);
    }
}
?>