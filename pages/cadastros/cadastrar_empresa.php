<?php
require_once __DIR__ . '/../../php/db.php';
require_once __DIR__ . '/../../php/validacoes.php';

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
$erros = [];
$valoresDigitados = null; // só é preenchido quando a validação falha, para reexibir o que a pessoa digitou

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM empresas_cadastradas WHERE id = :id')->execute([':id' => $id]);
            $mensagem = '<div class="alerta sucesso">Empresa removida com sucesso.</div>';
        }
    } else {
        $valores = [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'cnpj' => trim((string) ($_POST['cnpj'] ?? '')),
            'site' => trim((string) ($_POST['site'] ?? '')),
            'contato' => trim((string) ($_POST['contato'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'telefone' => trim((string) ($_POST['telefone'] ?? '')),
            'observacoes' => trim((string) ($_POST['observacoes'] ?? '')),
        ];

        // Cada campo é validado individualmente. Quando um campo está errado,
        // SÓ ELE é apagado (para receber o valor correto) - os demais continuam
        // preenchidos exatamente como a pessoa digitou.
        if (!nexusTextoValido($valores['nome'], 3)) {
            $erros['nome'] = 'Informe o nome da empresa (mínimo 3 caracteres).';
            $valores['nome'] = '';
        }
        if (!nexusTextoValido($valores['contato'], 3)) {
            $erros['contato'] = 'Informe o nome do contato principal (mínimo 3 caracteres).';
            $valores['contato'] = '';
        }
        if (!nexusValidarEmail($valores['email'])) {
            $erros['email'] = 'E-mail inválido. Use o formato nome@empresa.com.br.';
            $valores['email'] = '';
        }
        if (!nexusValidarTelefone($valores['telefone'])) {
            $erros['telefone'] = 'Telefone inválido. Informe DDD + número, ex: (11) 98765-4321.';
            $valores['telefone'] = '';
        }
        if (!nexusValidarCNPJ($valores['cnpj'])) {
            $erros['cnpj'] = 'CNPJ inválido. Formato esperado: 00.000.000/0000-00.';
            $valores['cnpj'] = '';
        }
        if ($valores['site'] !== '' && !nexusValidarUrl($valores['site'])) {
            $erros['site'] = 'Link inválido. Deve começar com http:// ou https://.';
            $valores['site'] = '';
        }

        if (empty($erros)) {
            $cnpjLimpo = nexusApenasNumeros($valores['cnpj']);

            // CNPJ duplicado: apenas avisa, não bloqueia o cadastro.
            $avisoDuplicado = '';
            $idAtual = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
            $dup = $pdo->prepare('SELECT nome FROM empresas_cadastradas WHERE cnpj = :cnpj AND id != :id LIMIT 1');
            $dup->execute([':cnpj' => $cnpjLimpo, ':id' => $idAtual]);
            if ($existente = $dup->fetch()) {
                $avisoDuplicado = '<div class="alerta erro">Atenção: já existe uma empresa cadastrada com este CNPJ (' . htmlspecialchars($existente['nome']) . '). Cadastro salvo mesmo assim.</div>';
            }

            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE empresas_cadastradas SET nome = :nome, cnpj = :cnpj, site = :site, contato = :contato, email = :email, telefone = :telefone, observacoes = :observacoes WHERE id = :id');
                    $stmt->execute([
                        ':nome' => $valores['nome'],
                        ':cnpj' => $cnpjLimpo,
                        ':site' => $valores['site'],
                        ':contato' => $valores['contato'],
                        ':email' => $valores['email'],
                        ':telefone' => $valores['telefone'],
                        ':observacoes' => $valores['observacoes'],
                        ':id' => $id,
                    ]);
                    $mensagem = $avisoDuplicado . '<div class="alerta sucesso">Empresa atualizada com sucesso.</div>';
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO empresas_cadastradas (nome, cnpj, site, contato, email, telefone, observacoes) VALUES (:nome, :cnpj, :site, :contato, :email, :telefone, :observacoes)');
                $stmt->execute([
                    ':nome' => $valores['nome'],
                    ':cnpj' => $cnpjLimpo,
                    ':site' => $valores['site'],
                    ':contato' => $valores['contato'],
                    ':email' => $valores['email'],
                    ':telefone' => $valores['telefone'],
                    ':observacoes' => $valores['observacoes'],
                ]);
                $mensagem = $avisoDuplicado . '<div class="alerta sucesso">Empresa cadastrada com sucesso.</div>';
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
    $stmtRegistro = $pdo->prepare('SELECT * FROM empresas_cadastradas WHERE id = :id');
    $stmtRegistro->execute([':id' => $id]);
    $registro = $stmtRegistro->fetch();
}

// O que é exibido no formulário:
// 1) se a validação acabou de falhar -> o que a pessoa digitou (com os campos errados em branco)
// 2) senão, se está editando -> os dados do banco
// 3) senão -> formulário em branco
$valoresForm = $valoresDigitados ?? [
    'nome' => $registro['nome'] ?? '',
    'cnpj' => $registro['cnpj'] ?? '',
    'site' => $registro['site'] ?? '',
    'contato' => $registro['contato'] ?? '',
    'email' => $registro['email'] ?? '',
    'telefone' => $registro['telefone'] ?? '',
    'observacoes' => $registro['observacoes'] ?? '',
];

// Mantém o formulário em modo "edição" (action=update + id) se: já estava editando,
// ou se acabou de falhar uma tentativa de atualização.
$emEdicao = $registro !== null || ($valoresDigitados !== null && ($_POST['action'] ?? '') === 'update');
$idFormulario = $registro['id'] ?? (int) ($_POST['id'] ?? 0);

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
                <h1><i class="bi bi-building"></i> Gestão de Empresas</h1>
                <p>Cadastre, edite e consulte as empresas vinculadas ao sistema.</p>
            </section>

            <?= $mensagem ?>

            <form method="POST" action="" id="form-empresa">
                <input type="hidden" name="action" value="<?= $emEdicao ? 'update' : 'create' ?>">
                <?php if ($emEdicao): ?>
                    <input type="hidden" name="id" value="<?= (int) $idFormulario ?>">
                <?php endif; ?>
                <div class="form-card">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome da empresa *</label>
                            <input type="text" name="nome" value="<?= htmlspecialchars($valoresForm['nome']) ?>" maxlength="150" class="<?= isset($erros['nome']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['nome'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['nome']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>CNPJ *</label>
                            <input type="text" name="cnpj" value="<?= htmlspecialchars($valoresForm['cnpj']) ?>" placeholder="00.000.000/0000-00" maxlength="18" inputmode="numeric" data-mascara="cnpj" class="<?= isset($erros['cnpj']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['cnpj'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['cnpj']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Site</label>
                            <input type="url" name="site" value="<?= htmlspecialchars($valoresForm['site']) ?>" placeholder="https://www.empresa.com.br" maxlength="150" class="<?= isset($erros['site']) ? 'campo-invalido' : '' ?>">
                            <?php if (isset($erros['site'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['site']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Contato principal *</label>
                            <input type="text" name="contato" value="<?= htmlspecialchars($valoresForm['contato']) ?>" maxlength="80" class="<?= isset($erros['contato']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['contato'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['contato']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>E-mail *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($valoresForm['email']) ?>" placeholder="contato@empresa.com.br" maxlength="100" class="<?= isset($erros['email']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['email'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['email']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Telefone *</label>
                            <input type="text" name="telefone" value="<?= htmlspecialchars($valoresForm['telefone']) ?>" placeholder="(11) 98765-4321" maxlength="15" inputmode="numeric" data-mascara="telefone" class="<?= isset($erros['telefone']) ? 'campo-invalido' : '' ?>" required>
                            <?php if (isset($erros['telefone'])): ?><small class="erro-campo"><?= htmlspecialchars($erros['telefone']) ?></small><?php endif; ?>
                        </div>
                        <div class="form-group form-grid-full">
                            <label>Observações</label>
                            <textarea name="observacoes" maxlength="2000"><?= htmlspecialchars($valoresForm['observacoes']) ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> <?= $emEdicao ? 'Salvar alterações' : 'Cadastrar empresa' ?></button>
                        <?php if ($emEdicao): ?>
                            <a href="cadastrar_empresa.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <div class="table-container">
                <h3><i class="bi bi-list-check"></i> Empresas cadastradas</h3>
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
                            <tr><td colspan="6" class="table-empty"><i class="bi bi-inbox"></i> Nenhuma empresa cadastrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lista as $empresa): ?>
                                <tr>
                                    <td><?= htmlspecialchars($empresa['nome']) ?></td>
                                    <td><?= htmlspecialchars($empresa['cnpj']) ?></td>
                                    <td><?= htmlspecialchars($empresa['contato']) ?></td>
                                    <td><?= htmlspecialchars($empresa['email']) ?></td>
                                    <td><?= htmlspecialchars($empresa['telefone']) ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="?edit=<?= (int) $empresa['id'] ?>" class="btn-table btn-table-edit"><i class="bi bi-pencil"></i> Editar</a>
                                            <form method="POST" action="" style="margin:0;" onsubmit="return confirm('Deseja excluir esta empresa?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $empresa['id'] ?>">
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
