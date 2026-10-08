<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin for GLPI
 * -------------------------------------------------------------------------
 * LICENSE: GPLv2+
 * -------------------------------------------------------------------------
 */

define('PLUGIN_DYNAMICFIELDS_VERSION', '1.1.0');
define('PLUGIN_DYNAMICFIELDS_MIN_GLPI', '10.0.0');
define('PLUGIN_DYNAMICFIELDS_MAX_GLPI', '10.0.99');

if (!defined('PLUGINDYNAMICFIELDS_DIR')) {
    define('PLUGINDYNAMICFIELDS_DIR', Plugin::getPhpDir('dynamicfields'));
}
if (!defined('PLUGINDYNAMICFIELDS_WEB_DIR')) {
    define('PLUGINDYNAMICFIELDS_WEB_DIR', Plugin::getWebDir('dynamicfields'));
}

/**
 * Init hooks of the plugin.
 * GLPI automatically loads locale/{lang}.mo when the plugin is active.
 */
function plugin_init_dynamicfields()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['dynamicfields'] = true;

    // Carregar tradução do plugin explicitamente
    Plugin::loadLang('dynamicfields');

    // -----------------------------------------------------------------------
    // Autoload: PluginDynamicfieldsXxx  →  inc/xxx.class.php
    // -----------------------------------------------------------------------
    spl_autoload_register(function ($classname) {
        if (strpos($classname, 'PluginDynamicfields') !== 0) {
            return;
        }
        $short = strtolower(substr($classname, strlen('PluginDynamicfields')));
        $file  = PLUGINDYNAMICFIELDS_DIR . '/inc/' . $short . '.class.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });

    if ((Session::getLoginUserID() || isCommandLine()) && Plugin::isPluginActive('dynamicfields')) {
        $PLUGIN_HOOKS['add_css']['dynamicfields'][] = 'css/dynamicfields.css';
        $PLUGIN_HOOKS['add_javascript']['dynamicfields'][] = 'js/load_css.js';

        if (isset($_SESSION['glpiactiveentities'])) {
            $PLUGIN_HOOKS['config_page']['dynamicfields'] = 'front/field.php';
            $PLUGIN_HOOKS['menu_toadd']['dynamicfields']  = ['config' => 'PluginDynamicfieldsMenu'];

            // Inject custom fields into ITIL forms
            $PLUGIN_HOOKS['post_item_form']['dynamicfields'] = [
                'PluginDynamicfieldsField',
                'showForTicket',
            ];

            // Persist / clean values on item lifecycle
            foreach (['Ticket', 'Problem', 'Change'] as $itiltype) {
                $PLUGIN_HOOKS['item_add']['dynamicfields'][$itiltype]       = ['PluginDynamicfieldsValue', 'afterAdd'];
                $PLUGIN_HOOKS['item_update']['dynamicfields'][$itiltype]    = ['PluginDynamicfieldsValue', 'afterUpdate'];
                $PLUGIN_HOOKS['pre_item_purge']['dynamicfields'][$itiltype] = ['PluginDynamicfieldsValue', 'beforePurge'];
            }
        }
    }
}

function plugin_version_dynamicfields()
{
    return [
        'name'         => 'Campos Dinâmicos',
        'version'      => PLUGIN_DYNAMICFIELDS_VERSION,
        'author'       => 'Anderson Thales',
        'homepage'     => 'https://github.com/andersonthales/dynamicfields',
        'license'      => 'GPLv2+',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_DYNAMICFIELDS_MIN_GLPI,
                'max' => PLUGIN_DYNAMICFIELDS_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_dynamicfields_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, PLUGIN_DYNAMICFIELDS_MIN_GLPI, 'lt')) {
        echo sprintf('This plugin requires GLPI >= %s', PLUGIN_DYNAMICFIELDS_MIN_GLPI);
        return false;
    }
    return true;
}

function plugin_dynamicfields_check_config($verbose = false)
{
    return true;
}
