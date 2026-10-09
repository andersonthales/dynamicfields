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

class PluginDynamicfieldsField extends CommonDBTM
{
    public static $rightname = 'config';

    // O right nativo 'config' do GLPI só expõe os bits READ/UPDATE na tela de
    // perfis (não existe checkbox de "Criar"/"Excluir" pra esse right). Por
    // isso o check() padrão do CommonDBTM (que exige CREATE/PURGE) nunca
    // passa. Sobrescrevemos para usar UPDATE como o único gate administrativo,
    // igual já é feito em showList() ($canedit) e nos front controllers.
    public static function canCreate(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    public static function canPurge(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    /**
     * Ao excluir um campo, remove também os valores salvos e os vínculos
     * com categorias — sem isso ficam linhas órfãs nas duas tabelas.
     */
    public function cleanDBonPurge()
    {
        global $DB;

        $fid = (int) $this->fields['id'];
        $DB->delete('glpi_plugin_dynamicfields_values', ['plugin_dynamicfields_fields_id' => $fid]);
        $DB->delete('glpi_plugin_dynamicfields_categories', ['plugin_dynamicfields_fields_id' => $fid]);
    }

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
            'text'        => 'Texto',
            'textarea'    => 'Texto longo',
            'email'       => 'E-mail',
            'url'         => 'Link (URL)',
            'number'      => 'Número inteiro',
            'float'       => 'Número decimal',
            'date'        => 'Data',
            'datetime'    => 'Data e hora',
            'checkbox'    => 'Caixa de seleção',
            'radio'       => 'Botões de opção (radio)',
            'dropdown'    => 'Lista suspensa',
            'multiselect' => 'Lista suspensa (múltipla escolha)',
            'description' => 'Texto informativo (sem resposta)',
        ];
    }

    /** Tipos que usam a lista de valores (dropdown_values). */
    private static function choiceTypes(): array
    {
        return ['dropdown', 'radio', 'multiselect'];
    }

    public static function getAvailableItemtypes(): array
    {
        return [
            'Ticket'  => __('Ticket'),
            'Problem' => __('Problem'),
            'Change'  => __('Change'),
        ];
    }

    // ── Admin form ───────────────────────────────────────────────────────────

    public function showForm($ID, array $options = [])
    {
        global $DB;

        $is_new = ($ID <= 0);

        if ($is_new) {
            $this->fields = array_merge([
                'id'                       => 0,
                'name'                     => '',
                'label'                    => '',
                'type'                     => 'text',
                'is_mandatory'             => 0,
                'is_active'                => 1,
                'is_readonly_after_create' => 0,
                'ranking'                  => 0,
                'default_value'            => '',
                'dropdown_values'          => '[]',
                'itemtypes'                => '[]',
            ], $this->fields ?? []);
        }

        $dropdown_values    = json_decode($this->fields['dropdown_values'] ?? '[]', true) ?? [];
        $selected_itemtypes = json_decode($this->fields['itemtypes'] ?? '[]', true) ?? [];

        $linked_categories = [];
        if (!$is_new) {
            $rows = $DB->request(['FROM' => 'glpi_plugin_dynamicfields_categories', 'WHERE' => ['plugin_dynamicfields_fields_id' => $ID]]);
            foreach ($rows as $row) {
                $linked_categories[] = (int) $row['itilcategories_id'];
            }
        }

        $all_categories = [];
        $cat_iter = $DB->request(['SELECT' => ['id', 'completename'], 'FROM' => 'glpi_itilcategories', 'WHERE' => ['is_helpdeskvisible' => 1], 'ORDER' => 'completename ASC']);
        foreach ($cat_iter as $cat) {
            $all_categories[(int)$cat['id']] = $cat['completename'];
        }

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

        // Label + Internal name
        echo '<div class="row mb-3">';
        echo '<div class="col-md-6"><label class="form-label">Etiqueta <span class="text-danger">*</span></label>';
        echo '<input type="text" name="label" class="form-control" value="' . htmlspecialchars($this->fields['label']) . '" required></div>';
        echo '<div class="col-md-6"><label class="form-label">Nome interno <span class="text-danger">*</span></label>';
        if ($is_new) {
            echo '<input type="text" name="name" class="form-control" value="' . htmlspecialchars($this->fields['name']) . '" pattern="[a-z0-9_]+" title="Apenas letras minúsculas, números e sublinhados" required>';
            echo '<div class="form-text">Minúsculas, sem espaços. Não pode ser alterado depois.</div>';
        } else {
            echo '<input type="text" class="form-control" value="' . htmlspecialchars($this->fields['name']) . '" readonly>';
            echo '<div class="form-text text-muted">Não pode ser alterado após a criação.</div>';
        }
        echo '</div></div>';

        // Type + Active + Mandatory + Readonly
        echo '<div class="row mb-3">';
        echo '<div class="col-md-3"><label class="form-label">Tipo de campo</label>';
        echo '<select name="type" class="form-select" id="cf_type_select">';
        foreach (self::getTypes() as $val => $lbl) {
            $sel = ($this->fields['type'] === $val) ? ' selected' : '';
            echo '<option value="' . $val . '"' . $sel . '>' . $lbl . '</option>';
        }
        echo '</select></div>';

        echo '<div class="col-md-3"><label class="form-label">' . __('Active') . '</label>';
        echo '<select name="is_active" class="form-select">';
        echo '<option value="1"' . ($this->fields['is_active'] ? ' selected' : '') . '>' . __('Yes') . '</option>';
        echo '<option value="0"' . (!$this->fields['is_active'] ? ' selected' : '') . '>' . __('No') . '</option>';
        echo '</select></div>';

        echo '<div class="col-md-3"><label class="form-label">Obrigatório</label>';
        echo '<select name="is_mandatory" class="form-select">';
        echo '<option value="0"' . (!$this->fields['is_mandatory'] ? ' selected' : '') . '>' . __('No') . '</option>';
        echo '<option value="1"' . ($this->fields['is_mandatory'] ? ' selected' : '') . '>' . __('Yes') . '</option>';
        echo '</select></div>';

        echo '<div class="col-md-3"><label class="form-label">Somente leitura após abertura</label>';
        echo '<select name="is_readonly_after_create" class="form-select">';
        echo '<option value="0"' . (!($this->fields['is_readonly_after_create'] ?? 0) ? ' selected' : '') . '>' . __('No') . '</option>';
        echo '<option value="1"' . (($this->fields['is_readonly_after_create'] ?? 0) ? ' selected' : '') . '>' . __('Yes') . '</option>';
        echo '</select></div>';
        echo '</div>';

        // Default value + Ranking
        echo '<div class="row mb-3">';
        echo '<div class="col-md-8"><label class="form-label" id="cf_default_value_label">' . ($this->fields['type'] === 'description' ? 'Texto a exibir' : 'Valor padrão') . '</label>';
        echo '<input type="text" name="default_value" class="form-control" value="' . htmlspecialchars($this->fields['default_value'] ?? '') . '"></div>';
        echo '<div class="col-md-4"><label class="form-label">Ordem de exibição</label>';
        echo '<input type="number" name="ranking" class="form-control" value="' . (int)($this->fields['ranking'] ?? 0) . '" min="0"></div>';
        echo '</div>';

        // Dropdown/radio/multiselect values
        $show_dv = in_array($this->fields['type'], self::choiceTypes(), true) ? '' : ' style="display:none"';
        echo '<div class="row mb-3" id="cf_dv_section"' . $show_dv . '>';
        echo '<div class="col-12"><label class="form-label">Valores da lista</label>';
        echo '<div id="cf_dv_list">';
        if (empty($dropdown_values)) { $dropdown_values = ['']; }
        foreach ($dropdown_values as $dv) {
            echo '<div class="input-group mb-1 cf-dv-row" draggable="true">';
            echo '<span class="input-group-text cf-drag-handle" style="cursor:grab" title="Arrastar para reordenar">☰</span>';
            echo '<input type="text" name="dropdown_values[]" class="form-control" value="' . htmlspecialchars($dv) . '">';
            echo '<button type="button" class="btn btn-outline-danger cf-remove-dv">' . __('Remove') . '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="cf_add_dv">';
        echo '<i class="fas fa-plus"></i> Adicionar valor';
        echo '</button></div></div>';

        // Item types
        echo '<div class="row mb-3"><div class="col-12"><label class="form-label">Aplicar a</label><br>';
        foreach (self::getAvailableItemtypes() as $type => $label) {
            $checked = in_array($type, $selected_itemtypes, true) ? ' checked' : '';
            echo '<div class="form-check form-check-inline">';
            echo '<input class="form-check-input" type="checkbox" name="itemtypes[]" value="' . $type . '" id="cf_it_' . $type . '"' . $checked . '>';
            echo '<label class="form-check-label" for="cf_it_' . $type . '">' . $label . '</label>';
            echo '</div>';
        }
        echo '</div></div>';

        // ITIL Categories
        echo '<div class="row mb-3"><div class="col-12"><label class="form-label">Exibir para as categorias</label>';
        echo '<div class="form-text mb-2">Deixe em branco para exibir em TODAS as categorias.</div>';
        if (empty($all_categories)) {
            echo '<div class="alert alert-warning">Nenhuma categoria ITIL encontrada.</div>';
        } else {
            echo '<select name="itilcategories_id[]" id="cf_categories" class="form-select" multiple size="8" style="max-width:500px;">';
            foreach ($all_categories as $cat_id => $cat_name) {
                $sel = in_array($cat_id, $linked_categories, true) ? ' selected' : '';
                echo '<option value="' . $cat_id . '"' . $sel . '>' . htmlspecialchars($cat_name) . '</option>';
            }
            echo '</select>';
            echo '<div class="form-text">Segure Ctrl (ou Cmd no Mac) para selecionar múltiplas categorias.</div>';
        }
        echo '</div></div>';

        // Buttons
        echo '</div><div class="card-footer">';
        echo '<button type="submit" name="' . $btn_action . '" class="btn btn-primary"><i class="fas fa-save me-1"></i> ' . $btn_label . '</button> ';
        echo '<a href="' . PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.php" class="btn btn-secondary"><i class="fas fa-times me-1"></i> ' . __('Cancel') . '</a>';
        if (!$is_new) {
            echo ' <button type="submit" name="purge" class="btn btn-danger float-end" onclick="return confirm(\'' . addslashes('Excluir este campo e todos os seus valores salvos permanentemente?') . '\')">';
            echo '<i class="fas fa-trash me-1"></i> ' . __('Delete') . '</button>';
        }
        echo '</div></div></form></div>';

        echo <<<JS
<script>
(function () {
    "use strict";

    var typeSelect  = document.getElementById('cf_type_select');
    var dvSection   = document.getElementById('cf_dv_section');
    var defValLabel = document.getElementById('cf_default_value_label');
    var choiceTypes = ['dropdown', 'radio', 'multiselect'];

    typeSelect.addEventListener('change', function () {
        dvSection.style.display = choiceTypes.indexOf(this.value) !== -1 ? '' : 'none';
        if (defValLabel) {
            defValLabel.textContent = (this.value === 'description') ? 'Texto a exibir' : 'Valor padrão';
        }
    });

    document.getElementById('cf_add_dv').addEventListener('click', function () {
        var list = document.getElementById('cf_dv_list');
        var row  = document.createElement('div');
        row.className = 'input-group mb-1 cf-dv-row';
        row.setAttribute('draggable', 'true');
        row.innerHTML =
            '<span class="input-group-text cf-drag-handle" style="cursor:grab" title="Arrastar para reordenar">☰</span>' +
            '<input type="text" name="dropdown_values[]" class="form-control">' +
            '<button type="button" class="btn btn-outline-danger cf-remove-dv">Remover</button>';
        list.appendChild(row);
        bindRemove();
        bindDragDrop();
    });

    function bindRemove() {
        document.querySelectorAll('.cf-remove-dv').forEach(function (btn) {
            btn.onclick = function () { btn.closest('.cf-dv-row').remove(); };
        });
    }
    bindRemove();

    // Drag-and-drop reorder
    var dragSrc = null;
    function bindDragDrop() {
        document.querySelectorAll('.cf-dv-row').forEach(function(row) {
            row.addEventListener('dragstart', function(e) {
                dragSrc = this;
                this.style.opacity = '0.4';
                e.dataTransfer.effectAllowed = 'move';
            });
            row.addEventListener('dragend', function() {
                this.style.opacity = '1';
                document.querySelectorAll('.cf-dv-row').forEach(function(r) { r.classList.remove('cf-drag-over'); });
            });
            row.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                return false;
            });
            row.addEventListener('dragenter', function() { this.classList.add('cf-drag-over'); });
            row.addEventListener('dragleave', function() { this.classList.remove('cf-drag-over'); });
            row.addEventListener('drop', function(e) {
                e.stopPropagation();
                if (dragSrc !== this) {
                    var list = document.getElementById('cf_dv_list');
                    var rows = Array.from(list.querySelectorAll('.cf-dv-row'));
                    var srcIdx = rows.indexOf(dragSrc);
                    var dstIdx = rows.indexOf(this);
                    if (srcIdx < dstIdx) {
                        list.insertBefore(dragSrc, this.nextSibling);
                    } else {
                        list.insertBefore(dragSrc, this);
                    }
                }
                return false;
            });
        });
    }
    bindDragDrop();

    // Auto-generate internal name
    var labelInput = document.querySelector('[name="label"]');
    var nameInput  = document.querySelector('[name="name"]');
    if (labelInput && nameInput && !nameInput.readOnly) {
        labelInput.addEventListener('input', function () {
            nameInput.value = this.value
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
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
        unset($input['name']);
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

        $is_choice_type = in_array(($input['type'] ?? $this->fields['type'] ?? ''), self::choiceTypes(), true);
        if ($is_choice_type) {
            $raw   = is_array($input['dropdown_values'] ?? null) ? $input['dropdown_values'] : [];
            $clean = array_values(array_filter(array_map('trim', $raw)));
            $input['dropdown_values'] = json_encode($clean, JSON_UNESCAPED_UNICODE);
        } else {
            $input['dropdown_values'] = null;
        }

        $input['is_readonly_after_create'] = (int) ($input['is_readonly_after_create'] ?? 0);
        $input['is_mandatory']             = (int) (($input['is_mandatory'] ?? 0) ? 1 : 0);
        $input['is_active']                = (int) (($input['is_active'] ?? 1) ? 1 : 0);
        $input['ranking']                  = max(0, (int) ($input['ranking'] ?? 0));

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

        $existing_mandatory = [];
        $old_rows = $DB->request(['FROM' => 'glpi_plugin_dynamicfields_categories', 'WHERE' => ['plugin_dynamicfields_fields_id' => $field_id]]);
        foreach ($old_rows as $row) {
            $existing_mandatory[(int)$row['itilcategories_id']] = $row['is_mandatory'];
        }

        $DB->delete('glpi_plugin_dynamicfields_categories', ['plugin_dynamicfields_fields_id' => $field_id]);

        if (!is_array($category_ids)) return;

        foreach ($category_ids as $cat_id) {
            $cat_id = (int) $cat_id;
            if ($cat_id > 0) {
                $mandatory_val = $existing_mandatory[$cat_id] ?? null;
                $DB->insert('glpi_plugin_dynamicfields_categories', [
                    'plugin_dynamicfields_fields_id' => $field_id,
                    'itilcategories_id'               => $cat_id,
                    'is_mandatory'                    => $mandatory_val,
                ]);
            }
        }
    }

    // ── Business logic ───────────────────────────────────────────────────────

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
            $cat_count = countElementsInTable('glpi_plugin_dynamicfields_categories', ['plugin_dynamicfields_fields_id' => $field['id']]);

            if ($cat_count === 0) {
                $fields[] = $field;
            } elseif ($category_id > 0) {
                $link_iter = $DB->request([
                    'FROM'  => 'glpi_plugin_dynamicfields_categories',
                    'WHERE' => [
                        'plugin_dynamicfields_fields_id' => $field['id'],
                        'itilcategories_id'               => $category_id,
                    ],
                    'LIMIT' => 1,
                ]);
                if ($link_iter->count() > 0) {
                    $link = $link_iter->current();
                    if ($link['is_mandatory'] !== null) {
                        $field['is_mandatory'] = (int) $link['is_mandatory'];
                    }
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    // ── Hook: render fields ──────────────────────────────────────────────────

    public static function showForTicket(array $params): void
    {
        $item = $params['item'] ?? null;
        if (!($item instanceof CommonITILObject)) return;

        $itemtype    = $item->getType();
        $items_id    = (int) ($item->fields['id'] ?? 0);
        $category_id = (int) ($item->fields['itilcategories_id'] ?? 0);
        $is_existing = $items_id > 0;

        $url_fields = PLUGINDYNAMICFIELDS_WEB_DIR . '/ajax/get_fields.php';
        $url_subs   = PLUGINDYNAMICFIELDS_WEB_DIR . '/ajax/get_subunidades.php';

        echo '<div id="plugin-dynamicfields-container" class="plugin-dynamicfields-container">';

        if ($category_id > 0) {
            $fields = self::getFieldsForItemAndCategory($itemtype, $category_id);
            if (!empty($fields)) {
                $saved = $is_existing ? PluginDynamicfieldsValue::getValuesForItem($items_id, $itemtype) : [];
                self::renderFields($fields, $saved, $url_subs, $is_existing);
            }
        }

        echo '</div>';

        $js_itemtype   = json_encode($itemtype);
        $js_items_id   = $items_id;
        $js_url_fields = json_encode($url_fields);
        $js_existing   = $is_existing ? 'true' : 'false';

        echo <<<JS
<script>
(function () {
    'use strict';

    var ITEMTYPE   = {$js_itemtype};
    var ITEM_ID    = {$js_items_id};
    var IS_EXISTING = {$js_existing};
    var FIELDS_URL = {$js_url_fields};
    var container  = document.getElementById('plugin-dynamicfields-container');

    if (!container) return;

    (function detectContext() {
        var isBackOffice = !!(
            document.querySelector('.itil-object') ||
            document.querySelector('#mainformtable') ||
            document.querySelector('.glpi-tabs') ||
            document.querySelector('[data-bs-target="#timeline"]') ||
            document.querySelector('.actor-content')
        );
        container.classList.toggle('cf-backoffice', isBackOffice);
    })();

    (function fixLayout() {
        var anchor = null;
        var el = container.parentElement;
        while (el && el !== document.body) {
            if (el.classList.contains('card-body')) { anchor = el; break; }
            el = el.parentElement;
        }
        if (anchor && container.parentElement !== anchor) anchor.appendChild(container);
        container.style.cssText += ';display:block!important;width:100%!important;max-width:100%!important;box-sizing:border-box!important;';
    })();

    function findCategorySelect() {
        return document.querySelector('select[name="itilcategories_id"]') ||
               document.querySelector('select[id*="itilcategories_id"]') ||
               document.querySelector('[id*="itilcategories"] select');
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
            + '&item_id='     + encodeURIComponent(ITEM_ID)
            + '&is_existing=' + encodeURIComponent(IS_EXISTING ? '1' : '0');

        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                container.innerHTML = html;
                container.style.display = html.trim() ? '' : 'none';
                container.querySelectorAll('script').forEach(function(oldScript) {
                    var s = document.createElement('script');
                    s.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(s, oldScript);
                });
            })
            .catch(function(e) { console.error('[DynamicFields] Erro ao carregar campos:', e); });
    }

    function bindMandatoryValidation() {
        var form = container.closest('form');
        if (!form || form._dfValidationBound) return;
        form._dfValidationBound = true;
        form.addEventListener('submit', function(e) {
            var missing = [];
            container.querySelectorAll('[required]').forEach(function(input) {
                if (input.disabled) return;
                var val = input.type === 'checkbox' ? input.checked : input.value.trim();
                if (!val) { missing.push(input); input.classList.add('cf-invalid'); }
                else { input.classList.remove('cf-invalid'); }
            });
            if (missing.length > 0) {
                e.preventDefault();
                missing[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                var existing = container.querySelector('.cf-validation-msg');
                if (existing) existing.remove();
                var msg = document.createElement('div');
                msg.className = 'cf-validation-msg';
                msg.style.cssText = 'padding:8px 12px;margin-bottom:12px;background:#f8d7da;border:1px solid #f5c2c7;border-radius:6px;color:#842029;font-size:14px;';
                msg.textContent = 'Preencha todos os campos obrigatórios antes de enviar.';
                container.insertBefore(msg, container.firstChild);
                setTimeout(function() { if (msg.parentNode) msg.remove(); }, 5000);
            }
        });
    }

    function attachCategoryListener() {
        var sel = findCategorySelect();
        if (!sel) { setTimeout(attachCategoryListener, 500); return; }

        sel.addEventListener('change', function() { loadFields(this.value); });

        if (typeof jQuery !== 'undefined') {
            try {
                jQuery(sel).on('select2:select select2:clear', function(e) {
                    loadFields(e.params && e.params.data ? e.params.data.id : '');
                });
            } catch(ex) {}
        }

        if (sel.value && sel.value !== '0' && container.innerHTML.trim() === '') {
            loadFields(sel.value);
        }
        bindMandatoryValidation();
    }

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
     * Renderiza os campos — suporta modo somente leitura para chamados existentes.
     */
    public static function renderFields(array $fields, array $saved, string $url_subs, bool $is_existing = false): void
    {
        // Pre-load all subunidades map for client-side filtering (melhoria 6)
        $all_subs_map = [];
        foreach ($fields as $field) {
            if ($field['name'] === 'centroacadmicofield') {
                // Load from config table
                global $DB;
                // 'centro_subunidade_map' é a chave lida por ajax/get_subunidades.php;
                // 'centro_sub_map' fica como fallback para bases antigas.
                $cfg = $DB->request([
                    'FROM'  => 'glpi_plugin_dynamicfields_config',
                    'WHERE' => ['cfg_key' => ['centro_subunidade_map', 'centro_sub_map']],
                    'ORDER' => 'cfg_key DESC',
                    'LIMIT' => 1,
                ]);
                if ($cfg->count() > 0) {
                    $all_subs_map = json_decode($cfg->current()['cfg_value'] ?? '{}', true) ?? [];
                }
                break;
            }
        }

        echo '<h3 class="cf-section-title">Campos adicionais</h3>';
        echo '<div class="cf-grid">';

        foreach ($fields as $field) {
            $fid        = (int) $field['id'];
            $value      = $saved[$fid] ?? ($field['default_value'] ?? '');
            $input_name = 'plugin_dynamicfields[' . $fid . ']';
            $required   = (bool) $field['is_mandatory'];
            $is_helpdesk = isset($_SESSION['glpiactiveprofile']['id']) && (int)$_SESSION['glpiactiveprofile']['id'] === 1;
            $readonly   = $is_existing && (bool) ($field['is_readonly_after_create'] ?? 0) && $is_helpdesk;
            $req_mark   = $required ? ' <span class="required text-danger">*</span>' : '';

            echo '<div class="cf-field">';
            echo '<label class="cf-label">' . htmlspecialchars($field['label']) . $req_mark . '</label>';

            if ($readonly) {
                // Display as text, send as hidden
                echo '<div class="cf-readonly-value">' . htmlspecialchars($value ?: '—') . '</div>';
                echo '<input type="hidden" name="' . $input_name . '" value="' . htmlspecialchars($value) . '">';
                echo '</div>';
                continue;
            }

            switch ($field['type']) {
                case 'text':
                    echo '<input type="text" name="' . $input_name . '" class="cf-input" value="' . htmlspecialchars($value) . '"' . ($required ? ' required' : '') . '>';
                    break;

                case 'textarea':
                    echo '<textarea name="' . $input_name . '" class="cf-input cf-textarea" rows="3"' . ($required ? ' required' : '') . '>' . htmlspecialchars($value) . '</textarea>';
                    break;

                case 'date':
                    echo '<input type="date" name="' . $input_name . '" class="cf-input" value="' . htmlspecialchars($value) . '"' . ($required ? ' required' : '') . '>';
                    break;

                case 'checkbox':
                    $checked = ($value == '1' || $value === 'on') ? ' checked' : '';
                    echo '<div class="cf-checkbox-wrap">';
                    echo '<input type="hidden" name="' . $input_name . '" value="0">';
                    echo '<input type="checkbox" name="' . $input_name . '" class="cf-checkbox" value="1"' . $checked . ($required ? ' required' : '') . '>';
                    echo '<span class="cf-checkbox-label">Sim</span>';
                    echo '</div>';
                    break;

                case 'number':
                    echo '<input type="number" step="1" name="' . $input_name . '" class="cf-input" value="' . htmlspecialchars($value) . '"' . ($required ? ' required' : '') . '>';
                    break;

                case 'float':
                    echo '<input type="number" step="any" name="' . $input_name . '" class="cf-input" value="' . htmlspecialchars($value) . '"' . ($required ? ' required' : '') . '>';
                    break;

                case 'email':
                    echo '<input type="email" name="' . $input_name . '" class="cf-input" value="' . htmlspecialchars($value) . '"' . ($required ? ' required' : '') . '>';
                    break;

                case 'url':
                    echo '<input type="url" name="' . $input_name . '" class="cf-input" placeholder="https://..." value="' . htmlspecialchars($value) . '"' . ($required ? ' required' : '') . '>';
                    break;

                case 'datetime':
                    echo '<input type="datetime-local" name="' . $input_name . '" class="cf-input" value="' . htmlspecialchars($value) . '"' . ($required ? ' required' : '') . '>';
                    break;

                case 'multiselect':
                    $opts          = json_decode($field['dropdown_values'] ?? '[]', true) ?? [];
                    $selected_vals = $value !== '' ? array_map('trim', explode(',', $value)) : [];
                    echo '<select name="' . $input_name . '[]" class="cf-select" multiple' . ($required ? ' required' : '') . '>';
                    foreach ($opts as $dv) {
                        $sel = in_array($dv, $selected_vals, true) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($dv) . '"' . $sel . '>' . htmlspecialchars($dv) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'description':
                    echo '<div class="cf-description-text">' . nl2br(htmlspecialchars($value)) . '</div>';
                    break;

                case 'radio':
                    $opts = json_decode($field['dropdown_values'] ?? '[]', true) ?? [];
                    echo '<div class="cf-radio-group"' . ($required ? ' data-required="1"' : '') . '>';
                    foreach ($opts as $opt) {
                        $checked = ($value === $opt) ? ' checked' : '';
                        $rid = 'cf_radio_' . $fid . '_' . md5($opt);
                        echo '<div class="form-check">';
                        echo '<input class="form-check-input" type="radio" name="' . $input_name . '" id="' . $rid . '" value="' . htmlspecialchars($opt) . '"' . $checked . ($required ? ' required' : '') . '>';
                        echo '<label class="form-check-label" for="' . $rid . '">' . htmlspecialchars($opt) . '</label>';
                        echo '</div>';
                    }
                    echo '</div>';
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
                    echo '<option value="">-- Selecione --</option>';
                    foreach ($opts as $dv) {
                        $sel = ($value === $dv) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($dv) . '"' . $sel . '>' . htmlspecialchars($dv) . '</option>';
                    }
                    echo '</select>';
                    break;

                default:
                    echo '<input type="text" name="' . $input_name . '" class="cf-input" value="' . htmlspecialchars($value) . '">';
            }

            echo '</div>'; // cf-field
        }

        echo '</div>'; // cf-grid

        // JS filtro centro→subunidade com cache client-side
        $all_subs_js = json_encode($all_subs_map, JSON_UNESCAPED_UNICODE);
        $url_subs_js = json_encode($url_subs);

        echo <<<JS
<script>
(function initCentroSub() {
    var container = document.getElementById('plugin-dynamicfields-container');
    if (!container) return;
    var centroSel = container.querySelector('[data-cf-role="centro"]');
    var subSel    = container.querySelector('[data-cf-role="subunidade"]');
    if (!centroSel || !subSel) return;

    var subsMap  = {$all_subs_js};
    var ajaxUrl  = {$url_subs_js};
    var savedSub = subSel.value;

    function filterSubs(val, restore) {
        if (!val) {
            subSel.innerHTML = '<option value="">-- Selecione --</option>';
            subSel.disabled  = true;
            return;
        }

        // Use client-side map if available
        if (subsMap && subsMap[val] && subsMap[val].length > 0) {
            subSel.innerHTML = '<option value="">-- Selecione --</option>';
            subsMap[val].forEach(function(s) {
                var o = document.createElement('option');
                o.value = s; o.textContent = s;
                if (restore && s === restore) o.selected = true;
                subSel.appendChild(o);
            });
            subSel.disabled = false;
            return;
        }

        // Fallback to AJAX
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

        echo <<<JS
<script>
(function initPaisCnpj() {
    var container = document.getElementById('plugin-dynamicfields-container');
    if (!container) return;

    function findPaisSelect() {
        return container.querySelector('select[data-cf-role="pais"]') ||
               Array.from(container.querySelectorAll('select.cf-select')).find(function(s) {
                   var label = s.closest('.cf-field') && s.closest('.cf-field').querySelector('.cf-label');
                   return label && label.textContent.trim().startsWith('País');
               });
    }

    function findCnpjInput() {
        return Array.from(container.querySelectorAll('input.cf-input')).find(function(i) {
            var label = i.closest('.cf-field') && i.closest('.cf-field').querySelector('.cf-label');
            return label && label.textContent.trim().startsWith('CNPJ');
        });
    }

    function toggleCnpj(paisVal) {
        var cnpj = findCnpjInput();
        if (!cnpj) return;

        var field = cnpj.closest('.cf-field');
        var label = field ? field.querySelector('.cf-label') : null;
        var isBrasil = (!paisVal || paisVal === 'Brasil');

        if (isBrasil) {
            cnpj.required = true;
            cnpj.disabled = false;
            cnpj.placeholder = '';
            if (field) field.style.opacity = '1';
            if (label && !label.querySelector('.required')) {
                var star = document.createElement('span');
                star.className = 'required text-danger';
                star.textContent = ' *';
                label.appendChild(star);
            }
            var warn = field ? field.querySelector('.cf-pais-warn') : null;
            if (warn) warn.remove();
        } else {
            cnpj.required = false;
            cnpj.disabled = false;
            cnpj.placeholder = 'Não aplicável para instituições estrangeiras';
            cnpj.value = '';
            if (field) field.style.opacity = '0.5';
            if (label) {
                var star2 = label.querySelector('.required');
                if (star2) star2.remove();
            }
            if (field && !field.querySelector('.cf-pais-warn')) {
                var warn2 = document.createElement('div');
                warn2.className = 'cf-pais-warn';
                warn2.style.cssText = 'font-size:11px;color:#6c757d;margin-top:3px;';
                warn2.textContent = 'CNPJ não obrigatório para instituições estrangeiras.';
                field.appendChild(warn2);
            }
        }
    }

    function init() {
        var paisSel = findPaisSelect();
        if (!paisSel) return;
        paisSel.addEventListener('change', function() { toggleCnpj(this.value); });
        toggleCnpj(paisSel.value);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 100);
    }
})();
</script>
JS;
    }

    // ── Admin list ───────────────────────────────────────────────────────────

    public static function showList(): void
    {
        global $DB;

        $canedit  = Session::haveRight('config', UPDATE);
        $iterator = $DB->request(['FROM' => self::getTable(), 'ORDER' => ['ranking ASC', 'label ASC']]);

        echo '<div class="container-fluid mt-3">';

        if ($canedit) {
            echo '<div class="d-flex gap-2 mb-3">';
            echo '<a href="' . PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.form.php" class="btn btn-primary">';
            echo '<i class="fas fa-plus me-1"></i> Adicionar campo</a>';
            echo '<a href="' . PLUGINDYNAMICFIELDS_WEB_DIR . '/front/export.php?itemtype=Ticket" class="btn btn-outline-success">';
            echo '<i class="fas fa-file-csv me-1"></i> Exportar CSV (Tickets)</a>';
            echo '</div>';
        }

        if ($iterator->count() === 0) {
            echo '<div class="alert alert-info">Nenhum campo definido. Clique em "Adicionar campo" para começar.</div>';
            echo '</div>';
            return;
        }

        $types     = self::getTypes();
        $itemtypes = self::getAvailableItemtypes();

        echo '<div class="table-responsive"><table class="table table-striped table-hover">';
        echo '<thead class="table-dark"><tr>';
        foreach (['Etiqueta', 'Nome interno', 'Tipo', 'Aplicável a', 'Categorias', 'Obrigatório', 'Somente leitura', __('Active')] as $th) {
            echo '<th>' . $th . '</th>';
        }
        if ($canedit) echo '<th>' . __('Actions') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($iterator as $row) {
            $sel_types   = json_decode($row['itemtypes'] ?? '[]', true) ?? [];
            $type_labels = implode(', ', array_map(fn($t) => $itemtypes[$t] ?? $t, $sel_types));

            $cat_iter = $DB->request([
                'SELECT'    => ['glpi_itilcategories.completename'],
                'FROM'      => 'glpi_plugin_dynamicfields_categories',
                'LEFT JOIN' => ['glpi_itilcategories' => ['FKEY' => ['glpi_plugin_dynamicfields_categories' => 'itilcategories_id', 'glpi_itilcategories' => 'id']]],
                'WHERE'     => ['plugin_dynamicfields_fields_id' => $row['id']],
            ]);
            $cat_names = [];
            foreach ($cat_iter as $c) { $cat_names[] = $c['completename']; }

            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($row['label']) . '</strong></td>';
            echo '<td><code>' . htmlspecialchars($row['name']) . '</code></td>';
            echo '<td>' . htmlspecialchars($types[$row['type']] ?? $row['type']) . '</td>';
            echo '<td>' . ($type_labels ?: '—') . '</td>';
            echo '<td>' . (empty($cat_names) ? '<span class="text-muted fst-italic">Todas as categorias</span>' : implode('<br>', array_map('htmlspecialchars', $cat_names))) . '</td>';
            echo '<td>' . ($row['is_mandatory'] ? '<span class="badge bg-warning">' . __('Yes') . '</span>' : __('No')) . '</td>';
            echo '<td>' . (($row['is_readonly_after_create'] ?? 0) ? '<span class="badge bg-info">Sim</span>' : __('No')) . '</td>';
            echo '<td>' . ($row['is_active'] ? '<span class="badge bg-success">' . __('Yes') . '</span>' : '<span class="badge bg-secondary">' . __('No') . '</span>') . '</td>';
            if ($canedit) {
                echo '<td><a href="' . PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.form.php?id=' . (int)$row['id'] . '" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a></td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></div></div>';
    }

    // ── Search options ───────────────────────────────────────────────────────

    public function rawSearchOptions(): array
    {
        $tab   = [];
        $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];
        $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'label',     'name' => __('Label'),    'datatype' => 'itemlink', 'massiveaction' => false];
        $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'name',      'name' => 'Nome interno', 'datatype' => 'string'];
        $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'type',      'name' => 'Tipo',         'datatype' => 'string'];
        $tab[] = ['id' => 4, 'table' => self::getTable(), 'field' => 'is_active', 'name' => __('Active'),   'datatype' => 'bool'];
        return $tab;
    }
}
