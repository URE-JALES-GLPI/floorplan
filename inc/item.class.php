<?php
/**
 * inc/item.class.php
 * Gerencia as posições dos ativos no mapa
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginFloorplanItem extends CommonDBTM {

    static $rightname = 'entity';

    static function getTypeName($nb = 0) {
        return _n('Item do Mapa', 'Itens do Mapa', $nb, 'floorplan');
    }

    /**
     * Salva ou atualiza a posição de um ativo no mapa
     * Retorna array ['success' => bool, 'id' => int]
     */
    static function savePosition(int $fp_id, string $itemtype, int $items_id, float $pos_x, float $pos_y): array {
        global $DB;

        // Verifica se já existe
        $existing = $DB->request([
            'FROM'  => 'glpi_plugin_floorplan_items',
            'WHERE' => [
                'plugin_floorplan_floorplans_id' => $fp_id,
                'itemtype'                        => $itemtype,
                'items_id'                       => $items_id,
            ],
        ])->current();

        if ($existing) {
            $ok = $DB->update(
                'glpi_plugin_floorplan_items',
                ['pos_x' => $pos_x, 'pos_y' => $pos_y],
                ['id' => $existing['id']]
            );
            return ['success' => (bool)$ok, 'id' => $existing['id']];
        }

        $ok = $DB->insert('glpi_plugin_floorplan_items', [
            'plugin_floorplan_floorplans_id' => $fp_id,
            'itemtype'                        => $itemtype,
            'items_id'                       => $items_id,
            'pos_x'                           => $pos_x,
            'pos_y'                           => $pos_y,
        ]);

        return ['success' => (bool)$ok, 'id' => (int)$DB->insertId()];
    }

    /**
     * Remove um ativo do mapa
     */
    static function removePosition(int $fp_id, string $itemtype, int $items_id): bool {
        global $DB;
        return (bool) $DB->delete('glpi_plugin_floorplan_items', [
            'plugin_floorplan_floorplans_id' => $fp_id,
            'itemtype'                        => $itemtype,
            'items_id'                       => $items_id,
        ]);
    }
}
