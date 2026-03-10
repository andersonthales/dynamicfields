# Campos Dinâmicos — Plugin GLPI 10

Plugin para o [GLPI 10](https://glpi-project.org) que adiciona **campos personalizados dinâmicos** (Texto, Número e Lista suspensa) aos formulários de Chamados, Problemas e Mudanças, filtrados automaticamente por categoria ITIL.

## ✨ Funcionalidades

- Campos aparecem **durante a abertura do chamado**, em tempo real ao selecionar a categoria
- Filtragem por **categoria ITIL** — cada conjunto de campos pode ser vinculado a categorias específicas
- Campos sem categoria vinculada aparecem em **todas as categorias**
- Suporte a **Chamado, Problema e Mudança**
- Tipos de campo: **Texto**, **Número** e **Lista suspensa**
- Campos de lista suspensa com **filtro dinâmico** entre campos relacionados (ex: Centro → Subunidade)
- Layout em **2 colunas** responsivo
- Interface de administração para gerenciar campos e vínculos

## 📋 Requisitos

- GLPI >= 10.0.0
- PHP >= 7.4

## 🚀 Instalação

1. Baixe o plugin e extraia na pasta `plugins/` do GLPI:
   ```
   plugins/
   └── dynamicfields/
   ```
2. Acesse **Configurar → Plugins** no GLPI
3. Localize **Campos Dinâmicos** e clique em **Instalar**, depois **Ativar**

## ⚙️ Configuração

Após ativar, acesse **Configurar → Campos Dinâmicos**:

1. Clique em **Adicionar campo** para criar um novo campo
2. Defina a **Etiqueta**, **Nome interno**, **Tipo** e se é **Obrigatório**
3. Selecione em quais objetos o campo aparece (Chamado, Problema, Mudança)
4. Vincule o campo às **categorias ITIL** desejadas (deixe em branco para todas)

## 🗄️ Banco de dados

O plugin cria automaticamente as tabelas necessárias durante a instalação:

| Tabela | Descrição |
|---|---|
| `glpi_plugin_dynamicfields_fields` | Definições dos campos |
| `glpi_plugin_dynamicfields_categories` | Vínculos campo × categoria |
| `glpi_plugin_dynamicfields_values` | Valores salvos por chamado |
| `glpi_plugin_dynamicfields_config` | Configurações (ex: mapa de listas relacionadas) |

## 👤 Autor

**Anderson Thales**  
GitHub: [https://github.com/andersonthales](https://github.com/andersonthales)

## 📄 Licença

Este plugin é distribuído sob a licença [GPLv2+](LICENSE).
