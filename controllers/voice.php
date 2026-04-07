<?php
header("Content-Type: text/xml");

echo '
<Response>
    <Say voice="Polly.Lupe">Hola, dime tu nombre después del tono.</Say>
    <Play>https://api.twilio.com/cowbell.mp3</Play>
    <Gather input="speech"
            method="POST"
            language="es-ES"
            speechTimeout="auto"
            action="https://ivr-3knv.onrender.com/controllers/process.php?step=1">
    </Gather>
</Response>
';