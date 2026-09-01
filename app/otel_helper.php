<?php
// Envia um span OTLP/HTTP (JSON) diretamente via curl, sem SDK/Composer.
// Objetivo: manter o build da imagem simples e a instrumentacao sem dependencias externas.

function gerar_hex($bytes) {
    return bin2hex(random_bytes($bytes));
}

function enviar_trace_otel($nome_operacao, $status_ok, $atributos = []) {
    $endpoint = getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'http://otel-collector:4318';

    $trace_id = gerar_hex(16);
    $span_id  = gerar_hex(8);
    $inicio_ns = (int) (microtime(true) * 1e9);
    usleep(random_int(5000, 40000)); // simula latencia de processamento do "sistema bancario"
    $fim_ns = (int) (microtime(true) * 1e9);

    $attrs = [];
    foreach ($atributos as $chave => $valor) {
        $attrs[] = ["key" => $chave, "value" => ["stringValue" => (string) $valor]];
    }

    $payload = [
        "resourceSpans" => [[
            "resource" => [
                "attributes" => [
                    ["key" => "service.name", "value" => ["stringValue" => "payments-simulator"]]
                ]
            ],
            "scopeSpans" => [[
                "scope" => ["name" => "payments-simulator-php"],
                "spans" => [[
                    "traceId" => $trace_id,
                    "spanId" => $span_id,
                    "name" => $nome_operacao,
                    "kind" => 2,
                    "startTimeUnixNano" => (string) $inicio_ns,
                    "endTimeUnixNano" => (string) $fim_ns,
                    "attributes" => $attrs,
                    "status" => ["code" => $status_ok ? 1 : 2]
                ]]
            ]]
        ]]
    ];

    $ch = curl_init(rtrim($endpoint, '/') . '/v1/traces');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
    @curl_exec($ch);
    curl_close($ch);
}
?>
