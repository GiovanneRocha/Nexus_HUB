<?php
require_once __DIR__ . '/db.php';

$mensagem = '';
$nome = '';
$email = '';
$senha = '';
$confirmarSenha = '';
$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'admin';
$cargoId = $tipo === 'tecnico' ? 2 : 1;
$tipoLabel = $tipo === 'tecnico' ? 'Técnico' : 'Administrador';

// ==========================================
// PROCESSAMENTO DO FORMULÁRIO (BACKEND)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');
    $tipo = ($_POST['tipo'] ?? 'admin') === 'tecnico' ? 'tecnico' : 'admin';
    $cargoId = $tipo === 'tecnico' ? 2 : 1;
    $tipoLabel = $tipo === 'tecnico' ? 'Técnico' : 'Administrador';
    
    if ($nome === '' || $email === '' || $senha === '' || $confirmarSenha === '') {
        $mensagem = "<div class='alerta-form erro'><i class='bi bi-exclamation-triangle'></i> Todos os campos são obrigatórios.</div>";
    } elseif (mb_strlen($nome) > 100) {
        $mensagem = "<div class='alerta-form erro'><i class='bi bi-person-x'></i> O nome deve ter no máximo 100 caracteres.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "<div class='alerta-form erro'><i class='bi bi-envelope-x'></i> Formato de e-mail inválido.</div>";
    } elseif (mb_strlen($senha) < 6) {
        $mensagem = "<div class='alerta-form erro'><i class='bi bi-key'></i> A senha deve ter pelo menos 6 caracteres.</div>";
    } elseif ($senha !== $confirmarSenha) {
        $mensagem = "<div class='alerta-form erro'><i class='bi bi-key-fill'></i> As senhas não conferem.</div>";
    } else {
        try {
            $pdo = nexusDb();

            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            
            if ($stmt->fetch()) {
                $mensagem = "<div class='alerta-form erro'><i class='bi bi-x-circle'></i> Este e-mail já está cadastrado.</div>";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql = 'INSERT INTO usuarios (nome, email, senha_hash, ativo, cargo_id) VALUES (:nome, :email, :senha_hash, 1, :cargo_id)';
                $stmt = $pdo->prepare($sql);
                
                $stmt->execute([
                    'nome' => $nome,
                    'email' => $email,
                    'senha_hash' => $senha_hash,
                    'cargo_id' => $cargoId,
                ]);
                $mensagem = "<div class='alerta-form sucesso'><i class='bi bi-check-circle'></i> Conta de {$tipoLabel} criada com sucesso! <a href='../index.html' style='color:inherit; text-decoration:underline;'>Fazer login</a></div>";
                $nome = '';
                $email = '';
                $senha = '';
                $confirmarSenha = '';
            }
        } catch(PDOException $e) {
            $mensagem = "<div class='alerta-form erro'><i class='bi bi-database-x'></i> Erro de conexão com o banco.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus HUB - Nova Conta</title>
    <link rel="icon" type="image/png" href="../assets/images/icon-sistem.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Estilos específicos para os alertas do formulário mantendo a identidade visual */
        .alerta-form {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 22px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alerta-form.erro { background-color: #fee2e2; color: #b91c1c; border: 1px solid #f87171; }
        .alerta-form.sucesso { background-color: #d1fae5; color: #047857; border: 1px solid #34d399; }
        .error-text { color: #ef4444; font-size: 0.8rem; margin-top: 5px; display: none; font-weight: 500; }
    </style>
</head>
<body>
    <div class="container-principal">
        <div class="secao-apresentacao fundo-login">
            <div class="conteudo-apresentacao">
                <div class="logotipo"><img src="../assets/images/icon-sistem.png" alt="Nexus HUB" class="logo-icon" onerror="this.src=''"> Nexus HUB</div>
                <p class="subtitulo-topo">Junte-se à Plataforma</p>
                <h1>Crie sua <span>Conta de Acesso</span></h1>
                <p class="descricao-texto">Tenha acesso à plataforma completa para gerenciamento de atendimentos, acompanhamento de ordens de serviço e histórico.</p>
                <div class="citacao-rodape">
                    <i class="bi bi-shield-check"></i> Ambiente seguro e restrito a colaboradores.
                </div>
            </div>
        </div>

        <div class="secao-formulario">
            <div class="botao-voltar">
                <a href="../pages/auth/cadastro_tipo.html"><i class="bi bi-arrow-left"></i> Voltar ao Tipo de Acesso</a>
            </div>

            <div class="caixa-formulario">
                <h2><i class="bi bi-person-plus-fill"></i> Cadastro</h2>
                <p class="subtitulo-formulario">Preencha os dados abaixo para registrar seu perfil.</p>
                
                <?= $mensagem ?>

                <form id="formCadastro" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    
                    <div class="bloco-campo">
                        <label for="nome"><i class="bi bi-person"></i> Nome Completo</label>
                        <input type="text" id="nome" name="nome" placeholder="Seu nome e sobrenome" value="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>" maxlength="100" autocomplete="name" required>
                        <div id="erroNome" class="error-text"><i class="bi bi-info-circle"></i> O nome é obrigatório.</div>
                    </div>

                    <div class="bloco-campo">
                        <label for="email"><i class="bi bi-envelope"></i> E-mail Corporativo</label>
                        <input type="email" id="email" name="email" placeholder="nome@empresa.com.br" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" maxlength="150" autocomplete="email" required>
                        <div id="erroEmail" class="error-text"><i class="bi bi-info-circle"></i> Insira um e-mail válido.</div>
                    </div>
                    
                    <div class="bloco-campo">
                        <label for="senha"><i class="bi bi-key"></i> Criar Senha</label>
                                        <input type="password" id="senha" name="senha" placeholder="Mínimo de 6 caracteres" minlength="6" autocomplete="new-password" required>
                        <div id="erroSenha" class="error-text"><i class="bi bi-info-circle"></i> A senha deve ter pelo menos 6 caracteres.</div>
                    </div>

                                        <div class="bloco-campo">
                                                <label for="confirmar_senha"><i class="bi bi-key-fill"></i> Confirmar Senha</label>
                                                <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita sua senha" minlength="6" autocomplete="new-password" required>
                                                <div id="erroConfirmarSenha" class="error-text"><i class="bi bi-info-circle"></i> As senhas devem ser iguais.</div>
                                        </div>

                                        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>">

                    <button type="submit" class="botao-acao"><i class="bi bi-check2-circle"></i> Finalizar Cadastro</button>
                </form>

                <div class="rodape-tela">
                    <div class="links-institucionais">
                        <a href="#"><i class="bi bi-question-circle"></i> Precisa de ajuda?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('formCadastro').addEventListener('submit', function(event) {
            let isValid = true;
            const nome = document.getElementById('nome').value.trim();
            const email = document.getElementById('email').value.trim();
            const senha = document.getElementById('senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;

            if (nome === '') {
                document.getElementById('erroNome').style.display = 'block';
                isValid = false;
            } else { document.getElementById('erroNome').style.display = 'none'; }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                document.getElementById('erroEmail').style.display = 'block';
                isValid = false;
            } else { document.getElementById('erroEmail').style.display = 'none'; }

            if (senha.length < 6) {
                document.getElementById('erroSenha').style.display = 'block';
                isValid = false;
            } else { document.getElementById('erroSenha').style.display = 'none'; }

            if (senha !== confirmarSenha) {
                document.getElementById('erroConfirmarSenha').style.display = 'block';
                isValid = false;
            } else { document.getElementById('erroConfirmarSenha').style.display = 'none'; }

            if (!isValid) { event.preventDefault(); }
        });
    </script>
</body>
</html>