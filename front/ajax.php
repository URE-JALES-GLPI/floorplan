<?php
/**
 * front/ajax.php — Endpoint AJAX FloorPlan
 */

include('../../../inc/includes.php');

@ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!Session::getLoginUserID()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

Session::checkRight('plugin_floorplan', READ);

global $DB;
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        case 'save_positions':
            $input     = json_decode(file_get_contents('php://input'), true) ?? [];
            $fp_id     = (int)($input['floorplan_id'] ?? 0);
            $positions = $input['positions'] ?? [];
            if (!$fp_id) throw new RuntimeException('floorplan_id inválido');

            $DB->delete('glpi_plugin_floorplan_items', ['plugin_floorplan_floorplans_id' => $fp_id]);
            foreach ($positions as $pos) {
                $itemtype = (string)($pos['itemtype'] ?? '');
                $items_id = (int)($pos['items_id'] ?? 0);
                if (!$itemtype || !$items_id) continue;
                $DB->insert('glpi_plugin_floorplan_items', [
                    'plugin_floorplan_floorplans_id' => $fp_id,
                    'itemtype'  => $itemtype,
                    'items_id'  => $items_id,
                    'pos_x'     => (float)($pos['pos_x'] ?? 0),
                    'pos_y'     => (float)($pos['pos_y'] ?? 0),
                    'date_mod'  => date('Y-m-d H:i:s'),
                ]);
            }
            echo json_encode(['success' => true, 'saved' => count($positions)]);
            break;

        case 'remove_item':
            $input    = json_decode(file_get_contents('php://input'), true) ?? [];
            $fp_id    = (int)($input['floorplan_id'] ?? 0);
            $itemtype = (string)($input['itemtype'] ?? '');
            $items_id = (int)($input['items_id'] ?? 0);
            $ok = $DB->delete('glpi_plugin_floorplan_items', [
                'plugin_floorplan_floorplans_id' => $fp_id,
                'itemtype' => $itemtype,
                'items_id' => $items_id,
            ]);
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'get_positions':
            $fp_id = (int)($_GET['floorplan_id'] ?? 0);
            if (!$fp_id) throw new RuntimeException('floorplan_id inválido');
            $rows = [];
            foreach ($DB->request([
                'FROM'  => 'glpi_plugin_floorplan_items',
                'WHERE' => ['plugin_floorplan_floorplans_id' => $fp_id],
            ]) as $row) {
                $rows[] = $row;
            }
            echo json_encode(['success' => true, 'positions' => $rows]);
            break;

        // ── Tooltip: detalhes do ativo ──────────────────────────────
        case 'get_asset_details':
            $itemtype = (string)($_GET['itemtype'] ?? '');
            $items_id = (int)($_GET['items_id'] ?? 0);
            if (!$itemtype || !$items_id) throw new RuntimeException('Parâmetros inválidos');

            $details = ['name' => '', 'location' => '', 'status' => '', 'user' => '', 'serial' => '', 'link' => ''];

            // Custom Asset
            if (strpos($itemtype, 'CustomAsset') !== false) {
                // Busca via SQL direto para garantir aliases corretos
                $row = $DB->request([
                    'SELECT' => [
                        'glpi_assets_assets.id',
                        'glpi_assets_assets.name',
                        'glpi_assets_assets.serial',
                        'glpi_assets_assets.otherserial',
                        'glpi_entities.completename AS entity_name',
                        'glpi_states.name AS state_name',
                    ],
                    'FROM'      => 'glpi_assets_assets',
                    'LEFT JOIN' => [
                        'glpi_entities' => ['FKEY' => ['glpi_assets_assets' => 'entities_id', 'glpi_entities' => 'id']],
                        'glpi_states'   => ['FKEY' => ['glpi_assets_assets' => 'states_id',   'glpi_states'   => 'id']],
                    ],
                    'WHERE' => ['glpi_assets_assets.id' => $items_id],
                ])->current();

                if ($row) {
                    $details['name']      = $row['name'] ?? '';
                    $details['entity']    = $row['entity_name'] ?? '—';
                    $details['status']    = $row['state_name'] ?? '—';
                    $details['inventory'] = !empty($row['otherserial']) ? $row['otherserial'] : '—';
                    $details['serial']    = $row['serial'] ?? '—';
                }
            } elseif (class_exists($itemtype)) {
                // Asset nativo
                $table = getTableForItemType($itemtype);
                $row = $DB->request([
                    'SELECT' => [
                        "$table.id",
                        "$table.name",
                        "$table.serial",
                        "$table.otherserial",
                        'glpi_entities.completename AS entity_name',
                        'glpi_states.name AS state_name',
                    ],
                    'FROM'      => $table,
                    'LEFT JOIN' => [
                        'glpi_entities' => ['FKEY' => [$table => 'entities_id', 'glpi_entities' => 'id']],
                        'glpi_states'   => ['FKEY' => [$table => 'states_id',   'glpi_states'   => 'id']],
                    ],
                    'WHERE' => ["$table.id" => $items_id],
                ])->current();

                if ($row) {
                    $details['name']      = $row['name'] ?? '';
                    $details['entity']    = $row['entity_name'] ?? '—';
                    $details['status']    = $row['state_name'] ?? '—';
                    $details['inventory'] = !empty($row['otherserial']) ? $row['otherserial'] : '—';
                    $details['serial']    = $row['serial'] ?? '—';
                }
            }

            // Link para o ativo no GLPI
            global $CFG_GLPI;
            if (strpos($itemtype, 'CustomAsset') !== false) {
                $system_name = basename(str_replace('\\', '/', $itemtype));
                $details['link'] = $CFG_GLPI['root_doc'] . '/front/asset/asset.php?itemtype=' . urlencode($itemtype) . '&id=' . $items_id;
            } elseif (class_exists($itemtype)) {
                $obj = new $itemtype();
                $details['link'] = $CFG_GLPI['root_doc'] . '/' . $obj->getFormURL(false) . '?id=' . $items_id;
            }

            echo json_encode(['success' => true, 'details' => $details]);
            break;

        default:
            throw new RuntimeException('Ação desconhecida');
    }

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
