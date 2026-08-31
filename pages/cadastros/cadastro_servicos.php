<?php
require_once __DIR__ . '/../../php/db.php';

$pdo = nexusDb();
$pdo->exec("CREATE TABLE IF NOT EXISTS cod_servicos (
    id INT NOT NULL AUTO_INCREMENT,
    nome_servico VARCHAR(100) NOT NULL,
    descricao TEXT DEFAULT NULL,
    preco DECIMAL(10,2) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mensagem = '';
$registro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM cod_servicos WHERE id = :id')->execute([':id' => $id]);
            $mensagem = '<div class="alerta sucesso">Serviço removido com sucesso.</div>';
        }
    } else {
        $nome_servico = trim((string) ($_POST['nome_servico'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $preco = (float) ($_POST['preco'] ?? 0);

        if ($nome_servico === '') {
            $mensagem = '<div class="alerta erro">Nome do serviço é obrigatório.</div>';
        } else {
            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE cod_servicos SET nome_servico = :nome_servico, descricao = :descricao, preco = :preco WHERE id = :id');
                    $stmt->execute([
                        ':nome_servico' => $nome_servico,
                        ':descricao' => $descricao,
                        ':preco' => $preco,
                        ':id' => $id,
                    ]);
                    $mensagem = '<div class="alerta sucesso">Serviço atualizado com sucesso.</div>';
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO cod_servicos (nome_servico, descricao, preco) VALUES (:nome_servico, :descricao, :preco)');
                $stmt->execute([
                    ':nome_servico' => $nome_servico,
                    ':descricao' => $descricao,
                    ':preco' => $preco,
                ]);
                $mensagem = '<div class="alerta sucesso">Serviço cadastrado com sucesso.</div>';
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $registro = $pdo->prepare('SELECT * FROM cod_servicos WHERE id = :id');
    $registro->execute([':id' => $id]);
    $registro = $registro->fetch();
}

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
    <script src="../../assets/js/common.js"></script>
    <script src="../../assets/js/pages.js"></script>
    <style>
        body { background: #f4f7fb; }
        .alerta { padding: 12px 16px; border-radius: 12px; margin-bottom: 18px; font-weight: 600; }
        .alerta.sucesso { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alerta.erro { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .campo { display: flex; flex-direction: column; gap: 8px; }
        .campo input, .campo textarea { width: 100%; padding: 14px 16px; border: 1px solid #dfe3eb; border-radius: 12px; background: white; }
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
                <h1><i class="bi bi-tools"></i> Gestão de Serviços</h1>
                <p>Cadastre, edite e consulte os serviços disponíveis no sistema.</p>
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
                            <label>Nome do serviço *</label>
                            <input type="text" name="nome_servico" value="<?= htmlspecialchars($registro['nome_servico'] ?? '') ?>" required>
                        </div>
                        <div class="campo">
                            <label>Preço</label>
                            <input type="number" step="0.01" name="preco" value="<?= htmlspecialchars((string) ($registro['preco'] ?? 0)) ?>">
                        </div>
                        <div class="campo" style="grid-column: 1 / -1;">
                            <label>Descrição</label>
                            <textarea name="descricao"><?= htmlspecialchars($registro['descricao'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="acoes">
                        <button type="submit" class="btn-small btn-primary"><?= $registro ? 'Salvar alterações' : 'Cadastrar serviço' ?></button>
                        <?php if ($registro): ?>
                            <a href="cadastro_servicos.php" class="btn-small btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="card-os" style="padding: 24px; margin-top: 30px; background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.04);">
                <h3>Serviços cadastrados</h3>
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
                            <tr><td colspan="4">Nenhum serviço cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista as $servico): ?>
                                <tr>
                                    <td><?= htmlspecialchars($servico['nome_servico']) ?></td>
                                    <td><?= htmlspecialchars($servico['descricao']) ?></td>
                                    <td>R$ <?= number_format((float) $servico['preco'], 2, ',', '.') ?></td>
                                    <td>
                                        <div style="display:flex; gap:8px;">
                                            <a href="?edit=<?= (int) $servico['id'] ?>" class="btn-small btn-secondary" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Editar</a>
                                            <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Deseja excluir este serviço?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $servico['id'] ?>">
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