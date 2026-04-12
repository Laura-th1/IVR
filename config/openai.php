<?php

function extraerDatosReservaIA($texto, $fechaActual = null) {
    $apiKey = getenv("OPENAI_API_KEY");

    if (!$apiKey) {
        return [
            "ok" => false,
            "error" => "Falta la variable OPENAI_API_KEY en Render",
            "data" => []
        ];
    }

    if (!$fechaActual) {
        $fechaActual = date("d/m/y H:i");
    }

    $prompt = "Analiza este mensaje de un cliente que quiere hacer una reserva en un restaurante.

Fecha actual: $fechaActual

Mensaje del cliente: \"$texto\"

Devuelve SOLO JSON válido con esta estructura exacta:

{
  \"nombre\": \"\",
  \"personas\": null,
  \"fecha_hora\": \"\"
}

Reglas:
- Extrae el nombre si aparece
- Extrae la cantidad de personas si aparece
- Convierte la fecha y hora al formato dd/mm/yy HH:MM en 24 horas
- Usa la fecha actual como referencia
- 'hoy' = misma fecha
- 'mañana' = +1 día
- 'pasado mañana' = +2 días
- Si falta algún dato, déjalo vacío o null
- No expliques nada
- No pongas texto fuera del JSON";

    $payload = [
        "model" => "gpt-4.1-mini",
        "temperature" => 0,
        "messages" => [
            [
                "role" => "system",
                "content" => "Extraes datos de reservas y respondes solo JSON válido."
            ],
            [
                "role" => "user",
                "content" => $prompt
            ]
        ]
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($curlError) {
        return [
            "ok" => false,
            "error" => "Error cURL: " . $curlError,
            "data" => []
        ];
    }

    $json = json_decode($response, true);

    if ($httpCode >= 400) {
        return [
            "ok" => false,
            "error" => $json["error"]["message"] ?? "Error HTTP " . $httpCode,
            "data" => []
        ];
    }

    $content = $json["choices"][0]["message"]["content"] ?? "";

    $content = str_replace(["```json", "```"], "", $content);
    $content = trim($content);

    $data = json_decode($content, true);

    if (!is_array($data)) {
        return [
            "ok" => false,
            "error" => "La IA no devolvió un JSON válido",
            "data" => []
        ];
    }

    return [
        "ok" => true,
        "error" => null,
        "data" => [
            "nombre" => $data["nombre"] ?? "",
            "personas" => $data["personas"] ?? null,
            "fecha_hora" => $data["fecha_hora"] ?? ""
        ]
    ];
}