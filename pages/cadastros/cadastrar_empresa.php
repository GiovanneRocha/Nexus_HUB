<?php
require_once __DIR__ . '/../../php/db.php';

$pdo = nexusDb();
$pdo->exec("CREATE TABLE IF NOT EXISTS empresas_cadastradas (
    id INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    cnpj VARCHAR(20) DEFAULT NULL,
    site VARCHAR(255) DEFAULT NULL,
    contato VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    telefone VARCHAR(50) DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$mensagem = '';
$registro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM empresas_cadastradas WHERE id = :id')->execute([':id' => $id]);
            $mensagem = '<div class="alerta sucesso">Empresa removida com sucesso.</div>';
        }
    } else {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $cnpj = trim((string) ($_POST['cnpj'] ?? ''));
        $site = trim((string) ($_POST['site'] ?? ''));
        $contato = trim((string) ($_POST['contato'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $telefone = trim((string) ($_POST['telefone'] ?? ''));
        $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

        if ($nome === '' || $cnpj === '' || $email === '' || $telefone === '' || $contato === '') {
            $mensagem = '<div class="alerta erro">Preencha todos os campos obrigatórios.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = '<div class="alerta erro">Informe um e-mail válido.</div>';
        } else {
            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE empresas_cadastradas SET nome = :nome, cnpj = :cnpj, site = :site, contato = :contato, email = :email, telefone = :telefone, observacoes = :observacoes WHERE id = :id');
                    $stmt->execute([
                        ':nome' => $nome,
                        ':cnpj' => $cnpj,
                        ':site' => $site,
                        ':contato' => $contato,
                        ':email' => $email,
                        ':telefone' => $telefone,
                        ':observacoes' => $observacoes,
                        ':id' => $id,
                    ]);
                    $mensagem = '<div class="alerta sucesso">Empresa atualizada com sucesso.</div>';
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO empresas_cadastradas (nome, cnpj, site, contato, email, telefone, observacoes) VALUES (:nome, :cnpj, :site, :contato, :email, :telefone, :observacoes)');
                $stmt->execute([
                    ':nome' => $nome,
                    ':cnpj' => $cnpj,
                    ':site' => $site,
                    ':contato' => $contato,
                    ':email' => $email,
                    ':telefone' => $telefone,
                    ':observacoes' => $observacoes,
                ]);
                $mensagem = '<div class="alerta sucesso">Empresa cadastrada com sucesso.</div>';
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $registro = $pdo->prepare('SELECT * FROM empresas_cadastradas WHERE id = :id');
    $registro->execute([':id' => $id]);
    $registro = $registro->fetch();
}

$lista = $pdo->query('SELECT * FROM empresas_cadastradas ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresas - Nexus HUB</title>
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
                <h1><i class="bi bi-building"></i> Gestão de Empresas</h1>
                <p>Cadastre, edite e consulte as empresas vinculadas ao sistema.</p>
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
                            <label>Nome da empresa *</label>
                            <input type="text" name="nome" value="<?= htmlspecialchars($registro['nome'] ?? '') ?>" required>
                        </div>
                        <div class="campo">
                            <label>CNPJ *</label>
                            <input type="text" name="cnpj" value="<?= htmlspecialchars($registro['cnpj'] ?? '') ?>" required>
                        </div>
                        <div class="campo">
                            <label>Site</label>
                            <input type="url" name="site" value="<?= htmlspecialchars($registro['site'] ?? '') ?>">
                        </div>
                        <div class="campo">
                            <label>Contato principal *</label>
                            <input type="text" name="contato" value="<?= htmlspecialchars($registro['contato'] ?? '') ?>" required>
                        </div>
                        <div class="campo">
                            <label>E-mail *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($registro['email'] ?? '') ?>" required>
                        </div>
                        <div class="campo">
                            <label>Telefone *</label>
                            <input type="text" name="telefone" value="<?= htmlspecialchars($registro['telefone'] ?? '') ?>" required>
                        </div>
                        <div class="campo" style="grid-column: 1 / -1;">
                            <label>Observações</label>
                            <textarea name="observacoes"><?= htmlspecialchars($registro['observacoes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="acoes">
                        <button type="submit" class="btn-small btn-primary"><?= $registro ? 'Salvar alterações' : 'Cadastrar empresa' ?></button>
                        <?php if ($registro): ?>
                            <a href="cadastrar_empresa.php" class="btn-small btn-secondary" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="card-os" style="padding: 24px; margin-top: 30px; background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.04);">
                <h3>Empresas cadastradas</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>CNPJ</th>
                            <th>Contato</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lista)): ?>
                            <tr><td colspan="6">Nenhuma empresa cadastrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista as $empresa): ?>
                                <tr>
                                    <td><?= htmlspecialchars($empresa['nome']) ?></td>
                                    <td><?= htmlspecialchars($empresa['cnpj']) ?></td>
                                    <td><?= htmlspecialchars($empresa['contato']) ?></td>
                                    <td><?= htmlspecialchars($empresa['email']) ?></td>
                                    <td><?= htmlspecialchars($empresa['telefone']) ?></td>
                                    <td>
                                        <div style="display:flex; gap:8px;">
                                            <a href="?edit=<?= (int) $empresa['id'] ?>" class="btn-small btn-secondary" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Editar</a>
                                            <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Deseja excluir esta empresa?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $empresa['id'] ?>">
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
