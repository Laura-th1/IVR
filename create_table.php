<?php
require_once "./config/bd.php";

// Crear tabla respuestas
$sqlRespuestas = "CREATE TABLE IF NOT EXISTS respuestas (
    id SERIAL PRIMARY KEY,
    telefono VARCHAR(20),
    pregunta VARCHAR(255),
    respuesta TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

// Crear tabla reservas_temp
$sqlReservasTemp = "CREATE TABLE IF NOT EXISTS reservas_temp (
    telefono VARCHAR(30) PRIMARY KEY,
    nombre VARCHAR(150),
    personas INTEGER,
    fecha_hora VARCHAR(30),
    updated_at TIMESTAMP DEFAULT NOW()
);";

// Crear tabla reservas (FINAL)
$sqlReservas = "CREATE TABLE IF NOT EXISTS reservas (
    id SERIAL PRIMARY KEY,
    telefono VARCHAR(30),
    nombre VARCHAR(150),
    personas INTEGER,
    fecha_hora VARCHAR(30),
    created_at TIMESTAMP DEFAULT NOW()
);";

try {
    $conn->exec($sqlRespuestas);
    echo "✓ Tabla 'respuestas' creada/verificada<br>";
    
    $conn->exec($sqlReservasTemp);
    echo "✓ Tabla 'reservas_temp' creada/verificada<br>";
    
    $conn->exec($sqlReservas);
    echo "✓ Tabla 'reservas' creada/verificada<br>";
    
} catch (PDOException $e) {
    echo "Error al crear tablas: " . $e->getMessage();
}
?>