<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of DynamicFields.
 *
 * DynamicFields is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2024 by DynamicFields plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * -------------------------------------------------------------------------
 */

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_dynamicfields_install()
{
    /** @var DBmysql $DB */
    global $DB;

    $migration = new Migration(PLUGIN_DYNAMICFIELDS_VERSION);

    // Table: plugin_dynamicfields_fields
    // Stores field definitions
    if (!$DB->tableExists('glpi_plugin_dynamicfields_fields')) {
        $query = "CREATE TABLE `glpi_plugin_dynamicfields_fields` (
            `id`              INT(11)       NOT NULL AUTO_INCREMENT,
            `name`            VARCHAR(255)  NOT NULL DEFAULT '',
            `label`           VARCHAR(255)  NOT NULL DEFAULT '',
            `type`            VARCHAR(50)   NOT NULL DEFAULT 'text',
            `is_mandatory`    TINYINT(1)    NOT NULL DEFAULT 0,
            `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
            `ranking`         INT(11)       NOT NULL DEFAULT 0,
            `default_value`   TEXT          NULL,
            `dropdown_values` TEXT          NULL COMMENT 'JSON array of values for dropdown type',
            `itemtypes`       TEXT          NULL COMMENT 'JSON array of itemtypes (Ticket, Problem, Change)',
            `date_creation`   DATETIME      NULL,
            `date_mod`        DATETIME      NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->query($query);
    }

    // Table: plugin_dynamicfields_categories
    // Links fields to ITIL categories
    if (!$DB->tableExists('glpi_plugin_dynamicfields_categories')) {
        $query = "CREATE TABLE `glpi_plugin_dynamicfields_categories` (
            `id`                                INT(11) NOT NULL AUTO_INCREMENT,
            `plugin_dynamicfields_fields_id`   INT(11) NOT NULL DEFAULT 0,
            `itilcategories_id`                 INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `plugin_dynamicfields_fields_id` (`plugin_dynamicfields_fields_id`),
            KEY `itilcategories_id` (`itilcategories_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->query($query);
    }

    // Table: plugin_dynamicfields_values
    // Stores actual field values per ticket/item
    if (!$DB->tableExists('glpi_plugin_dynamicfields_values')) {
        $query = "CREATE TABLE `glpi_plugin_dynamicfields_values` (
            `id`                                INT(11)  NOT NULL AUTO_INCREMENT,
            `plugin_dynamicfields_fields_id`   INT(11)  NOT NULL DEFAULT 0,
            `items_id`                          INT(11)  NOT NULL DEFAULT 0,
            `itemtype`                          VARCHAR(100) NOT NULL DEFAULT '',
            `value`                             TEXT     NULL,
            `date_creation`                     DATETIME NULL,
            `date_mod`                          DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_dynamicfields_fields_id` (`plugin_dynamicfields_fields_id`),
            KEY `item` (`itemtype`, `items_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->query($query);
    }


    // Table: plugin_dynamicfields_config
    // Stores plugin configuration key-value pairs (including centro→subunidade map)
    if (!$DB->tableExists('glpi_plugin_dynamicfields_config')) {
        $query = "CREATE TABLE `glpi_plugin_dynamicfields_config` (
            `id`        INT(11)      NOT NULL AUTO_INCREMENT,
            `cfg_key`   VARCHAR(100) NOT NULL DEFAULT '',
            `cfg_value` LONGTEXT     NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `cfg_key` (`cfg_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->query($query);
    }

    $migration->executeMigration();

    return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_dynamicfields_uninstall()
{
    /** @var DBmysql $DB */
    global $DB;

    $tables = [
        'glpi_plugin_dynamicfields_config',
        'glpi_plugin_dynamicfields_fields',
        'glpi_plugin_dynamicfields_categories',
        'glpi_plugin_dynamicfields_values',
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->query("DROP TABLE `$table`");
        }
    }

    return true;
}
