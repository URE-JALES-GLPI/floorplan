<?php
/**
 * hook.php — Instalação e desinstalação do plugin FloorPlan
 */

function plugin_floorplan_install(): bool {
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    if (!$DB->tableExists('glpi_plugin_floorplan_floorplans')) {
        $DB->doQuery("
            CREATE TABLE `glpi_plugin_floorplan_floorplans` (
                `id`            INT {$sign} NOT NULL AUTO_INCREMENT,
                `name`          VARCHAR(255) NOT NULL DEFAULT '',
                `entities_id`   INT {$sign} NOT NULL DEFAULT '0',
                `is_recursive`  TINYINT(1)   NOT NULL DEFAULT '0',
                `comment`       LONGTEXT     DEFAULT NULL,
                `image_path`    VARCHAR(512)          DEFAULT NULL,
                `image_type`    VARCHAR(20)           DEFAULT NULL,
                `date_creation` DATETIME     DEFAULT NULL,
                `date_mod`      DATETIME     DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `entities_id` (`entities_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die($DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_floorplan_items')) {
        $DB->doQuery("
            CREATE TABLE `glpi_plugin_floorplan_items` (
                `id`                             INT {$sign} NOT NULL AUTO_INCREMENT,
                `plugin_floorplan_floorplans_id` INT {$sign} NOT NULL DEFAULT '0',
                `itemtype`                       VARCHAR(100) NOT NULL DEFAULT '',
                `items_id`                       INT {$sign} NOT NULL DEFAULT '0',
                `pos_x`                          FLOAT        NOT NULL DEFAULT '0',
                `pos_y`                          FLOAT        NOT NULL DEFAULT '0',
                `date_mod`                       DATETIME     DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_item` (`plugin_floorplan_floorplans_id`, `itemtype`, `items_id`),
                KEY `plugin_floorplan_floorplans_id` (`plugin_floorplan_floorplans_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die($DB->error());
    }

    // Tabela 3: tipos de ativo permitidos por perfil
    if (!$DB->tableExists('glpi_plugin_floorplan_profile_itemtypes')) {
        $DB->doQuery("
            CREATE TABLE `glpi_plugin_floorplan_profile_itemtypes` (
                `id`           INT {$sign} NOT NULL AUTO_INCREMENT,
                `profiles_id`  INT {$sign} NOT NULL DEFAULT '0',
                `itemtype`     VARCHAR(100) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_profile_itemtype` (`profiles_id`, `itemtype`),
                KEY `profiles_id` (`profiles_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die($DB->error());
    }

    PluginFloorplanProfile::install();
    return true;
}

function plugin_floorplan_uninstall(): bool {
    global $DB;

    PluginFloorplanProfile::uninstall();

    foreach ([
        'glpi_plugin_floorplan_items',
        'glpi_plugin_floorplan_profile_itemtypes',
        'glpi_plugin_floorplan_floorplans',
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `{$table}`");
        }
    }

    $upload_dir = GLPI_PLUGIN_DOC_DIR . '/floorplan/images/';
    if (is_dir($upload_dir)) {
        array_map('unlink', glob($upload_dir . '*'));
        @rmdir($upload_dir);
    }

    return true;
}
