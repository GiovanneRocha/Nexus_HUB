<?php
// ==========================================
// CONFIGURAÇÕES DO FORMULÁRIO DE SUPORTE
// ==========================================

// Habilitar CORS se necessário
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Verificar se é uma requisição POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método de requisição inválido.'
    ]);
    exit;
}

// ==========================================
// RECEBER E VALIDAR DADOS
// ==========================================

$nome = isset($_POST['nome']) ? trim(htmlspecialchars($_POST['nome'])) : '';
$email = isset($_POST['email']) ? trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL)) : '';
$telefone = isset($_POST['telefone']) ? trim(htmlspecialchars($_POST['telefone'])) : '';
$assunto = isset($_POST['assunto']) ? trim(htmlspecialchars($_POST['assunto'])) : '';
$mensagem = isset($_POST['mensagem']) ? trim(htmlspecialchars($_POST['mensagem'])) : '';

// Validações básicas
$erros = [];

if (empty($nome)) {
    $erros[] = 'Nome é obrigatório.';
}

if (empty($assunto)) {
    $erros[] = 'Assunto é obrigatório.';
}

if (empty($mensagem)) {
    $erros[] = 'Mensagem é obrigatória.';
}

if (empty($email) && empty($telefone)) {
    $erros[] = 'Forneça pelo menos um meio de contato (e-mail ou telefone).';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'E-mail inválido.';
}

// Se há erros, retornar
if (!empty($erros)) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => implode(' ', $erros)
    ]);
    exit;
}

// ==========================================
// CONFIGURAÇÕES DE EMAIL
// ==========================================

// Email para onde as mensagens serão enviadas
$email_destino = 'suporte@nexushub.com.br'; // ALTERAR PARA EMAIL REAL
$email_remetente = !empty($email) ? $email : 'suporte@nexushub.com.br';
$nome_remetente = $nome;

// Assunto do email
$assunto_email = "Nova Mensagem de Suporte: {$assunto}";

// ==========================================
// CONSTRUIR CORPO DO EMAIL
// ==========================================

$corpo_email = "
<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
        }
        .header {
            background: #0f1b2f;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .header h2 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: white;
            padding: 20px;
            border-radius: 0 0 8px 8px;
        }
        .campo {
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        .campo:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: bold;
            color: #10b981;
            font-size: 14px;
            text-transform: uppercase;
        }
        .valor {
            margin-top: 5px;
            color: #333;
            font-size: 16px;
            word-wrap: break-word;
        }
        .footer {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .prioridade {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Nova Mensagem de Suporte</h2>
        </div>
        
        <div class='content'>
            <div class='campo'>
                <div class='label'>Nome do Solicitante</div>
                <div class='valor'>{$nome}</div>
            </div>

            <div class='campo'>
                <div class='label'>E-mail para Contato</div>
                <div class='valor'>{$email}</div>
            </div>

            <div class='campo'>
                <div class='label'>Telefone para Contato</div>
                <div class='valor'>{$telefone}</div>
            </div>

            <div class='campo'>
                <div class='label'>Assunto</div>
                <div class='valor'>{$assunto}</div>
            </div>

            <div class='campo'>
                <div class='label'>Mensagem</div>
                <div class='valor'>" . nl2br($mensagem) . "</div>
            </div>

            <div class='campo'>
                <div class='label'>Data e Hora</div>
                <div class='valor'>" . date('d/m/Y H:i:s') . "</div>
            </div>

            <div style='margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;'>
                <p style='margin: 0; font-size: 14px; color: #666;'>
                    <span class='prioridade'>NOVO</span> Esta é uma nova solicitação que requer resposta.
                </p>
            </div>
        </div>

        <div class='footer'>
            <p>Este é um e-mail automático gerado pelo sistema Nexus HUB. Não responda este e-mail diretamente.</p>
        </div>
    </div>
</body>
</html>
";

// ==========================================
// ENVIAR EMAIL
// ==========================================

// Headers do email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
$headers .= "From: <{$email_remetente}>" . "\r\n";
$headers .= "Reply-To: {$email_remetente}" . "\r\n";
$headers .= "X-Mailer: Nexus HUB Suporte" . "\r\n";

// Tentar enviar o email
$email_enviado = false;

// Método 1: Usar a função mail() do PHP
if (function_exists('mail')) {
    $email_enviado = @mail(
        $email_destino,
        $assunto_email,
        $corpo_email,
        $headers
    );
}

// Se email foi enviado ou se não há função mail (ambiente simulado)
if ($email_enviado || !function_exists('mail')) {
    // Registrar a solicitação em um arquivo de log (opcional)
    $arquivo_log = __DIR__ . '/../logs/suporte_' . date('Y-m-d') . '.txt';
    
    // Criar diretório de logs se não existir
    $dir_logs = dirname($arquivo_log);
    if (!is_dir($dir_logs)) {
        @mkdir($dir_logs, 0755, true);
    }
    
    // Registrar no log
    $log = date('Y-m-d H:i:s') . " | Nome: {$nome} | Email: {$email} | Telefone: {$telefone} | Assunto: {$assunto}\n";
    @file_put_contents($arquivo_log, $log, FILE_APPEND);
    
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Sua mensagem foi recebida com sucesso! Nossa equipe entrará em contato em breve.'
    ]);
} else {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Houve um erro ao enviar a mensagem. Tente novamente mais tarde.'
    ]);
}

exit;
?>
