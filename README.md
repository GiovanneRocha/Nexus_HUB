# Sistema de Gestão de Ordens de Serviço (OS)

## 📋 Visão Geral

Sistema web completo para gerenciamento de atendimentos e ordens de serviço para oficinas e prestadores de serviço. Permite registro, revisão, aprovação e arquivo de todas as ordens de serviço, com geração de PDF e histórico de clientes.

---

## ✨ Funcionalidades Implementadas

### 1. **Autenticação e Login** (Página Principal)
- Acesso seguro ao sistema com validação de e-mail e senha
- Recuperação de senha com redirecionamento
- Link para cadastro de novos usuários

### 2. **Dashboard Principal** (`menu.html`)
- **Métricas em tempo real:**
  - OS Abertas (aguardando revisão)
  - Atendimentos em Análise
  - Ordens Finalizadas no Mês
  - Total de Clientes
- **Gráfico de atendimentos** por mês
- **Menu de navegação** para todas as funcionalidades

### 3. **Registro de Atendimento** (`novo_atendimento.html`) - RF01, RF02
- **Informações do Cliente:** Nome, telefone, e-mail, endereço
- **Informações do Veículo:** Placa, modelo, ano, cor
- **Serviço Realizado:**
  - Tipo de serviço (dropdown com opções pré-definidas)
  - Descrição detalhada
  - Valor e data
  - Observações adicionais
- **Armazenamento** em localStorage com ID único
- **Status automático** como "Pendente Revisão"

### 4. **Painel de Revisão Administrativa** (`revisao.html`) - RF05, RF06, RF07
- **Lista de atendimentos** para revisão
- **Filtro por status:** Pendente, Em Análise, Aprovado, Rejeitado
- **Modal de edição** para correção de dados
- **Alteração de status** com observações administrativas
- **Exclusão de atendimentos** incorretos
- **Validação** antes de aprovação final

### 5. **Arquivo de Histórico** (`historico.html`) - RF10
- **Listagem de todas as OSs finalizadas** e aprovadas
- **Busca** por cliente ou placa de veículo
- **Visualização de detalhes** completos de cada OS
- **Exportação individual** de OSs em PDF
- **Geração de relatório geral** com todas as OSs e valores

### 6. **Base de Clientes** (`clientes.html`)
- **Visualização de todos os clientes** cadastrados no sistema
- **Informações resumidas:** E-mail, telefone, endereço
- **Veículos associados** ao cliente com placa e modelo
- **Total de atendimentos** por cliente
- **Histórico completo** de cada cliente
- **Busca por nome**

### 7. **Exportação em PDF** - RF08, RF09
- **PDF individual:** Cada OS pode ser exportada em PDF formatado
- **Relatório geral:** Exporta todas as OSs aprovadas com totalizações
- **Layout profissional** com dados completos do cliente, veículo e serviço
- **Integração com html2pdf.js**

---

## 📊 Fluxo de Dados

```
1. Novo Atendimento (novo_atendimento.html)
   ↓ (Status: Pendente Revisão)
   ↓
2. Painel de Revisão (revisao.html)
   ↓ (Status: Em Análise → Aprovado)
   ↓
3. Arquivo de Histórico (historico.html)
   ↓
4. Exportação para PDF e Relatórios
```

---

## 🗂️ Estrutura de Arquivos

```
Nexus_HUB-main/
├── index.html              # Login do sistema
├── README.md               # Documentação principal
├── assets/
│   ├── css/
│   │   └── style.css       # Estilos principais
│   └── images/
│       ├── manoel.jpeg     # Foto de perfil
│       └── atomo.png       # Logo do cadastro
├── docs/
│   ├── GUIA_TESTE.md       # Guia de teste
│   └── RESUMO.md           # Resumo executivo
└── pages/
    ├── menu.html
    ├── novo_atendimento.html
    ├── revisao.html
    ├── historico.html
    ├── clientes.html
    ├── recuperar.html
    ├── signup.html
    └── demo.html
```

---

## 💾 Armazenamento de Dados

O sistema utiliza **localStorage** do navegador para armazenar:

### Estrutura de Atendimento:
```json
{
  "id": 1234567890,
  "cliente": {
    "nome": "João Silva",
    "telefone": "(11) 99999-9999",
    "email": "joao@email.com",
    "endereco": "Rua X, nº 123"
  },
  "veiculo": {
    "placa": "ABC-1234",
    "modelo": "Gol 1.0",
    "ano": 2020,
    "cor": "Prata"
  },
  "servico": {
    "tipo": "Manutenção Preventiva",
    "descricao": "Descrição do serviço...",
    "valor": 150.00,
    "data": "2026-04-06",
    "observacoes": "Anotações..."
  },
  "status": "pendente_revisao|em_analise|aprovado|rejeitado",
  "dataCriacao": "06/04/2026 10:30:45",
  "observacoes_admin": "Notas da revisão..."
}
```

---

## 🎨 Design e UI/UX

- **Paleta de Cores:**
  - Azul Escuro: `#0f1221`
  - Verde Água: `#5f8d8d`
  - Branco: `#ffffff`
  - Cinza: `#666` / `#999`

- **Layout Responsivo:** Adaptado para desktop com menu lateral
- **Cards e Métricas:** Visualização clara de dados
- **Modais:** Para edição inline sem sair da página
- **Botões Intuitivos:** Com ícones para ações rápidas

---

## 🚀 Como Usar

### 1. **Login**
- Abra `index.html` no navegador
- Preencha qualquer email e senha (validação simples)
- Clique em "Acessar Sistema"

### 2. **Registrar um Novo Atendimento**
- Acesse "Novo Atendimento" no menu
- Preencha os dados do cliente, veículo e serviço
- Clique em "Registrar Atendimento"
- A OS será criada com status "Pendente Revisão"

### 3. **Revisar Atendimentos**
- Acesse "Revisão de OS" no menu
- Visualize os atendimentos pendentes
- Clique em "Revisar" para editar
- Altere o status para "Aprovado" ou "Rejeitado"
- Salve as alterações

### 4. **Consultar Histórico**
- Acesse "Histórico" no menu
- Busque por cliente ou placa
- Exporte individual ou geral em PDF

### 5. **Gerenciar Clientes**
- Acesse "Clientes" no menu
- Visualize todos os clientes cadastrados
- Veja o histórico de cada um

---

## 📱 Requisitos Atendidos

| Requisito | Funcionalidade | Status |
|-----------|---------------|--------|
| RF01 | Registro de informações do serviço | ✅ Implementado |
| RF02 | Envio de dados para o sistema | ✅ Implementado |
| RF03 | Recepção do apontamento | ✅ Implementado |
| RF04 | Vinculação automática à OS e cliente | ✅ Implementado |
| RF05 | Interface para leitura de dados | ✅ Implementado |
| RF06 | Correção de erros | ✅ Implementado |
| RF07 | Organização antes de aprovação | ✅ Implementado |
| RF08 | Fechamento de OS | ✅ Implementado |
| RF09 | Exportação em PDF formatado | ✅ Implementado |
| RF10 | Arquivo de histórico de OSs | ✅ Implementado |

---

## 🔐 Segurança e Validações

- ✅ Validação de campos obrigatórios
- ✅ Confirmação antes de deletar
- ✅ Status de controle de fluxo
- ✅ IDs únicos por timestamp
- ⚠️ *Nota:* Login atualmente é apenas front-end. Para produção, implementar backend com autenticação real.

---

## 🛠️ Tecnologias Utilizadas

- **HTML5** - Estrutura
- **CSS3** - Estilos e responsividade
- **JavaScript (ES6+)** - Lógica e interatividade
- **localStorage** - Persistência de dados
- **html2pdf.js** - Geração de PDFs
- **Font Awesome** - Ícones (para signup.html)

---

## 📈 Próximas Melhorias Sugeridas

1. **Backend com NodeJS/Express** - Substituir localStorage por banco de dados
2. **Autenticação Real** - JWT tokens e proteção de rotas
3. **Upload de Fotos** - Adicionar fotos do serviço à OS
4. **Relatórios Avançados** - Gráficos e análises de vendas
5. **Notificações** - Alertas de novas OSs por email
6. **Versionamento** - Histórico de alterações em cada OS
7. **Acesso por Perfil** - Diferentes permissões (técnico, gerente, admin)
8. **Mobile App** - Versão nativa para smartphones

---

## 📞 Suporte

Para dúvidas ou sugestões sobre o sistema, consulte a seção de contato ou suporte dentro da aplicação.

---

**Versão:** 1.0  
**Última Atualização:** 06 de abril de 2026  
**Status:** ✅ Pronto para uso
