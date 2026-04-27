<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once "../config/bd.php";
require_once "../config/openaii.php";
require_once "../config/languages.php";

header("Content-Type: text/xml; charset=UTF-8");
date_default_timezone_set('America/Mexico_City');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo "<Response>";

$telefono = trim($_POST['From'] ?? '');
$texto    = trim($_POST['SpeechResult'] ?? '');

// =========================
// DETECTAR IDIOMA
// =========================
$idioma = detectarIdioma($texto); // 'es' o 'en'

// =========================
// LOG DEBUG
// =========================
@file_put_contents(
    __DIR__ . "/log_ai.txt",
    "Fecha: " . date('Y-m-d H:i:s') . "\n" .
    "Idioma: $idioma\n" .
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

function preguntarYSalir($mensaje, $idioma = 'es') {
    $mensajeSeguro = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
    $lenguaje = ($idioma === 'en') ? 'en-US' : 'es-ES';
    
    echo '<Gather
            input="speech"
            language="' . $lenguaje . '"
            speechTimeout="auto"
            method="POST"
            action="https://ivr-3knv.onrender.com/controllers/process_ai.php"
            timeout="5">';
    echo '<Say voice="Polly.Lupe">' . $mensajeSeguro . '</Say>';
    echo '</Gather>';
    echo '<Say voice="Polly.Lupe">' . ($idioma === 'en' ? 'I did not understand your response.' : 'No entendí su respuesta.') . '</Say>';
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

    // PRIMERO: Buscar número después de palabras clave de personas
    if (preg_match('/(?:para|de)\s+(\d+|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once|doce|trece|catorce|quince|dieciseis|dieciséis|diecisiete|dieciocho|diecinueve|veinte)\s+(?:personas|pax|person)/ui', $texto, $match)) {
        $numero = strtolower($match[1]);
        return is_numeric($numero) ? intval($numero) : ($map[$numero] ?? null);
    }

    // SEGUNDO: Si ya es un número directo
    if (is_numeric($texto)) {
        return intval($texto);
    }

    // TERCERO: Buscar palabra número
    foreach ($map as $palabra => $numero) {
        if (strpos($texto, $palabra) !== false) {
            return $numero;
        }
    }

    // CUARTO: Buscar números dentro del texto (evitar horas)
    if (preg_match('/para\s+(\d+)|(\d+)\s+personas/i', $texto, $match)) {
        $numero = $match[1] ?? $match[2];
        return intval($numero);
    }

    // FALLBACK: último recurso - buscar el primer número (pero NO después de "las")
    if (preg_match('/(?<!las\s)(?<!a\s)(?<!\s)(\d+)(?!\s*(?:am|pm|de la))/u', $texto, $match)) {
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
    responderYSalir(obtenerMensaje($idioma, 'error_conexion'));
}

if (empty($telefono)) {
    responderYSalir(obtenerMensaje($idioma, 'error_telefono'));
}

if (empty($texto)) {
    preguntarYSalir(obtenerMensaje($idioma, 'error_sin_respuesta'), $idioma);
}

// =========================
// CARGAR CONTEXTO PREVIO
// =========================
$temp = obtenerReservaTemporal($conn, $telefono);

$nombreAnterior    = trim($temp["nombre"] ?? "");
$personasAnterior  = isset($temp["personas"]) && $temp["personas"] !== '' ? intval($temp["personas"]) : null;
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

    if (isset($data["personas"]) && $data["personas"] !== '') {
        if ($idioma === 'en') {
            $personasNuevo = convertirNumeroIngles((string)$data["personas"]);
        } else {
            $personasNuevo = convertirNumero((string)$data["personas"]);
        }
    }

    $fechaHoraNueva = trim($data["fecha_hora"] ?? "");
}

// =========================
// FALLBACKS MANUALES
// =========================
if ($personasNuevo === null) {
    if ($idioma === 'en') {
        $personasManual = convertirNumeroIngles($texto);
    } else {
        $personasManual = convertirNumero($texto);
    }
    if ($personasManual !== null) {
        $personasNuevo = $personasManual;
    }
}

if (empty($fechaHoraNueva)) {
    if ($idioma === 'en') {
        $fechaManual = parseFechaHoraManualIngles($texto);
    } else {
        $fechaManual = parseFechaHoraManual($texto);
    }
    if (!empty($fechaManual)) {
        $fechaHoraNueva = $fechaManual;
    }
}

if (empty($nombreNuevo)) {
    if ($idioma === 'en') {
        // Extracción para inglés
        $nombreNuevo = extraerNombreIngles($texto);
        
        // ESTRATEGIA 2: Si la respuesta es sólo un nombre corto
        if (empty($nombreNuevo) && preg_match('/^\s*([A-Za-z]+(?:\s+[A-Za-z]+)?)\s*$/', $texto, $match)) {
            $posibleNombre = trim($match[1]);
            $nombresComunes = ['I', 'Hi', 'Hello', 'Want', 'Need', 'Book', 'Make', 'For', 'Reservation', 'Table', 'People'];
            if (!in_array($posibleNombre, $nombresComunes, true) && strlen($posibleNombre) > 2) {
                $nombreNuevo = ucwords(strtolower($posibleNombre));
            }
        }

        // Si no encuentra por patrón explícito, intenta buscar palabra capitalizada
        if (empty($nombreNuevo)) {
            if (preg_match('/\b([A-Z][a-z]+)\b/', $texto, $match)) {
                $nombre = trim($match[1]);
                $nombresComunes = ['I', 'Hi', 'Hello', 'Want', 'Need', 'Book', 'Make', 'For'];
                if (!in_array($nombre, $nombresComunes) && strlen($nombre) > 2) {
                    $nombreNuevo = $nombre;
                }
            }
        }
    } else {
        // Extracción para español
        $textoLimpio = limpiarTexto($texto);
        $palabrasComunes = ['hola', 'quiero', 'quisiera', 'necesito', 'tengo', 'puedo', 'reserva', 'reservar', 'personas', 'mesa', 'para', 'a', 'de', 'con'];

        // ESTRATEGIA 1: Buscar patrones explícitos "soy", "me llamo", "a nombre de"
        if (preg_match('/(?:soy|me llamo|a nombre de)\s+([a-záéíóúñ]+)/ui', $texto, $match)) {
            $nombreNuevo = trim($match[1]);
        }
        // ESTRATEGIA 2: Buscar nombre entre comas "Hola, Laura, quiero"
        elseif (preg_match('/,\s*([a-záéíóúñ]+)\s*,/ui', $texto, $match)) {
            $nombreNuevo = trim($match[1]);
        }
        // ESTRATEGIA 3: Si la respuesta es sólo un nombre o dos palabras de nombre
        elseif (preg_match('/^\s*([a-záéíóúñ]+(?:\s+[a-záéíóúñ]+)?)\s*$/ui', $texto, $match)) {
            $posibleNombre = trim($match[1]);
            $nombreMinusculas = strtolower($posibleNombre);
            if (!in_array($nombreMinusculas, $palabrasComunes, true) && strlen($nombreMinusculas) > 2) {
                $nombreNuevo = ucwords($nombreMinusculas);
            }
        }
        // ESTRATEGIA 4: Buscar palabra capitalizada "Hola Laura quiero" (primera mayúscula)
        elseif (preg_match('/\b([A-Z][a-záéíóúñ]+)\b/u', $texto, $match)) {
            $palabraComun = strtolower($match[1]);
            if (!in_array($palabraComun, $palabrasComunes, true) && strlen($palabraComun) > 2) {
                $nombreNuevo = trim($match[1]);
            }
        }
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
$personasFinal  = $personasNuevo !== null ? $personasNuevo : $personasAnterior;
$fechaHoraFinal = !empty($fechaHoraNueva) ? $fechaHoraNueva : $fechaHoraAnterior;

if (empty($nombreFinal) && !empty($nombreNuevo)) {
    $nombreFinal = $nombreNuevo;
}

if ($personasFinal === null && $personasNuevo !== null) {
    $personasFinal = $personasNuevo;
}

if (empty($fechaHoraFinal) && !empty($fechaHoraNueva)) {
    $fechaHoraFinal = $fechaHoraNueva;
}
// GUARDAR TEMPORAL (para mantener contexto)
// =========================
$okTemp = guardarReservaTemporal($conn, $telefono, $nombreFinal, $personasFinal, $fechaHoraFinal);

if (!$okTemp) {
    responderYSalir(obtenerMensaje($idioma, 'error_guardar_temp'));
}

// =========================
// PREGUNTAR LO QUE FALTA (UNA SOLA COSA)
// =========================
if (empty($nombreFinal)) {
    preguntarYSalir(obtenerMensaje($idioma, 'pregunta_nombre'), $idioma);
} elseif ($personasFinal === null) {
    preguntarYSalir(obtenerMensaje($idioma, 'pregunta_personas'), $idioma);
} elseif (empty($fechaHoraFinal)) {
    preguntarYSalir(obtenerMensaje($idioma, 'pregunta_fecha'), $idioma);
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
            responderYSalir(obtenerMensaje($idioma, 'error_registro_cliente'));
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
        responderYSalir(obtenerMensaje($idioma, 'error_guardar_final'));
    }
} catch (Exception $e) {
    error_log("Error guardando reserva final: " . $e->getMessage());
    responderYSalir(obtenerMensaje($idioma, 'error_guardar_final'));
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

$mensajeConfirmacion = obtenerMensaje($idioma, 'confirmacion', [
    'nombre' => $nombreSeguro,
    'personas' => $personasFinal,
    'fecha' => $fechaSeguro,
    'hora' => $horaSegura
]);

echo "<Say voice='Polly.Lupe'>" . $mensajeConfirmacion . "</Say>";
echo "</Response>";
?>