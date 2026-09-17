<?php
require_once __DIR__ . '/../../php/db.php';
require_once __DIR__ . '/../../php/validacoes.php';

$pdo = nexusDb();
$pdo->exec("CREATE TABLE IF NOT EXISTS cod_servicos (
    id INT NOT NULL AUTO_INCREMENT,
    nome_servico VARCHAR(100) NOT NULL,
    descricao TEXT DEFAULT NULL,
    preco DECIMAL(10,2) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mensagem = '';
$erros = [];
$valoresDigitados = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM cod_servicos WHERE id = :id')->execute([':id' => $id]);
            $mensagem = '<div class="alerta sucesso">Serviço removido com sucesso.</div>';
        }
    } else {
        $valores = [
            'nome_servico' => trim((string) ($_POST['nome_servico'] ?? '')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
            'preco' => trim((string) ($_POST['preco'] ?? '')),
        ];

        if (!nexusTextoValido($valores['nome_servico'], 3)) {
            $erros['nome_servico'] = 'O nome do serviço deve ter pelo menos 3 caracteres.';
            $valores['nome_servico'] = '';
        }
        if (!nexusValidarValorMonetario($valores['preco']) || (float) $valores['preco'] <= 0) {
            $erros['preco'] = 'Informe um valor maior que R$ 0,00 (limite: R$ 999.999,99).';
            $valores['preco'] = '';
        }
        if (!nexusTextoValido($valores['descricao'], 5)) {
            $erros['descricao'] = 'A descrição do serviço é obrigatória (mínimo 5 caracteres).';
            $valores['descricao'] = '';
        }

        if (empty($erros)) {
            $preco = (float) $valores['preco'];
            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE cod_servicos SET nome_servico = :nome_servico, descricao = :descricao, preco = :preco WHERE id = :id');
                    $stmt->execute([
                        ':nome_servico' => $valores['nome_servico'],
                        ':descricao' => $valores['descricao'],
                        ':preco' => $preco,
                        ':id' => $id,
                    ]);
                    $mensagem = '<div class="alerta sucesso">Serviço atualizado com sucesso.</div>';
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO cod_servicos (nome_servico, descricao, preco) VALUES (:nome_servico, :descricao, :preco)');
                $stmt->execute([
                    ':nome_servico' => $valores['nome_servico'],
                    ':descricao' => $valores['descricao'],
                    ':preco' => $preco,
                ]);
                $mensagem = '<div class="alerta sucesso">Serviço cadastrado com sucesso.</div>';
            }
        } else {
            $mensagem = '<div class="alerta erro">Corrija o(s) campo(s) destacado(s) abaixo.</div>';
            $valoresDigitados = $valores;
        }
    }
}

$registro = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmtRegistro = $pdo->prepare('SELECT * FROM cod_servicos WHERE id = :id');
    $stmtRegistro->execute([':id' => $id]);
    $registro = $stmtRegistro->fetch();
}

$valoresForm = $valoresDigitados ?? [
    'nome_servico' => $registro['nome_servico'] ?? '',
    'descricao' => $registro['descricao'] ?? '',
    'preco' => $registro['preco'] ?? '',
];

$emEdicao = $registro !== null || ($valoresDigitados !== null && ($_POST['action'] ?? '') === 'update');
$idFormulario = $registro['id'] ?? (int) ($_POST['id'] ?? 0);

$lista = $pdo->query('SELECT * FROM cod_servicos ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serviços - Nexus HUB</title>
    <link rel="icon" type="image/png" href="../../assets/images/icon-sistem.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/pages.css">
    <link rel="stylesheet" href="../../assets/css/admin-forms.css">
    <script src="../../assets/js/validacoes-cliente.js"></script>
    <script src="../../assets/js/common.js"></script>
    <script src="../../assets/js/pages.js"></script>
    <style>
        .erro-campo { display: block; color: #ef4444; font-size: .78rem; margin-top: 4px; }
        .form-group input.campo-invalido, .form-group textarea.campo-invalido { border-color: #ef4444 !important; }
    </style>
</head>
<body class="corpo-dashboard">
    <div class="layout-erp">
        <main class="conteudo-principal">
            <section class="titulo-pagina-stack">
                <h1><i class="bi bi-tools"></i> Gestão de Serviços</h1>
                <p>Cadastre, edite e consulte os serviços disponíveis no sistema.</p>
            </section>

            <?= $mensagem ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="<?= $emEdicao ? 'update' : 'create' ?>">
                <?php if ($emEdicao): ?>
                    <input type="hidden" name="id" value="<?= (int) $idFormulario ?>">
                <?php endif; ?>
                <div class="form-card">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome do serviço *</label>
                            <input type="text" name="nome_servico" value="<?= htmlspecialchars($valoresForm['nome_servico']) ?>" maxlength="100" class="<?= isset($erros['nome_servico']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['nome_servico'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['nome_servico']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Preço *</label>
                            <input type="number" step="0.01" min="0" max="999999.99" name="preco" value="<?= htmlspecialchars((string) $valoresForm['preco']) ?>" placeholder="0,00" class="<?= isset($erros['preco']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['preco'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['preco']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group form-grid-full">
                            <label>Descrição *</label>
                            <textarea name="descricao" minlength="5" placeholder="Descreva o serviço (mínimo 5 caracteres)..." class="<?= isset($erros['descricao']) ? 'campo-invalido' : '' ?>" required><?= htmlspecialchars($valoresForm['descricao']) ?></textarea>
                            <?php if (isset($erros['descricao'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['descricao']) ?></small><?php endif; ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> <?= $emEdicao ? 'Salvar alterações' : 'Cadastrar serviço' ?></button>
                        <?php if ($emEdicao): ?>
                            <a href="cadastro_servicos.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="table-container">
                <h3><i class="bi bi-list-check"></i> Serviços cadastrados</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th>Descrição</th>
                            <th>Preço</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista)): ?>
                            <tr><td colspan="4" class="table-empty"><i class="bi bi-inbox"></i> Nenhum serviço cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista as $servico): ?>
                                <tr>
                                    <td><?= htmlspecialchars($servico['nome_servico']) ?></td>
                                    <td><?= htmlspecialchars($servico['descricao']) ?></td>
                                    <td>R$ <?= number_format((float) $servico['preco'], 2, ',', '.') ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="?edit=<?= (int) $servico['id'] ?>" class="btn-table btn-table-edit"><i class="bi bi-pencil"></i> Editar</a>
                                            <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Deseja excluir este serviço?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $servico['id'] ?>">
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
