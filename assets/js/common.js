function alternarMenu() {
    const menu = document.getElementById('menuSuspenso');
    if (!menu) return;
    menu.classList.toggle('visivel');
}

function updateThemeButtonText() {
    const button = document.getElementById('themeToggleButton');
    if (!button) return;
    if (document.body.classList.contains('light-mode')) {
        button.innerHTML = '<i class="bi bi-moon-fill"></i> Modo Escuro';
    } else {
        button.innerHTML = '<i class="bi bi-sun-fill"></i> Modo Claro';
    }
}

function toggleTheme() {
    const isLight = document.body.classList.toggle('light-mode');
    localStorage.setItem('nexus-theme', isLight ? 'light' : 'dark');
    updateThemeButtonText();
}

function initTheme() {
    if (localStorage.getItem('nexus-theme') === 'light') {
        document.body.classList.add('light-mode');
    } else {
        document.body.classList.remove('light-mode');
    }
    updateThemeButtonText();
}

function closeSuspensoMenus(event) {
    if (event.target.matches('.botao-menu-trigger') || event.target.closest('.botao-menu-trigger') || event.target.closest('.menu-suspenso')) {
        return;
    }

    const menus = document.getElementsByClassName('menu-suspenso');
    for (let i = 0; i < menus.length; i++) {
        menus[i].classList.remove('visivel');
    }
}

function getSidebarHTML(activePage = 'home') {
    const menuItems = [
        { href: 'home.html', icon: 'bi-house-door', text: 'início', key: 'home' },
        { href: 'menu.html', icon: 'bi-speedometer2', text: 'Painel', key: 'menu' },
        { href: 'clientes.html', icon: 'bi-people', text: 'Clientes', key: 'clientes' },
        {
            icon: 'bi-wrench',
            text: 'Ordem de Serviço',
            key: 'ordem_servico',
            children: [
                { href: 'novo_atendimento.html', icon: 'bi-plus-circle', text: 'Nova OS', key: 'novo_atendimento' },
                { href: 'revisao.html', icon: 'bi-check-square', text: 'Revisão de OS', key: 'revisao' }
            ]
        },
        {
            icon: 'bi-journal-plus',
            text: 'Gestão',
            key: 'cadastro',
            children: [
                { href: 'cadastro_servicos.html', icon: 'bi-gear', text: 'Gestão de Serviços', key: 'cadastro_servicos' },
                { href: 'cadastro_pecas.html', icon: 'bi-box-seam', text: 'Gestão de Peças', key: 'cadastro_pecas' },
                { href: 'cadastro_veiculo.html', icon: 'bi-truck', text: 'Gestão de Veículos', key: 'cadastro_veiculo' }
            ]
        },
        { href: 'historico.html', icon: 'bi-clock-history', text: 'OS Aprovadas', key: 'historico' }
    ];

    const menuHTML = menuItems.map(item => {
        const isGroupActive = item.key === activePage || (item.children && item.children.some(child => child.key === activePage));
        const groupClass = item.children ? ' menu-grupo' : '';
        const activeClass = isGroupActive ? ' class="item-ativo"' : '';

        if (item.children) {
            const submenuHTML = item.children.map(child => {
                const isChildActive = child.key === activePage ? ' class="item-ativo"' : '';
                return `<li${isChildActive}><a href="${child.href}" class="link-menu"><i class="bi ${child.icon}"></i> ${child.text}</a></li>`;
            }).join('\n                        ');

            return `
                <li class="menu-grupo${isGroupActive ? ' item-ativo' : ''}">
                    <div class="menu-grupo-titulo"><i class="bi ${item.icon}"></i> ${item.text}</div>
                    <ul class="submenu">
                        ${submenuHTML}
                    </ul>
                </li>`;
        }

        return `<li${activeClass}><a href="${item.href}" class="link-menu"><i class="bi ${item.icon}"></i> ${item.text}</a></li>`;
    }).join('\n                    ');

    return `
        <aside class="barra-lateral">
            <div class="topo-lateral">
                <div class="logo-erp"><img src="../assets/images/icon-sistem.png" alt="Nexus" class="logo-icon-small"> Nexus HUB</div>
                <div class="busca-global">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="Buscar...">
                </div>
            </div>

            <nav class="menu-navegacao">
                <ul>
                    ${menuHTML}
                </ul>
            </nav>
        </aside>
    `;
}

function getHeaderHTML() {
    return `
        <header class="cabecalho-topo">
            <div class="alerta-notificacao">
                <i class="bi bi-bell"></i>
            </div>
            <div class="perfil-usuario">
                <div class="info-usuario">
                    <p class="usuario-nome">Julian Vane</p>
                    <p class="usuario-cargo">Administrador</p>
                </div>
                <img src="../assets/images/manoel.jpeg" alt="Perfil" class="foto-perfil">
                
                <div class="menu-opcoes-container">
                    <button class="botao-menu-trigger" onclick="alternarMenu()">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <div class="menu-suspenso" id="menuSuspenso">
                        <button id="themeToggleButton" type="button" onclick="toggleTheme()"><i class="bi bi-circle-half"></i> Modo Claro</button>
                        <a href="#"><i class="bi bi-gear"></i> Configurações</a>
                        <a href="#"><i class="bi bi-headset"></i> Suporte</a>
                        <hr>
                        <a href="../index.html" class="logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>
    `;
}

// ==========================================
// FUNÇÃO PARA REGISTRAR ATIVIDADES
// ==========================================
function registrarAtividade(tipo, descricao, detalhes = '') {
    /**
     * Registra uma atividade no localStorage em nexus_atividades
     * @param {string} tipo - Tipo da atividade (ex: 'empresa_criada', 'os_aprovada', 'veiculo_cadastrado')
     * @param {string} descricao - Descrição breve da atividade
     * @param {string} detalhes - Detalhes adicionais (opcional)
     */
    const atividades = JSON.parse(localStorage.getItem('nexus_atividades')) || [];
    
    const novaAtividade = {
        id: Date.now(),
        tipo: tipo,
        descricao: descricao,
        detalhes: detalhes,
        usuario: "Julian Vane", // Pode ser obtido de sessão futura
        data: new Date().toLocaleString('pt-BR')
    };
    
    atividades.push(novaAtividade);
    localStorage.setItem('nexus_atividades', JSON.stringify(atividades));
}

window.addEventListener('DOMContentLoaded', initTheme);
window.addEventListener('click', closeSuspensoMenus);
