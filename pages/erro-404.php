<?php
http_response_code(404);

$scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$pagesPosition = strpos($scriptPath, '/pages/');
$basePath = $pagesPosition === false
    ? rtrim(dirname($scriptPath), '/') . '/'
    : substr($scriptPath, 0, $pagesPosition + 1);
$basePath = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Página não encontrada - Nexus HUB</title>
    <link rel="icon" type="image/png" href="<?= $basePath ?>assets/images/icon-sistem.png">
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/error.css">
</head>
<body>
    <main class="pagina-erro">
        <section class="erro-cartao" aria-labelledby="titulo-erro">
            <a class="erro-marca" href="<?= $basePath ?>index.html" aria-label="Nexus HUB - página inicial">
                <img src="<?= $basePath ?>assets/images/icon-sistem.png" alt="">
                <span>Nexus HUB</span>
            </a>
            <div class="erro-codigo" aria-hidden="true">404</div>
            <div class="erro-icone" aria-hidden="true"><i class="bi bi-compass"></i></div>
            <h1 id="titulo-erro">Não encontramos esta página</h1>
            <p>O endereço pode estar incorreto ou a página pode ter sido movida. Confira o link ou volte para continuar usando o Nexus HUB.</p>
            <div class="erro-acoes">
                <a class="erro-acao-principal" href="<?= $basePath ?>index.html">
                    <i class="bi bi-house-door"></i> Ir para o início
                </a>
                <button class="erro-acao-secundaria" id="botao-voltar" type="button">
                    <i class="bi bi-arrow-left"></i> Voltar à página anterior
                </button>
            </div>
            <a class="erro-suporte" href="<?= $basePath ?>pages/info/suporte.html">
                <i class="bi bi-headset"></i> Precisa de ajuda? Fale com o suporte
            </a>
        </section>
    </main>
    <script>
        document.getElementById('botao-voltar').addEventListener('click', () => {
            if (window.history.length > 1) {
                window.history.back();
                return;
            }
            window.location.href = <?= json_encode($basePath . 'index.html', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        });
    </script>
</body>
</html>
