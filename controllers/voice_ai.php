<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header("Content-Type: text/xml; charset=UTF-8");

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<Response>
    <Gather
        input="speech"
        language="es-ES"
        speechTimeout="auto"
        method="POST"
        action="https://ivr-3knv.onrender.com/controllers/process_ai.php"
        timeout="5">
        <Say voice="Polly.Lupe"> 
            Hello, thank you for calling By The Restaurant, how can I help you today?
        </Say>
    </Gather>
    
</Response>';
?>