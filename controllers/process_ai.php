<?php
require_once "../config/bd.php";
require_once "../config/openai.php";

header("Content-Type: text/xml");
date_default_timezone_set('America/Mexico_City');

$telefono = trim($_POST['From'] ?? '');
$texto    = trim($_POST['SpeechResult'] ?? '');

echo "<Response>";

// =========================
// FUNCIONES AUXILIARES
// =========================
function limpiarTexto($texto) {
    return trim(mb_strtolower($texto, 'UTF-8'));
}

function convertirNumero($texto) {
    $texto = limpiarTexto($texto);

    $map = [
        "uno" => 1, "una" => 1,
        "dos" => 2,
        "tres" => 3,
        "cuatro" => 4,
        "cinco" => 5,
        "seis" => 6,
        "siete" => 7,
        "ocho" => 8,
        "nueve" => 9,
        "diez" => 10,
        "once" => 11,
        "doce" => 12,
        "trece" => 13,
        "catorce" => 14,
        "quince" => 15,
        "dieciseis" => 16,
        "dieciséis" => 16,
        "diecisiete" => 17,
        "dieciocho" => 18,
        "diecinueve" => 19,
        "veinte" => 20
    ];

    if (is_numeric($texto)) {
        return intval($texto);
    }

    foreach ($map as $palabra => $numero) {
        if (strpos($texto, $palabra) !== false) {
            return $numero;
        }
    }

    if (preg_match('/\b(\d+)\b/', $texto, $match)) {
        return intval($match[1]);
    }

    return null;
}

function normalizarHora($hora, $minutos = "00", $periodo = "") {
    $hora = intval($hora);
    $minutos = str_pad($minutos, 2, '0', STR_PAD_LEFT);
    $periodo = limpiarTexto($periodo);

    if ($periodo === 'am') {
        if ($hora === 12) {
            $hora = 0;
        }
    } elseif ($periodo === 'pm') {
        if ($hora !== 12) {
            $hora += 12;
        }
    } elseif ($periodo === 'mañana') {
        if ($hora === 12) {
            $hora = 0;
        }
    } elseif (in_array($periodo, ['tarde', 'noche', 'madrugada'], true)) {
        if ($hora < 12) {
            $hora += 12;
        }
    }

    return sprintf('%02d:%02d', $hora, $minutos);
}

function extraerHoraTexto($texto) {
    $texto = limpiarTexto($texto);

    if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/u', $texto, $match)) {
        return normalizarHora($match[1], $match[2] ?? '00', $match[3]);
    }

    if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s+de la\s+(mañana|tarde|noche|madrugada)\b/u', $texto, $match)) {
        return normalizarHora($match[1], $match[2] ?? '00', $match[3]);
    }

    if (preg_match('/\b(\d{1,2})(?::(\d{2}))\b/u', $texto, $match)) {
        return normalizarHora($match[1], $match[2]);
    }

    return null;
}

function parseFechaHoraManual($texto) {
    $texto = limpiarTexto($texto);
    $fecha = new DateTime();

    if (strpos($texto, 'pasado mañana') !== false) {
        $fecha->modify('+2 days');
    } elseif (strpos($texto, 'mañana') !== false) {
        $fecha->modify('+1 day');
    } elseif (strpos($texto, 'hoy') !== false) {
        // misma fecha
    } else {
        $dias = [
            'lunes' => 'monday',
            'martes' => 'tuesday',
            'miércoles' => 'wednesday',
            'miercoles' => 'wednesday',
            'jueves' => 'thursday',
            'viernes' => 'friday',
            'sábado' => 'saturday',
            'sabado' => 'saturday',
            'domingo' => 'sunday'
        ];

        foreach ($dias as $diaEs => $diaEn) {
            if (strpos($texto, $diaEs) !== false) {
                $fecha->modify('next ' . $diaEn);
                break;
            }
        }
    }

    $hora = extraerHoraTexto($texto);

    if ($hora) {
        return $fecha->format('d/m/y') . ' ' . $hora;
    }

    // Si no hay hora clara, no cierres la fecha aún como válida
    return "";
}

function obtenerReservaTemporal($conn, $telefono) {
    $query = "SELECT nombre, personas, fecha_hora
              FROM reservas_temp
              WHERE telefono = $1
              LIMIT 1";

    $result = pg_query_params($conn, $query, [$telefono]);

    if ($result && pg_num_rows($result) > 0) {
        return pg_fetch_assoc($result);
    }

    return [
        "nombre" => "",
        "personas" => null,
        "fecha_hora" => ""
    ];
}

function guardarReservaTemporal($conn, $telefono, $nombre, $personas, $fechaHora) {
    $query = "
        INSERT INTO reservas_temp (telefono, nombre, personas, fecha_hora, updated_at)
        VALUES ($1, $2, $3, $4, NOW())
        ON CONFLICT (telefono)
        DO UPDATE SET
            nombre = EXCLUDED.nombre,
            personas = EXCLUDED.personas,
            fecha_hora = EXCLUDED.fecha_hora,
            updated_at = NOW()
    ";

    return pg_query_params($conn, $query, [$telefono, $nombre, $personas, $fechaHora]);
}

function borrarReservaTemporal($conn, $telefono) {
    $query = "DELETE FROM reservas_temp WHERE telefono = $1";
    pg_query_params($conn, $query, [$telefono]);
}

function responderGather($mensaje) {
    $mensajeSeguro = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');

    echo '<Gather input="speech"
                  language="es-ES"
                  speechTimeout="auto"
                  method="POST"
                  action="https://ivr-3knv.onrender.com/controllers/process_ai.php">';
    echo '<Say voice="Polly.Lupe">' . $mensajeSeguro . '</Say>';
    echo '</Gather>';
    echo "</Response>";
    exit;
}

// =========================
// VALIDACIONES BÁSICAS
// =========================
if (!$conn) {
    echo "<Say voice='Polly.Lupe'>Error de conexión con la base de datos.</Say>";
    echo "</Response>";
    exit;
}

if (empty($telefono)) {
    echo "<Say voice='Polly.Lupe'>No se pudo identificar el número de teléfono.</Say>";
    echo "</Response>";
    exit;
}

if (empty($texto)) {
    responderGather("No entendí. Por favor repite.");
}

// Debug opcional
@file_put_contents(
    "log_ai.txt",
    "Telefono: {$telefono}\nTexto: {$texto}\nFecha: " . date('Y-m-d H:i:s') . "\n-----------------\n",
    FILE_APPEND
);

// =========================
// CARGAR CONTEXTO PREVIO
// =========================
$temp = obtenerReservaTemporal($conn, $telefono);

$nombreAnterior    = trim($temp["nombre"] ?? "");
$personasAnterior  = !empty($temp["personas"]) ? intval($temp["personas"]) : null;
$fechaHoraAnterior = trim($temp["fecha_hora"] ?? "");

// =========================
// IA PROCESA LO NUEVO
// =========================
$nombreNuevo = "";
$personasNuevo = null;
$fechaHoraNueva = "";

$resultadoIA = extraerDatosReservaIA($texto, date("d/m/y H:i"));

if (is_array($resultadoIA) && !empty($resultadoIA["ok"])) {
    $data = $resultadoIA["data"] ?? [];

    $nombreNuevo = trim($data["nombre"] ?? "");

    if (!empty($data["personas"])) {
        $personasNuevo = convertirNumero((string)$data["personas"]);
    }

    $fechaHoraNueva = trim($data["fecha_hora"] ?? "");
}

// =========================
// FALLBACKS MANUALES
// =========================
if (!$personasNuevo) {
    $personasManual = convertirNumero($texto);
    if ($personasManual) {
        $personasNuevo = $personasManual;
    }
}

if (empty($fechaHoraNueva)) {
    $fechaManual = parseFechaHoraManual($texto);
    if (!empty($fechaManual)) {
        $fechaHoraNueva = $fechaManual;
    }
}

// Si el texto parece un nombre simple, lo tomamos como nombre
if (empty($nombreNuevo)) {
    $textoLimpio = limpiarTexto($texto);

    $frasesNoNombre = [
        'para', 'persona', 'personas', 'mañana', 'pasado mañana', 'hoy',
        'a las', 'am', 'pm', 'reserva', 'reservar', 'mesa', 'quiero'
    ];

    $pareceNombre = true;
    foreach ($frasesNoNombre as $frase) {
        if (strpos($textoLimpio, $frase) !== false) {
            $pareceNombre = false;
            break;
        }
    }

    if ($pareceNombre && str_word_count($textoLimpio) <= 4) {
        $nombreNuevo = trim($texto);
    }
}

// =========================
// MEZCLAR DATOS NUEVOS + ANTERIORES
// =========================
$nombreFinal    = !empty($nombreAnterior) ? $nombreAnterior : $nombreNuevo;
$personasFinal  = !empty($personasAnterior) ? $personasAnterior : $personasNuevo;
$fechaHoraFinal = !empty($fechaHoraAnterior) ? $fechaHoraAnterior : $fechaHoraNueva;

// Si ahora sí llegó un dato que antes faltaba, úsalo
if (empty($nombreFinal) && !empty($nombreNuevo)) {
    $nombreFinal = $nombreNuevo;
}

if (empty($personasFinal) && !empty($personasNuevo)) {
    $personasFinal = $personasNuevo;
}

if (empty($fechaHoraFinal) && !empty($fechaHoraNueva)) {
    $fechaHoraFinal = $fechaHoraNueva;
}

// =========================
// GUARDAR PROGRESO TEMPORAL
// =========================
$okTemp = guardarReservaTemporal($conn, $telefono, $nombreFinal, $personasFinal, $fechaHoraFinal);

if (!$okTemp) {
    echo "<Say voice='Polly.Lupe'>Hubo un error guardando los datos de la reserva.</Say>";
    echo "</Response>";
    exit;
}

// =========================
// PREGUNTAR SOLO LO QUE FALTA
// =========================
if (empty($nombreFinal)) {
    responderGather("Perfecto. ¿A nombre de quién hago la reserva?");
}

if (empty($personasFinal)) {
    responderGather("¿Para cuántas personas es la reserva?");
}

if (empty($fechaHoraFinal)) {
    responderGather("¿Para qué día y hora deseas la reserva?");
}

// =========================
// GUARDAR RESERVA FINAL
// =========================
$queryFinal = "INSERT INTO reservas (telefono, nombre, personas, fecha_hora)
               VALUES ($1, $2, $3, $4)";

$resultFinal = pg_query_params($conn, $queryFinal, [
    $telefono,
    $nombreFinal,
    $personasFinal,
    $fechaHoraFinal
]);

if (!$resultFinal) {
    echo "<Say voice='Polly.Lupe'>La información fue entendida, pero hubo un error al guardar la reserva final.</Say>";
    echo "</Response>";
    exit;
}

// =========================
// TELEGRAM
// =========================
$token = getenv("TELEGRAM_TOKEN");
$chat_id = "-4994123276";

$mensaje = "📞 Nueva reserva By Wifer:\n";
$mensaje .= "Telefono: $telefono\n";
$mensaje .= "Nombre: $nombreFinal\n";
$mensaje .= "Personas: $personasFinal\n";
$mensaje .= "Fecha y hora: $fechaHoraFinal";

$url = "https://api.telegram.org/bot{$token}/sendMessage";

$telegramResponse = @file_get_contents($url . "?" . http_build_query([
    "chat_id" => $chat_id,
    "text" => $mensaje
]));

// =========================
// LIMPIAR TEMPORAL
// =========================
borrarReservaTemporal($conn, $telefono);

// =========================
// RESPUESTA FINAL
// =========================
$nombreSeguro = htmlspecialchars($nombreFinal, ENT_QUOTES, 'UTF-8');
$fechaSeguro  = htmlspecialchars($fechaHoraFinal, ENT_QUOTES, 'UTF-8');

echo "<Say voice='Polly.Lupe'>Perfecto {$nombreSeguro}. Tu reserva para {$personasFinal} personas el {$fechaSeguro} ha sido confirmada. Gracias por llamar a By Wifer.</Say>";
echo "</Response>";
?>