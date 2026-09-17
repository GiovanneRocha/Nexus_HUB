<?php
require_once __DIR__ . '/../../php/db.php';
require_once __DIR__ . '/../../php/validacoes.php';

$pdo = nexusDb();
$pdo->exec("CREATE TABLE IF NOT EXISTS pecas (
    id INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    categoria VARCHAR(100) DEFAULT NULL,
    marca VARCHAR(100) DEFAULT NULL,
    fornecedor VARCHAR(150) DEFAULT NULL,
    codigo VARCHAR(80) DEFAULT NULL,
    estoque INT DEFAULT 0,
    unidade VARCHAR(40) DEFAULT 'Unidade',
    valor DECIMAL(10,2) DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'Ativa',
    imagem TEXT DEFAULT NULL,
    descricao TEXT DEFAULT NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
            $pdo->prepare('DELETE FROM pecas WHERE id = :id')->execute([':id' => $id]);
            $mensagem = '<div class="alerta sucesso">Peça removida com sucesso.</div>';
        }
    } else {
        $valores = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'categoria' => trim((string) ($_POST['categoria'] ?? '')),
            'marca' => trim((string) ($_POST['marca'] ?? '')),
            'fornecedor' => trim((string) ($_POST['fornecedor'] ?? '')),
            'codigo' => trim((string) ($_POST['codigo'] ?? '')),
            'estoque' => trim((string) ($_POST['estoque'] ?? '')),
            'unidade' => trim((string) ($_POST['unidade'] ?? 'Unidade')),
            'valor' => trim((string) ($_POST['valor'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'Ativa')),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
        ];

        if (!nexusTextoValido($valores['nome'], 3)) {
            $erros['nome'] = 'O nome da peça deve ter pelo menos 3 caracteres.';
            $valores['nome'] = '';
        }
        if (!nexusTextoValido($valores['codigo'], 2)) {
            $erros['codigo'] = 'Informe o código/referência da peça.';
            $valores['codigo'] = '';
        }
        if (!nexusValidarInteiroNaoNegativo($valores['estoque'])) {
            $erros['estoque'] = 'Quantidade em estoque inválida.';
            $valores['estoque'] = '';
        }
        if (!nexusValidarValorMonetario($valores['valor'])) {
            $erros['valor'] = 'Informe um valor válido (entre R$ 0,00 e R$ 999.999,99).';
            $valores['valor'] = '';
        }

        if (empty($erros)) {
            $estoque = (int) $valores['estoque'];
            $valor = (float) $valores['valor'];
            $codigo = nexusMaiusculas($valores['codigo']);

            // Código duplicado: apenas avisa, não bloqueia o cadastro.
            $avisoDuplicado = '';
            $idAtual = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
            $dup = $pdo->prepare('SELECT nome FROM pecas WHERE UPPER(codigo) = :codigo AND id != :id LIMIT 1');
            $dup->execute([':codigo' => $codigo, ':id' => $idAtual]);
            if ($existente = $dup->fetch()) {
                $avisoDuplicado = '<div class="alerta erro">Atenção: já existe uma peça cadastrada com o código ' . htmlspecialchars($codigo) . ' (' . htmlspecialchars((string) $existente['nome']) . '). Cadastro salvo mesmo assim.</div>';
            }
            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE pecas SET nome = :nome, categoria = :categoria, marca = :marca, fornecedor = :fornecedor, codigo = :codigo, estoque = :estoque, unidade = :unidade, valor = :valor, status = :status, descricao = :descricao WHERE id = :id');
                    $stmt->execute([
                        ':nome' => $valores['nome'],
                        ':categoria' => $valores['categoria'],
                        ':marca' => $valores['marca'],
                        ':fornecedor' => $valores['fornecedor'],
                        ':codigo' => $codigo,
                        ':estoque' => $estoque,
                        ':unidade' => $valores['unidade'],
                        ':valor' => $valor,
                        ':status' => $valores['status'],
                        ':descricao' => $valores['descricao'],
                        ':id' => $id,
                    ]);
                    $mensagem = $avisoDuplicado . '<div class="alerta sucesso">Peça atualizada com sucesso.</div>';
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO pecas (nome, categoria, marca, fornecedor, codigo, estoque, unidade, valor, status, descricao) VALUES (:nome, :categoria, :marca, :fornecedor, :codigo, :estoque, :unidade, :valor, :status, :descricao)');
                $stmt->execute([
                    ':nome' => $valores['nome'],
                    ':categoria' => $valores['categoria'],
                    ':marca' => $valores['marca'],
                    ':fornecedor' => $valores['fornecedor'],
                    ':codigo' => $codigo,
                    ':estoque' => $estoque,
                    ':unidade' => $valores['unidade'],
                    ':valor' => $valor,
                    ':status' => $valores['status'],
                    ':descricao' => $valores['descricao'],
                ]);
                $mensagem = $avisoDuplicado . '<div class="alerta sucesso">Peça cadastrada com sucesso.</div>';
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
    $stmtRegistro = $pdo->prepare('SELECT * FROM pecas WHERE id = :id');
    $stmtRegistro->execute([':id' => $id]);
    $registro = $stmtRegistro->fetch();
}

$valoresForm = $valoresDigitados ?? [
    'nome' => $registro['nome'] ?? '',
    'categoria' => $registro['categoria'] ?? '',
    'marca' => $registro['marca'] ?? '',
    'fornecedor' => $registro['fornecedor'] ?? '',
    'codigo' => $registro['codigo'] ?? '',
    'estoque' => $registro['estoque'] ?? 0,
    'unidade' => $registro['unidade'] ?? 'Unidade',
    'valor' => $registro['valor'] ?? 0,
    'status' => $registro['status'] ?? 'Ativa',
    'descricao' => $registro['descricao'] ?? '',
];

$emEdicao = $registro !== null || ($valoresDigitados !== null && ($_POST['action'] ?? '') === 'update');
$idFormulario = $registro['id'] ?? (int) ($_POST['id'] ?? 0);

$lista = $pdo->query('SELECT * FROM pecas ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peças - Nexus HUB</title>
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
        .form-group input.campo-invalido { border-color: #ef4444 !important; }
    </style>
</head>
<body class="corpo-dashboard">
    <div class="layout-erp">
        <main class="conteudo-principal">
            <section class="titulo-pagina-stack">
                <h1><i class="bi bi-box-seam"></i> Gestão de Peças</h1>
                <p>Cadastre, edite e acompanhe o estoque de peças do sistema.</p>
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
                            <label>Nome da peça *</label>
                            <input type="text" name="nome" value="<?= htmlspecialchars($valoresForm['nome']) ?>" maxlength="150" class="<?= isset($erros['nome']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['nome'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['nome']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Código *</label>
                            <input type="text" name="codigo" value="<?= htmlspecialchars($valoresForm['codigo']) ?>" maxlength="80" class="<?= isset($erros['codigo']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['codigo'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['codigo']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Categoria</label>
                            <input type="text" name="categoria" value="<?= htmlspecialchars($valoresForm['categoria']) ?>" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>Marca</label>
                            <input type="text" name="marca" value="<?= htmlspecialchars($valoresForm['marca']) ?>" maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>Fornecedor</label>
                            <input type="text" name="fornecedor" value="<?= htmlspecialchars($valoresForm['fornecedor']) ?>" maxlength="150">
                        </div>
                        <div class="form-group">
                            <label>Estoque</label>
                            <input type="number" name="estoque" min="0" max="9999999" value="<?= htmlspecialchars((string) $valoresForm['estoque']) ?>" class="<?= isset($erros['estoque']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['estoque'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['estoque']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Unidade</label>
                            <input type="text" name="unidade" value="<?= htmlspecialchars($valoresForm['unidade']) ?>" maxlength="40">
                        </div>
                        <div class="form-group">
                            <label>Valor</label>
                            <input type="number" step="0.01" min="0" max="999999.99" name="valor" value="<?= htmlspecialchars((string) $valoresForm['valor']) ?>" class="<?= isset($erros['valor']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['valor'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['valor']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="Ativa" <?= ($valoresForm['status'] === 'Ativa') ? 'selected' : '' ?>>Ativa</option>
                                <option value="Inativa" <?= ($valoresForm['status'] === 'Inativa') ? 'selected' : '' ?>>Inativa</option>
                            </select>
                        </div>
                        <div class="form-group form-grid-full">
                            <label>Descrição</label>
                            <textarea name="descricao"><?= htmlspecialchars($valoresForm['descricao']) ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> <?= $emEdicao ? 'Salvar alterações' : 'Cadastrar peça' ?></button>
                        <?php if ($emEdicao): ?>
                            <a href="cadastro_pecas.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="table-container">
                <h3><i class="bi bi-list-check"></i> Peças cadastradas</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Código</th>
                            <th>Categoria</th>
                            <th>Estoque</th>
                            <th>Valor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista)): ?>
                            <tr><td colspan="6" class="table-empty"><i class="bi bi-inbox"></i> Nenhuma peça cadastrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista as $peca): ?>
                                <tr>
                                    <td><?= htmlspecialchars($peca['nome']) ?></td>
                                    <td><?= htmlspecialchars($peca['codigo']) ?></td>
                                    <td><?= htmlspecialchars($peca['categoria']) ?></td>
                                    <td><?= (int) $peca['estoque'] ?></td>
                                    <td>R$ <?= number_format((float) $peca['valor'], 2, ',', '.') ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="?edit=<?= (int) $peca['id'] ?>" class="btn-table btn-table-edit"><i class="bi bi-pencil"></i> Editar</a>
                                            <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Deseja excluir esta peça?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $peca['id'] ?>">
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
