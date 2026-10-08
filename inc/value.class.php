<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin for GLPI
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginDynamicfieldsValue extends CommonDBTM
{
    public static $rightname = 'ticket';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_dynamicfields_values';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Field value', 'Field values', $nb, 'dynamicfields');
    }

    public static function afterAdd(CommonDBTM $item)
    {
        self::saveValues($item);
    }

    public static function afterUpdate(CommonDBTM $item)
    {
        self::saveValues($item);
    }

    public static function beforePurge(CommonDBTM $item)
    {
        global $DB;
        $DB->delete(self::getTable(), [
            'items_id' => $item->fields['id'],
            'itemtype' => $item->getType(),
        ]);
    }

    private static function saveValues(CommonDBTM $item)
    {
        global $DB;

        if (empty($_POST['plugin_dynamicfields']) || !is_array($_POST['plugin_dynamicfields'])) {
            return;
        }

        $items_id = $item->fields['id'];
        $itemtype = $item->getType();
        $is_new   = !isset($item->fields['date_creation']) || $item->fields['date_creation'] === $item->fields['date_mod'];

        // Load field definitions for readonly check
        $field_defs = [];
        $fids = array_keys($_POST['plugin_dynamicfields']);
        if (!empty($fids)) {
            $iter = $DB->request(['FROM' => 'glpi_plugin_dynamicfields_fields', 'WHERE' => ['id' => $fids]]);
            foreach ($iter as $f) {
                $field_defs[$f['id']] = $f;
            }
        }

        foreach ($_POST['plugin_dynamicfields'] as $field_id => $value) {
            $field_id = (int) $field_id;
            if ($field_id <= 0) continue;

            // Backend validation: skip readonly fields on update
            if (!$is_new && isset($field_defs[$field_id]) && $field_defs[$field_id]['is_readonly_after_create']) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $value = trim((string) $value);

            // Backend validation: mandatory fields
            if (isset($field_defs[$field_id]) && $field_defs[$field_id]['is_mandatory'] && $value === '') {
                Session::addMessageAfterRedirect(
                    sprintf('Campo obrigatório não preenchido: %s', $field_defs[$field_id]['label']),
                    true,
                    ERROR
                );
                continue;
            }

            // Backend validation: formato por tipo (não confiar só na validação HTML5 do navegador)
            if ($value !== '' && isset($field_defs[$field_id])) {
                $ftype   = $field_defs[$field_id]['type'];
                $invalid = match (true) {
                    $ftype === 'email'  => !filter_var($value, FILTER_VALIDATE_EMAIL),
                    $ftype === 'url'    => !filter_var($value, FILTER_VALIDATE_URL),
                    $ftype === 'number' => !preg_match('/^-?\d+$/', $value),
                    $ftype === 'float'  => !is_numeric($value),
                    default             => false,
                };
                if ($invalid) {
                    Session::addMessageAfterRedirect(
                        sprintf('Valor inválido para o campo: %s', $field_defs[$field_id]['label']),
                        true,
                        ERROR
                    );
                    continue;
                }
            }

            $existing = $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => [
                    'plugin_dynamicfields_fields_id' => $field_id,
                    'items_id'                        => $items_id,
                    'itemtype'                        => $itemtype,
                ],
            ]);

            if ($existing->count() > 0) {
                $existing_row = $existing->current();
                $DB->update(self::getTable(), [
                    'value'    => $value,
                    'date_mod' => $_SESSION['glpi_currenttime'],
                ], ['id' => $existing_row['id']]);
            } else {
                $DB->insert(self::getTable(), [
                    'plugin_dynamicfields_fields_id' => $field_id,
                    'items_id'                        => $items_id,
                    'itemtype'                        => $itemtype,
                    'value'                           => $value,
                    'date_creation'                   => $_SESSION['glpi_currenttime'],
                    'date_mod'                        => $_SESSION['glpi_currenttime'],
                ]);
            }
        }
    }

    public static function getValuesForItem($items_id, $itemtype)
    {
        global $DB;

        $values   = [];
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['items_id' => $items_id, 'itemtype' => $itemtype],
        ]);

        foreach ($iterator as $row) {
            $values[$row['plugin_dynamicfields_fields_id']] = $row['value'];
        }

        return $values;
    }

    /**
     * Export all values for a given itemtype as CSV.
     */
    public static function exportCSV(string $itemtype): void
    {
        global $DB;

        // Get field definitions
        $fields_iter = $DB->request([
            'FROM'  => 'glpi_plugin_dynamicfields_fields',
            'WHERE' => [
                'is_active' => 1,
                ['itemtypes' => ['LIKE', '%"' . $DB->escape($itemtype) . '"%']],
            ],
            'ORDER' => ['ranking ASC', 'id ASC'],
        ]);

        $fields = [];
        foreach ($fields_iter as $f) {
            $fields[$f['id']] = $f;
        }

        if (empty($fields)) {
            echo 'Nenhum campo encontrado.';
            return;
        }

        // Get all items with values
        $items_iter = $DB->request([
            'SELECT'   => ['items_id'],
            'FROM'     => self::getTable(),
            'WHERE'    => ['itemtype' => $itemtype],
            'GROUPBY'  => ['items_id'],
            'ORDER'    => ['items_id ASC'],
        ]);

        $filename = 'dynamicfields_' . strtolower($itemtype) . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

        // Header row
        $headers = ['ID do Chamado', 'Título'];
        foreach ($fields as $f) {
            $headers[] = $f['label'];
        }
        fputcsv($out, $headers, ';');

        foreach ($items_iter as $row) {
            $items_id = $row['items_id'];
            $saved    = self::getValuesForItem($items_id, $itemtype);

            // Get item title
            $title = '';
            $item_iter = $DB->request(['SELECT' => ['name'], 'FROM' => strtolower($itemtype) . 's', 'WHERE' => ['id' => $items_id]]);
            if ($item_iter->count() > 0) {
                $title = $item_iter->current()['name'] ?? '';
            }

            $line = [$items_id, $title];
            foreach ($fields as $fid => $f) {
                $line[] = $saved[$fid] ?? '';
            }
            fputcsv($out, $line, ';');
        }

        fclose($out);
        exit;
    }
}
