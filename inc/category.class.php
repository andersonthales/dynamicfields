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
}
