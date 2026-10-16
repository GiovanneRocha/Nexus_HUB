<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/validacoes.php';
require_once __DIR__ . '/smtp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    nexusSupportJson(['sucesso' => false, 'mensagem' => 'Método de requisição inválido.'], 405);
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$assunto = trim((string) ($_POST['assunto'] ?? ''));
$mensagem = trim((string) ($_POST['mensagem'] ?? ''));

$erros = [];
if (!nexusTextoValido($nome, 3) || nexusTamanhoTexto($nome) > 100) {
    $erros[] = 'Informe seu nome completo (entre 3 e 100 caracteres).';
}
if (!nexusValidarEmail($email) || strlen($email) > 254) {
    $erros[] = 'Informe um e-mail válido para receber a confirmação.';
}
if ($telefone !== '' && !nexusValidarTelefone($telefone)) {
    $erros[] = 'Telefone inválido. Informe DDD + número, ex: (11) 98765-4321.';
}
if (!nexusTextoValido($assunto, 3) || nexusTamanhoTexto($assunto) > 150) {
    $erros[] = 'Informe um assunto (entre 3 e 150 caracteres).';
}
if (!nexusTextoValido($mensagem, 10) || nexusTamanhoTexto($mensagem) > 2000) {
    $erros[] = 'A mensagem deve conter entre 10 e 2000 caracteres.';
}

if ($erros !== []) {
    nexusSupportJson(['sucesso' => false, 'mensagem' => implode(' ', $erros)], 400);
}

try {
    nexusSendSupportEmail($email, $nome, $assunto, $telefone, $mensagem);
} catch (RuntimeException $exception) {
    error_log('Falha no envio de e-mail de suporte: ' . $exception->getMessage());
    if ($exception->getCode() === 503) {
        nexusSupportJson([
            'sucesso' => false,
            'mensagem' => 'O envio de e-mail ainda não está configurado no servidor. Configure o SMTP do remetente antes de enviar solicitações.',
        ], 503);
    }

    nexusSupportJson([
        'sucesso' => false,
        'mensagem' => 'O servidor de e-mail recusou ou não conseguiu concluir o envio. Verifique a configuração SMTP e tente novamente.',
    ], 502);
}

nexusSupportJson([
    'sucesso' => true,
    'mensagem' => 'Sua solicitação foi enviada e uma confirmação foi encaminhada para o e-mail informado.',
]);

function nexusSupportJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
