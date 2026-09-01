CREATE TABLE IF NOT EXISTS pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conta_origem  VARCHAR(20) NOT NULL,
    conta_destino VARCHAR(20) NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    status ENUM('sucesso','erro') NOT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);
