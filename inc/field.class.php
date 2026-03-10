<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin for GLPI
 * -------------------------------------------------------------------------
 * LICENSE: GPLv2+
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * PluginDynamicfieldsField
 *
 * Manages custom field definitions: Text, Number and Dropdown.
 * Fields can be restricted to specific ITIL categories.
 */
class PluginDynamicfieldsField extends CommonDBTM
{
    public static $rightname = 'config';

    // ── Static helpers ──────────────────────────────────────────────────────

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_dynamicfields_fields';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Category Field', 'Campos Dinâmicos', $nb, 'dynamicfields');
    }

    public static function getTypes(): array
    {
        return [
            'text'     => 'Texto',
            'number'   => 'Número',
            'dropdown' => 'Lista suspensa',
        ];
    }

    public static function getAvailableItemtypes(): array
    {
        return [
            'Ticket'  => __('Ticket'),
            'Problem' => __('Problem'),
            'Change'  => __('Change'),
        ];
    }

    // ── Admin form ──────────────────────────────────────────────────────────

    public function showForm($ID, array $options = [])
    {
        global $DB;

        $is_new = ($ID <= 0);

        // Defaults for new record
        if ($is_new) {
            $this->fields = array_merge([
                'id'              => 0,
                'name'            => '',
                'label'           => '',
                'type'            => 'text',
                'is_mandatory'    => 0,
                'is_active'       => 1,
                'ranking'         => 0,
                'default_value'   => '',
                'dropdown_values' => '[]',
                'itemtypes'       => '[]',
            ], $this->fields ?? []);
        }

        $dropdown_values    = json_decode($this->fields['dropdown_values'] ?? '[]', true) ?? [];
        $selected_itemtypes = json_decode($this->fields['itemtypes'] ?? '[]', true) ?? [];

        // Linked categories for existing record
        $linked_categories = [];
        if (!$is_new) {
            $rows = $DB->request([
                'FROM'  => 'glpi_plugin_dynamicfields_categories',
                'WHERE' => ['plugin_dynamicfields_fields_id' => $ID],
            ]);
            foreach ($rows as $row) {
                $linked_categories[] = (int) $row['itilcategories_id'];
            }
        }

        // Build all available categories for the multi-select
        $all_categories = [];
        $cat_iter = $DB->request([
            'SELECT' => ['id', 'completename'],
            'FROM'   => 'glpi_itilcategories',
            'WHERE'  => ['is_helpdeskvisible' => 1],
            'ORDER'  => 'completename ASC',
        ]);
        foreach ($cat_iter as $cat) {
            $all_categories[(int)$cat['id']] = $cat['completename'];
        }

        // ── Form header ─────────────────────────────────────────────────────
        $form_action = PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.form.php';
        $btn_label   = $is_new ? __('Add') : __('Save');
        $btn_action  = $is_new ? 'add' : 'update';

        echo '<div class="container-fluid mt-2">';
        echo '<form method="POST" action="' . $form_action . '" enctype="multipart/form-data">';
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        if (!$is_new) {
            echo Html::hidden('id', ['value' => $ID]);
        }

        echo '<div class="card">';
        echo '<div class="card-header"><h3 class="card-title">' . ($is_new ? 'Novo campo' : 'Editar campo') . '</h3></div>';
        echo '<div class="card-body">';

        // ── Label + Internal name ────────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<div class="col-md-6">';
        echo '<label class="form-label">' . 'Etiqueta' . ' <span class="text-danger">*</span></label>';
        echo '<input type="text" name="label" class="form-control" value="' . htmlspecialchars($this->fields['label']) . '" required>';
        echo '</div>';
        echo '<div class="col-md-6">';
        echo '<label class="form-label">' . 'Nome interno' . ' <span class="text-danger">*</span></label>';
        if ($is_new) {
            echo '<input type="text" name="name" class="form-control" value="' . htmlspecialchars($this->fields['name']) . '" pattern="[a-z0-9_]+" title="' . 'Apenas letras minúsculas, números e sublinhados' . '" required>';
            echo '<div class="form-text">' . 'Minúsculas, sem espaços. Não pode ser alterado depois.' . '</div>';
        } else {
            echo '<input type="text" class="form-control" value="' . htmlspecialchars($this->fields['name']) . '" readonly>';
            echo '<div class="form-text text-muted">' . 'Não pode ser alterado após a criação.' . '</div>';
        }
        echo '</div>';
        echo '</div>';

        // ── Type + Active ────────────────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<div class="col-md-4">';
        echo '<label class="form-label">' . 'Tipo de campo' . '</label>';
        echo '<select name="type" class="form-select" id="cf_type_select">';
        foreach (self::getTypes() as $val => $lbl) {
            $sel = ($this->fields['type'] === $val) ? ' selected' : '';
            echo '<option value="' . $val . '"' . $sel . '>' . $lbl . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '<div class="col-md-4">';
        echo '<label class="form-label">' . __('Active') . '</label>';
        echo '<select name="is_active" class="form-select">';
        echo '<option value="1"' . ($this->fields['is_active'] ? ' selected' : '') . '>' . __('Yes') . '</option>';
        echo '<option value="0"' . (!$this->fields['is_active'] ? ' selected' : '') . '>' . __('No') . '</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="col-md-4">';
        echo '<label class="form-label">' . 'Obrigatório' . '</label>';
        echo '<select name="is_mandatory" class="form-select">';
        echo '<option value="0"' . (!$this->fields['is_mandatory'] ? ' selected' : '') . '>' . __('No') . '</option>';
        echo '<option value="1"' . ($this->fields['is_mandatory'] ? ' selected' : '') . '>' . __('Yes') . '</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';

        // ── Default value + Ranking ──────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<div class="col-md-8">';
        echo '<label class="form-label">' . 'Valor padrão' . '</label>';
        echo '<input type="text" name="default_value" class="form-control" value="' . htmlspecialchars($this->fields['default_value'] ?? '') . '">';
        echo '</div>';
        echo '<div class="col-md-4">';
        echo '<label class="form-label">' . 'Ordem de exibição' . '</label>';
        echo '<input type="number" name="ranking" class="form-control" value="' . (int)($this->fields['ranking'] ?? 0) . '" min="0">';
        echo '</div>';
        echo '</div>';

        // ── Dropdown values ──────────────────────────────────────────────────
        $show_dv = ($this->fields['type'] === 'dropdown') ? '' : ' style="display:none"';
        echo '<div class="row mb-3" id="cf_dv_section"' . $show_dv . '>';
        echo '<div class="col-12">';
        echo '<label class="form-label">' . 'Valores da lista' . '</label>';
        echo '<div id="cf_dv_list">';
        if (empty($dropdown_values)) {
            $dropdown_values = [''];
        }
        foreach ($dropdown_values as $dv) {
            echo '<div class="input-group mb-1 cf-dv-row">';
            echo '<input type="text" name="dropdown_values[]" class="form-control" value="' . htmlspecialchars($dv) . '">';
            echo '<button type="button" class="btn btn-outline-danger cf-remove-dv">' . __('Remove') . '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="cf_add_dv">';
        echo '<i class="fas fa-plus"></i> ' . 'Adicionar valor';
        echo '</button>';
        echo '</div>';
        echo '</div>';

        // ── Item types ───────────────────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<div class="col-12">';
        echo '<label class="form-label">' . 'Aplicar a' . '</label><br>';
        foreach (self::getAvailableItemtypes() as $type => $label) {
            $checked = in_array($type, $selected_itemtypes, true) ? ' checked' : '';
            echo '<div class="form-check form-check-inline">';
            echo '<input class="form-check-input" type="checkbox" name="itemtypes[]" value="' . $type . '" id="cf_it_' . $type . '"' . $checked . '>';
            echo '<label class="form-check-label" for="cf_it_' . $type . '">' . $label . '</label>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        // ── ITIL Categories multi-select ─────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<div class="col-12">';
        echo '<label class="form-label">' . 'Exibir para as categorias' . '</label>';
        echo '<div class="form-text mb-2">' . 'Deixe em branco para exibir em TODAS as categorias.' . '</div>';

        if (empty($all_categories)) {
            echo '<div class="alert alert-warning">' . 'Nenhuma categoria ITIL encontrada.' . '</div>';
        } else {
            echo '<select name="itilcategories_id[]" id="cf_categories" class="form-select" multiple size="8" style="max-width:500px;">';
            foreach ($all_categories as $cat_id => $cat_name) {
                $sel = in_array($cat_id, $linked_categories, true) ? ' selected' : '';
                echo '<option value="' . $cat_id . '"' . $sel . '>' . htmlspecialchars($cat_name) . '</option>';
            }
            echo '</select>';
            echo '<div class="form-text">' . 'Segure Ctrl (ou Cmd no Mac) para selecionar múltiplas categorias.' . '</div>';
        }
        echo '</div>';
        echo '</div>';

        // ── Buttons ──────────────────────────────────────────────────────────
        echo '</div>'; // card-body
        echo '<div class="card-footer">';
        echo '<button type="submit" name="' . $btn_action . '" class="btn btn-primary">';
        echo '<i class="fas fa-save me-1"></i> ' . $btn_label;
        echo '</button> ';
        echo '<a href="' . PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.php" class="btn btn-secondary">';
        echo '<i class="fas fa-times me-1"></i> ' . __('Cancel');
        echo '</a>';

        if (!$is_new) {
            echo ' <button type="submit" name="purge" class="btn btn-danger float-end" onclick="return confirm(\'' . addslashes('Excluir este campo e todos os seus valores salvos permanentemente?') . '\')">';
            echo '<i class="fas fa-trash me-1"></i> ' . __('Delete');
            echo '</button>';
        }

        echo '</div>'; // card-footer
        echo '</div>'; // card
        echo '</form>';
        echo '</div>'; // container

        // ── JavaScript ───────────────────────────────────────────────────────
        echo <<<JS
<script>
(function () {
    "use strict";

    // Toggle dropdown-values section on type change
    var typeSelect = document.getElementById('cf_type_select');
    var dvSection  = document.getElementById('cf_dv_section');

    typeSelect.addEventListener('change', function () {
        dvSection.style.display = (this.value === 'dropdown') ? '' : 'none';
    });

    // Add new value row
    document.getElementById('cf_add_dv').addEventListener('click', function () {
        var list = document.getElementById('cf_dv_list');
        var row  = document.createElement('div');
        row.className = 'input-group mb-1 cf-dv-row';
        row.innerHTML =
            '<input type="text" name="dropdown_values[]" class="form-control">' +
            '<button type="button" class="btn btn-outline-danger cf-remove-dv">Remover</button>';
        list.appendChild(row);
        bindRemove();
    });

    function bindRemove() {
        document.querySelectorAll('.cf-remove-dv').forEach(function (btn) {
            btn.onclick = function () { btn.closest('.cf-dv-row').remove(); };
        });
    }
    bindRemove();

    // Auto-generate internal name from label (new records only)
    var labelInput = document.querySelector('[name="label"]');
    var nameInput  = document.querySelector('[name="name"]');
    if (labelInput && nameInput && nameInput.readOnly === false) {
        labelInput.addEventListener('input', function () {
            nameInput.value = this.value
                .toLowerCase()
                .replace(/\s+/g, '_')
                .replace(/[^a-z0-9_]/g, '')
                .substring(0, 50);
        });
    }
})();
</script>
JS;

        return true;
    }

    // ── Input preparation ────────────────────────────────────────────────────

    public function prepareInputForAdd($input)
    {
        return $this->prepareInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        unset($input['name']); // immutable after creation
        return $this->prepareInput($input);
    }

    private function prepareInput(array $input): array
    {
        if (isset($input['name'])) {
            $input['name'] = strtolower(preg_replace('/[^a-z0-9_]/', '_', trim($input['name'])));
        }

        $input['itemtypes'] = json_encode(
            array_values(is_array($input['itemtypes'] ?? null) ? $input['itemtypes'] : [])
        );

        if (isset($input['dropdown_values']) && is_array($input['dropdown_values'])) {
            $clean = array_values(array_filter(array_map('trim', $input['dropdown_values'])));
            $input['dropdown_values'] = json_encode($clean);
        } elseif (($input['type'] ?? $this->fields['type'] ?? '') !== 'dropdown') {
            $input['dropdown_values'] = null;
        }

        $now = date('Y-m-d H:i:s');
        $input['date_mod'] = $now;
        if (empty($input['id'])) {
            $input['date_creation'] = $now;
        }

        return $input;
    }

    // ── Category sync ────────────────────────────────────────────────────────

    public function post_addItem()
    {
        $this->syncCategories((int) $this->fields['id'], $_POST['itilcategories_id'] ?? []);
    }

    public function post_updateItem($history = true)
    {
        $this->syncCategories((int) $this->fields['id'], $_POST['itilcategories_id'] ?? []);
    }

    private function syncCategories(int $field_id, $category_ids): void
    {
        global $DB;

        $DB->delete('glpi_plugin_dynamicfields_categories', [
            'plugin_dynamicfields_fields_id' => $field_id,
        ]);

        if (!is_array($category_ids)) {
            return;
        }

        foreach ($category_ids as $cat_id) {
            $cat_id = (int) $cat_id;
            if ($cat_id > 0) {
                $DB->insert('glpi_plugin_dynamicfields_categories', [
                    'plugin_dynamicfields_fields_id' => $field_id,
                    'itilcategories_id'               => $cat_id,
                ]);
            }
        }
    }

    // ── Business logic: field matching ───────────────────────────────────────

    /**
     * Return active fields for $itemtype that match $category_id.
     * Fields with NO category restrictions always show.
     */
    public static function getFieldsForItemAndCategory(string $itemtype, int $category_id = 0): array
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'is_active' => 1,
                ['itemtypes' => ['LIKE', '%"' . $DB->escape($itemtype) . '"%']],
            ],
            'ORDER' => ['ranking ASC', 'id ASC'],
        ]);

        $fields = [];

        foreach ($iterator as $field) {
            $cat_count = countElementsInTable(
                'glpi_plugin_dynamicfields_categories',
                ['plugin_dynamicfields_fields_id' => $field['id']]
            );

            if ($cat_count === 0) {
                $fields[] = $field; // no restriction
            } elseif ($category_id > 0) {
                $match = countElementsInTable(
                    'glpi_plugin_dynamicfields_categories',
                    [
                        'plugin_dynamicfields_fields_id' => $field['id'],
                        'itilcategories_id'               => $category_id,
                    ]
                );
                if ($match > 0) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    // ── Hook: render fields inside ticket / problem / change forms ────────────

    public static function showForTicket(array $params): void
    {
        $item = $params['item'] ?? null;
        if (!($item instanceof CommonITILObject)) {
            return;
        }

        $itemtype    = $item->getType();
        $items_id    = (int) ($item->fields['id'] ?? 0);
        $category_id = (int) ($item->fields['itilcategories_id'] ?? 0);

        // URLs dos endpoints AJAX
        $url_fields = PLUGINDYNAMICFIELDS_WEB_DIR . '/ajax/get_fields.php';
        $url_subs   = PLUGINDYNAMICFIELDS_WEB_DIR . '/ajax/get_subunidades.php';

        // Renderizar o container (pode estar vazio na criação se não há categoria)
        echo '<div id="plugin-dynamicfields-container" class="plugin-dynamicfields-container">';

        // Se já existe categoria (edição ou recarga), carregar campos imediatamente via PHP
        if ($category_id > 0) {
            $fields = self::getFieldsForItemAndCategory($itemtype, $category_id);
            if (!empty($fields)) {
                $saved = $items_id > 0
                    ? PluginDynamicfieldsValue::getValuesForItem($items_id, $itemtype)
                    : [];
                self::renderFields($fields, $saved, $url_subs);
            }
        }

        echo '</div>'; // #plugin-dynamicfields-container

        // ── JS: escuta mudança de categoria e recarrega campos via AJAX ───────
        $js_itemtype    = json_encode($itemtype);
        $js_items_id    = $items_id;
        $js_url_fields  = json_encode($url_fields);

        echo <<<JS
<script>
(function () {
    'use strict';

    var ITEMTYPE   = {$js_itemtype};
    var ITEM_ID    = {$js_items_id};
    var FIELDS_URL = {$js_url_fields};
    var container  = document.getElementById('plugin-dynamicfields-container');

    if (!container) return;

    // Detectar back-office (aba de atendimento) vs helpdesk (abertura)
    (function detectContext() {
        var isBackOffice = !!(
            document.querySelector('.itil-object')      ||
            document.querySelector('#mainformtable')    ||
            document.querySelector('.glpi-tabs')        ||
            document.querySelector('[data-bs-target="#timeline"]') ||
            document.querySelector('.actor-content')
        );
        container.classList.toggle('cf-backoffice', isBackOffice);
    })();

    // Reposicionar o container após o elemento pai estreito,
    // dentro do card-body que tem a largura real do formulário
    (function fixLayout() {
        // Encontrar o card-body pai (elemento com largura real)
        var anchor = null;
        var el = container.parentElement;
        while (el && el !== document.body) {
            if (el.classList.contains('card-body')) {
                anchor = el;
                break;
            }
            el = el.parentElement;
        }

        if (anchor && container.parentElement !== anchor) {
            // Mover o container para dentro do card-body diretamente
            anchor.appendChild(container);
        }

        // Garantir estilos de largura total
        container.style.cssText += ';display:block!important;width:100%!important;max-width:100%!important;box-sizing:border-box!important;';
    })();

    /**
     * Descobre o select de categoria do GLPI independentemente do DOM exato.
     * O GLPI 10 usa select2 e o elemento nativo tem name="itilcategories_id"
     * ou está dentro de um span[id*="itilcategories"].
     */
    function findCategorySelect() {
        // Tentativa 1: name direto
        var sel = document.querySelector('select[name="itilcategories_id"]');
        if (sel) return sel;
        // Tentativa 2: id contendo "itilcategories_id"
        sel = document.querySelector('select[id*="itilcategories_id"]');
        if (sel) return sel;
        // Tentativa 3: qualquer select dentro de span/div com id itilcategories
        sel = document.querySelector('[id*="itilcategories"] select');
        if (sel) return sel;
        return null;
    }

    function loadFields(categoryId) {
        if (!categoryId || categoryId === '0') {
            container.innerHTML = '';
            container.style.display = 'none';
            return;
        }

        var url = FIELDS_URL
            + '?itemtype='    + encodeURIComponent(ITEMTYPE)
            + '&category_id=' + encodeURIComponent(categoryId)
            + '&item_id='     + encodeURIComponent(ITEM_ID);

        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                container.innerHTML = html;
                container.style.display = html.trim() ? '' : 'none';
                // Re-executar scripts injetados (filtro centro→subunidade)
                container.querySelectorAll('script').forEach(function(oldScript) {
                    var s = document.createElement('script');
                    s.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(s, oldScript);
                });
            })
            .catch(function(e) {
                console.error('[DynamicFields] Erro ao carregar campos:', e);
            });
    }

    function attachCategoryListener() {
        var sel = findCategorySelect();
        if (!sel) {
            // Tentar novamente em 500 ms (o DOM pode ainda estar sendo construído)
            setTimeout(attachCategoryListener, 500);
            return;
        }

        // Escuta mudança nativa
        sel.addEventListener('change', function() {
            loadFields(this.value);
        });

        // Escuta evento do select2 (GLPI 10 usa jQuery select2)
        if (typeof jQuery !== 'undefined' && typeof jQuery(sel).select2 !== 'undefined') {
            jQuery(sel).on('select2:select select2:clear', function(e) {
                loadFields(e.params && e.params.data ? e.params.data.id : '');
            });
        }

        // Carga inicial: se já tem categoria selecionada no momento do carregamento
        // e o container ainda está vazio (caso de nova aba, etc.)
        if (sel.value && sel.value !== '0' && container.innerHTML.trim() === '') {
            loadFields(sel.value);
        }
    }

    // Iniciar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachCategoryListener);
    } else {
        attachCategoryListener();
    }
})();
</script>
JS;
    }

    /**
     * Renderiza os inputs dos campos (extraído para reutilização no endpoint AJAX).
     */
    public static function renderFields(array $fields, array $saved, string $url_subs): void
    {
        echo '<h3 class="cf-section-title">' . 'Campos adicionais' . '</h3>';
        echo '<div class="cf-grid">';

        foreach ($fields as $field) {
            $fid        = (int) $field['id'];
            $value      = $saved[$fid] ?? '';
            $input_name = 'plugin_dynamicfields[' . $fid . ']';
            $required   = (bool) $field['is_mandatory'];
            $req_mark   = $required ? ' <span class="required text-danger">*</span>' : '';

            echo '<div class="cf-field">';
            echo '<label class="cf-label">'
               . htmlspecialchars($field['label']) . $req_mark . '</label>';

            switch ($field['type']) {
                case 'text':
                    echo '<input type="text" name="' . $input_name . '" class="cf-input"'
                       . ' value="' . htmlspecialchars($value) . '"'
                       . ($required ? ' required' : '') . '>';
                    break;

                case 'number':
                    echo '<input type="number" name="' . $input_name . '" class="cf-input"'
                       . ' value="' . htmlspecialchars($value) . '"'
                       . ($required ? ' required' : '') . '>';
                    break;

                case 'dropdown':
                    $opts          = json_decode($field['dropdown_values'] ?? '[]', true) ?? [];
                    $is_centro     = ($field['name'] === 'centroacadmicofield');
                    $is_subunidade = ($field['name'] === 'subunidadeacadmicafield');
                    $select_id     = 'cf_field_' . $fid;
                    $attrs         = 'id="' . $select_id . '" name="' . $input_name . '" class="cf-select"';
                    if ($required)      { $attrs .= ' required'; }
                    if ($is_centro)     { $attrs .= ' data-cf-role="centro"'; }
                    if ($is_subunidade) { $attrs .= ' data-cf-role="subunidade"'; }

                    echo '<select ' . $attrs . '>';
                    echo '<option value="">-- ' . 'Selecione' . ' --</option>';
                    foreach ($opts as $dv) {
                        $sel = ($value === $dv) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($dv) . '"' . $sel . '>'
                           . htmlspecialchars($dv) . '</option>';
                    }
                    echo '</select>';
                    break;

                default:
                    echo '<input type="text" name="' . $input_name . '" class="cf-input"'
                       . ' value="' . htmlspecialchars($value) . '">';
            }

            echo '</div>'; // cf-field
        }

        echo '</div>'; // cf-grid

        // JS do filtro centro → subunidade
        $url_subs_js = json_encode($url_subs);
        echo <<<JS
<script>
(function initCentroSub() {
    var container = document.getElementById('plugin-dynamicfields-container');
    if (!container) return;
    var centroSel = container.querySelector('[data-cf-role="centro"]');
    var subSel    = container.querySelector('[data-cf-role="subunidade"]');
    if (!centroSel || !subSel) return;

    var ajaxUrl  = {$url_subs_js};
    var savedSub = subSel.value;

    function filterSubs(val, restore) {
        if (!val) {
            subSel.innerHTML = '<option value="">-- Selecione --</option>';
            subSel.disabled  = true;
            return;
        }
        subSel.disabled  = true;
        subSel.innerHTML = '<option value="">Carregando...</option>';
        fetch(ajaxUrl + '?centro=' + encodeURIComponent(val), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(subs) {
                subSel.innerHTML = '<option value="">-- Selecione --</option>';
                subs.forEach(function(s) {
                    var o = document.createElement('option');
                    o.value = s; o.textContent = s;
                    if (restore && s === restore) o.selected = true;
                    subSel.appendChild(o);
                });
                subSel.disabled = false;
            })
            .catch(function() {
                subSel.innerHTML = '<option value="">Erro ao carregar</option>';
                subSel.disabled  = false;
            });
    }

    centroSel.addEventListener('change', function() { filterSubs(this.value, null); });

    if (centroSel.value) {
        filterSubs(centroSel.value, savedSub);
    } else {
        subSel.disabled = true;
    }
})();
</script>
JS;
    }

    // ── Admin list page ──────────────────────────────────────────────────────

    public static function showList(): void
    {
        global $DB;

        $canedit  = Session::haveRight('config', UPDATE);
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'ORDER' => ['ranking ASC', 'label ASC'],
        ]);

        echo '<div class="container-fluid mt-3">';

        if ($canedit) {
            echo '<a href="' . PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.form.php" class="btn btn-primary mb-3">';
            echo '<i class="fas fa-plus me-1"></i> ' . 'Adicionar campo';
            echo '</a>';
        }

        if ($iterator->count() === 0) {
            echo '<div class="alert alert-info">';
            echo 'Nenhum campo definido. Clique em "Adicionar campo" para começar.';
            echo '</div>';
            echo '</div>';
            return;
        }

        $types     = self::getTypes();
        $itemtypes = self::getAvailableItemtypes();

        echo '<div class="table-responsive">';
        echo '<table class="table table-striped table-hover">';
        echo '<thead class="table-dark"><tr>';
        foreach ([
            __('Label'),
            'Nome interno',
            'Tipo',
            'Aplicável a',
            'Categorias',
            'Obrigatório',
            __('Active'),
        ] as $th) {
            echo '<th>' . $th . '</th>';
        }
        if ($canedit) {
            echo '<th>' . __('Actions') . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($iterator as $row) {
            $sel_types   = json_decode($row['itemtypes'] ?? '[]', true) ?? [];
            $type_labels = implode(', ', array_map(fn($t) => $itemtypes[$t] ?? $t, $sel_types));

            $cat_iter = $DB->request([
                'SELECT'    => ['glpi_itilcategories.completename'],
                'FROM'      => 'glpi_plugin_dynamicfields_categories',
                'LEFT JOIN' => [
                    'glpi_itilcategories' => [
                        'FKEY' => [
                            'glpi_plugin_dynamicfields_categories' => 'itilcategories_id',
                            'glpi_itilcategories'                   => 'id',
                        ],
                    ],
                ],
                'WHERE' => ['plugin_dynamicfields_fields_id' => $row['id']],
            ]);
            $cat_names = [];
            foreach ($cat_iter as $c) {
                $cat_names[] = $c['completename'];
            }

            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($row['label']) . '</strong></td>';
            echo '<td><code>' . htmlspecialchars($row['name']) . '</code></td>';
            echo '<td>' . ($types[$row['type']] ?? $row['type']) . '</td>';
            echo '<td>' . ($type_labels ?: '—') . '</td>';
            echo '<td>';
            if (empty($cat_names)) {
                echo '<span class="text-muted fst-italic">' . 'Todas as categorias' . '</span>';
            } else {
                echo implode('<br>', array_map('htmlspecialchars', $cat_names));
            }
            echo '</td>';
            echo '<td>' . ($row['is_mandatory'] ? '<span class="badge bg-warning">' . __('Yes') . '</span>' : __('No')) . '</td>';
            echo '<td>' . ($row['is_active'] ? '<span class="badge bg-success">' . __('Yes') . '</span>' : '<span class="badge bg-secondary">' . __('No') . '</span>') . '</td>';

            if ($canedit) {
                $edit_url  = PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.form.php?id=' . (int) $row['id'];
                $confirm   = addslashes('Excluir este campo e todos os seus valores salvos permanentemente?');
                echo '<td>';
                echo '<a href="' . $edit_url . '" class="btn btn-sm btn-outline-secondary me-1"><i class="fas fa-edit"></i></a>';
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></div></div>';
    }

    // ── Search options ────────────────────────────────────────────────────────

    public function rawSearchOptions(): array
    {
        $tab   = [];
        $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];
        $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'label',     'name' => __('Label'),   'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'name',      'name' => 'Nome interno', 'datatype' => 'string'];
        $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'type',      'name' => 'Tipo',          'datatype' => 'string'];
        $tab[] = ['id' => 4, 'table' => self::getTable(), 'field' => 'is_active', 'name' => __('Active'),  'datatype' => 'bool'];
        return $tab;
    }
}
