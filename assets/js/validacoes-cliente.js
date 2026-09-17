// ==========================================================
// NEXUS HUB - MÁSCARAS DE CAMPO (tempo real, no navegador)
//
// Aplica automaticamente em qualquer <input data-mascara="...">.
// Isso é só conveniência de digitação (impede letra em campo de telefone,
// por exemplo). A validação que realmente decide se pode salvar continua
// sendo feita no servidor, em php/validacoes.php - esta aqui pode ser
// contornada (JS desligado), então nunca é a única linha de defesa.
// ==========================================================

(function () {
    function mascaraTelefone(valor) {
        let v = valor.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) return v.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
        if (v.length > 6) return v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        if (v.length > 2) return v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        if (v.length > 0) return v.replace(/^(\d{0,2})/, '($1');
        return v;
    }

    function mascaraCNPJ(valor) {
        let v = valor.replace(/\D/g, '').slice(0, 14);
        if (v.length > 12) return v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})/, '$1.$2.$3/$4-$5');
        if (v.length > 8) return v.replace(/^(\d{2})(\d{3})(\d{3})(\d{0,4})/, '$1.$2.$3/$4');
        if (v.length > 5) return v.replace(/^(\d{2})(\d{3})(\d{0,3})/, '$1.$2.$3');
        if (v.length > 2) return v.replace(/^(\d{2})(\d{0,3})/, '$1.$2');
        return v;
    }

    function mascaraPlaca(valor) {
        let v = valor.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
        if (v.length > 3) return v.slice(0, 3) + '-' + v.slice(3);
        return v;
    }

    function mascaraAno(valor) {
        return valor.replace(/\D/g, '').slice(0, 4);
    }

    function mascaraNumerico(valor) {
        // Aceita dígitos e um único ponto/vírgula decimal (valores monetários/estoque
        // já usam <input type="number">, isto é reforço para navegadores antigos).
        return valor.replace(/[^\d.,]/g, '');
    }

    const mascaras = {
        telefone: mascaraTelefone,
        cnpj: mascaraCNPJ,
        placa: mascaraPlaca,
        ano: mascaraAno,
        numerico: mascaraNumerico,
    };

    function aplicarMascaras() {
        document.querySelectorAll('[data-mascara]').forEach((campo) => {
            const tipo = campo.getAttribute('data-mascara');
            const funcao = mascaras[tipo];
            if (!funcao || campo.dataset.mascaraAplicada) return;
            campo.dataset.mascaraAplicada = '1';
            campo.addEventListener('input', () => {
                const posicaoOriginal = campo.selectionStart;
                const tamanhoAntes = campo.value.length;
                campo.value = funcao(campo.value);
                const diferenca = campo.value.length - tamanhoAntes;
                if (posicaoOriginal !== null) {
                    campo.setSelectionRange(posicaoOriginal + diferenca, posicaoOriginal + diferenca);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', aplicarMascaras);
    } else {
        aplicarMascaras();
    }

    // Reexpõe para páginas que criam campos dinamicamente (ex: novo_atendimento.php,
    // que adiciona blocos de veículo/serviço/peça via JavaScript).
    window.NexusMascaras = { aplicar: aplicarMascaras, ...mascaras };
})();
