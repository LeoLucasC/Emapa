<?php
// users_api.php
header("Access-Control-Allow-Origin: *"); // Permite acceso desde cualquier lado (CORS)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST': // CREAR USUARIO
        handleCreate($conn);
        break;
    case 'GET':  // OBTENER USUARIOS
        handleGet($conn);
        break;
    case 'PUT':  // ACTUALIZAR USUARIO
        handleUpdate($conn);
        break;
    case 'DELETE': // ELIMINAR USUARIO
        handleDelete($conn);
        break;
    default:
        echo json_encode(["error" => "Método no permitido"]);
        break;
}

// --- FUNCIONES ---

function handleCreate($conn) {
    $data = json_decode(file_get_contents("php://input"), true);

    // Validar datos mínimos
    if (!isset($data['username']) || !isset($data['password']) || !isset($data['rol'])) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan datos (username, password, rol)"]);
        return;
    }

    // Encriptar contraseña (Nunca guardar texto plano)
    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);

    $sql = "INSERT INTO users (username, password_hash, rol) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $data['username'], $password_hash, $data['rol']);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "Usuario creado", "id" => $conn->insert_id]);
    } else {
        http_response_code(500);
        // Error 1062 es duplicidad (username ya existe)
        if ($conn->errno == 1062) {
             echo json_encode(["error" => "El nombre de usuario ya existe"]);
        } else {
             echo json_encode(["error" => "Error al crear: " . $stmt->error]);
        }
    }
}

function handleGet($conn) {
    // Si envían ?id=1, devuelve solo uno. Si no, devuelve todos.
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $sql = "SELECT id, username, rol, created_at FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
    } else {
        $sql = "SELECT id, username, rol, created_at FROM users";
        $result = $conn->query($sql);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode($data);
}

function handleUpdate($conn) {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el ID del usuario a editar"]);
        return;
    }

    // Solo permitimos editar username y rol en este ejemplo
    $sql = "UPDATE users SET username = ?, rol = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $data['username'], $data['rol'], $data['id']);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Usuario actualizado correctamente"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error al actualizar"]);
    }
}

function handleDelete($conn) {
    // Se puede enviar el ID por URL (?id=1) o por JSON body
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    
    // Si no está en URL, buscamos en el body
    if (!$id) {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'] ?? null;
    }

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el ID para eliminar"]);
        return;
    }

    $sql = "DELETE FROM users WHERE id = ?";
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