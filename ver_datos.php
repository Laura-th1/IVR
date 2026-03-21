<?php
require_once "config/bd.php";

$result = pg_query($conn, "SELECT * FROM respuestas");

while ($row = pg_fetch_assoc($result)) {
    echo $row['telefono'] . " - " . $row['pregunta'] . " - " . $row['respuesta'] . "<br>";
}
?>