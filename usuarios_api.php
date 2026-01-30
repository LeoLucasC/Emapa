<?php
// usuarios_api.php
// Configuración de Cabeceras (CORS y Métodos)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Incluir la conexión a la base de datos
require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        handleCreate($conn); // Aquí está la lógica de la IMAGEN
        break;
    case 'PUT':
        handleUpdate($conn); // Actualizar datos (JSON)
        break;
    case 'DELETE':
        handleDelete($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Método no permitido"]);
        break;
}

// ==========================================
// 1. FUNCIÓN OBTENER (GET)
// ==========================================
function handleGet($conn) {
    // A) Si piden un usuario especifico: ?id=1
    if (isset($_GET['id'])) {
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
        
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode($data ?: ["error" => "Usuario no encontrado"]);
    } 
    // B) Si piden buscar por DNI: ?dni=77777777
    elseif (isset($_GET['dni'])) {
        $sql = "SELECT u.*, z.nombre as nombre_zona FROM usuarios u 
                LEFT JOIN zonas z ON u.zona_id = z.id 
                WHERE dni_ruc = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $_GET['dni']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode($result->fetch_assoc() ?: ["error" => "DNI no encontrado"]);
    }
    // C) Listar todos (Por defecto)
    else {
        // Limitamos a 50 para no saturar la pantalla
        $sql = "SELECT u.id, u.codigo_usuario, u.dni_ruc, u.nombres, u.apellidos, 
                       u.foto_predio_url, z.nombre as zona, s.nombre as sector
                FROM usuarios u 
                LEFT JOIN zonas z ON u.zona_id = z.id 
                LEFT JOIN sectores s ON z.sector_id = s.id
                ORDER BY u.id DESC LIMIT 50";
        
        $result = $conn->query($sql);
        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode($usuarios);
    }
}

// ==========================================
// 2. FUNCIÓN CREAR CON FOTO (POST)
// ==========================================
function handleCreate($conn) {
    // NOTA: No usamos json_decode aquí porque llega como Form-Data (Multipart)
    
    // Validar campos obligatorios mínimos
    if (!isset($_POST['dni_ruc']) || !isset($_POST['zona_id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan datos obligatorios (dni_ruc, zona_id)"]);
        return;
    }

    // --- LÓGICA DE SUBIDA DE IMAGEN ---
    $url_final_foto = null;

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $directorio = "images/";
        
        // Crear carpeta si no existe
        if (!file_exists($directorio)) {
            mkdir($directorio, 0777, true);
        }

        // Generar nombre único: tiempo_random.jpg
        $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $nombre_archivo = time() . "_" . uniqid() . "." . $extension;
        $ruta_destino = $directorio . $nombre_archivo;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $ruta_destino)) {
            // Construir URL completa para guardar en BD
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $domain = $_SERVER['HTTP_HOST'];
            
            // Detectar subcarpeta (ej: /emapa_api/)
            $script_path = dirname($_SERVER['SCRIPT_NAME']);
            
            // Resultado ej: http://localhost/emapa_api/images/foto.jpg
            $url_final_foto = "$protocol://$domain$script_path/$ruta_destino";
        }
    }

    // --- INSERTAR EN BASE DE DATOS ---
    $sql = "INSERT INTO usuarios (
        codigo_usuario, dni_ruc, zona_id, nombres, apellidos, 
        direccion_tipo_via, direccion_nombre, direccion_manzana, direccion_lote,
        foto_predio_url, latitud, longitud, tipo_predio
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    // Recoger variables (usando operador ?? para evitar errores si están vacías)
    $codigo = $_POST['codigo_usuario'] ?? date('Ymd-His'); // Genera un código temporal si no envían uno
    $dni    = $_POST['dni_ruc'];
    $zona   = $_POST['zona_id'];
    $nom    = $_POST['nombres'] ?? '';
    $ape    = $_POST['apellidos'] ?? '';
    $via    = $_POST['direccion_tipo_via'] ?? 'CALLE';
    $calle  = $_POST['direccion_nombre'] ?? '';
    $mz     = $_POST['direccion_manzana'] ?? '';
    $lt     = $_POST['direccion_lote'] ?? '';
    $lat    = $_POST['latitud'] ?? null;
    $lon    = $_POST['longitud'] ?? null;
    $t_pred = $_POST['tipo_predio'] ?? 'DOMESTICO';

    // Tipos de datos para bind_param: s=string, i=integer, d=double
    $stmt->bind_param("ssisssssssdds", 
        $codigo, $dni, $zona, $nom, $ape, 
        $via, $calle, $mz, $lt, 
        $url_final_foto, $lat, $lon, $t_pred
    );

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            "message" => "Usuario creado exitosamente", 
            "id" => $conn->insert_id,
            "foto" => $url_final_foto
        ]);
    } else {
        http_response_code(500);
        // Verificar si es error de duplicado (ej: codigo o dni repetido)
        if ($conn->errno == 1062) {
            echo json_encode(["error" => "El Código o DNI ya existen en el sistema"]);
        } else {
            echo json_encode(["error" => "Error SQL: " . $stmt->error]);
        }
    }
}

// ==========================================
// 3. FUNCIÓN ACTUALIZAR (PUT)
// ==========================================
function handleUpdate($conn) {
    // PUT recibe JSON
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el ID para actualizar"]);
        return;
    }

    // Ejemplo: Actualizar estado y observaciones
    $sql = "UPDATE usuarios SET estado_usuario = ?, observaciones_tecnico = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $estado = $data['estado_usuario'] ?? 'EN_REVISION';
    $obs    = $data['observaciones_tecnico'] ?? '';
    
    $stmt->bind_param("ssi", $estado, $obs, $data['id']);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Usuario actualizado"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error al actualizar"]);
    }
}

// ==========================================
// 4. FUNCIÓN ELIMINAR (DELETE)
// ==========================================
function handleDelete($conn) {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Falta ID"]);
        return;
    }

    $sql = "DELETE FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Usuario eliminado"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error al eliminar"]);
    }
}
?>