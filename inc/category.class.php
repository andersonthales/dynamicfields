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
 * PluginDynamicfieldsCategory - links fields to ITIL categories
 */
class PluginDynamicfieldsCategory extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_dynamicfields_categories';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Category link', 'Category links', $nb, 'dynamicfields');
    }

    /**
     * Define is_mandatory de um campo para uma categoria especifica.
     * @param int $field_id
     * @param int $category_id
     * @param int|null $mandatory NULL=herda do field, 0=opcional, 1=obrigatorio
     */
    public static function setMandatory(int $field_id, int $category_id, ?int $mandatory): bool
    {
        global $DB;

        $row = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'plugin_dynamicfields_fields_id' => $field_id,
                'itilcategories_id'               => $category_id,
            ],
            'LIMIT' => 1,
        ]);

        if ($row->count() === 0) return false;

        $link = $row->current();
        return $DB->update(self::getTable(), ['is_mandatory' => $mandatory], ['id' => $link['id']]);
    }
}
