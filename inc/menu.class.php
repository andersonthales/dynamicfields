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
 * PluginDynamicfieldsMenu - plugin menu entry
 */
class PluginDynamicfieldsMenu extends CommonGLPI
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return 'Campos Dinâmicos';
    }

    public static function getMenuName()
    {
        return 'Campos Dinâmicos';
    }

    public static function getMenuContent()
    {
        $menu = [];

        if (Session::haveRight('config', READ)) {
            $menu['title'] = self::getMenuName();
            $menu['page']  = PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.php';
            $menu['icon']  = 'fas fa-list-alt';

            $menu['options']['field']['title']           = PluginDynamicfieldsField::getTypeName(2);
            $menu['options']['field']['page']            = PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.php';
            $menu['options']['field']['links']['search'] = PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.php';
            $menu['options']['field']['links']['add']    = PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.form.php';
        }

        return $menu;
    }

    public static function getAdditionalMenuLinks()
    {
        $links = [];

        if (Session::haveRight('config', READ)) {
            $links[PluginDynamicfieldsField::getTypeName(2)] = PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.php';
        }

        return $links;
    }
}
