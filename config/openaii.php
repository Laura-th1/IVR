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

   $prompt = "Extrae los datos de una reserva de restaurante.

Fecha actual: $fechaActual

Mensaje: \"$texto\"

Devuelve SOLO JSON válido:

{
  \"nombre\": \"string\",
  \"personas\": number|null,
  \"fecha_hora\": \"dd/mm/yy HH:MM\"
}

IMPORTANTE:
- El nombre es la persona que dice 'soy', 'mi nombre es' o 'a nombre de'
- Ejemplos:
  - 'soy Laura' → nombre: Laura
  - 'a nombre de Carlos' → nombre: Carlos
- personas debe ser número
- interpreta fechas y horas correctamente
- si falta algo, déjalo vacío o null
- NO escribas nada fuera del JSON";


    $payload = [
        "model" => "gpt-4.1-mini",
        "temperature" => 0,
        "response_format" => ["type" => "json_object"], // 🔥 CLAVE
        "messages" => [
            [
                "role" => "system",
                "content" => "Eres un sistema que extrae datos de reservas y responde SOLO JSON válido."
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

    // 🔥 LOG PARA DEBUG
    @file_put_contents(
        __DIR__ . "/log_openai_raw.txt",
        "INPUT: $texto\nRESPONSE: $content\n\n",
        FILE_APPEND
    );

    $data = json_decode($content, true);

    // 🔥 fallback por si viene sucio
    if (!is_array($data)) {
        if (preg_match('/\{.*\}/s', $content, $match)) {
            $data = json_decode($match[0], true);
        }
    }

    if (!is_array($data)) {
        return [
            "ok" => false,
            "error" => "La IA no devolvió JSON válido",
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