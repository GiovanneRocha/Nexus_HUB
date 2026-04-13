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

window.addEventListener('DOMContentLoaded', initTheme);
window.addEventListener('click', closeSuspensoMenus);
