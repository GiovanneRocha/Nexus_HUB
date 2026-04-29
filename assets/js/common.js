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
        { href: 'novo_atendimento.html', icon: 'bi-plus-circle', text: 'Nova OS', key: 'novo_atendimento' },
        { href: 'clientes.html', icon: 'bi-people', text: 'Clientes', key: 'clientes' },
        { href: 'revisao.html', icon: 'bi-check-square', text: 'Revisão de OS', key: 'revisao' },
        { href: 'historico.html', icon: 'bi-clock-history', text: 'Histórico', key: 'historico' },
        { href: 'cadastro_servicos.html', icon: 'bi-plus-circle', text: 'Cadastro de Serviços', key: 'cadastro_servicos' },
        { href: 'cadastro_pecas.html', icon: 'bi-plus-circle', text: 'Cadastro de Peças', key: 'cadastro_pecas' },
        { href: 'cadastro_veiculo.html', icon: 'bi-plus-circle', text: 'Cadastro de Veículos', key: 'cadastro_veiculo' }
    ];

    const menuHTML = menuItems.map(item => {
        const isActive = item.key === activePage ? ' class="item-ativo"' : '';
        return `<li${isActive}><a href="${item.href}" class="link-menu"><i class="bi ${item.icon}"></i> ${item.text}</a></li>`;
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

window.addEventListener('DOMContentLoaded', initTheme);
window.addEventListener('click', closeSuspensoMenus);
