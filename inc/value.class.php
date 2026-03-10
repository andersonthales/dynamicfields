<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin for GLPI
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * PluginDynamicfieldsValue - stores field values per item
 */
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

    /**
     * Hook called after a ticket/item is added.
     *
     * @param CommonDBTM $item
     */
    public static function afterAdd(CommonDBTM $item)
    {
        self::saveValues($item);
    }

    /**
     * Hook called after a ticket/item is updated.
     *
     * @param CommonDBTM $item
     */
    public static function afterUpdate(CommonDBTM $item)
    {
        self::saveValues($item);
    }

    /**
     * Hook called before a ticket/item is purged.
     *
     * @param CommonDBTM $item
     */
    public static function beforePurge(CommonDBTM $item)
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->delete(self::getTable(), [
            'items_id' => $item->fields['id'],
            'itemtype' => $item->getType(),
        ]);
    }

    /**
     * Save posted plugin_dynamicfields values.
     *
     * @param CommonDBTM $item
     */
    private static function saveValues(CommonDBTM $item)
    {
        /** @var DBmysql $DB */
        global $DB;

        if (empty($_POST['plugin_dynamicfields']) || !is_array($_POST['plugin_dynamicfields'])) {
            return;
        }

        $items_id = $item->fields['id'];
        $itemtype = $item->getType();

        foreach ($_POST['plugin_dynamicfields'] as $field_id => $value) {
            $field_id = (int) $field_id;
            if ($field_id <= 0) {
                continue;
            }

            // Sanitize value
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $value = trim((string) $value);

            // Check if a value already exists
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

    /**
     * Get all saved values for an item as field_id => value map.
     *
     * @param int    $items_id
     * @param string $itemtype
     * @return array
     */
    public static function getValuesForItem($items_id, $itemtype)
    {
        /** @var DBmysql $DB */
        global $DB;

        $values = [];
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'items_id' => $items_id,
                'itemtype' => $itemtype,
            ],
        ]);

        foreach ($iterator as $row) {
            $values[$row['plugin_dynamicfields_fields_id']] = $row['value'];
        }

        return $values;
    }
}
