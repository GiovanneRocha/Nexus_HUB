<?php
// ==========================================================
// NEXUS HUB - MÓDULO CENTRAL DE VALIDAÇÕES (PHP)
//
// Usado por todas as páginas que gravam dados no banco. Mesmas regras em
// todos os formulários: e-mail, telefone, CNPJ, placa (formato antigo ou
// Mercosul), ano do veículo (1950 até ano atual + 1), valores monetários e
// textos obrigatórios.
//
// Client-side (JS/HTML5) só melhora a experiência - esta é a validação que
// realmente protege o banco, porque pode ser chamada mesmo sem passar pelo
// formulário (JS desabilitado, requisição feita direto por fora do site).
// ==========================================================

/** E-MAIL */
function nexusValidarEmail(string $valor): bool
{
    return filter_var(trim($valor), FILTER_VALIDATE_EMAIL) !== false;
}

/** TELEFONE (fixo: 10 dígitos | celular: 11 dígitos) */
function nexusValidarTelefone(string $valor): bool
{
    $numeros = preg_replace('/\D/', '', $valor);
    return strlen($numeros) === 10 || strlen($numeros) === 11;
}

/** Apenas os dígitos de uma string (CNPJ, telefone, etc.) */
function nexusApenasNumeros(string $valor): string
{
    return preg_replace('/\D/', '', $valor);
}

/** CNPJ (com dígito verificador oficial) */
function nexusValidarCNPJ(string $valor): bool
{
    $numeros = nexusApenasNumeros($valor);
    if (strlen($numeros) !== 14) {
        return false;
    }
    if (preg_match('/^(\d)\1+$/', $numeros)) {
        return false;
    }

    $calcDigito = function (string $nums, array $pesos): int {
        $soma = 0;
        foreach (str_split($nums) as $i => $n) {
            $soma += (int) $n * $pesos[$i];
        }
        $resto = $soma % 11;
        return $resto < 2 ? 0 : 11 - $resto;
    };

    $d1 = $calcDigito(substr($numeros, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    $d2 = $calcDigito(substr($numeros, 0, 13), [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

    return (int) $numeros[12] === $d1 && (int) $numeros[13] === $d2;
}

/** PLACA (formato antigo ABC1234 ou Mercosul ABC1D23, com ou sem hífen) */
function nexusValidarPlaca(string $valor): bool
{
    $limpa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $valor));
    return (bool) preg_match('/^[A-Z]{3}(\d{4}|\d[A-Z]\d{2})$/', $limpa);
}

/** Normaliza a placa para armazenamento (maiúscula, sem hífen). */
function nexusNormalizarPlaca(string $valor): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $valor));
}

/** ANO DO VEÍCULO (1950 até ano atual + 1) */
function nexusLimitesAnoVeiculo(): array
{
    $anoAtual = (int) date('Y');
    return ['min' => 1950, 'max' => $anoAtual + 1];
}

function nexusValidarAnoVeiculo($valor): bool
{
    if (!is_numeric($valor)) {
        return false;
    }
    $limites = nexusLimitesAnoVeiculo();
    $ano = (int) $valor;
    return $ano >= $limites['min'] && $ano <= $limites['max'];
}

/** ----------------------------------------------------------
 * Compatibilidade: alguns servidores não têm a extensão mbstring
 * habilitada. Sem estes fallbacks, as páginas quebrariam com erro
 * fatal ("Call to undefined function mb_strlen()").
 * ---------------------------------------------------------- */
function nexusTamanhoTexto(string $valor): int
{
    return function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : strlen($valor);
}

function nexusMaiusculas(string $valor): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($valor, 'UTF-8') : strtoupper($valor);
}

/** TEXTO OBRIGATÓRIO (bloqueia strings só com espaço) */
function nexusTextoValido(string $valor, int $minimo = 2): bool
{
    return nexusTamanhoTexto(trim($valor)) >= $minimo;
}

/** VALOR MONETÁRIO (não-negativo e dentro de um teto razoável) */
function nexusValidarValorMonetario($valor, float $max = 999999.99): bool
{
    if (!is_numeric($valor)) {
        return false;
    }
    $n = (float) $valor;
    return $n >= 0 && $n <= $max;
}

/** QUANTIDADE / ESTOQUE (inteiro, não-negativo, teto razoável) */
function nexusValidarInteiroNaoNegativo($valor, int $max = 9999999): bool
{
    if (!is_numeric($valor)) {
        return false;
    }
    $n = (int) $valor;
    return $n >= 0 && $n <= $max;
}

/** URL simples (opcional - ex: site da empresa) */
function nexusValidarUrl(string $valor): bool
{
    $v = trim($valor);
    if ($v === '') {
        return true; // campo opcional
    }
    return filter_var($v, FILTER_VALIDATE_URL) !== false && (bool) preg_match('#^https?://#i', $v);
}

/** Data no formato aceito por <input type="date"> (YYYY-MM-DD) */
function nexusValidarData(string $valor): bool
{
    if ($valor === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $valor);
    return $d !== false && $d->format('Y-m-d') === $valor;
}
