<?php
header('Content-Type: text/plain; version=0.0.4');
require_once 'conexao_banco.php';

$db  = new db();
$con = $db->connect_mysql();

$total       = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS n FROM pagamentos"))['n'];
$sucesso     = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS n FROM pagamentos WHERE status='sucesso'"))['n'];
$erro        = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS n FROM pagamentos WHERE status='erro'"))['n'];
$valor_total = mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(SUM(valor),0) AS v FROM pagamentos WHERE status='sucesso'"))['v'];

mysqli_close($con);

echo "# HELP payments_total Total de simulacoes de pagamento processadas\n";
echo "# TYPE payments_total counter\n";
echo "payments_total $total\n";

echo "# HELP payments_success_total Simulacoes de pagamento com sucesso\n";
echo "# TYPE payments_success_total counter\n";
echo "payments_success_total $sucesso\n";

echo "# HELP payments_error_total Simulacoes de pagamento com erro\n";
echo "# TYPE payments_error_total counter\n";
echo "payments_error_total $erro\n";

echo "# HELP payments_amount_total_brl Soma dos valores processados com sucesso (BRL)\n";
echo "# TYPE payments_amount_total_brl counter\n";
echo "payments_amount_total_brl $valor_total\n";
