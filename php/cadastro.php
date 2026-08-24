<?php
require_once __DIR__ . '/env.php';

// ==========================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// ==========================================
$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
$dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? '');
$user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? '');
$pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

$mensagem = '';

// ==========================================
// PROCESSAMENTO DO FORMULÁRIO (BACKEND)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim(htmlspecialchars($_POST['nome']));
    $email = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
    $senha = $_POST['senha'];
    
    if (empty($nome) || empty($email) || empty($senha)) {
        $mensagem = "<div class='alerta-form erro'><i class='bi bi-exclamation-triangle'></i> Todos os campos são obrigatórios.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "<div class='alerta-form erro'><i class='bi bi-envelope-x'></i> Formato de e-mail inválido.</div>";
    } else {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $mensagem = "<div class='alerta-form erro'><i class='bi bi-x-circle'></i> Este e-mail já está cadastrado.</div>";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $data_criacao = date('Y-m-d H:i:s');
                $ativo = 1; 
                $cargo_id = 1; 

                $sql = "INSERT INTO usuarios (nome, email, senha_hash, data_criacao, ativo, cargo_id) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$nome, $email, $senha_hash, $data_criacao, $ativo, $cargo_id])) {
                    $mensagem = "<div class='alerta-form sucesso'><i class='bi bi-check-circle'></i> Conta criada com sucesso! <a href='../index.html' style='color:inherit; text-decoration:underline;'>Fazer login</a></div>";
                } else {
                    $mensagem = "<div class='alerta-form erro'><i class='bi bi-bug'></i> Erro ao processar o cadastro.</div>";
                }
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
                <a href="../index.html"><i class="bi bi-arrow-left"></i> Voltar ao Login</a>
            </div>

            <div class="caixa-formulario">
                <h2><i class="bi bi-person-plus-fill"></i> Cadastro</h2>
                <p class="subtitulo-formulario">Preencha os dados abaixo para registrar seu perfil.</p>
                
                <?= $mensagem ?>

                <form id="formCadastro" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    
                    <div class="bloco-campo">
                        <label for="nome"><i class="bi bi-person"></i> Nome Completo</label>
                        <input type="text" id="nome" name="nome" placeholder="Seu nome e sobrenome" required>
                        <div id="erroNome" class="error-text"><i class="bi bi-info-circle"></i> O nome é obrigatório.</div>
                    </div>

                    <div class="bloco-campo">
                        <label for="email"><i class="bi bi-envelope"></i> E-mail Corporativo</label>
                        <input type="email" id="email" name="email" placeholder="nome@empresa.com.br" required>
                        <div id="erroEmail" class="error-text"><i class="bi bi-info-circle"></i> Insira um e-mail válido.</div>
                    </div>
                    
                    <div class="bloco-campo">
                        <label for="senha"><i class="bi bi-key"></i> Criar Senha</label>
                        <input type="password" id="senha" name="senha" placeholder="Mínimo de 6 caracteres" required>
                        <div id="erroSenha" class="error-text"><i class="bi bi-info-circle"></i> A senha deve ter pelo menos 6 caracteres.</div>
                    </div>

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

            if (!isValid) { event.preventDefault(); }
        });
    </script>
</body>
</html>