# Nexus HUB

Sistema web para gestão de ordens de serviço, clientes, veículos, peças e histórico operacional de uma operação de manutenção e atendimento.

## Visão geral

O Nexus HUB foi pensado para funcionar como uma solução desktop-first de gestão operacional, com foco em visualização rápida, organização de dados e controle de fluxo de serviços. A interface reúne:

- cadastro de clientes
- cadastro de veículos
- cadastro de peças e serviços
- criação e acompanhamento de ordens de serviço
- revisão e aprovação de atendimentos
- histórico de OSs finalizadas
- exportação de relatórios em PDF

## Funcionalidades principais

### 1. Login e acesso
- Tela inicial com autenticação simples em front-end
- Redirecionamento para a home do sistema após validação de campos
- Recuperação de senha e cadastro de novos usuários

### 2. Dashboard e painel executivo
- visão geral de métricas e indicadores
- acompanhamento de atendimentos por status
- gráficos e resumo de operações
- navegação rápida para módulos principais

### 3. Gestão operacional
- cadastro de clientes e empresas
- cadastro de veículos por frota
- cadastro de serviços e peças
- registro de ordens de serviço com histórico de etapas

### 4. Revisão e aprovação
- fluxo de revisão com status controlado
- edição de dados antes da aprovação final
- organização de pendências e aprovação por administrador

### 5. Histórico e relatórios
- listagem de OSs finalizadas
- busca por cliente, veículo ou data
- exportação de relatórios em PDF
- controle documental do atendimento

## Estrutura do projeto

```text
Nexus_HUB-main/
├── index.html
├── README.md
├── teste-inputs.html
├── desktop.ini
├── package-lock.json
├── .env
├── assets/
│   ├── css/
│   │   ├── pages.css
│   │   └── style.css
│   ├── images/
│   └── js/
│       ├── common.js
│       ├── log.js
│       └── pages.js
├── docs/
│   ├── GUIA_TESTE.md
│   └── RESUMO.md
├── pages/
│   ├── auth/
│   │   ├── cadastro_tipo.html
│   │   ├── recuperar.html
│   │   └── signup.html
│   ├── cadastros/
│   │   ├── cadastrar_empresa.html
│   │   ├── cadastro_detalhes.html
│   │   ├── cadastro_pecas.html
│   │   ├── cadastro_servicos.html
│   │   └── cadastro_veiculo.html
│   ├── info/
│   │   ├── demo.html
│   │   ├── sobre.html
│   │   └── suporte.html
│   └── operacoes/
│       ├── clientes.html
│       ├── historico.html
│       ├── home.html
│       ├── menu.html
│       ├── novo_atendimento.html
│       ├── perfil_empresa.html
│       └── revisao.html
├── php/
│   ├── cadastro.php
│   ├── env.php
│   └── enviar_suporte.php
└── .gitignore
```

## Como executar

1. Abra a pasta do projeto no navegador.
2. Acesse o arquivo `index.html`.
3. Faça login com qualquer e-mail e senha válidos para avançar para a home do sistema.

> O projeto é front-end estático e utiliza armazenamento local do navegador para persistência de dados em `localStorage`.

## Tecnologias

- HTML5
- CSS3
- JavaScript
- Bootstrap Icons
- LocalStorage

## Observações

- O sistema foi construído para uso em ambiente desktop e notebook.
- A lógica atual é principalmente front-end, ideal para protótipos, demonstrações e gestão interna.
- Para uso em produção, recomenda-se evoluir para backend com autenticação real e banco de dados persistente.

## Documentação complementar

- [docs/GUIA_TESTE.md](docs/GUIA_TESTE.md)
- [docs/RESUMO.md](docs/RESUMO.md)

---

## 📞 Suporte

Para dúvidas ou sugestões sobre o sistema, consulte a seção de contato ou suporte dentro da aplicação.

---

**Versão:** 1.0  
**Última Atualização:** 06 de abril de 2026  
**Status:** ✅ Pronto para uso
