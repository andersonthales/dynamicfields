<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin for GLPI — Field list page
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('config', READ);

// Force-load classes
include_once(PLUGINDYNAMICFIELDS_DIR . '/inc/field.class.php');
include_once(PLUGINDYNAMICFIELDS_DIR . '/inc/category.class.php');
include_once(PLUGINDYNAMICFIELDS_DIR . '/inc/value.class.php');
include_once(PLUGINDYNAMICFIELDS_DIR . '/inc/menu.class.php');

Html::header(
    PluginDynamicfieldsField::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'config',
    'PluginDynamicfieldsMenu',
    'field'
);

PluginDynamicfieldsField::showList();

Html::footer();
