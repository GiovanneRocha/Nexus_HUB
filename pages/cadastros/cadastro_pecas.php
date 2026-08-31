<?php
require_once __DIR__ . '/../../php/db.php';

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
$registro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM pecas WHERE id = :id')->execute([':id' => $id]);
            $mensagem = '<div class="alerta sucesso">Peça removida com sucesso.</div>';
        }
    } else {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $categoria = trim((string) ($_POST['categoria'] ?? ''));
        $marca = trim((string) ($_POST['marca'] ?? ''));
        $fornecedor = trim((string) ($_POST['fornecedor'] ?? ''));
        $codigo = trim((string) ($_POST['codigo'] ?? ''));
        $estoque = (int) ($_POST['estoque'] ?? 0);
        $unidade = trim((string) ($_POST['unidade'] ?? 'Unidade'));
        $valor = (float) ($_POST['valor'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? 'Ativa'));
        $imagem = trim((string) ($_POST['imagem'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));

        if ($nome === '' || $codigo === '') {
            $mensagem = '<div class="alerta erro">Nome e código da peça são obrigatórios.</div>';
        } else {
            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE pecas SET nome = :nome, categoria = :categoria, marca = :marca, fornecedor = :fornecedor, codigo = :codigo, estoque = :estoque, unidade = :unidade, valor = :valor, status = :status, imagem = :imagem, descricao = :descricao WHERE id = :id');
                    $stmt->execute([
                        ':nome' => $nome,
                        ':categoria' => $categoria,
                        ':marca' => $marca,
                        ':fornecedor' => $fornecedor,
                        ':codigo' => $codigo,
                        ':estoque' => $estoque,
                        ':unidade' => $unidade,
                        ':valor' => $valor,
                        ':status' => $status,
                        ':imagem' => $imagem,
                        ':descricao' => $descricao,
                        ':id' => $id,
                    ]);
                    $mensagem = '<div class="alerta sucesso">Peça atualizada com sucesso.</div>';
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO pecas (nome, categoria, marca, fornecedor, codigo, estoque, unidade, valor, status, imagem, descricao) VALUES (:nome, :categoria, :marca, :fornecedor, :codigo, :estoque, :unidade, :valor, :status, :imagem, :descricao)');
                $stmt->execute([
                    ':nome' => $nome,
                    ':categoria' => $categoria,
                    ':marca' => $marca,
                    ':fornecedor' => $fornecedor,
                    ':codigo' => $codigo,
                    ':estoque' => $estoque,
                    ':unidade' => $unidade,
                    ':valor' => $valor,
                    ':status' => $status,
                    ':imagem' => $imagem,
                    ':descricao' => $descricao,
                ]);
                $mensagem = '<div class="alerta sucesso">Peça cadastrada com sucesso.</div>';
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $registro = $pdo->prepare('SELECT * FROM pecas WHERE id = :id');
    $registro->execute([':id' => $id]);
    $registro = $registro->fetch();
}

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
    <script src="../../assets/js/common.js"></script>
    <script src="../../assets/js/pages.js"></script>
    <style>
        body { background: #f4f7fb; }
        .alerta { padding: 12px 16px; border-radius: 12px; margin-bottom: 18px; font-weight: 600; }
        .alerta.sucesso { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alerta.erro { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .campo { display: flex; flex-direction: column; gap: 8px; }
        .campo input, .campo textarea, .campo select { width: 100%; padding: 14px 16px; border: 1px solid #dfe3eb; border-radius: 12px; background: white; }
        .campo textarea { min-height: 120px; resize: vertical; }
        .acoes { display: flex; gap: 10px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .btn-small { padding: 8px 10px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-primary { background: var(--azul-escuro); color: white; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
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
                <input type="hidden" name="action" value="<?= $registro ? 'update' : 'create' ?>">
                <?php if ($registro): ?>
                    <input type="hidden" name="id" value="<?= (int) $registro['id'] ?>">
                <?php endif; ?>
                <div class="card-os" style="padding: 28px; border-radius: 20px; background: white; box-shadow: 0 10px 30px rgba(0,0,0,0.04);">
                    <div class="grid">
                        <div class="campo">
                            <label>Nome da peça *</label>
                            <input type="text" name="nome" value="<?= htmlspecialchars($registro['nome'] ?? '') ?>" required>
                        </div>
                        <div class="campo">
                            <label>Código *</label>
                            <input type="text" name="codigo" value="<?= htmlspecialchars($registro['codigo'] ?? '') ?>" required>
                        </div>
                        <div class="campo">
                            <label>Categoria</label>
                            <input type="text" name="categoria" value="<?= htmlspecialchars($registro['categoria'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Marca</label>
                            <input type="text" name="marca" value="<?= htmlspecialchars($registro['marca'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Fornecedor</label>
                            <input type="text" name="fornecedor" value="<?= htmlspecialchars($registro['fornecedor'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Estoque</label>
                            <input type="number" name="estoque" value="<?= htmlspecialchars((string) ($registro['estoque'] ?? 0)) ?>">
                        </div>
                        <div class="campo">
                            <label>Unidade</label>
                            <input type="text" name="unidade" value="<?= htmlspecialchars($registro['unidade'] ?? 'Unidade') ?>">
                        </div>
                        <div class="campo">
                            <label>Valor</label>
                            <input type="number" step="0.01" name="valor" value="<?= htmlspecialchars((string) ($registro['valor'] ?? 0)) ?>">
                        </div>
                        <div class="campo">
                            <label>Status</label>
                            <select name="status">
                                <option value="Ativa" <?= (($registro['status'] ?? 'Ativa') === 'Ativa') ? 'selected' : '' ?>>Ativa</option>
                                <option value="Inativa" <?= (($registro['status'] ?? 'Ativa') === 'Inativa') ? 'selected' : '' ?>>Inativa</option>
                            </select>
                        </div>
                        <div class="campo" style="grid-column: 1 / -1;">
                            <label>Imagem (URL)</label>
                            <input type="text" name="imagem" value="<?= htmlspecialchars($registro['imagem'] ?? '') ?>">
                        </div>
                        <div class="campo" style="grid-column: 1 / -1;">
                            <label>Descrição</label>
                            <textarea name="descricao"><?= htmlspecialchars($registro['descricao'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="acoes">
                        <button type="submit" class="btn-small btn-primary"><?= $registro ? 'Salvar alterações' : 'Cadastrar peça' ?></button>
                        <?php if ($registro): ?>
                            <a href="cadastro_pecas.php" class="btn-small btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="card-os" style="padding: 24px; margin-top: 30px; background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.04);">
                <h3>Peças cadastradas</h3>
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
                            <tr><td colspan="6">Nenhuma peça cadastrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista as $peca): ?>
                                <tr>
                                    <td><?= htmlspecialchars($peca['nome']) ?></td>
                                    <td><?= htmlspecialchars($peca['codigo']) ?></td>
                                    <td><?= htmlspecialchars($peca['categoria']) ?></td>
                                    <td><?= (int) $peca['estoque'] ?></td>
                                    <td>R$ <?= number_format((float) $peca['valor'], 2, ',', '.') ?></td>
                                    <td>
                                        <div style="display:flex; gap:8px;">
                                            <a href="?edit=<?= (int) $peca['id'] ?>" class="btn-small btn-secondary" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Editar</a>
                                            <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Deseja excluir esta peça?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $peca['id'] ?>">
                                                <button type="submit" class="btn-small btn-danger">Excluir</button>
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
