<?php
require_once 'conexao_banco.php';
require_once 'otel_helper.php';

$origem  = trim($_POST['conta_origem']  ?? '');
$destino = trim($_POST['conta_destino'] ?? '');
$valor   = (float) ($_POST['valor'] ?? 0);

$status = 'sucesso';

if ($valor <= 0 || $origem === '' || $destino === '' || $origem === $destino) {
    $status = 'erro';
}

if ($status === 'sucesso' && random_int(1, 100) <= 5) {
    $status = 'erro';
}

$db  = new db();
$con = $db->connect_mysql();

$stmt = mysqli_prepare($con, "INSERT INTO pagamentos (conta_origem, conta_destino, valor, status) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssds", $origem, $destino, $valor, $status);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($con);

enviar_trace_otel('processa_pagamento', $status === 'sucesso', [
    'payment.origem'  => $origem,
    'payment.destino' => $destino,
    'payment.valor'   => $valor,
    'payment.status'  => $status,
]);

header("Location: index.php?status=" . urlencode($status) . "&valor=" . urlencode($valor));
exit;
