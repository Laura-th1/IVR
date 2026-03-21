<?php
$host = "dpg-d6v112s50q8c739c5g8g-a";
$db   = "bd_ivr";
$user = "bd_ivr_user";
$pass = "qyQqnpz9HAABB9e4nr0BVTy0JGXzcYGU";

$conn = pg_connect("host=$host dbname=$db user=$user password=$pass");

if (!$conn) {
    die("Error de conexión");
}

$sql = "CREATE TABLE IF NOT EXISTS respuestas (
    id SERIAL PRIMARY KEY,
    telefono VARCHAR(20),
    pregunta VARCHAR(255),
    respuesta TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

$result = pg_query($conn, $sql);

if ($result) {
    echo "Tabla creada correctamente";
} else {
    echo "Error al crear tabla";
}
?>