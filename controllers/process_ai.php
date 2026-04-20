<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once "../config/bd.php";
require_once "../config/openaii.php";

header("Content-Type: text/xml; charset=UTF-8");
date_default_timezone_set('America/Mexico_City');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo "<Response>";

$telefono = trim($_POST['From'] ?? '');
$texto    = trim($_POST['SpeechResult'] ?? '');

// =========================
// LOG DEBUG
// =========================
@file_put_contents(
    __DIR__ . "/log_ai.txt",
    "Fecha: " . date('Y-m-d H:i:s') . "\n" .
    "POST: " . print_r($_POST, true) . "\n" .
    "-----------------------------------\n",
    FILE_APPEND
);

// =========================
// FUNCIONES AUXILIARES
// =========================
function responderYSalir($mensaje) {
    $mensajeSeguro = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
    echo '<Say voice="Polly.Lupe">' . $mensajeSeguro . '</Say>';
    echo "</Response>";
    exit;
}

function preguntarYSalir($mensaje) {
    $mensajeSeguro = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
    echo '<Gather
            input="speech"
            language="es-ES"
            speechTimeout="auto"
            method="POST"
            action="https://ivr-3knv.onrender.com/controllers/process_ai.php"
            timeout="5">';
    echo '<Say voice="Polly.Lupe">' . $mensajeSeguro . '</Say>';
    echo '</Gather>';
    echo '<Say voice="Polly.Lupe">No entendí su respuesta.</Say>';
    echo "</Response>";
    exit;
}

function limpiarTexto($texto) {
    return trim(mb_strtolower($texto, 'UTF-8'));
}

function convertirNumero($texto) {
    $texto = limpiarTexto((string)$texto);

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

    if (preg_match('/\b(\d+)\b/u', $texto, $match)) {
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

    return "";
}

function obtenerReservaTemporal($conn, $telefono) {
    $query = "SELECT nombre, personas, fecha_hora
              FROM reservas_temp
              WHERE telefono = ?
              LIMIT 1";

    try {
        $result = $conn->prepare($query);
        $result->execute([$telefono]);
        $row = $result->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }
    } catch (Exception $e) {
        error_log("Error en obtenerReservaTemporal: " . $e->getMessage());
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
        VALUES (?, ?, ?, ?, NOW())
        ON CONFLICT (telefono)
        DO UPDATE SET
            nombre = EXCLUDED.nombre,
            personas = EXCLUDED.personas,
            fecha_hora = EXCLUDED.fecha_hora,
            updated_at = NOW()
    ";

    try {
        $stmt = $conn->prepare($query);
        return $stmt->execute([$telefono, $nombre, $personas, $fechaHora]);
    } catch (Exception $e) {
        error_log("Error en guardarReservaTemporal: " . $e->getMessage());
        return false;
    }
}

function borrarReservaTemporal($conn, $telefono) {
    $query = "DELETE FROM reservas_temp WHERE telefono = ?";
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute([$telefono]);
    } catch (Exception $e) {
        error_log("Error en borrarReservaTemporal: " . $e->getMessage());
    }
}

// =========================
// VALIDACIONES BÁSICAS
// =========================
if (!$conn) {
    responderYSalir("Error de conexión con la base de datos.");
}

if (empty($telefono)) {
    responderYSalir("No se pudo identificar el número de teléfono.");
}

if (empty($texto)) {
    preguntarYSalir("No entendí. Por favor repite tu reserva.");
}

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
$nombreNuevo    = "";
$personasNuevo  = null;
$fechaHoraNueva = "";

$resultadoIA = null;

if (function_exists('extraerDatosReservaIA')) {
    $resultadoIA = extraerDatosReservaIA($texto, date("d/m/y H:i"));
}

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
// DETECTAR FRASE COMPLETA (IA)
// =========================
if (!empty($nombreNuevo) && !empty($personasNuevo) && !empty($fechaHoraNueva)) {
    $nombreFinal = $nombreNuevo;
    $personasFinal = $personasNuevo;
    $fechaHoraFinal = $fechaHoraNueva;
}

// =========================
// MEZCLAR DATOS
// =========================
// 🔥 SIEMPRE priorizar lo nuevo (clave para frases completas)
$nombreFinal    = !empty($nombreNuevo) ? $nombreNuevo : $nombreAnterior;
$personasFinal  = !empty($personasNuevo) ? $personasNuevo : $personasAnterior;
$fechaHoraFinal = !empty($fechaHoraNueva) ? $fechaHoraNueva : $fechaHoraAnterior;

if (empty($nombreFinal) && !empty($nombreNuevo)) {
    $nombreFinal = $nombreNuevo;
}

if (empty($personasFinal) && !empty($personasNuevo)) {
    $personasFinal = $personasNuevo;
}

if (empty($fechaHoraFinal) && !empty($fechaHoraNueva)) {
    $fechaHoraFinal = $fechaHoraNueva;
}
// GUARDAR TEMPORAL (para mantener contexto)
// =========================
$okTemp = guardarReservaTemporal($conn, $telefono, $nombreFinal, $personasFinal, $fechaHoraFinal);

if (!$okTemp) {
    responderYSalir("Hubo un error guardando la información temporal de la reserva.");
}

// =========================
// PREGUNTAR LO QUE FALTA (UNA SOLA COSA)
if (empty($nombreFinal)) {
    preguntarYSalir("Perfecto. ¿A nombre de quién hago la reserva?");
} elseif (empty($personasFinal)) {
    preguntarYSalir("¿Para cuántas personas es la reserva?");
} elseif (empty($fechaHoraFinal)) {
    preguntarYSalir("¿Para qué día y hora deseas la reserva?");
}

// =========================
// GUARDAR RESERVA FINAL
// =========================
try {
    // Buscar id_cliente por teléfono
    $queryCliente = "SELECT id_cliente FROM clientes WHERE telefono = ? LIMIT 1";
    $stmtCliente = $conn->prepare($queryCliente);
    $stmtCliente->execute([$telefono]);
    $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

    // Si no existe cliente, crear uno automáticamente
    if (!$cliente) {
        $queryInsertCliente = "INSERT INTO clientes (nombre, telefono) VALUES (?, ?)";
        $stmtInsertCliente = $conn->prepare($queryInsertCliente);
        
        // Usar nombreFinal si existe, si no usar "Cliente" + teléfono
        $nombreCliente = !empty($nombreFinal) ? $nombreFinal : "Cliente IVR";
        
        $resultInsert = $stmtInsertCliente->execute([$nombreCliente, $telefono]);
        
        if (!$resultInsert) {
            responderYSalir("Hubo un error al registrar tu información. Por favor intenta más tarde.");
        }
        
        // Obtener el id_cliente del cliente recién creado
        $stmtCliente = $conn->prepare($queryCliente);
        $stmtCliente->execute([$telefono]);
        $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);
    }

    $idCliente = $cliente['id_cliente'];

    // Separar fecha y hora (formato actual: "d/m/y H:i")
    $partes = explode(' ', $fechaHoraFinal);
    $fechaParte = $partes[0] ?? ''; // "d/m/y"
    $horaParte = $partes[1] ?? '00:00'; // "H:i"

    // Convertir fecha de d/m/y a YYYY-MM-DD
    if (!empty($fechaParte)) {
        $fechaObj = DateTime::createFromFormat('d/m/y', $fechaParte);
        if ($fechaObj) {
            $fechaReserva = $fechaObj->format('Y-m-d');
        } else {
            $fechaReserva = date('Y-m-d'); // fallback a hoy
        }
    } else {
        $fechaReserva = date('Y-m-d');
    }

    // Insertar en reservas
    $queryFinal = "INSERT INTO reservas (id_cliente, fecha_reserva, hora_reserva, cantidad_personas, origen, estado)
                   VALUES (?, ?, ?, ?, ?, ?)";

    $stmtFinal = $conn->prepare($queryFinal);
    $resultFinal = $stmtFinal->execute([
        $idCliente,
        $fechaReserva,
        $horaParte,
        $personasFinal,
        'IVR',
        'confirmada'
    ]);

    if (!$resultFinal) {
        responderYSalir("Entendí la reserva, pero hubo un error al guardarla.");
    }
} catch (Exception $e) {
    error_log("Error guardando reserva final: " . $e->getMessage());
    responderYSalir("Entendí la reserva, pero hubo un error al guardarla.");
}

// =========================
// TELEGRAM
// =========================
$token = getenv("TELEGRAM_TOKEN");
$chat_id = "-4994123276";

if (!empty($token)) {
    $mensaje = "📞 Nueva reserva By Wifer:\n";
    $mensaje .= "Teléfono: $telefono\n";
    $mensaje .= "Nombre: $nombreFinal\n";
    $mensaje .= "Personas: $personasFinal\n";
    $mensaje .= "Fecha: $fechaReserva\n";
    $mensaje .= "Hora: $horaParte";

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    @file_get_contents($url . "?" . http_build_query([
        "chat_id" => $chat_id,
        "text" => $mensaje
    ]));
}

// =========================
// LIMPIAR TEMPORAL
// =========================
borrarReservaTemporal($conn, $telefono);

// =========================
// RESPUESTA FINAL
// =========================
$nombreSeguro = htmlspecialchars($nombreFinal, ENT_QUOTES, 'UTF-8');
$fechaSeguro  = htmlspecialchars($fechaReserva, ENT_QUOTES, 'UTF-8');
$horaSegura   = htmlspecialchars($horaParte, ENT_QUOTES, 'UTF-8');

echo "<Say voice='Polly.Lupe'>Perfecto {$nombreSeguro}. Tu reserva para {$personasFinal} personas el {$fechaSeguro} a las {$horaSegura} ha sido confirmada. Gracias por llamar a By Wifer.</Say>";
echo "</Response>";
?>