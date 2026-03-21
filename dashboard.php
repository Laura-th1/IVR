<?php
require_once __DIR__ . "/config/bd.php";
$result = pg_query($conn, "SELECT * FROM respuestas ORDER BY fecha DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard IVR</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f6f9;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        th {
            background: #007bff;
            color: white;
        }
        tr:hover {
            background: #f1f1f1;
        }
    </style>
</head>
<body>

<h1>📊 Dashboard IVR</h1>

<input type="text" id="searchInput" placeholder="🔍 Buscar por teléfono, pregunta o respuesta...">

<table id="tabla">
    <tr>
        <th>Teléfono</th>
        <th>Pregunta</th>
        <th>Respuesta</th>
        <th>Fecha</th>
    </tr>

    <?php while ($row = pg_fetch_assoc($result)) { ?>
        <tr>
            <td><?php echo $row['telefono']; ?></td>
            <td><?php echo $row['pregunta']; ?></td>
            <td><?php echo $row['respuesta']; ?></td>
            <td><?php echo $row['fecha']; ?></td>
        </tr>
    <?php } ?>
</table>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    let filtro = this.value.toLowerCase();
    let filas = document.querySelectorAll("#tabla tr");

    filas.forEach((fila, index) => {
        if (index === 0) return; // saltar encabezado

        let texto = fila.innerText.toLowerCase();
        fila.style.display = texto.includes(filtro) ? "" : "none";
    });
});
</script>

</body>
</html>