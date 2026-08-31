// ==========================================
// SISTEMA DE TEMA CLARO/ESCURO APRIMORADO
// ==========================================

/**
 * Obtém a preferência de tema do sistema
 * @returns {string} 'light' ou 'dark'
 */
function getSystemThemePreference() {
  if (
    window.matchMedia &&
    window.matchMedia("(prefers-color-scheme: dark)").matches
  ) {
    return "dark"
  }
  return "light"
}

/**
 * Aplica o tema ao documento
 * @param {string} theme - 'light' ou 'dark'
 */
function applyTheme(theme) {
  const html = document.documentElement

  if (theme === "light") {
    document.body.classList.add("light-mode")
    html.style.colorScheme = "light"
  } else {
    document.body.classList.remove("light-mode")
    html.style.colorScheme = "dark"
  }

  // Atualizar os botões de theme em toda a página
  updateThemeButtonText()

  // Disparar evento customizado para sincronizar entre abas
  window.dispatchEvent(new CustomEvent("themechanged", { detail: { theme } }))
}

/**
 * Obtém o tema salvo ou detecta do sistema
 * @returns {string} 'light' ou 'dark'
 */
function getSavedTheme() {
  const saved = localStorage.getItem("nexus-theme")
  if (saved) {
    return saved
  }

  // Se não tiver salvo, detectar preferência do sistema
  return getSystemThemePreference()
}

/**
 * Inicializa o tema na primeira carga
 */
function initTheme() {
  const theme = getSavedTheme()
  applyTheme(theme)

  // Sincronizar com mudanças de preferência do sistema
  if (window.matchMedia) {
    const darkModeQuery = window.matchMedia("(prefers-color-scheme: dark)")
    darkModeQuery.addListener((e) => {
      if (!localStorage.getItem("nexus-theme")) {
        applyTheme(e.matches ? "dark" : "light")
      }
    })
  }

  // Sincronizar entre abas
  window.addEventListener("storage", (e) => {
    if (e.key === "nexus-theme" && e.newValue) {
      applyTheme(e.newValue)
    }
  })
}

/**
 * Alterna entre tema claro e escuro
 */
function toggleTheme() {
  const isLight = document.body.classList.contains("light-mode")
  const newTheme = isLight ? "dark" : "light"

  // Salvar preferência
  localStorage.setItem("nexus-theme", newTheme)

  // Aplicar tema
  applyTheme(newTheme)
}

function openSidebar() {
  const sidebar = document.getElementById("barraLateral")
  const overlay = document.getElementById("barraLateralOverlay")
  if (sidebar) sidebar.classList.add("aberto")
  if (overlay) overlay.classList.add("aberto")
}

function closeSidebar() {
  const sidebar = document.getElementById("barraLateral")
  const overlay = document.getElementById("barraLateralOverlay")
  if (sidebar) sidebar.classList.remove("aberto")
  if (overlay) overlay.classList.remove("aberto")
}

function toggleSidebar() {
  const sidebar = document.getElementById("barraLateral")
  if (!sidebar) return
  if (sidebar.classList.contains("aberto")) {
    closeSidebar()
  } else {
    openSidebar()
  }
}

function setSidebarCollapsedState(collapsed) {
  const body = document.body
  if (!body) return
  body.classList.toggle("sidebar-collapsed", collapsed)
}

function toggleSidebarCollapse() {
  const body = document.body
  if (!body) return

  const isCollapsed = body.classList.contains("sidebar-collapsed")
  body.classList.toggle("sidebar-collapsed", !isCollapsed)

  const sidebar = document.getElementById("barraLateral")
  if (sidebar) {
    sidebar.classList.toggle("aberto", !isCollapsed)
  }
}

/**
 * Atualiza o texto e ícone do botão de tema
 */
function updateThemeButtonText() {
  const buttons = document.querySelectorAll("#themeToggleButton")
  const isLight = document.body.classList.contains("light-mode")

  buttons.forEach((button) => {
    if (isLight) {
      button.innerHTML = '<i class="bi bi-moon-fill"></i> Modo Escuro'
      button.title = "Alternar para modo escuro"
    } else {
      button.innerHTML = '<i class="bi bi-sun-fill"></i> Modo Claro'
      button.title = "Alternar para modo claro"
    }
  })
}

// Inicializar tema quando DOM estiver pronto
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initTheme)
} else {
  initTheme()
}

function alternarMenu() {
  const menu = document.getElementById("menuSuspenso")
  if (!menu) return
  menu.classList.toggle("visivel")
}

function closeSuspensoMenus(event) {
  if (
    event.target.matches(".botao-menu-trigger") ||
    event.target.closest(".botao-menu-trigger") ||
    event.target.closest(".menu-suspenso")
  ) {
    return
  }

  const menus = document.getElementsByClassName("menu-suspenso")
  for (let i = 0; i < menus.length; i++) {
    menus[i].classList.remove("visivel")
  }
}

function normalizeRole(role) {
  const normalized = String(role || "")
    .trim()
    .toLowerCase()
  const aliases = {
    admin: "administrador",
    adm: "administrador",
    administrador: "administrador",
    tecnico: "mecanico",
    mecanico: "mecanico",
    mechanic: "mecanico",
    usuario: "usuario",
    user: "usuario",
    cliente: "usuario",
    cliente: "usuario",
  }

  return aliases[normalized] || "administrador"
}

function getCurrentUserRole() {
  const savedRole =
    localStorage.getItem("nexus-role") || sessionStorage.getItem("nexus-role")
  return normalizeRole(savedRole)
}

function getCurrentUserName() {
  const savedName =
    localStorage.getItem("nexus-user-name") ||
    sessionStorage.getItem("nexus-user-name")
  return savedName || "Julian Vane"
}

function getRoleLabel(role) {
  const labels = {
    administrador: "Administrador",
    mecanico: "Mecânico",
    usuario: "Usuário",
  }

  return labels[normalizeRole(role)] || "Administrador"
}

function getAllowedPageKeysForRole(role) {
  const permissions = {
    administrador: [
      "home",
      "menu",
      "clientes",
      "novo_atendimento",
      "revisao",
      "cadastro_servicos",
      "cadastro_pecas",
      "cadastro_veiculo",
      "historico",
    ],
    mecanico: ["home", "menu", "novo_atendimento", "revisao", "historico"],
    usuario: ["home", "menu", "clientes", "historico"],
  }

  return permissions[normalizeRole(role)] || permissions.administrador
}

function getDefaultPageForRole(role) {
  return normalizeRole(role) === "mecanico" ? "home.html" : "home.html"
}

function setCurrentUserRole(role, name = "") {
  const normalizedRole = normalizeRole(role)
  localStorage.setItem("nexus-role", normalizedRole)
  sessionStorage.setItem("nexus-role", normalizedRole)

  if (name) {
    localStorage.setItem("nexus-user-name", name)
    sessionStorage.setItem("nexus-user-name", name)
  }
}

function canAccessPage(pageKey) {
  return getAllowedPageKeysForRole(getCurrentUserRole()).includes(pageKey)
}

function getProjectRootBasePath() {
  const pathname = window.location.pathname || ""
  if (pathname.includes("/pages/operacoes/")) return "../../"
  if (pathname.includes("/pages/cadastros/")) return "../../"
  if (pathname.includes("/pages/auth/")) return "../../"
  if (pathname.includes("/pages/info/")) return "../../"
  if (pathname.includes("/pages/")) return "../"
  return "./"
}

function getPagesBasePath() {
  const pathname = window.location.pathname || ""
  if (pathname.includes("/pages/operacoes/")) return "../"
  if (pathname.includes("/pages/cadastros/")) return "../"
  if (pathname.includes("/pages/auth/")) return "../"
  if (pathname.includes("/pages/info/")) return "../"
  return ""
}

function resolveAssetPath(assetPath) {
  return `${getProjectRootBasePath()}${assetPath.replace(/^\.?\//, "")}`
}

function resolveRootLink(pagePath) {
  return `${getProjectRootBasePath()}${pagePath.replace(/^\.?\//, "")}`
}

function getSidebarHTML(activePage = "home") {
  const menuItems = [
    { href: "home.html", icon: "bi-house-door", text: "início", key: "home" },
    { href: "menu.html", icon: "bi-speedometer2", text: "Painel", key: "menu" },
    {
      href: "clientes.html",
      icon: "bi-people",
      text: "Clientes",
      key: "clientes",
    },
    {
      icon: "bi-wrench",
      text: "Ordem de Serviço",
      key: "ordem_servico",
      children: [
        {
          href: "novo_atendimento.html",
          icon: "bi-plus-circle",
          text: "Nova OS",
          key: "novo_atendimento",
        },
        {
          href: "revisao.html",
          icon: "bi-check-square",
          text: "Revisão de OS",
          key: "revisao",
        },
      ],
    },
    {
      icon: "bi-journal-plus",
      text: "Gestão",
      key: "cadastro",
      children: [
        {
          href: "cadastros/cadastro_servicos.php",
          icon: "bi-gear",
          text: "Gestão de Serviços",
          key: "cadastro_servicos",
        },
        {
          href: "cadastros/cadastro_pecas.php",
          icon: "bi-box-seam",
          text: "Gestão de Peças",
          key: "cadastro_pecas",
        },
        {
          href: "cadastros/cadastro_veiculo.php",
          icon: "bi-truck",
          text: "Gestão de Veículos",
          key: "cadastro_veiculo",
        },
      ],
    },
    {
      href: "historico.html",
      icon: "bi-clock-history",
      text: "OS Aprovadas",
      key: "historico",
    },
  ]

  const role = getCurrentUserRole()
  const allowed = new Set(getAllowedPageKeysForRole(role))
  const filteredMenuItems = menuItems.filter((item) => {
    if (item.children) {
      const children = item.children.filter((child) => allowed.has(child.key))
      if (!children.length) return false
      item.children = children
      return true
    }
    return allowed.has(item.key)
  })

  const menuHTML = filteredMenuItems
    .map((item) => {
      const isGroupActive =
        item.key === activePage ||
        (item.children &&
          item.children.some((child) => child.key === activePage))
      const activeClass = isGroupActive ? ' class="item-ativo"' : ""

      if (item.children) {
        const submenuHTML = item.children
          .map((child) => {
            const isChildActive =
              child.key === activePage ? ' class="item-ativo"' : ""
            return `<li${isChildActive}><a href="${getPagesBasePath()}${child.href}" class="link-menu"><i class="bi ${child.icon}"></i> <span>${child.text}</span></a></li>`
          })
          .join("\n                        ")

        return `
                <li class="menu-grupo${isGroupActive ? " item-ativo" : ""}">
                    <div class="menu-grupo-titulo"><i class="bi ${item.icon}"></i> <span>${item.text}</span></div>
                    <ul class="submenu">
                        ${submenuHTML}
                    </ul>
                </li>`
      }

      return `<li${activeClass}><a href="${getPagesBasePath()}${item.href}" class="link-menu"><i class="bi ${item.icon}"></i> <span>${item.text}</span></a></li>`
    })
    .join("\n                    ")

  return `
        <aside class="barra-lateral" id="barraLateral">
            <div class="barra-lateral-topo">
                <div class="topo-lateral">
                    <button type="button" class="logo-erp logo-toggle" onclick="toggleSidebarCollapse()" aria-label="Expandir menu lateral">
                        <img src="${resolveAssetPath("assets/images/icon-sistem.png")}" alt="Nexus" class="logo-icon-small">
                        <span class="logo-texto">Nexus HUB</span>
                    </button>
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
                <a href="${resolveRootLink("index.html")}" class="link-rodape"><i class="bi bi-box-arrow-right"></i> <span>Sair</span></a>
            </div>
        </aside>
        <div class="barra-lateral-overlay" id="barraLateralOverlay" onclick="closeSidebar()"></div>
    `
}

function getHeaderHTML() {
  const usuarioNome = getCurrentUserName()
  const cargo = getRoleLabel(getCurrentUserRole())

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
                    <p class="usuario-nome">${usuarioNome}</p>
                    <p class="usuario-cargo">${cargo}</p>
                </div>
                <img src="${resolveAssetPath("assets/images/manoel.jpeg")}" alt="Perfil" class="foto-perfil">
                
                <div class="menu-opcoes-container">
                    <button class="botao-menu-trigger" onclick="alternarMenu()">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <div class="menu-suspenso" id="menuSuspenso">
                        <button id="themeToggleButton" type="button" onclick="toggleTheme()"><i class="bi bi-circle-half"></i> Modo Claro</button>
                        <a href="#"><i class="bi bi-gear"></i> Configurações</a>
                        <a href="#"><i class="bi bi-headset"></i> Suporte</a>
                        <hr>
                        <a href="${resolveRootLink("index.html")}" class="logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>
    `
}

// ==========================================
// FUNÇÃO PARA REGISTRAR ATIVIDADES
// ==========================================
function registrarAtividade(tipo, descricao, detalhes = "") {
  /**
   * Registra uma atividade no localStorage em nexus_atividades
   * @param {string} tipo - Tipo da atividade (ex: 'empresa_criada', 'os_aprovada', 'veiculo_cadastrado')
   * @param {string} descricao - Descrição breve da atividade
   * @param {string} detalhes - Detalhes adicionais (opcional)
   */
  const atividades = JSON.parse(localStorage.getItem("nexus_atividades")) || []

  const novaAtividade = {
    id: Date.now(),
    tipo: tipo,
    descricao: descricao,
    detalhes: detalhes,
    usuario: getCurrentUserName(),
    data: new Date().toLocaleString("pt-BR"),
  }

  atividades.push(novaAtividade)
  localStorage.setItem("nexus_atividades", JSON.stringify(atividades))
}

window.addEventListener("DOMContentLoaded", initTheme)
window.addEventListener("click", closeSuspensoMenus)
