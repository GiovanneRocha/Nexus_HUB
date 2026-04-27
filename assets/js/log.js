// log.js - Histórico de Alterações para OS, Veículos e Peças
// Salva e recupera logs do LocalStorage

function salvarLog(tipo, id, acao, detalhes) {
    const chave = `log_${tipo}_${id}`;
    const agora = new Date().toLocaleString();
    const novoLog = { data: agora, acao, detalhes };
    let historico = JSON.parse(localStorage.getItem(chave)) || [];
    historico.push(novoLog);
    localStorage.setItem(chave, JSON.stringify(historico));
}

function obterLog(tipo, id) {
    const chave = `log_${tipo}_${id}`;
    return JSON.parse(localStorage.getItem(chave)) || [];
}

// Exemplo de uso:
// salvarLog('veiculo', '123', 'Edição', 'Alterou placa para XYZ-0000');
// const logs = obterLog('veiculo', '123');
