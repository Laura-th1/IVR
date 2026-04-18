<?php
header("Content-Type: text/xml");

echo '<Response>
<Gather input="speech"
        language="es-ES"
        speechTimeout="auto"
        method="POST"
        action="https://ivr-3knv.onrender.com/controllers/process_ai.php">
    <Say voice="Polly.Lupe">
        Hola, gracias por llamar a By Wifer. Dime tu reserva completa, por ejemplo:
        Enrique, mañana a las ocho, para cuatro personas.
    </Say>
</Gather>
<Say voice="Polly.Lupe">No recibí respuesta. Por favor vuelve a llamar.</Say>
</Response>';
?>