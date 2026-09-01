<?php
require_once 'conexao_banco.php';

$status = isset($_GET['status']) ? $_GET['status'] : null;
$valor  = isset($_GET['valor']) ? $_GET['valor'] : null;

$db  = new db();
$con = $db->connect_mysql();
$res = mysqli_query($con, "SELECT * FROM pagamentos ORDER BY id DESC LIMIT 10");
$historico = mysqli_fetch_all($res, MYSQLI_ASSOC);
mysqli_close($con);
?>
<!DOCTYPE HTML>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Simulador de Pagamentos - Lab SRE</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
    <style>
        body { background-color: #f5f8fa; font-family: "Helvetica Neue",Helvetica,Arial,sans-serif; }
        .navbar { background-color: #fff; border-bottom: 1px solid rgba(0,0,0,0.15); margin-bottom: 0; }
        .navbar-brand { font-weight: bold; color: #0a3d62 !important; }
        .jumbotron { background-color: #0a3d62; color: #fff; margin-top: 30px; border-radius: 16px; text-align: center; padding: 30px; }
        .btn-primary { background-color: #0a3d62; border-radius: 10px; font-weight: bold; border: none; padding: 10px 20px; }
        .panel { border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .badge-sucesso { background-color: #16a34a; }
        .badge-erro { background-color: #dc2626; }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="container">
        <span class="navbar-brand">💳 Simulador de Pagamentos - Lab SRE</span>
    </div>
</nav>

<div class="container">
    <div class="jumbotron">
        <h2>Simulação de Transferência Bancária</h2>
        <p>Cada envio gera um trace OTel (Cloud Trace + Datadog) e alimenta métricas em /metrics.php (Zabbix/Prometheus).</p>
    </div>

    <?php if ($status): ?>
        <div class="alert alert-<?= $status === 'sucesso' ? 'success' : 'danger' ?>">
            Pagamento de R$ <?= htmlspecialchars($valor) ?>
            <?= $status === 'sucesso' ? 'processado com sucesso.' : 'falhou no processamento.' ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h4>Nova simulação</h4>
                    <form action="processa_pagamento.php" method="POST">
                        <div class="form-group">
                            <label>Conta origem</label>
                            <input type="text" name="conta_origem" class="form-control" placeholder="Ex: 1001-2" required>
                        </div>
                        <div class="form-group">
                            <label>Conta destino</label>
                            <input type="text" name="conta_destino" class="form-control" placeholder="Ex: 2002-9" required>
                        </div>
                        <div class="form-group">
                            <label>Valor (R$)</label>
                            <input type="number" step="0.01" min="0.01" name="valor" class="form-control" placeholder="Ex: 150.00" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Simular pagamento</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h4>Últimas simulações</h4>
                    <table class="table">
                        <thead>
                            <tr><th>Origem</th><th>Destino</th><th>Valor</th><th>Status</th><th>Quando</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($historico as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['conta_origem']) ?></td>
                                <td><?= htmlspecialchars($p['conta_destino']) ?></td>
                                <td>R$ <?= number_format($p['valor'], 2, ',', '.') ?></td>
                                <td><span class="label badge-<?= $p['status'] ?>"><?= $p['status'] ?></span></td>
                                <td><?= $p['criado_em'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
