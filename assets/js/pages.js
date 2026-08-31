;(function () {
  function resolveProjectUrl(relativePath) {
    const pathname = window.location.pathname || ""
    let basePath = "./"

    if (pathname.includes("/pages/operacoes/")) basePath = "../../"
    else if (pathname.includes("/pages/cadastros/")) basePath = "../../"
    else if (pathname.includes("/pages/auth/")) basePath = "../../"
    else if (pathname.includes("/pages/info/")) basePath = "../../"
    else if (pathname.includes("/pages/")) basePath = "../"

    return `${basePath}${relativePath.replace(/^\.?\//, "")}`
  }

  function initPage() {
    enforceRoleAccess()
    initLogin()
    initCadastroTipo()
    initCadastroServicos()
    initCadastroVeiculo()
    initCadastroPeca()
    initClientes()
    initHomePage()
    initDemoPage()
    initHistoricoPage()
    initCadastroEmpresa()
    insertCommonLayoutForPage()
  }

  function enforceRoleAccess() {
    const role = (
      localStorage.getItem("nexus-role") ||
      sessionStorage.getItem("nexus-role") ||
      "administrador"
    ).toLowerCase()
    const currentPage = getActivePageFromURL()
    const allowedPages = {
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

    const normalizedRole =
      role === "admin" || role === "adm"
        ? "administrador"
        : role === "tecnico" || role === "mecanico"
          ? "mecanico"
          : role === "usuario" || role === "user" || role === "cliente"
            ? "usuario"
            : role
    const isAllowed =
      !currentPage || allowedPages[normalizedRole]?.includes(currentPage)

    if (currentPage && !isAllowed) {
      const safePage = normalizedRole === "mecanico" ? "home.html" : "home.html"
      window.location.href = safePage
      return
    }

    const pageButtons = document.querySelectorAll("[data-role-restrict]")
    pageButtons.forEach((button) => {
      const allowedRoles = button.getAttribute("data-role-restrict").split(",")
      if (!allowedRoles.includes(normalizedRole)) {
        button.style.display = "none"
      }
    })
  }

  function getActivePageFromURL() {
    const currentPage = window.location.pathname.split("/").pop().toLowerCase()
    const pageMap = {
      "home.html": "home",
      "home.php": "home",
      "menu.html": "menu",
      "menu.php": "menu",
      "clientes.html": "clientes",
      "clientes.php": "clientes",
      "novo_atendimento.html": "novo_atendimento",
      "novo_atendimento.php": "novo_atendimento",
      "revisao.html": "revisao",
      "revisao.php": "revisao",
      "cadastro_servicos.html": "cadastro_servicos",
      "cadastro_servicos.php": "cadastro_servicos",
      "cadastro_pecas.html": "cadastro_pecas",
      "cadastro_pecas.php": "cadastro_pecas",
      "cadastro_veiculo.html": "cadastro_veiculo",
      "cadastro_veiculo.php": "cadastro_veiculo",
      "historico.html": "historico",
      "historico.php": "historico",
      "perfil_empresa.html": "clientes",
      "perfil_empresa.php": "clientes",
      "demo.html": "demo",
      "demo.php": "demo",
      "cadastrar_empresa.html": "clientes",
      "cadastrar_empresa.php": "clientes",
    }
    return pageMap[currentPage] || ""
  }

  function insertCommonLayoutForPage() {
    const layout = document.querySelector(".layout-erp")
    const main = document.querySelector(".conteudo-principal")
    if (!layout || !main || document.getElementById("barraLateral")) return
    const activePage = getActivePageFromURL()
    if (!activePage) return
    insertCommonLayout(activePage)
    applyPageSpecificStyles(activePage)
  }

  function applyPageSpecificStyles(activePage) {
    const main = document.querySelector(".conteudo-principal")
    if (!main) return

    main.setAttribute("data-pagina", activePage)

    const titleSection = main.querySelector(".titulo-pagina-stack")
    if (!titleSection) return

    const colorSchemes = {
      cadastro_servicos: {
        borderColor: "#3b82f6",
        accentColor: "#3b82f6",
        bgGradient: "rgba(59, 130, 246, 0.08)",
        icon: "bi-tools",
      },
      cadastro_pecas: {
        borderColor: "#f59e0b",
        accentColor: "#f59e0b",
        bgGradient: "rgba(245, 158, 11, 0.08)",
        icon: "bi-box-seam",
      },
      cadastro_veiculo: {
        borderColor: "#8b5cf6",
        accentColor: "#8b5cf6",
        bgGradient: "rgba(139, 92, 246, 0.08)",
        icon: "bi-truck",
      },
    }

    const scheme = colorSchemes[activePage]
    if (scheme) {
      titleSection.style.borderLeftColor = scheme.borderColor
      titleSection.style.background =
        "linear-gradient(135deg, " +
        scheme.bgGradient +
        " 0%, transparent 100%)"
      const titleIcon = titleSection.querySelector("i")
      if (titleIcon) {
        titleIcon.style.color = scheme.borderColor
      }
    }
  }

  function initLogin() {
    const formLogin = document.getElementById("formLogin")
    if (!formLogin) return

    formLogin.addEventListener("submit", function (evento) {
      evento.preventDefault()
      const email = document.getElementById("email").value.trim()
      const senha = document.getElementById("senha").value.trim()
      const perfilSelecionado =
        document.getElementById("perfilAcesso")?.value || "administrador"

      if (!email || !senha) {
        alert("Por favor, preencha todos os campos para acessar o sistema.")
        return
      }

      const role = perfilSelecionado || "administrador"
      const userName = email.includes("@")
        ? email.split("@")[0].replace(/[._-]/g, " ")
        : "Usuário"
      const formattedName = userName
        .split(" ")
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ")

      setCurrentUserRole(role, formattedName)
      window.location.href = "pages/home.html"
    })
  }

  function initCadastroTipo() {
    const cardsType = document.querySelectorAll(".card-tipo")
    const botaoContinuar = document.getElementById("botaoContinuar")
    if (!cardsType.length || !botaoContinuar) return

    let tipoSelecionado = null

    cardsType.forEach((card) => {
      card.addEventListener("click", function () {
        cardsType.forEach((c) => c.classList.remove("ativo"))
        this.classList.add("ativo")
        tipoSelecionado = this.getAttribute("data-tipo")
        botaoContinuar.disabled = false
      })
    })

    botaoContinuar.addEventListener("click", function () {
      if (!tipoSelecionado) return
      sessionStorage.setItem("tipoUsuario", tipoSelecionado)
      window.location.href = "cadastro_detalhes.html"
    })
  }

  function initCadastroServicos() {
    const formServico = document.getElementById("formCadastroServico")
    if (!formServico) return

    let telaAtual = "cadastro"
    let servicoEmEdicao = null

    function mostrarNotificacao(mensagem) {
      const notif = document.createElement("div")
      notif.className = "notificacao-sucesso"
      notif.innerHTML = `<i class="bi bi-check-circle"></i> ${mensagem}`
      document.body.appendChild(notif)
      setTimeout(() => notif.remove(), 3000)
    }

    function mostrarTela(tela) {
      telaAtual = tela
      const cadastro = document.getElementById("view-cadastro")
      const lista = document.getElementById("view-lista")
      if (!cadastro || !lista) return
      cadastro.classList.toggle("tela-oculta", tela !== "cadastro")
      lista.classList.toggle("tela-oculta", tela !== "lista")
      document
        .getElementById("btn-cadastro")
        .classList.toggle("botao-aba-ativo", tela === "cadastro")
      document
        .getElementById("btn-lista")
        .classList.toggle("botao-aba-ativo", tela === "lista")
      renderizarServicos()
    }

    function renderizarServicos() {
      const servicos =
        JSON.parse(localStorage.getItem("nexus_servicos_custom")) || []
      const container = document.getElementById("lista-servicos-cadastrados")
      if (!container) return

      if (telaAtual !== "lista") {
        container.innerHTML = ""
        return
      }

      const query =
        document
          .getElementById("filtro-servicos")
          ?.value.trim()
          .toLowerCase() || ""
      const filtrados = servicos
        .map((s, index) => ({ ...s, originalIndex: index }))
        .filter((s) => {
          const nome = String(s.nome || "").toLowerCase()
          const desc = String(s.desc || "").toLowerCase()
          return nome.includes(query) || desc.includes(query)
        })
        .sort((a, b) =>
          String(a.nome || "").localeCompare(String(b.nome || ""), "pt-BR", {
            sensitivity: "base",
          }),
        )

      if (!filtrados.length) {
        container.innerHTML = `<p style="color: var(--texto-secundario); margin: 0;">Nenhum serviço encontrado.</p>`
        return
      }

      container.innerHTML = `
                <table class="tabela-custom" style="width: 100%; color: #000000;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid var(--bordar);">
                            <th>Nome</th>
                            <th>Valor Padrão</th>
                            <th>Descrição</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${filtrados
                          .map(
                            (s) => `
                            <tr>
                                <td><strong>${s.nome}</strong></td>
                                <td>R$ ${parseFloat(s.valor || 0).toFixed(2)}</td>
                                <td style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${s.desc || ""}">${s.desc || "-"}</td>
                                <td style="display: flex; gap: 8px;">
                                    <button type="button" class="botao-acao" onclick="window._nexusPages.editarServico(${s.originalIndex})" style="padding: 5px 10px; background: #3b82f6; color: white;"><i class="bi bi-pencil"></i></button>
                                    <button type="button" class="botao-cancelar" onclick="window._nexusPages.removerServico(${s.originalIndex})" style="padding: 5px 10px;"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        `,
                          )
                          .join("")}
                    </tbody>
                </table>
            `
    }

    function editarServico(index) {
      const servicos =
        JSON.parse(localStorage.getItem("nexus_servicos_custom")) || []
      const s = servicos[index]
      if (!s) return
      document.getElementById("nome-servico").value = s.nome || ""
      document.getElementById("valor-servico").value = s.valor || 0
      document.getElementById("desc-servico").value = s.desc || ""
      servicoEmEdicao = index
      document.getElementById("titulo-form-servico").innerHTML =
        '<i class="bi bi-pencil"></i> Editar Serviço'
      mostrarTela("cadastro")
      window.scrollTo({ top: 0, behavior: "smooth" })
    }

    function removerServico(index) {
      if (!confirm("Deseja excluir este serviço?")) return
      const servicos =
        JSON.parse(localStorage.getItem("nexus_servicos_custom")) || []
      const nome = servicos[index]?.nome || "serviço"
      servicos.splice(index, 1)
      localStorage.setItem("nexus_servicos_custom", JSON.stringify(servicos))
      mostrarNotificacao(`Serviço "${nome}" removido com sucesso!`)
      renderizarServicos()
    }

    function resetarFormulario() {
      formServico.reset()
      servicoEmEdicao = null
      document.getElementById("titulo-form-servico").innerHTML =
        '<i class="bi bi-plus-lg"></i> Novo Serviço'
    }

    window._nexusPages = window._nexusPages || {}
    window._nexusPages.editarServico = editarServico
    window._nexusPages.removerServico = removerServico

    formServico.addEventListener("submit", (e) => {
      e.preventDefault()
      const valor = parseFloat(document.getElementById("valor-servico").value)
      if (valor < 0) {
        alert("Valor inválido.")
        return
      }
      const dados = {
        nome: document.getElementById("nome-servico").value,
        valor: valor,
        desc: document.getElementById("desc-servico").value,
      }
      const servicos =
        JSON.parse(localStorage.getItem("nexus_servicos_custom")) || []
      if (servicoEmEdicao !== null) {
        servicos[servicoEmEdicao] = { ...servicos[servicoEmEdicao], ...dados }
        mostrarNotificacao(`Serviço "${dados.nome}" atualizado com sucesso!`)
        servicoEmEdicao = null
      } else {
        servicos.push({
          id: Date.now(),
          ...dados,
          dataCadastro: new Date().toLocaleDateString("pt-BR"),
        })
        registrarAtividade(
          "servico_cadastrado",
          `Novo serviço cadastrado: ${dados.nome}`,
          `Valor: R$ ${valor.toFixed(2)}`,
        )
        mostrarNotificacao(`Serviço "${dados.nome}" cadastrado com sucesso!`)
      }
      localStorage.setItem("nexus_servicos_custom", JSON.stringify(servicos))
      resetarFormulario()
    })

    document
      .getElementById("btn-cadastro")
      ?.addEventListener("click", () => mostrarTela("cadastro"))
    document
      .getElementById("btn-lista")
      ?.addEventListener("click", () => mostrarTela("lista"))
    document
      .getElementById("filtro-servicos")
      ?.addEventListener("input", renderizarServicos)
  }

  function initCadastroVeiculo() {
    const formVeiculo = document.getElementById("formCadastroVeiculo")
    if (!formVeiculo) return

    let telaAtual = "cadastro"
    let veiculoEmEdicao = null
    const apiUrl = resolveProjectUrl("php/veiculos_crud.php")

    function mostrarNotificacao(mensagem) {
      const notif = document.createElement("div")
      notif.className = "notificacao-sucesso"
      notif.innerHTML = `<i class="bi bi-check-circle"></i> ${mensagem}`
      document.body.appendChild(notif)
      setTimeout(() => notif.remove(), 3000)
    }

    function mostrarTela(tela) {
      telaAtual = tela
      const viewCadastro = document.getElementById("view-cadastro")
      const viewFrota = document.getElementById("view-frota")
      if (viewCadastro)
        viewCadastro.classList.toggle("tela-oculta", tela !== "cadastro")
      if (viewFrota) viewFrota.classList.toggle("tela-oculta", tela !== "frota")
      const btnCadastro = document.getElementById("btn-cadastro")
      const btnFrota = document.getElementById("btn-frota")
      if (btnCadastro)
        btnCadastro.classList.toggle("botao-aba-ativo", tela === "cadastro")
      if (btnFrota)
        btnFrota.classList.toggle("botao-aba-ativo", tela === "frota")
      renderizarVeiculos()
    }

    async function carregarEmpresas() {
      const select = document.getElementById("selecao-empresa-veiculo")
      if (!select) return

      try {
        const response = await fetch(
          resolveProjectUrl("php/empresas_crud.php?action=list"),
        )
        const resultado = await response.json()
        const empresas = resultado?.data || []
        select.innerHTML = '<option value="">Escolha uma empresa...</option>'
        empresas.forEach((emp) => {
          const opt = document.createElement("option")
          opt.value = emp.nome
          opt.textContent = emp.nome
          select.appendChild(opt)
        })
      } catch (error) {
        select.innerHTML =
          '<option value="">Não foi possível carregar empresas</option>'
      }
    }

    async function renderizarVeiculos() {
      const container = document.getElementById("lista-veiculos-cadastrados")
      if (!container) return
      if (telaAtual !== "frota") {
        container.innerHTML = ""
        return
      }

      try {
        const response = await fetch(apiUrl)
        const resultado = await response.json()
        const veiculos = resultado?.data || []
        const query =
          document
            .getElementById("filtro-veiculos")
            ?.value.trim()
            .toLowerCase() || ""
        const filtrados = veiculos
          .filter((v) => {
            const placa = String(v.placa || "").toLowerCase()
            const modelo = String(v.modelo || "").toLowerCase()
            const empresa = String(v.nome_caminhao || "").toLowerCase()
            return (
              placa.includes(query) ||
              modelo.includes(query) ||
              empresa.includes(query)
            )
          })
          .sort((a, b) =>
            String(a.placa || "").localeCompare(
              String(b.placa || ""),
              "pt-BR",
              { sensitivity: "base" },
            ),
          )

        if (!filtrados.length) {
          container.innerHTML =
            '<p style="color: var(--texto-secundario); margin: 0;">Nenhum veículo encontrado.</p>'
          return
        }

        container.innerHTML = `
                    <table class="tabela-custom" style="width: 100%; color: #000000;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid var(--bordar);">
                                <th>Placa</th>
                                <th>Empresa</th>
                                <th>Modelo</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${filtrados
                              .map(
                                (v) => `
                                <tr>
                                    <td><strong>${v.placa}</strong></td>
                                    <td><span class="badge-empresa">${v.nome_caminhao || "-"}</span></td>
                                    <td>${v.modelo || "-"}</td>
                                    <td style="display: flex; gap: 8px;">
                                        <button type="button" class="botao-acao" onclick="window._nexusPages.editarVeiculo(${v.id})" style="padding: 5px 10px; background: #3b82f6; color: white;"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="botao-cancelar" onclick="window._nexusPages.removerVeiculo(${v.id})" style="padding: 5px 10px;"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            `,
                              )
                              .join("")}
                        </tbody>
                    </table>
                `
      } catch (error) {
        container.innerHTML =
          '<p style="color: var(--texto-secundario); margin: 0;">Não foi possível carregar veículos.</p>'
      }
    }

    async function editarVeiculo(id) {
      try {
        const response = await fetch(`${apiUrl}?id=${id}`)
        const resultado = await response.json()
        const veiculo = (resultado?.data || []).find(
          (v) => Number(v.id) === Number(id),
        )
        if (!veiculo) return

        await carregarEmpresas()
        const select = document.getElementById("selecao-empresa-veiculo")
        if (select) select.value = veiculo.nome_caminhao || ""
        document.getElementById("placa-veiculo").value = veiculo.placa || ""
        document.getElementById("modelo-veiculo").value = veiculo.modelo || ""
        document.getElementById("titulo-form-veiculo").innerHTML =
          '<i class="bi bi-pencil"></i> Editar Veículo'
        veiculoEmEdicao = id
        mostrarTela("cadastro")
        window.scrollTo({ top: 0, behavior: "smooth" })
      } catch (error) {
        alert("Não foi possível carregar o veículo para edição.")
      }
    }

    async function removerVeiculo(id) {
      if (!confirm("Deseja excluir este veículo?")) return
      try {
        const formData = new URLSearchParams({
          action: "delete",
          id: String(id),
        })
        const response = await fetch(apiUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: formData.toString(),
        })
        const resultado = await response.json()
        if (!resultado?.success)
          throw new Error(resultado?.message || "Erro ao excluir.")
        mostrarNotificacao("Veículo removido com sucesso!")
        await renderizarVeiculos()
      } catch (error) {
        alert(error.message || "Erro ao excluir veículo.")
      }
    }

    function resetarFormulario() {
      formVeiculo.reset()
      veiculoEmEdicao = null
      document.getElementById("titulo-form-veiculo").innerHTML =
        '<i class="bi bi-plus-lg"></i> Novo Veículo'
      carregarEmpresas()
    }

    window._nexusPages = window._nexusPages || {}
    window._nexusPages.editarVeiculo = editarVeiculo
    window._nexusPages.removerVeiculo = removerVeiculo

    formVeiculo.addEventListener("submit", async (e) => {
      e.preventDefault()
      const dados = {
        empresa: document.getElementById("selecao-empresa-veiculo").value,
        placa: document.getElementById("placa-veiculo").value.toUpperCase(),
        modelo: document.getElementById("modelo-veiculo").value,
        nome_caminhao: document.getElementById("selecao-empresa-veiculo").value,
        marca: document.getElementById("marca-veiculo").value,
        ano: document.getElementById("ano-veiculo").value,
        cor: document.getElementById("cor-veiculo").value,
        combustivel: document.getElementById("combustivel-veiculo").value,
        km: document.getElementById("km-veiculo").value,
        chassi: document.getElementById("chassi-veiculo").value,
        obs: document.getElementById("obs-veiculo").value,
      }

      if (!dados.nome_caminhao || !dados.modelo || !dados.placa) {
        alert("Informe empresa, modelo e placa.")
        return
      }

      const payload = {
        action: veiculoEmEdicao ? "update" : "create",
        id: veiculoEmEdicao || "",
        nome_caminhao: dados.nome_caminhao,
        modelo: dados.modelo,
        placa: dados.placa,
        marca: dados.marca,
        ano: dados.ano,
        cor: dados.cor,
        combustivel: dados.combustivel,
        km: dados.km,
        chassi: dados.chassi,
        obs: dados.obs,
      }

      try {
        const response = await fetch(apiUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: new URLSearchParams(payload).toString(),
        })
        const resultado = await response.json()
        if (!resultado?.success)
          throw new Error(resultado?.message || "Erro ao salvar veículo.")
        mostrarNotificacao(
          `Veículo "${dados.placa}" ${veiculoEmEdicao ? "atualizado" : "cadastrado"} com sucesso!`,
        )
        resetarFormulario()
        await renderizarVeiculos()
      } catch (error) {
        alert(error.message || "Erro ao salvar veículo.")
      }
    })

    document
      .getElementById("btn-cadastro")
      ?.addEventListener("click", () => mostrarTela("cadastro"))
    document
      .getElementById("btn-frota")
      ?.addEventListener("click", () => mostrarTela("frota"))
    document
      .getElementById("filtro-veiculos")
      ?.addEventListener("input", renderizarVeiculos)
    carregarEmpresas()
    renderizarVeiculos()
  }

  function initCadastroPeca() {
    const formPeca = document.getElementById("formCadastroPeca")
    if (!formPeca) return

    let telaAtual = "cadastro"
    let pecaEmEdicao = null
    const apiUrl = resolveProjectUrl("php/pecas_crud.php")

    function mostrarNotificacao(mensagem) {
      const notif = document.createElement("div")
      notif.className = "notificacao-sucesso"
      notif.innerHTML = `<i class="bi bi-check-circle"></i> ${mensagem}`
      document.body.appendChild(notif)
      setTimeout(() => notif.remove(), 3000)
    }

    function mostrarTela(tela) {
      telaAtual = tela
      const viewCadastro = document.getElementById("view-cadastro")
      const viewEstoque = document.getElementById("view-estoque")
      if (viewCadastro)
        viewCadastro.classList.toggle("tela-oculta", tela !== "cadastro")
      if (viewEstoque)
        viewEstoque.classList.toggle("tela-oculta", tela !== "estoque")
      const btnCadastro = document.getElementById("btn-cadastro")
      const btnEstoque = document.getElementById("btn-estoque")
      if (btnCadastro)
        btnCadastro.classList.toggle("botao-aba-ativo", tela === "cadastro")
      if (btnEstoque)
        btnEstoque.classList.toggle("botao-aba-ativo", tela === "estoque")
      renderizarPecas()
    }

    async function renderizarPecas() {
      const container = document.getElementById("lista-pecas-cadastradas")
      if (!container) return
      if (telaAtual !== "estoque") {
        container.innerHTML = ""
        return
      }

      try {
        const response = await fetch(apiUrl)
        const resultado = await response.json()
        const pecas = resultado?.data || []
        const query =
          document
            .getElementById("filtro-estoque")
            ?.value.trim()
            .toLowerCase() || ""
        const pecasFiltradas = pecas
          .filter((p) => {
            const nome = String(p.nome || "").toLowerCase()
            const codigo = String(p.codigo || "").toLowerCase()
            return nome.includes(query) || codigo.includes(query)
          })
          .sort((a, b) => {
            const estoqueA = Number(a.estoque) > 0 ? 1 : 0
            const estoqueB = Number(b.estoque) > 0 ? 1 : 0
            if (estoqueA !== estoqueB) return estoqueB - estoqueA
            if (Number(b.estoque) !== Number(a.estoque))
              return Number(b.estoque) - Number(a.estoque)
            return String(a.nome || "").localeCompare(
              String(b.nome || ""),
              "pt-BR",
              { sensitivity: "base" },
            )
          })

        if (!pecasFiltradas.length) {
          container.innerHTML =
            '<p style="color: var(--texto-secundario); margin: 0;">Nenhuma peça encontrada.</p>'
          return
        }

        container.innerHTML = `
                    <table class="tabela-custom" style="width: 100%; color: #000000;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid var(--bordar);">
                                <th>Peça</th>
                                <th>Categoria</th>
                                <th>Marca</th>
                                <th>Fornecedor</th>
                                <th>Código</th>
                                <th>Estoque</th>
                                <th>Unidade</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${pecasFiltradas
                              .map(
                                (p) => `
                                <tr>
                                    <td><strong>${p.nome}</strong></td>
                                    <td><span class="badge-categoria">${p.categoria || "-"}</span></td>
                                    <td>${p.marca || "-"}</td>
                                    <td>${p.fornecedor || "-"}</td>
                                    <td>${p.codigo || "-"}</td>
                                    <td>${p.estoque || 0}</td>
                                    <td>${p.unidade || "Unidade"}</td>
                                    <td>R$ ${parseFloat(p.valor || 0).toFixed(2)}</td>
                                    <td>${p.status || "Ativa"}</td>
                                    <td style="display: flex; gap: 8px;">
                                        <button type="button" class="botao-acao" onclick="window._nexusPages.editarPeca(${p.id})" style="padding: 5px 10px; background: #3b82f6; color: white;"><i class="bi bi-pencil"></i></button>
                                        <button type="button" class="botao-cancelar" onclick="window._nexusPages.removerPeca(${p.id})" style="padding: 5px 10px;"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            `,
                              )
                              .join("")}
                        </tbody>
                    </table>
                `
      } catch (error) {
        container.innerHTML =
          '<p style="color: var(--texto-secundario); margin: 0;">Não foi possível carregar peças.</p>'
      }
    }

    async function editarPeca(id) {
      try {
        const response = await fetch(`${apiUrl}?id=${id}`)
        const resultado = await response.json()
        const peca = (resultado?.data || []).find(
          (p) => Number(p.id) === Number(id),
        )
        if (!peca) return

        document.getElementById("nome-peca").value = peca.nome || ""
        document.getElementById("categoria-peca").value = peca.categoria || ""
        document.getElementById("marca-peca").value = peca.marca || ""
        document.getElementById("fornecedor-peca").value = peca.fornecedor || ""
        document.getElementById("codigo-peca").value = peca.codigo || ""
        document.getElementById("estoque-peca").value = peca.estoque || 0
        document.getElementById("unidade-peca").value =
          peca.unidade || "Unidade"
        document.getElementById("valor-peca").value = peca.valor || 0
        document.getElementById("status-peca").value = peca.status || "Ativa"
        document.getElementById("desc-peca").value =
          peca.descricao || peca.desc || ""
        document.getElementById("imagem-peca").value = peca.imagem || ""
        if (peca.imagem) {
          const img = document.getElementById("img-preview")
          const preview = document.getElementById("preview-imagem-peca")
          img.src = peca.imagem
          preview.style.display = "block"
        }
        pecaEmEdicao = id
        document.getElementById("titulo-form-peca").innerHTML =
          '<i class="bi bi-pencil"></i> Editar Peça'
        mostrarTela("cadastro")
      } catch (error) {
        alert("Não foi possível carregar a peça para edição.")
      }
    }

    async function removerPeca(id) {
      if (!confirm("Deseja excluir esta peça?")) return
      try {
        const formData = new URLSearchParams({
          action: "delete",
          id: String(id),
        })
        const response = await fetch(apiUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: formData.toString(),
        })
        const resultado = await response.json()
        if (!resultado?.success)
          throw new Error(resultado?.message || "Erro ao excluir.")
        mostrarNotificacao("Peça removida com sucesso!")
        await renderizarPecas()
      } catch (error) {
        alert(error.message || "Erro ao excluir peça.")
      }
    }

    function resetarFormulario() {
      formPeca.reset()
      pecaEmEdicao = null
      document.getElementById("titulo-form-peca").innerHTML =
        '<i class="bi bi-plus-lg"></i> Nova Peça'
      const preview = document.getElementById("preview-imagem-peca")
      const img = document.getElementById("img-preview")
      if (preview) preview.style.display = "none"
      if (img) img.src = ""
      const fileInput = document.getElementById("imagem-peca-file")
      if (fileInput) fileInput.value = ""
    }

    function carregarImagemPeca(input) {
      const file = input.files?.[0]
      if (!file) return
      const reader = new FileReader()
      reader.onload = function (e) {
        const preview = document.getElementById("preview-imagem-peca")
        const img = document.getElementById("img-preview")
        img.src = e.target.result
        document.getElementById("imagem-peca").value = e.target.result
        preview.style.display = "block"
      }
      reader.readAsDataURL(file)
    }

    function removerImagemPeca() {
      document.getElementById("imagem-peca").value = ""
      const preview = document.getElementById("preview-imagem-peca")
      const img = document.getElementById("img-preview")
      if (preview) preview.style.display = "none"
      if (img) img.src = ""
      const fileInput = document.getElementById("imagem-peca-file")
      if (fileInput) fileInput.value = ""
    }

    window._nexusPages = window._nexusPages || {}
    window._nexusPages.editarPeca = editarPeca
    window._nexusPages.removerPeca = removerPeca

    formPeca.addEventListener("submit", async (e) => {
      e.preventDefault()
      const valor = parseFloat(document.getElementById("valor-peca").value)
      const estoque = parseInt(
        document.getElementById("estoque-peca").value,
        10,
      )
      if (valor < 0 || estoque < 0) {
        alert("Valores inválidos.")
        return
      }

      const dados = {
        action: pecaEmEdicao ? "update" : "create",
        id: pecaEmEdicao || "",
        nome: document.getElementById("nome-peca").value,
        categoria: document.getElementById("categoria-peca").value,
        marca: document.getElementById("marca-peca").value,
        fornecedor: document.getElementById("fornecedor-peca").value,
        codigo: document.getElementById("codigo-peca").value,
        estoque: estoque,
        unidade: document.getElementById("unidade-peca").value,
        valor: valor,
        status: document.getElementById("status-peca").value,
        imagem: document.getElementById("imagem-peca").value,
        descricao: document.getElementById("desc-peca").value,
        desc: document.getElementById("desc-peca").value,
      }

      try {
        const response = await fetch(apiUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: new URLSearchParams(dados).toString(),
        })
        const resultado = await response.json()
        if (!resultado?.success)
          throw new Error(resultado?.message || "Erro ao salvar peça.")
        mostrarNotificacao(
          `Peça "${dados.nome}" ${pecaEmEdicao ? "atualizada" : "cadastrada"} com sucesso!`,
        )
        resetarFormulario()
        await renderizarPecas()
      } catch (error) {
        alert(error.message || "Erro ao salvar peça.")
      }
    })

    document
      .getElementById("btn-cadastro")
      ?.addEventListener("click", () => mostrarTela("cadastro"))
    document
      .getElementById("btn-estoque")
      ?.addEventListener("click", () => mostrarTela("estoque"))
    document
      .getElementById("filtro-estoque")
      ?.addEventListener("input", renderizarPecas)
    document
      .getElementById("imagem-peca-file")
      ?.addEventListener("change", function () {
        carregarImagemPeca(this)
      })
    renderizarPecas()
  }

  function initClientes() {
    const tabelaCorpo = document.getElementById("tabela-clientes-corpo")
    if (!tabelaCorpo) return

    async function renderizarClientes() {
      try {
        const response = await fetch(
          resolveProjectUrl("php/empresas_crud.php?action=list"),
        )
        const resultado = await response.json()
        const empresas = resultado?.data || []

        if (!empresas.length) {
          tabelaCorpo.innerHTML =
            '<tr><td colspan="6" class="sem-dados">Nenhuma empresa cadastrada.</td></tr>'
          return
        }

        tabelaCorpo.innerHTML = empresas
          .map(
            (emp) => `
                    <tr>
                        <td>
                            <div class="tabela-empresa">
                                <div class="tabela-icone"><i class="bi bi-building"></i></div>
                                <strong>${emp.nome}</strong>
                            </div>
                        </td>
                        <td>${emp.cnpj || "---"}</td>
                        <td>${emp.contato || "---"}</td>
                        <td>${emp.telefone || "---"}</td>
                        <td>${emp.email || "---"}</td>
                        <td>
                            <button type="button" class="botao-pequeno btn-pequeno-acoes" onclick="window._nexusPages.verPerfil(${emp.id})">Visualizar Perfil</button>
                        </td>
                    </tr>
                `,
          )
          .join("")
      } catch (error) {
        tabelaCorpo.innerHTML =
          '<tr><td colspan="6" class="sem-dados">Não foi possível carregar as empresas.</td></tr>'
      }
    }

    function verPerfil(id) {
      localStorage.setItem("empresa_selecionada", id)
      window.location.href = getPagesBasePath() + "perfil_empresa.html"
    }

    window._nexusPages = window._nexusPages || {}
    window._nexusPages.verPerfil = verPerfil

    renderizarClientes()
    insertCommonLayout("clientes")
  }

  function initHomePage() {
    if (!document.querySelector(".grid-onboarding")) return
    insertCommonLayout("home")

    window.abrirMenu = function () {
      window.location.href = getPagesBasePath() + "menu.html"
    }
  }

  function initDemoPage() {
    const btnCarregar = document.getElementById("btn-carregar-demo")
    const btnLimpar = document.getElementById("btn-limpar")
    const resultado = document.getElementById("resultado-demo")
    if (!btnCarregar || !btnLimpar || !resultado) return

    const dadosDemonstracao = [
      {
        id: 1704067200000,
        cliente: {
          nome: "Carlos Oliveira",
          telefone: "(11) 98765-4321",
          email: "carlos.oliveira@email.com",
          endereco: "Av. Paulista, 1000",
        },
        veiculo: {
          placa: "ABC-1234",
          modelo: "Fiat Uno 1.4",
          ano: 2022,
          cor: "Branco",
        },
        servico: {
          tipo: "Manutenção Preventiva",
          descricao:
            "Troca de óleo, filtro de ar e filtro de óleo. Verificação de freios e alinhamento.",
          valor: 320.0,
          data: "2024-01-02",
          observacoes: "Cliente solicitou limpeza interna",
        },
        status: "aprovado",
        dataCriacao: "02/01/2024 09:15:30",
        observacoes_admin: "Atendimento excelente, cliente satisfeito",
      },
      {
        id: 1704153600000,
        cliente: {
          nome: "Marina Silva",
          telefone: "(11) 99876-5432",
          email: "marina.silva@email.com",
          endereco: "Rua Oscar Freire, 500",
        },
        veiculo: {
          placa: "XYZ-9876",
          modelo: "Hyundai HB20 1.0",
          ano: 2023,
          cor: "Prata",
        },
        servico: {
          tipo: "Reparo Geral",
          descricao:
            "Conserto de amortecedor traseiro danificado. Ajuste de suspensão dianteira.",
          valor: 680.0,
          data: "2024-01-03",
          observacoes: "Peça foi pedida conforme solicitação do cliente",
        },
        status: "aprovado",
        dataCriacao: "03/01/2024 14:45:22",
        observacoes_admin: "",
      },
      {
        id: 1704240000000,
        cliente: {
          nome: "João Santos",
          telefone: "(21) 98765-4321",
          email: "joao.santos@email.com",
          endereco: "Copacabana, RJ",
        },
        veiculo: {
          placa: "DEF-5678",
          modelo: "Toyota Corolla 2.0",
          ano: 2020,
          cor: "Preto",
        },
        servico: {
          tipo: "Ar-Condicionado",
          descricao:
            "Limpeza completa do sistema de ar-condicionado. Recarga de gás refrigerante.",
          valor: 450.0,
          data: "2024-01-04",
          observacoes: "",
        },
        status: "aprovado",
        dataCriacao: "04/01/2024 10:30:15",
        observacoes_admin: "",
      },
    ]

    function mostrarResultado(mensagem, tipo = "success") {
      resultado.style.display = "block"
      resultado.style.background = tipo === "success" ? "#ecfdf5" : "#fef3c7"
      resultado.style.color = tipo === "success" ? "#166534" : "#92400e"
      resultado.textContent = mensagem
    }

    btnCarregar.addEventListener("click", () => {
      localStorage.setItem(
        "atendimentos_demo",
        JSON.stringify(dadosDemonstracao),
      )
      mostrarResultado("Dados de demonstração carregados com sucesso!")
    })

    btnLimpar.addEventListener("click", () => {
      localStorage.removeItem("atendimentos_demo")
      mostrarResultado("Dados de demonstração limpos.")
    })

    insertCommonLayout("demo")
  }

  function initHistoricoPage() {
    const tabelaCorpo = document.getElementById("tabela-historico-corpo")
    if (!tabelaCorpo) return
    insertCommonLayout("historico")

    function renderizarTabela() {
      const atendimentos =
        JSON.parse(localStorage.getItem("atendimentos")) || []
      const finalizadas = atendimentos.filter((a) => a.status === "finalizada")
      tabelaCorpo.innerHTML = finalizadas
        .reverse()
        .map((os) => {
          const data = os.dataRegistro || os.data || "---"
          return `
                    <tr>
                        <td>${data}</td>
                        <td><strong>${os.cliente || "---"}</strong></td>
                        <td>${os.frota ? os.frota.length : 1} veículo(s)</td>
                        <td><button type="button" class="botao-pequeno btn-pequeno-acoes" onclick="window._nexusPages.abrirEditor(${os.id})">Visualizar Relatório</button></td>
                    </tr>
                `
        })
        .join("")
    }

    function abrirEditor(id) {
      const atendimentos =
        JSON.parse(localStorage.getItem("atendimentos")) || []
      const empresas = JSON.parse(localStorage.getItem("nexus_empresas")) || []
      const osSelecionada = atendimentos.find((a) => a.id == id)
      if (!osSelecionada) return
      const container = document.getElementById("paginas-container")
      if (!container) return
      container.innerHTML = ""
      const capa = document.createElement("div")
      capa.className = "folha-pdf"
      capa.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid #4f46e5; padding-bottom: 20px; margin-bottom: 30px;">
                    <div>
                        <h2 style="margin: 0; color: #4f46e5; font-size: 28px;">Nexus HUB</h2>
                        <p style="margin: 4px 0 0 0; color: #4f46e5;">Relatório de Ordem de Serviço</p>
                    </div>
                    <div style="text-align: right; font-size: 0.85rem; color: #555;">${new Date().toLocaleDateString("pt-BR")}</div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div>
                        <h3 style="margin: 0 0 8px 0;">Cliente</h3>
                        <p style="margin: 0;">${osSelecionada.cliente || "---"}</p>
                    </div>
                    <div>
                        <h3 style="margin: 0 0 8px 0;">Status</h3>
                        <p style="margin: 0;">${osSelecionada.status || "---"}</p>
                    </div>
                </div>
                <div style="margin-bottom: 30px;">
                    <h3 style="margin-bottom: 12px;">Serviço</h3>
                    <p style="margin: 0;">${osSelecionada.servico?.descricao || "---"}</p>
                </div>
                <div class="rodape-pdf">Ordem de Serviço gerada pelo Nexus HUB</div>
            `
      container.appendChild(capa)
      document.getElementById("modalEditor").style.display = "block"
    }

    function fecharEditor() {
      document.getElementById("modalEditor").style.display = "none"
    }

    function salvarEGerarPDF() {
      const modal = document.getElementById("modalEditor")
      if (!modal) return
      const pagina = modal.querySelector(".folha-pdf")
      if (!pagina) return
      html2canvas(pagina).then((canvas) => {
        const imgData = canvas.toDataURL("image/png")
        const pdf = new jsPDF("portrait", "pt", "a4")
        const largura = pdf.internal.pageSize.getWidth()
        const altura = pdf.internal.pageSize.getHeight()
        pdf.addImage(imgData, "PNG", 0, 0, largura, altura)
        pdf.save("relatorio-os.pdf")
      })
    }

    window._nexusPages = window._nexusPages || {}
    window._nexusPages.abrirEditor = abrirEditor
    window._nexusPages.fecharEditor = fecharEditor
    window._nexusPages.salvarEGerarPDF = salvarEGerarPDF

    renderizarTabela()
  }

  function initCadastroEmpresa() {
    const formNovaEmpresa = document.getElementById("formNovaEmpresa")
    if (!formNovaEmpresa) return
    insertCommonLayout("clientes")

    function aplicarMascaraCNPJ(input) {
      let v = input.value.replace(/\D/g, "").slice(0, 14)
      if (v.length > 12)
        v = v.replace(
          /^(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})/,
          "$1.$2.$3/$4-$5",
        )
      else if (v.length > 8)
        v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{0,4})/, "$1.$2.$3/$4")
      else if (v.length > 5)
        v = v.replace(/^(\d{2})(\d{3})(\d{0,3})/, "$1.$2.$3")
      else if (v.length > 2) v = v.replace(/^(\d{2})(\d{0,3})/, "$1.$2")
      input.value = v
    }

    function aplicarMascaraTelefone(input) {
      let v = input.value.replace(/\D/g, "").slice(0, 11)
      if (v.length > 10) v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, "($1) $2-$3")
      else if (v.length > 6)
        v = v.replace(/^(\d{2})(\d{4,5})(\d{0,4})/, "($1) $2-$3")
      else if (v.length > 2) v = v.replace(/^(\d{2})(\d{0,5})/, "($1) $2")
      else if (v.length > 0) v = v.replace(/^(\d{0,2})/, "($1")
      input.value = v
    }

    formNovaEmpresa.addEventListener("submit", async function (event) {
      event.preventDefault()
      const nome = document.getElementById("nome-empresa").value.trim()
      const cnpj = document.getElementById("cnpj-empresa").value.trim()
      const email = document.getElementById("email-empresa").value.trim()
      const telefone = document.getElementById("tel-empresa").value.trim()
      const contato = document.getElementById("contato-principal").value.trim()

      if (!nome || !cnpj || !email || !telefone || !contato) {
        alert("Preencha todos os campos obrigatórios.")
        return
      }
      if (!validarEmail(email)) {
        alert("Informe um e-mail válido.")
        return
      }
      if (!validarTelefone(telefone)) {
        alert("Informe um telefone válido.")
        return
      }
      if (!validarCNPJ(cnpj)) {
        alert("CNPJ inválido.")
        return
      }

      try {
        const body = new URLSearchParams({
          action: "create",
          nome,
          cnpj,
          site: document.getElementById("site-empresa").value.trim(),
          contato,
          email,
          telefone,
          observacoes: document.getElementById("obs-empresa").value.trim(),
        }).toString()

        const response = await fetch(
          resolveProjectUrl("php/empresas_crud.php"),
          {
            method: "POST",
            headers: {
              "Content-Type":
                "application/x-www-form-urlencoded; charset=UTF-8",
            },
            body,
          },
        )
        const resultado = await response.json()

        if (!resultado?.success) {
          throw new Error(resultado?.message || "Erro ao cadastrar empresa.")
        }

        alert("Empresa cadastrada com sucesso!")
        window.location.href = "../../pages/clientes.html"
      } catch (erro) {
        alert(erro.message || "Erro ao cadastrar empresa.")
      }
    })

    document
      .getElementById("cnpj-empresa")
      ?.addEventListener("input", function () {
        aplicarMascaraCNPJ(this)
      })
    document
      .getElementById("tel-empresa")
      ?.addEventListener("input", function () {
        aplicarMascaraTelefone(this)
      })
  }

  function validarEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
  }

  function validarTelefone(tel) {
    const numeros = tel.replace(/\D/g, "")
    return numeros.length === 10 || numeros.length === 11
  }

  function validarCNPJ(cnpj) {
    const numeros = cnpj.replace(/\D/g, "")
    if (numeros.length !== 14) return false
    if (/^(\d)\1+$/.test(numeros)) return false
    const calcDigito = (nums, pesos) => {
      const soma = nums
        .split("")
        .reduce((acc, n, i) => acc + parseInt(n, 10) * pesos[i], 0)
      const resto = soma % 11
      return resto < 2 ? 0 : 11 - resto
    }
    const d1 = calcDigito(
      numeros.slice(0, 12),
      [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
    )
    const d2 = calcDigito(
      numeros.slice(0, 13),
      [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
    )
    return parseInt(numeros[12], 10) === d1 && parseInt(numeros[13], 10) === d2
  }

  function insertCommonLayout(activePage = "home") {
    const layout = document.querySelector(".layout-erp")
    const main = document.querySelector(".conteudo-principal")
    if (!layout || !main || document.getElementById("barraLateral")) return
    layout.insertAdjacentHTML("afterbegin", getSidebarHTML(activePage))
    main.insertAdjacentHTML("afterbegin", getHeaderHTML())
  }

  window.addEventListener("DOMContentLoaded", initPage)
})()
