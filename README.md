# Campos Dinâmicos (dynamicfields) — Plugin para GLPI 10

Adiciona **campos personalizados** aos formulários de **Chamados, Problemas e Mudanças**, exibidos conforme a **categoria ITIL** escolhida. Os campos aparecem em tempo real enquanto o usuário preenche o chamado, e os valores ficam salvos junto com o item.

| | |
|---|---|
| **Versão** | 1.1.0 |
| **GLPI** | 10.0.x (testado em 10.0.16 a 10.0.23) |
| **PHP** | 8.0 ou superior |
| **Licença** | GPLv2+ |

---

## Funcionalidades

- **13 tipos de campo**: Texto, Texto longo, E-mail, Link (URL), Número inteiro, Número decimal, Data, Data e hora, Caixa de seleção, Botões de opção (radio), Lista suspensa, Lista de múltipla escolha e Texto informativo (somente exibição, sem resposta).
- **Filtragem por categoria ITIL**: cada campo pode ser vinculado a uma ou mais categorias. Campo sem categoria vinculada aparece em **todas**.
- **Por tipo de objeto**: escolha se o campo aparece em Chamado, Problema e/ou Mudança.
- **Atualização em tempo real**: ao trocar a categoria no formulário, os campos são recarregados via AJAX.
- **Validação no servidor**: obrigatoriedade e formato (e-mail, URL, inteiro e decimal) são conferidos no backend, não só no navegador.
- **Somente leitura após a criação**: o valor fica travado para o requerente (perfil Self-Service) depois que o chamado é aberto.
- **Listas relacionadas (Centro → Subunidade)**: uma lista filtra as opções de outra. Veja [Listas relacionadas](#listas-relacionadas-centro--subunidade).
- **Exportação CSV** dos valores preenchidos, com um chamado por linha e um campo por coluna.
- Ordem de exibição configurável e layout responsivo em 2 colunas.

## Instalação

1. Copie a pasta do plugin para `plugins/dynamicfields` dentro do GLPI:
   ```bash
   cd /var/www/html/glpi/plugins
   git clone https://github.com/andersonthales/dynamicfields.git
   chown -R www-data:www-data dynamicfields
   ```
2. No GLPI, acesse **Configurar → Plugins**.
3. Clique em **Instalar** e depois em **Ativar** no plugin **Campos Dinâmicos**.

### Atualização a partir da 1.0.0

Substitua os arquivos do plugin e, em **Configurar → Plugins**, clique em **Atualizar** (ou desative e ative novamente). A instalação detecta as tabelas existentes e acrescenta as colunas novas (`is_readonly_after_create` e `is_mandatory` por categoria) **sem apagar dados**.

## Como usar

Acesse **Configurar → Campos Dinâmicos** (é necessário o direito *Configuração → Atualizar*).

1. Clique em **Adicionar campo**.
2. Preencha a **Etiqueta** (o texto que o usuário vê). O **Nome interno** é gerado a partir dela e **não pode ser alterado depois**.
3. Escolha o **Tipo**. Para Lista suspensa, Radio e Múltipla escolha, cadastre os **valores da lista** (dá para reordená-los arrastando).
4. Defina se é **Obrigatório**, **Ativo** e **Somente leitura após a criação**.
5. Marque em quais objetos o campo aparece: **Chamado**, **Problema** e/ou **Mudança**.
6. Selecione as **categorias ITIL**. Deixe em branco para exibir em todas.

Para o tipo **Texto informativo**, o campo *Valor padrão* vira *Texto a exibir*. Ele mostra uma orientação ao usuário e não guarda resposta.

### Exportação

Na lista de campos, o botão **Exportar CSV (Tickets)** gera um arquivo separado por `;` em UTF-8 (abre direto no Excel), com o ID do chamado, o título e uma coluna por campo.

## Listas relacionadas (Centro → Subunidade)

Quando existem dois campos do tipo Lista suspensa com os nomes internos **`centroacadmicofield`** e **`subunidadeacadmicafield`**, a escolha do primeiro filtra as opções do segundo.

O mapa de relação fica na tabela `glpi_plugin_dynamicfields_config` e **ainda não tem tela de cadastro**: ele é gravado direto no banco, como um JSON `{ "Centro": ["Subunidade 1", "Subunidade 2"] }`:

```sql
INSERT INTO glpi_plugin_dynamicfields_config (cfg_key, cfg_value)
VALUES ('centro_subunidade_map', '{"Centro de Ciências Exatas": ["Departamento de Física", "Departamento de Química"]}')
ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value);
```

Sem o mapa, a lista de subunidades mostra todas as opções cadastradas no próprio campo.

## Obrigatoriedade por categoria

Além do *Obrigatório* geral do campo, cada vínculo campo × categoria tem a coluna `is_mandatory` em `glpi_plugin_dynamicfields_categories`:

- `NULL`: herda o valor do campo (padrão);
- `0` ou `1`: força opcional ou obrigatório só naquela categoria.

Essa coluna também é ajustada direto no banco por enquanto.

## Banco de dados

| Tabela | Conteúdo |
|---|---|
| `glpi_plugin_dynamicfields_fields` | Definição dos campos |
| `glpi_plugin_dynamicfields_categories` | Vínculos campo × categoria ITIL (com obrigatoriedade opcional) |
| `glpi_plugin_dynamicfields_values` | Valores preenchidos, por item (`itemtype` + `items_id`) |
| `glpi_plugin_dynamicfields_config` | Configurações chave/valor (ex.: mapa Centro → Subunidade) |

Ao **desinstalar** o plugin, as quatro tabelas são removidas com todos os valores. Faça backup antes.

## Limitações conhecidas

- **Obrigatório não impede o salvamento.** No navegador, o formulário não envia se o campo estiver vazio. Se o envio passar mesmo assim (API, JavaScript desativado), o servidor só exibe um aviso: o chamado é salvo sem o valor.
- Os campos aparecem apenas nos formulários do GLPI. Chamados criados por **coletor de e-mail**, **API** ou **Formcreator** não recebem valores.
- Compatível somente com **GLPI 10**. O GLPI 11 mudou a estrutura de plugins e ainda não é suportado.

## Estrutura

```
dynamicfields/
├── setup.php                  # Registro do plugin e hooks
├── hook.php                   # Instalação, migração e desinstalação
├── plugin.xml                 # Metadados para o catálogo do GLPI
├── inc/
│   ├── field.class.php        # Definição dos campos, formulário admin e renderização
│   ├── value.class.php        # Gravação, validação e exportação dos valores
│   ├── category.class.php     # Vínculo campo × categoria
│   └── menu.class.php         # Entrada no menu Configurar
├── front/
│   ├── field.php              # Lista de campos
│   ├── field.form.php         # Criar / editar campo
│   └── export.php             # Exportação CSV
├── ajax/
│   ├── get_fields.php         # HTML dos campos para a categoria escolhida
│   └── get_subunidades.php    # Subunidades de um centro (JSON)
├── css/dynamicfields.css
├── js/load_css.js
└── locale/                    # Traduções pt_BR e en_GB
```

## Changelog

Veja [CHANGELOG.md](CHANGELOG.md).

## Licença

[GPLv2 ou posterior](LICENSE).

## Autor

**Anderson Thales** — [@andersonthales](https://github.com/andersonthales)
