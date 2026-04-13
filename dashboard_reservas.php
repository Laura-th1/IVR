<?php

require_once __DIR__ . "/../config/bd.php";

$query = "
SELECT 
    telefono,
    MAX(CASE WHEN pregunta = 'Nombre' THEN respuesta END) as nombre,
    MAX(CASE WHEN pregunta = 'Personas' THEN respuesta END) as personas,
    MAX(CASE WHEN pregunta = 'FechaHora' THEN respuesta END) as fecha_hora
FROM respuestas
GROUP BY telefono
ORDER BY MAX(fecha) DESC
";

$result = pg_query($conn, $query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reservas - By Wifer</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        th { background: #222; color: white; }
    </style>
</head>
<body>

<h2>📞 Panel de Reservas</h2>

<table>
    <tr>
        <th>Teléfono</th>
        <th>Nombre</th>
        <th>Personas</th>
        <th>Fecha y Hora</th>
    </tr>

    <?php while ($row = pg_fetch_assoc($result)) { ?>
        <tr>
            <td><?= $row['telefono'] ?></td>
            <td><?= $row['nombre'] ?></td>
            <td><?= $row['personas'] ?></td>
            <td><?= $row['fecha_hora'] ?></td>
        </tr>
    <?php } ?>

</table>

</body>
</html>