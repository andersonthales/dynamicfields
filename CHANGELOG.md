# Changelog

## [1.1.0] - 2026-08-18

Versão que estava em produção nos servidores e foi trazida para o repositório.

### Adicionado
- Tipos de campo: Texto longo, E-mail, Link (URL), Número decimal, Data, Data e hora, Caixa de seleção, Botões de opção (radio), Lista de múltipla escolha e Texto informativo
- Validação no servidor de obrigatoriedade e de formato (e-mail, URL, inteiro, decimal)
- Opção "Somente leitura após a criação" para o perfil Self-Service
- Obrigatoriedade por categoria (`glpi_plugin_dynamicfields_categories.is_mandatory`)
- Exportação CSV dos valores (`front/export.php`)
- Reordenação dos valores da lista por arrastar e soltar
- Migração automática das tabelas de instalações 1.0.0

### Alterado
- Permissões administrativas baseadas em *Configuração → Atualizar* (o direito `config` do GLPI não tem os níveis Criar/Excluir)
- Chaves das tabelas passam a ser `INT UNSIGNED`
- CSS carregado em todas as páginas, inclusive no helpdesk

### Corrigido
- Valores salvos de um chamado só são exibidos para quem tem permissão de leitura nele

## [1.0.0] - 2026-03-10

### Lançamento inicial
- Campos personalizados por categoria ITIL (Texto, Número, Lista suspensa)
- Exibição dinâmica via AJAX durante a abertura do chamado
- Suporte a Chamado, Problema e Mudança
- Layout responsivo em 2 colunas
- Filtro dinâmico entre listas relacionadas (ex.: Centro Acadêmico → Subunidade)
- Interface de administração
- Tradução em português (pt_BR)
