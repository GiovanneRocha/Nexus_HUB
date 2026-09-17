<?php
/**
 * Histórico de atividades por empresa.
 *
 * Centraliza a criação da tabela e o registro de eventos que aparecem na linha do
 * tempo da página "Visualizar Empresa" (pages/perfil_empresa.php): OS criadas,
 * aprovadas, reprovadas, veículos adicionados à frota e notas manuais digitadas
 * pelo usuário (chat).
 */

function nexusGarantirTabelaHistoricoEmpresas(PDO $pdo): void
{
    static $verificado = false;
    if ($verificado) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS historico_atividades_empresas (
        id INT NOT NULL AUTO_INCREMENT,
        empresa_id INT NOT NULL,
        tipo VARCHAR(100) NOT NULL,
        mensagem TEXT NOT NULL,
        data_registro DATETIME NOT NULL,
        usuario VARCHAR(255) NOT NULL,
        PRIMARY KEY (id),
        KEY empresa_id (empresa_id),
        CONSTRAINT historico_atividades_empresas_ibfk_1 FOREIGN KEY (empresa_id) REFERENCES empresas_cadastradas (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $verificado = true;
}

/**
 * Registra um evento na linha do tempo da empresa.
 *
 * @param string $tipo Categoria do evento: registro, frota, relatorio, aprovacao, reprovacao, manual...
 */
function nexusRegistrarHistoricoEmpresa(PDO $pdo, int $empresaId, string $tipo, string $mensagem, string $usuario = 'Sistema'): void
{
    $mensagem = trim($mensagem);
    if ($empresaId <= 0 || $mensagem === '') {
        return;
    }

    nexusGarantirTabelaHistoricoEmpresas($pdo);

    $stmt = $pdo->prepare('INSERT INTO historico_atividades_empresas (empresa_id, tipo, mensagem, data_registro, usuario) VALUES (:empresa_id, :tipo, :mensagem, NOW(), :usuario)');
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':tipo' => $tipo !== '' ? $tipo : 'manual',
        ':mensagem' => $mensagem,
        ':usuario' => $usuario !== '' ? $usuario : 'Sistema',
    ]);
}

/**
 * Retorna o nome do usuário informado numa requisição (form POST ou AJAX),
 * com um valor padrão de segurança quando nada é enviado.
 */
function nexusUsuarioAtual(array $origem): string
{
    $usuario = trim((string) ($origem['usuario'] ?? ''));
    return $usuario !== '' ? $usuario : 'Sistema';
}
