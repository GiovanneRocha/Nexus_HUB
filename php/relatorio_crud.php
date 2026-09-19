<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validacoes.php';

header('Content-Type: application/json; charset=utf-8');
$pdo = nexusDb();

function nexusRelatorioResponder(bool $sucesso, array $extra = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge(['sucesso' => $sucesso], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'salvar') {
    $osId = (int) ($_POST['os_id'] ?? 0);
    $relatorioJson = (string) ($_POST['relatorio_dados'] ?? '');

    if ($osId <= 0) {
        nexusRelatorioResponder(false, ['mensagem' => 'OS inválida.'], 400);
    }

    $decodificado = json_decode($relatorioJson, true);
    if (!is_array($decodificado)) {
        nexusRelatorioResponder(false, ['mensagem' => 'Dados do relatório inválidos.'], 400);
    }

    // Limite de tamanho por segurança (evita blobs absurdos vindos de erro de cliente).
    if (strlen($relatorioJson) > 500000) {
        nexusRelatorioResponder(false, ['mensagem' => 'Conteúdo do relatório excede o tamanho permitido.'], 400);
    }

    $check = $pdo->prepare('SELECT id FROM ordens_servico WHERE id = :id LIMIT 1');
    $check->execute([':id' => $osId]);
    if (!$check->fetch()) {
        nexusRelatorioResponder(false, ['mensagem' => 'Ordem de serviço não encontrada.'], 404);
    }

    $stmt = $pdo->prepare('UPDATE ordens_servico SET relatorio_dados = :dados WHERE id = :id');
    $stmt->execute([':dados' => $relatorioJson, ':id' => $osId]);

    nexusRelatorioResponder(true, ['mensagem' => 'Relatório salvo com sucesso.']);
}

if ($action === 'carregar') {
    $osId = (int) ($_GET['os_id'] ?? 0);
    if ($osId <= 0) {
        nexusRelatorioResponder(false, ['mensagem' => 'OS inválida.'], 400);
    }

    $stmt = $pdo->prepare('SELECT relatorio_dados FROM ordens_servico WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $osId]);
    $linha = $stmt->fetch();

    if (!$linha) {
        nexusRelatorioResponder(false, ['mensagem' => 'Ordem de serviço não encontrada.'], 404);
    }

    $dados = $linha['relatorio_dados'] ? json_decode($linha['relatorio_dados'], true) : null;
    nexusRelatorioResponder(true, ['dados' => $dados]);
}

nexusRelatorioResponder(false, ['mensagem' => 'Ação inválida.'], 400);
