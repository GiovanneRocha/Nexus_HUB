<?php
require_once __DIR__ . '/../../php/db.php';

session_start();

$pdo = nexusDb();
$usuarioSessaoId = (int) ($_SESSION['usuario_id'] ?? 0);
$stmtAcesso = $pdo->prepare(
    'SELECT u.id, c.nome AS cargo_nome
     FROM usuarios AS u
     LEFT JOIN cargos AS c ON c.id = u.cargo_id
     WHERE u.id = :id AND u.ativo = 1
     LIMIT 1'
);
$stmtAcesso->execute(['id' => $usuarioSessaoId]);
$usuarioSessao = $stmtAcesso->fetch();
$cargoSessao = strtolower(trim((string) ($usuarioSessao['cargo_nome'] ?? '')));

if (!$usuarioSessao || !str_contains($cargoSessao, 'admin')) {
    http_response_code(403);
    exit('Acesso restrito a administradores.');
}

$_SESSION['usuario_role'] = 'administrador';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$mensagem = '';
$tipoMensagem = 'sucesso';

function hUsuario($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function redirecionarUsuarios(): never
{
    header('Location: usuarios.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        http_response_code(419);
        exit('Token de segurança inválido.');
    }

    $acao = (string) ($_POST['acao'] ?? '');
    try {
        if ($acao === 'criar') {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $senha = (string) ($_POST['senha'] ?? '');
            $cargo = (string) ($_POST['cargo'] ?? 'usuario');
            $cargos = ['administrador' => 1, 'usuario' => 2, 'mecanico' => 3];

            if ($nome === '' || mb_strlen($nome) > 100) {
                throw new InvalidArgumentException('Informe um nome com até 100 caracteres.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
                throw new InvalidArgumentException('Informe um e-mail válido.');
            }
            if (mb_strlen($senha) < 6) {
                throw new InvalidArgumentException('A senha deve ter pelo menos 6 caracteres.');
            }
            if (!isset($cargos[$cargo])) {
                throw new InvalidArgumentException('Perfil de acesso inválido.');
            }

            $pdo->exec("INSERT INTO cargos (id, nome, descricao) VALUES (3, 'Mecânico', 'Acesso operacional') ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao)");
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                throw new InvalidArgumentException('Este e-mail já está cadastrado.');
            }

            $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, ativo, cargo_id) VALUES (:nome, :email, :senha_hash, 1, :cargo_id)');
            $stmt->execute([
                'nome' => $nome,
                'email' => $email,
                'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
                'cargo_id' => $cargos[$cargo],
            ]);
            $mensagem = 'Usuário cadastrado com sucesso.';
        } elseif ($acao === 'alternar_status') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) ($_SESSION['usuario_id'] ?? 0)) {
                throw new InvalidArgumentException('Você não pode desativar o próprio usuário.');
            }
            $stmt = $pdo->prepare('UPDATE usuarios SET ativo = IF(ativo = 1, 0, 1) WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $mensagem = 'Status do usuário atualizado.';
        } elseif ($acao === 'excluir') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) ($_SESSION['usuario_id'] ?? 0)) {
                throw new InvalidArgumentException('Você não pode excluir o próprio usuário.');
            }
            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $mensagem = 'Usuário excluído com sucesso.';
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }
    } catch (InvalidArgumentException $erro) {
        $mensagem = $erro->getMessage();
        $tipoMensagem = 'erro';
    } catch (PDOException $erro) {
        $mensagem = 'Não foi possível concluir a operação no banco de dados.';
        $tipoMensagem = 'erro';
    }
}

$stmt = $pdo->query('SELECT u.id, u.nome, u.email, u.ativo, u.data_criacao, c.nome AS cargo_nome FROM usuarios u LEFT JOIN cargos c ON c.id = u.cargo_id ORDER BY u.nome ASC');
$usuarios = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuários - Nexus HUB</title>
<link rel="icon" type="image/png" href="../../assets/images/icon-sistem.png">
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/pages.css">
<style>
.admin-usuarios-grid{display:grid;grid-template-columns:minmax(280px,.75fr) minmax(0,1.5fr);gap:18px}.admin-card{background:var(--surface);border:1px solid var(--bordar);border-radius:12px;padding:22px;box-shadow:var(--sombra-sm)}.admin-card h2{margin:0 0 16px;font-size:1.05rem;color:var(--texto-principal)}.admin-form{display:grid;gap:13px}.admin-form label{display:grid;gap:6px;color:var(--texto-secundario);font-size:.76rem;font-weight:700;text-transform:uppercase}.admin-form input,.admin-form select{width:100%;box-sizing:border-box;min-height:42px;padding:9px 11px;border:1px solid var(--bordar);border-radius:8px;background:var(--surface-light);color:var(--texto-principal)}.admin-form button{min-height:42px}.admin-alert{padding:11px 13px;margin-bottom:16px;border-radius:8px;border:1px solid rgba(16,185,129,.3);color:var(--verde-esmeralda-claro);background:rgba(16,185,129,.08)}.admin-alert.erro{border-color:rgba(239,68,68,.35);color:#fca5a5;background:rgba(239,68,68,.08)}.admin-tabela{width:100%;border-collapse:collapse}.admin-tabela th,.admin-tabela td{padding:12px 10px;text-align:left;border-bottom:1px solid var(--bordar);vertical-align:middle}.admin-tabela th{color:var(--texto-secundario);font-size:.7rem;text-transform:uppercase}.admin-tabela td{color:var(--texto-principal);font-size:.86rem}.admin-tabela small{display:block;color:var(--texto-secundario);margin-top:3px}.admin-status{font-size:.72rem;font-weight:700;color:var(--verde-esmeralda)}.admin-status.inativo{color:#fca5a5}.admin-acoes{display:flex;gap:7px;flex-wrap:wrap}.admin-acoes form{margin:0}.admin-acoes button{border:1px solid var(--bordar);border-radius:7px;padding:7px 9px;background:transparent;color:var(--texto-principal);cursor:pointer}.admin-acoes button:hover{border-color:var(--verde-esmeralda);color:var(--verde-esmeralda)}@media(max-width:900px){.admin-usuarios-grid{grid-template-columns:1fr}.admin-card{overflow-x:auto}.admin-form{max-width:520px}.admin-tabela{min-width:650px}}
</style>
</head>
<body class="corpo-dashboard"><div class="layout-erp"><main class="conteudo-principal">
<section class="titulo-pagina-stack"><h1><i class="bi bi-person-gear"></i> Gestão de usuários</h1><p>Cadastre e controle as contas autorizadas a acessar o Nexus HUB.</p></section>
<?php if ($mensagem): ?><div class="admin-alert <?= $tipoMensagem === 'erro' ? 'erro' : '' ?>"><?= hUsuario($mensagem) ?></div><?php endif; ?>
<div class="admin-usuarios-grid">
<section class="admin-card"><h2><i class="bi bi-person-plus"></i> Novo usuário</h2><form class="admin-form" method="post"><input type="hidden" name="csrf" value="<?= hUsuario($_SESSION['admin_csrf']) ?>"><input type="hidden" name="acao" value="criar"><label>Nome completo<input name="nome" maxlength="100" required></label><label>E-mail<input type="email" name="email" maxlength="150" autocomplete="off" required></label><label>Senha inicial<input type="password" name="senha" minlength="6" autocomplete="new-password" required></label><label>Perfil<select name="cargo"><option value="usuario">Usuário</option><option value="mecanico">Mecânico</option><option value="administrador">Administrador</option></select></label><button class="botao-acao" type="submit"><i class="bi bi-check2"></i> Cadastrar usuário</button></form></section>
<section class="admin-card"><h2><i class="bi bi-people"></i> Contas cadastradas</h2><table class="admin-tabela"><thead><tr><th>Usuário</th><th>Perfil</th><th>Status</th><th>Ações</th></tr></thead><tbody><?php if (!$usuarios): ?><tr><td colspan="4">Nenhum usuário cadastrado.</td></tr><?php else: foreach ($usuarios as $usuario): ?><tr><td><strong><?= hUsuario($usuario['nome']) ?></strong><small><?= hUsuario($usuario['email']) ?></small></td><td><?= hUsuario($usuario['cargo_nome'] ?: 'Sem perfil') ?></td><td><span class="admin-status <?= (int) $usuario['ativo'] === 1 ? '' : 'inativo' ?>"><?= (int) $usuario['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td><td><div class="admin-acoes"><form method="post"><input type="hidden" name="csrf" value="<?= hUsuario($_SESSION['admin_csrf']) ?>"><input type="hidden" name="acao" value="alternar_status"><input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>"><button type="submit" title="Alternar status"><i class="bi bi-power"></i></button></form><form method="post" onsubmit="return confirm('Excluir este usuário?')"><input type="hidden" name="csrf" value="<?= hUsuario($_SESSION['admin_csrf']) ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>"><button type="submit" title="Excluir usuário"><i class="bi bi-trash"></i></button></form></div></td></tr><?php endforeach; endif; ?></tbody></table></section>
</div></main></div>
<script src="../../assets/js/common.js"></script><script src="../../assets/js/pages.js"></script><script>document.addEventListener('DOMContentLoaded',()=>{document.querySelector('.layout-erp').insertAdjacentHTML('afterbegin',getSidebarHTML('admin_usuarios'));document.querySelector('main').insertAdjacentHTML('afterbegin',getHeaderHTML())})</script>
</body></html>
