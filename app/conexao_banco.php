<?php
class db {
    private $host = 'mysql-service';
    private $usuario = 'root';
    private $senha = '';
    private $bd = 'payments_lab';

    public function connect_mysql() {
        try {
            $con = mysqli_connect($this->host, $this->usuario, $this->senha, $this->bd);
            mysqli_set_charset($con, 'utf8');
            return $con;
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            error_log("Falha critica de Banco de Dados: " . $e->getMessage());
            die("Sistema de pagamentos temporariamente indisponivel.");
        }
    }
}
?>
