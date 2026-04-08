<?php
header("Content-Type: text/xml");

echo "<Response>";
echo "<Say voice='Polly.Lupe'>Hola, Bienvenido al restaurante, dime tu nombre completo para reservar.</Say>";

echo "<Gather input='speech' method='POST'
      language='es-ES'
      speechTimeout='auto'
      action='https://ivr-3knv.onrender.com/controllers/process.php?step=1'>";
echo "</Gather>";

echo "</Response>";