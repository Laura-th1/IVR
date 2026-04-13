<?php
header("Content-Type: text/xml");

echo "<Response>";

echo "<Say voice='Polly.Lupe'>
Hola, bienvenido a By Wifer. Puedes decir tu reserva completa después del tono.
</Say>";



echo '<Gather 
        input="speech"
        language="es-ES"
        speechTimeout="auto"
        method="POST"
        action="https://ivr-3knv.onrender.com/controllers/process_ai.php/">
      </Gather>';

echo "</Response>";