<?php
$host = "dpg-d6v112s50q8c739c5g8g-a";
$db   = "bd_ivr";
$user = "bd_ivr_user";
$pass = "qyQqnpz9HAABB9e4nr0BVTy0JGXzcYGU";

$conn = pg_connect("host=$host dbname=$db user=$user password=$pass");

if (!$conn) {
    die("Error de conexión");
}

?>
