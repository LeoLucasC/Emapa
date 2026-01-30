<?php
// C:\xampp\htdocs\emapa_api\conexion.php

$host = "localhost";
$usuario = "root";      // Usuario por defecto de XAMPP
$clave = "";            // En XAMPP la contraseña suele estar vacía
$bd = "db_emapa";       // El nombre que le pusiste en phpMyAdmin

$conn = new mysqli($host, $usuario, $clave, $bd);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->set_charset("utf8");
echo "Conexión exitosa";