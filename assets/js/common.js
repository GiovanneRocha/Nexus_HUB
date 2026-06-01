// ==========================================
// SISTEMA DE TEMA CLARO/ESCURO APRIMORADO
// ==========================================

/**
 * Obtém a preferência de tema do sistema
 * @returns {string} 'light' ou 'dark'
 */
function getSystemThemePreference() {
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'dark';
    }
    return 'light';
}

/**
 * Aplica o tema ao documento
 * @param {string} theme - 'light' ou 'dark'
 */
function applyTheme(theme) {
    const html = document.documentElement;
    
    if (theme === 'light') {
        document.body.classList.add('light-mode');
        html.style.colorScheme = 'light';
    } else {
        document.body.classList.remove('light-mode');
        html.style.colorScheme = 'dark';
    }
    
    // Atualizar os botões de theme em toda a página
    updateThemeButtonText();
    
    // Disparar evento customizado para sincronizar entre abas
    window.dispatchEvent(new CustomEvent('themechanged', { detail: { theme } }));
}

/**
 * Obtém o tema salvo ou detecta do sistema
 * @returns {string} 'light' ou 'dark'
 */
function getSavedTheme() {
    const saved = localStorage.getItem('nexus-theme');
    if (saved) {
        return saved;
    }
    
    // Se não tiver salvo, detectar preferência do sistema
    return getSystemThemePreference();
}

/**
 * Inicializa o tema na primeira carga
 */
function initTheme() {
    const theme = getSavedTheme();
    applyTheme(theme);
    
    // Sincronizar com mudanças de preferência do sistema
    if (window.matchMedia) {
        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        darkModeQuery.addListener((e) => {
            if (!localStorage.getItem('nexus-theme')) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }
    
    // Sincronizar entre abas
    window.addEventListener('storage', (e) => {
        if (e.key === 'nexus-theme' && e.newValue) {
            applyTheme(e.newValue);
        }
    });
}

/**
 * Alterna entre tema claro e escuro
 */
function toggleTheme() {
    const isLight = document.body.classList.contains('light-mode');
    const newTheme = isLight ? 'dark' : 'light';
    
    // Salvar preferência
    localStorage.setItem('nexus-theme', newTheme);
    
    // Aplicar tema
    applyTheme(newTheme);
}

function openSidebar() {
    const sidebar = document.getElementById('barraLateral');
    const overlay = document.getElementById('barraLateralOverlay');
    if (sidebar) sidebar.classList.add('aberto');
    if (overlay) overlay.classList.add('aberto');
}

function closeSidebar() {
    const sidebar = document.getElementById('barraLateral');
    const overlay = document.getElementById('barraLateralOverlay');
    if (sidebar) sidebar.classList.remove('aberto');
    if (overlay) overlay.classList.remove('aberto');
}

function toggleSidebar() {
    const sidebar = document.getElementById('barraLateral');
    if (!sidebar) return;
    if (sidebar.classList.contains('aberto')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

/**
 * Atualiza o texto e ícone do botão de tema
 */
function updateThemeButtonText() {
    const buttons = document.querySelectorAll('#themeToggleButton');
    const isLight = document.body.classList.contains('light-mode');
    
    buttons.forEach(button => {
        if (isLight) {
            button.innerHTML = '<i class="bi bi-moon-fill"></i> Modo Escuro';
            button.title = 'Alternar para modo escuro';
        } else {
            button.innerHTML = '<i class="bi bi-sun-fill"></i> Modo Claro';
            button.title = 'Alternar para modo claro';
        }
    });
}

// Inicializar tema quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
} else {
    initTheme();
}

function alternarMenu() {
    const menu = document.getElementById('menuSuspenso');
    if (!menu) return;
    menu.classList.toggle('visivel');
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
        <aside class="barra-lateral" id="barraLateral">
            <div class="barra-lateral-topo">
                <div class="topo-lateral">
                    <div class="logo-erp"><img src="../assets/images/icon-sistem.png" alt="Nexus" class="logo-icon-small"> Nexus HUB</div>
                    <div class="busca-global">
                        <i class="bi bi-search"></i>
                        <input type="text" placeholder="Buscar...">
                    </div>
                </div>
            </div>

            <div class="barra-lateral-meio">
                <nav class="menu-navegacao">
                    <ul>
                        ${menuHTML}
                    </ul>
                </nav>
            </div>

            <div class="barra-lateral-rodape">
                <a href="../index.html" class="link-rodape"><i class="bi bi-box-arrow-right"></i> Sair</a>
            </div>
        </aside>
        <div class="barra-lateral-overlay" id="barraLateralOverlay" onclick="closeSidebar()"></div>
    `;
}

function getHeaderHTML() {
    return `
        <header class="cabecalho-topo">
            <button class="botao-abre-menu-lateral" type="button" onclick="toggleSidebar()" aria-label="Abrir menu">
                <i class="bi bi-list"></i>
            </button>
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
