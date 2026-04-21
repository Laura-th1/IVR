<?php
// Configuración de idiomas y mensajes

$mensajes = [
    'es' => [
        'error_conexion' => 'Error de conexión con la base de datos.',
        'error_telefono' => 'No se pudo identificar el número de teléfono.',
        'error_sin_respuesta' => 'No entendí. Por favor repite tu reserva.',
        'error_guardar_temp' => 'Hubo un error guardando la información temporal de la reserva.',
        'pregunta_nombre' => 'Perfecto. ¿A nombre de quién hago la reserva?',
        'pregunta_personas' => '¿Para cuántas personas es la reserva?',
        'pregunta_fecha' => '¿Para qué día y hora deseas la reserva?',
        'error_guardar_final' => 'Entendí la reserva, pero hubo un error al guardarla.',
        'error_no_cliente' => 'No encontramos tu registro en el sistema. Por favor contacta a soporte.',
        'error_registro_cliente' => 'Hubo un error al registrar tu información. Por favor intenta más tarde.',
        'confirmacion' => 'Perfecto {nombre}. Tu reserva para {personas} personas el {fecha} a las {hora} ha sido confirmada. Gracias por llamar a By Wifer.',
    ],
    'en' => [
        'error_conexion' => 'Database connection error.',
        'error_telefono' => 'Could not identify your phone number.',
        'error_sin_respuesta' => 'I did not understand. Please try again.',
        'error_guardar_temp' => 'There was an error saving your reservation information.',
        'pregunta_nombre' => 'Perfect. What name should the reservation be under?',
        'pregunta_personas' => 'How many people is the reservation for?',
        'pregunta_fecha' => 'What day and time would you like the reservation?',
        'error_guardar_final' => 'I understood your reservation, but there was an error saving it.',
        'error_no_cliente' => 'We could not find your registration in the system. Please contact support.',
        'error_registro_cliente' => 'There was an error registering your information. Please try again later.',
        'confirmacion' => 'Perfect {nombre}. Your reservation for {personas} people on {fecha} at {hora} has been confirmed. Thank you for calling By Wifer.',
    ]
];

// Números en inglés
$numerosIngles = [
    "zero" => 0, "one" => 1, "a" => 1,
    "two" => 2,
    "three" => 3,
    "four" => 4,
    "five" => 5,
    "six" => 6,
    "seven" => 7,
    "eight" => 8,
    "nine" => 9,
    "ten" => 10,
    "eleven" => 11,
    "twelve" => 12,
    "thirteen" => 13,
    "fourteen" => 14,
    "fifteen" => 15,
    "sixteen" => 16,
    "seventeen" => 17,
    "eighteen" => 18,
    "nineteen" => 19,
    "twenty" => 20
];

// Días en inglés
$diasIngles = [
    'monday' => 'monday',
    'tuesday' => 'tuesday',
    'wednesday' => 'wednesday',
    'thursday' => 'thursday',
    'friday' => 'friday',
    'saturday' => 'saturday',
    'sunday' => 'sunday'
];

function detectarIdioma($texto) {
    // Palabras clave en español
    $palabrasES = ['para', 'personas', 'mañana', 'sábado', 'domingo', 'lunes', 'quiero', 'reserva', 'nombre'];
    // Palabras clave en inglés
    $palabrasEN = ['for', 'people', 'tomorrow', 'saturday', 'sunday', 'monday', 'want', 'reservation', 'name', 'booking'];
    
    $textoLower = strtolower($texto);
    $countES = 0;
    $countEN = 0;
    
    foreach ($palabrasES as $palabra) {
        if (strpos($textoLower, $palabra) !== false) {
            $countES++;
        }
    }
    
    foreach ($palabrasEN as $palabra) {
        if (strpos($textoLower, $palabra) !== false) {
            $countEN++;
        }
    }
    
    // Si hay más palabras en inglés, retornar 'en', si no por defecto español
    return $countEN > $countES ? 'en' : 'es';
}

function obtenerMensaje($idioma, $clave, $reemplazos = []) {
    global $mensajes;
    
    // Validar idioma, si no existe usar español por defecto
    if (!isset($mensajes[$idioma])) {
        $idioma = 'es';
    }
    
    $mensaje = $mensajes[$idioma][$clave] ?? $mensajes['es'][$clave] ?? '';
    
    // Reemplazar placeholders
    foreach ($reemplazos as $clave => $valor) {
        $mensaje = str_replace('{' . $clave . '}', $valor, $mensaje);
    }
    
    return $mensaje;
}

function convertirNumeroIngles($texto) {
    global $numerosIngles;
    
    $texto = strtolower($texto);

    // PRIMERO: Buscar número después de palabras clave en inglés
    if (preg_match('/(?:for|of)\s+(\d+|zero|one|a|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty)\s+(?:people|persons|pax)/ui', $texto, $match)) {
        $numero = strtolower($match[1]);
        return is_numeric($numero) ? intval($numero) : ($numerosIngles[$numero] ?? null);
    }

    // SEGUNDO: Si ya es un número directo
    if (is_numeric($texto)) {
        return intval($texto);
    }

    // TERCERO: Buscar palabra número en inglés
    foreach ($numerosIngles as $palabra => $numero) {
        if (strpos($texto, $palabra) !== false) {
            return $numero;
        }
    }

    // CUARTO: Buscar números dentro del texto (evitar horas)
    if (preg_match('/for\s+(\d+)|(\d+)\s+people/i', $texto, $match)) {
        $numero = $match[1] ?? $match[2];
        return intval($numero);
    }

    return null;
}

function extraerHoraTextoIngles($texto) {
    $texto = strtolower($texto);

    // Formato: "3:30 pm", "7:45 am"
    if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $texto, $match)) {
        $hora = intval($match[1]);
        $minutos = $match[2] ?? '00';
        $periodo = strtolower($match[3]);

        if ($periodo === 'pm' && $hora !== 12) {
            $hora += 12;
        } elseif ($periodo === 'am' && $hora === 12) {
            $hora = 0;
        }

        return sprintf('%02d:%02d', $hora, intval($minutos));
    }

    // Formato: "7 in the evening", "6 in the afternoon"
    if (preg_match('/\b(\d{1,2})\b\s+(?:in the\s+)?(?:morning|afternoon|evening|night)\b/i', $texto, $match)) {
        $hora = intval($match[1]);
        if ($hora < 12 && preg_match('/afternoon|evening|night/i', $texto)) {
            $hora += 12;
        }
        return sprintf('%02d:%02d', $hora, 0);
    }

    return null;
}

function parseFechaHoraManualIngles($texto) {
    $texto = strtolower($texto);
    $fecha = new DateTime();

    // Tomorrow, today, etc.
    if (strpos($texto, 'tomorrow') !== false) {
        $fecha->modify('+1 day');
    } elseif (strpos($texto, 'day after tomorrow') !== false) {
        $fecha->modify('+2 days');
    } elseif (strpos($texto, 'today') !== false) {
        // same date
    } else {
        $dias = [
            'monday' => 'monday',
            'tuesday' => 'tuesday',
            'wednesday' => 'wednesday',
            'thursday' => 'thursday',
            'friday' => 'friday',
            'saturday' => 'saturday',
            'sunday' => 'sunday'
        ];

        foreach ($dias as $diaEn => $modificador) {
            if (strpos($texto, $diaEn) !== false) {
                $fecha->modify('next ' . $modificador);
                break;
            }
        }
    }

    $hora = extraerHoraTextoIngles($texto);

    if ($hora) {
        return $fecha->format('d/m/y') . ' ' . $hora;
    }

    return "";
}
?>
