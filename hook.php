<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin for GLPI
 * -------------------------------------------------------------------------
 * LICENSE: GPLv2+
 */

function plugin_dynamicfields_install()
{
    global $DB;

    $migration = new Migration(PLUGIN_DYNAMICFIELDS_VERSION);

    if (!$DB->tableExists('glpi_plugin_dynamicfields_fields')) {
        $query = "CREATE TABLE `glpi_plugin_dynamicfields_fields` (
            `id`                        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `name`                      VARCHAR(255)  NOT NULL DEFAULT '',
            `label`                     VARCHAR(255)  NOT NULL DEFAULT '',
            `type`                      VARCHAR(50)   NOT NULL DEFAULT 'text',
            `is_mandatory`              TINYINT(1)    NOT NULL DEFAULT 0,
            `is_active`                 TINYINT(1)    NOT NULL DEFAULT 1,
            `is_readonly_after_create`  TINYINT(1)    NOT NULL DEFAULT 0,
            `ranking`                   INT UNSIGNED  NOT NULL DEFAULT 0,
            `default_value`             TEXT          NULL,
            `dropdown_values`           TEXT          NULL COMMENT 'JSON array of values for dropdown type',
            `itemtypes`                 TEXT          NULL COMMENT 'JSON array of itemtypes (Ticket, Problem, Change)',
            `date_creation`             TIMESTAMP     NULL,
            `date_mod`                  TIMESTAMP     NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->query($query);
    } else {
        // Migration: add is_readonly_after_create if missing
        $cols = $DB->request(['SELECT' => ['COLUMN_NAME'], 'FROM' => 'information_schema.COLUMNS',
            'WHERE' => ['TABLE_SCHEMA' => $DB->dbdefault, 'TABLE_NAME' => 'glpi_plugin_dynamicfields_fields', 'COLUMN_NAME' => 'is_readonly_after_create']]);
        if ($cols->count() === 0) {
            $DB->query("ALTER TABLE `glpi_plugin_dynamicfields_fields` ADD COLUMN `is_readonly_after_create` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_mandatory`");
        }
    }

    if (!$DB->tableExists('glpi_plugin_dynamicfields_categories')) {
        $query = "CREATE TABLE `glpi_plugin_dynamicfields_categories` (
            `id`                                INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `plugin_dynamicfields_fields_id`    INT UNSIGNED NOT NULL DEFAULT 0,
            `itilcategories_id`                 INT UNSIGNED NOT NULL DEFAULT 0,
            `is_mandatory`                      TINYINT(1) NULL DEFAULT NULL COMMENT 'NULL = herda do field',
            PRIMARY KEY (`id`),
            KEY `plugin_dynamicfields_fields_id` (`plugin_dynamicfields_fields_id`),
            KEY `itilcategories_id` (`itilcategories_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->query($query);
    } else {
        $cols2 = $DB->request(['SELECT' => ['COLUMN_NAME'], 'FROM' => 'information_schema.COLUMNS',
            'WHERE' => ['TABLE_SCHEMA' => $DB->dbdefault, 'TABLE_NAME' => 'glpi_plugin_dynamicfields_categories', 'COLUMN_NAME' => 'is_mandatory']]);
        if ($cols2->count() === 0) {
            $DB->query("ALTER TABLE `glpi_plugin_dynamicfields_categories` ADD COLUMN `is_mandatory` TINYINT(1) NULL DEFAULT NULL COMMENT 'NULL = herda do field'");
            $DB->query("UPDATE `glpi_plugin_dynamicfields_categories` c JOIN `glpi_plugin_dynamicfields_fields` f ON f.id = c.plugin_dynamicfields_fields_id SET c.is_mandatory = f.is_mandatory");
        }
    }

    if (!$DB->tableExists('glpi_plugin_dynamicfields_values')) {
        $query = "CREATE TABLE `glpi_plugin_dynamicfields_values` (
            `id`                                INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `plugin_dynamicfields_fields_id`    INT UNSIGNED NOT NULL DEFAULT 0,
            `items_id`                          INT UNSIGNED NOT NULL DEFAULT 0,
            `itemtype`                          VARCHAR(100) NOT NULL DEFAULT '',
            `value`                             TEXT         NULL,
            `date_creation`                     TIMESTAMP    NULL,
            `date_mod`                          TIMESTAMP    NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_dynamicfields_fields_id` (`plugin_dynamicfields_fields_id`),
            KEY `item` (`itemtype`, `items_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->query($query);
    }

    if (!$DB->tableExists('glpi_plugin_dynamicfields_config')) {
        $query = "CREATE TABLE `glpi_plugin_dynamicfields_config` (
            `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
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

function plugin_dynamicfields_uninstall()
{
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
