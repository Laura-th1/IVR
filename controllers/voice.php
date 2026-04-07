<?php
header("Content-Type: text/xml");

echo "<Response>";
echo "<Say voice='Polly.Lupe'>Hola, dime tu nombre después del tono.</Say>";

echo "<Gather input='speech' method='POST'
      language='es-ES'
      speechTimeout='auto'
      action='https://ivr-3knv.onrender.com/controllers/process.php?step=1'>";
echo "</Gather>";

echo "</Response>";