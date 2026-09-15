<?php
require_once __DIR__ . '/../../php/db.php';

$pdo = nexusDb();
$pdo->exec("CREATE TABLE IF NOT EXISTS caminhoes (
    id INT NOT NULL AUTO_INCREMENT,
    empresa_id INT DEFAULT NULL,
    nome_caminhao VARCHAR(100) DEFAULT NULL,
    modelo VARCHAR(100) DEFAULT NULL,
    placa VARCHAR(10) NOT NULL,
    ano INT DEFAULT NULL,
    cor VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$colunasVeiculo = [
    'empresa_id' => 'INT DEFAULT NULL',
    'ano' => 'INT DEFAULT NULL',
    'cor' => 'VARCHAR(50) DEFAULT NULL',
];
$stmtColuna = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "caminhoes" AND COLUMN_NAME = :coluna');
foreach ($colunasVeiculo as $coluna => $definicao) {
    $stmtColuna->execute(['coluna' => $coluna]);
    if (!(int) $stmtColuna->fetchColumn()) {
        $pdo->exec("ALTER TABLE caminhoes ADD COLUMN `{$coluna}` {$definicao}");
    }
}

$stmtRelacao = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = "caminhoes" AND CONSTRAINT_NAME = "fk_caminhoes_empresa"');
$stmtRelacao->execute();
if (!(int) $stmtRelacao->fetchColumn()) {
    $pdo->exec('ALTER TABLE caminhoes ADD CONSTRAINT fk_caminhoes_empresa FOREIGN KEY (empresa_id) REFERENCES empresas_cadastradas (id)');
}

$empresas = $pdo->query('SELECT id, nome, cnpj FROM empresas_cadastradas ORDER BY nome ASC')->fetchAll();
$mensagem = '';
$registro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM caminhoes WHERE id = :id')->execute([':id' => $id]);
            $mensagem = '<div class="alerta sucesso">Veículo removido com sucesso.</div>';
        }
    } else {
        $empresa_id = (int) ($_POST['empresa_id'] ?? 0);
        $modelo = trim((string) ($_POST['modelo'] ?? ''));
        $placa = trim(strtoupper((string) ($_POST['placa'] ?? '')));
        $ano = (int) ($_POST['ano'] ?? 0);
        $cor = trim((string) ($_POST['cor'] ?? ''));
        $empresaSelecionada = $pdo->prepare('SELECT id, nome FROM empresas_cadastradas WHERE id = :id LIMIT 1');
        $empresaSelecionada->execute([':id' => $empresa_id]);
        $empresa = $empresaSelecionada->fetch();
        $nome_caminhao = $empresa['nome'] ?? '';

        if (!$empresa || $modelo === '' || $placa === '' || $ano < 1900 || $ano > (int) date('Y') || $cor === '') {
            $mensagem = '<div class="alerta erro">Selecione uma empresa e preencha modelo, placa, ano e cor válidos.</div>';
        } else {
            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE caminhoes SET empresa_id = :empresa_id, nome_caminhao = :nome_caminhao, modelo = :modelo, placa = :placa, ano = :ano, cor = :cor WHERE id = :id');
                    $stmt->execute([
                        ':empresa_id' => $empresa_id,
                        ':nome_caminhao' => $nome_caminhao,
                        ':modelo' => $modelo,
                        ':placa' => $placa,
                        ':ano' => $ano,
                        ':cor' => $cor,
                        ':id' => $id,
                    ]);
                    $mensagem = '<div class="alerta sucesso">Veículo atualizado com sucesso.</div>';
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO caminhoes (empresa_id, nome_caminhao, modelo, placa, ano, cor) VALUES (:empresa_id, :nome_caminhao, :modelo, :placa, :ano, :cor)');
                $stmt->execute([
                    ':empresa_id' => $empresa_id,
                    ':nome_caminhao' => $nome_caminhao,
                    ':modelo' => $modelo,
                    ':placa' => $placa,
                    ':ano' => $ano,
                    ':cor' => $cor,
                ]);
                $mensagem = '<div class="alerta sucesso">Veículo cadastrado com sucesso.</div>';
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $registro = $pdo->prepare('SELECT * FROM caminhoes WHERE id = :id');
    $registro->execute([':id' => $id]);
    $registro = $registro->fetch();
}

$lista = $pdo->query('SELECT caminhoes.*, empresas_cadastradas.nome AS empresa_nome FROM caminhoes LEFT JOIN empresas_cadastradas ON empresas_cadastradas.id = caminhoes.empresa_id ORDER BY caminhoes.id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veículos - Nexus HUB</title>
    <link rel="icon" type="image/png" href="../../assets/images/icon-sistem.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/pages.css">
    <link rel="stylesheet" href="../../assets/css/admin-forms.css">
    <script src="../../assets/js/common.js"></script>
    <script src="../../assets/js/pages.js"></script>
</head>
<body class="corpo-dashboard">
    <div class="layout-erp">
        <main class="conteudo-principal">
            <section class="titulo-pagina-stack">
                <h1><i class="bi bi-truck"></i> Gestão de Veículos</h1>
                <p>Cadastre, edite e consulte a frota vinculada à operação.</p>
            </section>

            <?= $mensagem ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="<?= $registro ? 'update' : 'create' ?>">
                <?php if ($registro): ?>
                    <input type="hidden" name="id" value="<?= (int) $registro['id'] ?>">
                <?php endif; ?>
                <div class="form-card">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="empresa_id">Empresa *</label>
                            <select id="empresa_id" name="empresa_id" required>
                                <option value="">Selecione uma empresa cadastrada...</option>
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?= (int) $empresa['id'] ?>" <?= ((int) ($registro['empresa_id'] ?? 0) === (int) $empresa['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($empresa['nome']) ?><?= $empresa['cnpj'] ? ' - ' . htmlspecialchars($empresa['cnpj']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Placa *</label>
                            <input type="text" name="placa" value="<?= htmlspecialchars($registro['placa'] ?? '') ?>" required>
                        </div>
                        <div class="form-group form-grid-full">
                            <label>Modelo *</label>
                            <input type="text" name="modelo" value="<?= htmlspecialchars($registro['modelo'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="ano">Ano *</label>
                            <input type="number" id="ano" name="ano" value="<?= htmlspecialchars($registro['ano'] ?? '') ?>" min="1900" max="<?= (int) date('Y') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="cor">Cor *</label>
                            <input type="text" id="cor" name="cor" value="<?= htmlspecialchars($registro['cor'] ?? '') ?>" maxlength="50" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> <?= $registro ? 'Salvar alterações' : 'Cadastrar veículo' ?></button>
                        <?php if ($registro): ?>
                            <a href="cadastro_veiculo.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="table-container">
                <h3><i class="bi bi-list-check"></i> Veículos cadastrados</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Modelo</th>
                            <th>Placa</th>
                            <th>Ano</th>
                            <th>Cor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista)): ?>
                            <tr><td colspan="6" class="table-empty"><i class="bi bi-inbox"></i> Nenhum veículo cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista as $veiculo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($veiculo['empresa_nome'] ?: $veiculo['nome_caminhao']) ?></td>
                                    <td><?= htmlspecialchars($veiculo['modelo']) ?></td>
                                    <td><?= htmlspecialchars($veiculo['placa']) ?></td>
                                    <td><?= htmlspecialchars($veiculo['ano'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($veiculo['cor'] ?? '-') ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="?edit=<?= (int) $veiculo['id'] ?>" class="btn-table btn-table-edit"><i class="bi bi-pencil"></i> Editar</a>
                                            <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Deseja excluir este veículo?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $veiculo['id'] ?>">
                                                <button type="submit" class="btn-table btn-table-delete"><i class="bi bi-trash"></i> Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
