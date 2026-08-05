<?php
/**
 * front/floorplan.php — Listagem de plantas baixas
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_floorplan', READ);

Html::header(
    'Plantas Baixas',
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginFloorplanFloorplan'
);

$canedit = Session::haveRight('plugin_floorplan', UPDATE);

// Pega entidade ativa e filhas
$entities = $_SESSION['glpiactiveentities'] ?? [0];

global $DB;
$iterator = $DB->request([
    'FROM'  => 'glpi_plugin_floorplan_floorplans',
    'WHERE' => ['entities_id' => $entities],
    'ORDER' => 'name ASC',
]);

echo "<div class='container-fluid mt-3'>";
echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
echo "<h2><i class='ti ti-map me-2'></i>Plantas Baixas</h2>";

if ($canedit) {
    echo "<a href='floorplan.form.php' class='btn btn-primary'>
            <i class='ti ti-plus'></i> Nova Planta
          </a>";
}
echo "</div>";

if (count($iterator) === 0) {
    echo "<div class='alert alert-info'>Nenhuma planta cadastrada. Clique em <strong>Nova Planta</strong> para começar.</div>";
} else {
    echo "<div class='row row-cols-1 row-cols-md-3 g-3'>";
    foreach ($iterator as $row) {
        $edit_url = 'floorplan.form.php?id=' . (int)$row['id'];
        $img_url  = Plugin::getWebDir('floorplan') . '/front/image.php?file=' . urlencode($row['image_path'] ?? '');

        // Conta ativos no mapa
        $count = countElementsInTable('glpi_plugin_floorplan_items', [
            'plugin_floorplan_floorplans_id' => $row['id'],
        ]);

        echo "<div class='col'>
                <div class='card h-100 shadow-sm'>
                    <div style='height:160px;overflow:hidden;background:#1a1d23;display:flex;align-items:center;justify-content:center;'>";

        if (!empty($row['image_path'])) {
            echo "<img src='{$img_url}' style='width:100%;height:160px;object-fit:cover;opacity:.85;' alt=''>";
        } else {
            echo "<i class='ti ti-map-2' style='font-size:3rem;color:#444;'></i>";
        }

        echo "      </div>
                    <div class='card-body'>
                        <h5 class='card-title'>" . htmlspecialchars($row['name']) . "</h5>";

        if (!empty($row['comment'])) {
            echo "<p class='card-text text-muted small'>" . htmlspecialchars($row['comment']) . "</p>";
        }

        echo "          <p class='card-text'><small class='text-muted'><i class='ti ti-box'></i> {$count} ativo(s) no mapa</small></p>
                    </div>
                    <div class='card-footer d-flex gap-2'>
                        <a href='{$edit_url}' class='btn btn-sm btn-primary flex-fill'>
                            <i class='ti ti-map-pin'></i> Abrir Mapa
                        </a>";

        if ($canedit) {
            echo "      <a href='{$edit_url}' class='btn btn-sm btn-outline-secondary'>
                            <i class='ti ti-pencil'></i>
                        </a>";
        }

        echo "      </div>
                </div>
              </div>";
    }
    echo "</div>";
}

echo "</div>";

Html::footer();
