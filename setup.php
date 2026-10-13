<?php
require_once __DIR__ . '/inc/compat.php';

define('PLUGIN_FLOORPLAN_VERSION', '1.2.0');
define('PLUGIN_FLOORPLAN_MIN_GLPI', '11.0.0');
define('PLUGIN_FLOORPLAN_MAX_GLPI', '12.0.99');

function plugin_init_floorplan() {
    global $PLUGIN_HOOKS, $CFG_GLPI;

    // CSRF compliance - no GLPI 12 o hook foi removido (CheckCsrfListener cuida);
    // mantém true apenas no GLPI <12 (mesmo esquema do kanpro/protocolo).
    if (!fp_is_glpi12_plus()) {
        $PLUGIN_HOOKS['csrf_compliant']['floorplan'] = true;
    }

    Plugin::registerClass('PluginFloorplanProfile', ['addtabon' => 'Profile']);

    if (Session::haveRight('plugin_floorplan', READ)) {
        $PLUGIN_HOOKS['menu_toadd']['floorplan'] = ['tools' => 'PluginFloorplanFloorplan'];
    }

    // Aponta para pasta public/ igual outros plugins GLPI 11
    $PLUGIN_HOOKS['add_css']['floorplan']        = ['public/css/floorplan.css'];
    $PLUGIN_HOOKS['add_javascript']['floorplan'] = ['public/js/floorplan.js'];
}

function plugin_version_floorplan() {
    return [
        'name'         => '[URE] FloorPlan',
        'version'      => PLUGIN_FLOORPLAN_VERSION,
        'author'       => 'Você',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => ['glpi' => ['min' => PLUGIN_FLOORPLAN_MIN_GLPI, 'max' => PLUGIN_FLOORPLAN_MAX_GLPI]],
    ];
}

function plugin_floorplan_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_FLOORPLAN_MIN_GLPI, 'lt')) {
        echo 'Este plugin requer GLPI >= ' . PLUGIN_FLOORPLAN_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_floorplan_check_config() {
    return true;
}
