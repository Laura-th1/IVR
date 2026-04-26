<?php

require_once __DIR__ . "/config/bd.php";

try {
    $query = "
    SELECT
        r.id_reserva,
        c.telefono,
        COALESCE(c.nombre, 'Sin nombre') AS nombre,
        r.cantidad_personas AS personas,
        r.fecha_reserva,
        r.hora_reserva,
        r.origen,
        r.estado
    FROM reservas r
    LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
    ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    $rows = [];
}
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
        <th>Fecha</th>
        <th>Hora</th>
    </tr>

    <?php foreach ($rows as $row) { ?>
        <tr>
            <td><?= htmlspecialchars($row['telefono'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['nombre'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['personas'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['fecha_reserva'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['hora_reserva'] ?? '') ?></td>
        </tr>
    <?php } ?>

</table>

</body>
</html>