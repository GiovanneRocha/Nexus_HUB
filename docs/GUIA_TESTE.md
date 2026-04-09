# 🎮 GUIA DE TESTE - Sistema de Ordens de Serviço

## ▶️ COMEÇANDO AGORA

### **OPÇÃO 1: Teste Rápido (5 minutos)**

1. Abra `demo.html` em seu navegador
2. Clique em **"Carregar Dados de Demonstração"**
3. Você terá 10 Ordens de Serviço pré-carregadas para explorar

**O que você verá:**
- ✅ OSs aprovadas e pendentes
- ✅ Diferentes clientes e veículos
- ✅ Vários tipos de serviço
- ✅ Sistema totalmente funcional

---

### **OPÇÃO 2: Começar do Zero (10 minutos)**

1. Abra `index.html` no navegador
2. Faça login com qualquer email/senha
3. Você estará no Dashboard
4. Comece a criar seu primeiro atendimento!

---

## 🧪 CENÁRIOS DE TESTE COMPLETOS

### **Teste 1: Criar um Novo Atendimento**
**Tempo:** 2 minutos

```
1. Na tela de Dashboard, clique em "📝 Novo Atendimento"
2. Preencha os dados:
   - Cliente: João da Silva
   - Telefone: (11) 99999-9999
   - Placa: ABC-1234
   - Modelo: Gol 1.0
   - Tipo de Serviço: Manutenção Preventiva
   - Descrição: Troca de óleo e filtros
   - Valor: 250.00
3. Clique em "💾 Registrar Atendimento"
4. Você verá a mensagem de sucesso com o ID
```

**Resultado Esperado:** ✅ OS criada com status "Pendente Revisão"

---

### **Teste 2: Revisar e Aprovar uma OS**
**Tempo:** 2 minutos

```
1. Vá para "✅ Revisão de OS"
2. Você verá a OS que criou anteriormente
3. Clique em "✏️ Revisar"
4. O modal abrirá com os dados
5. Altere o Status para "Aprovado"
6. Adicione uma observação: "Serviço executado com sucesso"
7. Clique em "💾 Salvar Alterações"
```

**Resultado Esperado:** ✅ OS atualizada e movida para aprovados

---

### **Teste 3: Consultar Histórico e Gerar PDF**
**Tempo:** 3 minutos

```
1. Vá para "📋 Histórico"
2. Você verá a OS aprovada na tabela
3. Clique em "📥 PDF" para gerar PDF individual
4. Clique em "👁️ Ver" para ver detalhes completos
5. Clique em "📄 Exportar para PDF" para relatório geral
```

**Resultado Esperado:** 
- ✅ PDFs baixados no computador
- ✅ Informações formatadas profissionalmente

---

### **Teste 4: Gerenciar Clientes**
**Tempo:** 1 minuto

```
1. Vá para "👥 Clientes"
2. Você verá o cliente "João da Silva" com seus dados
3. Clique em "👁️ Ver Histórico"
4. Verá todos os atendimentos deste cliente
```

**Resultado Esperado:** ✅ Cliente listado com histórico completo

---

### **Teste 5: Busca e Filtros**
**Tempo:** 1 minuto

```
Teste 5.1 - Filtro por Status (Revisão):
1. Vá para "✅ Revisão de OS"
2. Altere o filtro para "Aprovado"
3. Veja apenas OSs aprovadas

Teste 5.2 - Busca de Cliente (Histórico):
1. Vá para "📋 Histórico"
2. Digite "João" na busca
3. Veja apenas OSs deste cliente

Teste 5.3 - Busca de Cliente (Clientes):
1. Vá para "👥 Clientes"
2. Digite "João" na busca
3. Veja apenas este cliente
```

**Resultado Esperado:** ✅ Filtros funcionam em tempo real

---

### **Teste 6: Editar e Corrigir Dados**
**Tempo:** 2 minutos

```
1. Vá para "✅ Revisão de OS"
2. Clique em "✏️ Revisar" de uma OS pendente
3. Mude o Status para "Em Análise"
4. Altere o valor do serviço
5. Adicione observações
6. Clique em "💾 Salvar"
```

**Resultado Esperado:** ✅ Dados atualizados com sucesso

---

### **Teste 7: Deletar Atendimento**
**Tempo:** 1 minuto

```
1. Vá para "✅ Revisão de OS"
2. Clique em "🗑️ Deletar" de qualquer OS
3. Confirme a exclusão
4. Veja a OS desaparecer da lista
```

**Resultado Esperado:** ✅ OS removida do sistema

---

## 📊 TESTE DE DADOS

### **Clientes de Teste Pré-Carregados (demo.html)**

| Nome | Telefone | Placa | Status |
|------|----------|-------|--------|
| Carlos Oliveira | (11) 98765-4321 | ABC-1234 | Aprovado |
| Marina Silva | (11) 99876-5432 | XYZ-9876 | Aprovado |
| João Santos | (21) 98765-4321 | DEF-5678 | Aprovado |
| Ana Costa | (31) 99876-5432 | GHI-3456 | Aprovado |
| Ricardo Ferreira | (85) 98765-4321 | JKL-7890 | Aprovado |
| Patricia Mendes | (47) 99876-5432 | MNO-4567 | Em Análise |
| Carlos Oliveira | (11) 98765-4321 | ABC-1234 | Pendente |
| Marina Silva | (11) 99876-5432 | PQR-8901 | Pendente |
| João Santos | (21) 98765-4321 | DEF-5678 | Pendente |
| Mais... | ... | ... | ... |

---

## ✅ CHECKLIST DE FUNCIONALIDADES

Teste cada item abaixo:

- [ ] **Login** - Acessar o sistema com qualquer email/senha
- [ ] **Dashboard** - Ver métricas atualizadas
- [ ] **Novo Atendimento** - Criar uma OS completa
- [ ] **Armazenamento** - Dados persistem ao recarregar a página
- [ ] **Revisão** - Filtrar e revisar OSs por status
- [ ] **Edição** - Alterar dados de uma OS no modal
- [ ] **Aprovação** - Mudar status para "Aprovado"
- [ ] **Histórico** - Visualizar OSs finalizadas
- [ ] **Busca** - Procurar por cliente ou placa
- [ ] **PDF Individual** - Baixar OS em PDF
- [ ] **Relatório PDF** - Exportar todas as OSs em PDF
- [ ] **Clientes** - Ver base de clientes com histórico
- [ ] **Exclusão** - Deletar uma OS com confirmação
- [ ] **Responsividade** - Interface funciona bem
- [ ] **Validações** - Campos obrigatórios são validados

---

## 🐛 TROUBLESHOOTING

### **"Os dados não estão salvando"**
- Verifique se localStorage está habilitado no navegador
- Tente abrir a ferramenta do desenvolvedor (F12) e procure por erros
- Teste em modo anônimo (se estiver em modo privado, dados não persistem)

### **"PDFs não estão sendo baixados"**
- Verifique se pop-ups estão bloqueados
- Verifique a conexão com internet (biblioteca html2pdf precisa de acesso)
- Atualize a página e tente novamente

### **"Estilos não aparecem corretamente"**
- Certifique-se que `style.css` está na mesma pasta que os `.html`
- Limpe o cache do navegador (Ctrl+Shift+Del)
- Tente em outro navegador

### **"Modal não aparece"**
- Abra o console do desenvolvedor (F12)
- Procure por erros JavaScript
- Tente recarregar a página

---

## 🎯 CASOS DE USO REAIS

### **Cenário 1: Oficina Recebe Veículo**
```
Segundo-feira, 10:00 AM

1. Técnico vai para "novo_atendimento.html"
2. Registra dados do cliente que chegou
3. Registra o veículo
4. Descreve o problema a ser resolvido
5. Clica em "Registrar"

⏳ OS criada com status "Pendente Revisão"
```

### **Cenário 2: Administrador Revisa**
```
Mesmo dia, 14:00

1. Admin vai para "revisao.html"
2. Vê a lista de OSs pendentes
3. Revisa cada uma
4. Corrige valores/informações se necessário
5. Aprova as que estão corretas
6. Rejeita as que têm problema

✅ OSs aprovadas entram no histórico
```

### **Cenário 3: Emissão de Nota Fiscal**
```
Terça-feira

1. Admin vai para "historico.html"
2. Busca a OS específica
3. Clica em "PDF"
4. Salva o documento
5. Usa para emitir nota fiscal
6. Entrega ao cliente

✅ Documentação completa gerada
```

### **Cenário 4: Histórico de Cliente**
```
Cliente liga perguntando sobre serviços anteriores

1. Admin vai para "clientes.html"
2. Busca o cliente pelo nome
3. Clica em "Ver Histórico"
4. Tem acesso a todos os serviços dele
5. Responde o cliente com informações precisas

✅ Rastreabilidade completa
```

---

## 📈 MÉTRICAS ESPERADAS

### Após carregar dados de demonstração:

```
Dashboard deve mostrar:
- OS Abertas: 18
- Em Revisão: 7
- Finalizadas: 42
- Total de Clientes: 156
- Gráfico com dados preenchidos

Revisão deve mostrar:
- Pendentes: X
- Em Análise: Y
- Aprovados: Z
- Rejeitados: W

Histórico deve mostrar:
- Apenas OSs aprovadas
- Valor total dos serviços
- Clientes únicos
```

---

## 🚀 PRÓXIMAS ETAPAS

Após validar que tudo funciona:

1. **Implementar Backend**
   - Node.js + Express
   - PostgreSQL ou MongoDB
   - APIs REST

2. **Autenticação Real**
   - JWT tokens
   - Senhas com hash
   - Permissões por role

3. **Melhorias Visual**
   - Dark mode
   - Temas personalizáveis
   - Gráficos avançados

4. **Mobile**
   - React Native
   - Sincronização em tempo real
   - Offline-first

---

## 📞 SUPORTE

Se encontrar algum problema:

1. Verifique o console (F12) para erros
2. Limpe o cache e tente novamente
3. Consulte a seção "Troubleshooting" acima
4. Verifique a documentação em README.md

---

**Versão:** 1.0  
**Data:** 06 de Abril de 2026  
**Status:** ✅ Pronto para Teste

**Divirta-se testando! 🎉**
