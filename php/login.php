<?php
require_once __DIR__ . '/db.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nexusJson(['sucesso' => false, 'mensagem' => 'Método de requisição inválido.'], 405);
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$senha = (string) ($_POST['senha'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
    nexusJson(['sucesso' => false, 'mensagem' => 'Informe um e-mail e uma senha válidos.'], 422);
}

try {
    $pdo = nexusDb();
    $stmt = $pdo->prepare(
        'SELECT u.id, u.nome, u.email, u.senha_hash, u.cargo_id, c.nome AS cargo_nome
         FROM usuarios AS u
         LEFT JOIN cargos AS c ON c.id = u.cargo_id
         WHERE u.email = :email AND u.ativo = 1
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
        nexusJson(['sucesso' => false, 'mensagem' => 'E-mail ou senha inválidos.'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_nome'] = $usuario['nome'];

    $cargo = strtolower(trim((string) ($usuario['cargo_nome'] ?? '')));
    $role = str_contains($cargo, 'mec') || str_contains($cargo, 'técn') || str_contains($cargo, 'tec')
        ? 'mecanico'
        : (str_contains($cargo, 'usu') || str_contains($cargo, 'comum') ? 'usuario' : 'administrador');
    $_SESSION['usuario_role'] = $role;

    $avatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($usuario['email']))) . '?s=160&d=identicon&r=g';

    nexusJson([
        'sucesso' => true,
        'usuario' => [
            'id' => (int) $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'cargo' => $role,
            'avatar' => $avatar,
        ],
    ]);
} catch (Throwable $erro) {
    nexusJson(['sucesso' => false, 'mensagem' => 'Não foi possível consultar o usuário agora.'], 500);
}
