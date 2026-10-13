<?php
/**
 * front/floorplan.view.php — Visualização pública da planta (somente leitura)
 */

include('../../../inc/includes.php');
require_once __DIR__ . '/../inc/compat.php';

Session::checkRight('plugin_floorplan', READ);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    Html::redirect(fp_web_dir('floorplan') . '/front/floorplan.php');
}

$fp = new PluginFloorplanFloorplan();
if (!$fp->getFromDB($id)) {
    Html::redirect(fp_web_dir('floorplan') . '/front/floorplan.php');
}

global $CFG_GLPI;

Html::header(
    htmlspecialchars($fp->fields['name']),
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginFloorplanFloorplan'
);

$img_url = fp_web_dir('floorplan') . '/front/image.php?file=' . urlencode($fp->fields['image_path'] ?? '');
?>
<div class="container-fluid mt-3 mb-5">
    <div class="d-flex align-items-center gap-3 mb-3">
        <a href="floorplan.php" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left"></i> Voltar
        </a>
        <h2 class="mb-0">
            <i class="ti ti-map me-2 text-primary"></i>
            <?= htmlspecialchars($fp->fields['name']) ?>
        </h2>
        <?php if (!empty($fp->fields['comment'])): ?>
            <span class="text-muted small"><?= htmlspecialchars($fp->fields['comment']) ?></span>
        <?php endif; ?>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="fpMap.zoom(0.15)" title="Zoom +">
                <i class="ti ti-zoom-in"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="fpMap.zoom(-0.15)" title="Zoom -">
                <i class="ti ti-zoom-out"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="fpMap.resetZoom()" title="Resetar zoom">
                <i class="ti ti-maximize"></i>
            </button>
            <button class="btn btn-sm btn-success" onclick="fpMap.exportImage()" title="Exportar como imagem">
                <i class="ti ti-download"></i> Exportar
            </button>
            <?php if (Session::haveRight('plugin_floorplan', UPDATE)): ?>
            <a href="floorplan.form.php?id=<?= $id ?>" class="btn btn-sm btn-primary">
                <i class="ti ti-pencil"></i> Editar
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mapa somente leitura -->
    <div class="card shadow-sm">
        <div class="card-body p-0" style="background:#0d1117;border-radius:8px;overflow:auto;min-height:500px;position:relative;cursor:default" id="fp-map-container">
            <div id="fp-map-canvas" style="position:relative;display:inline-block;min-width:100%;transform-origin:top left">
                <img src="<?= $img_url ?>" id="fp-bg-image" alt="Planta Baixa"
                     style="display:block;max-width:100%;user-select:none" draggable="false">
                <div id="fp-pins-layer" style="position:absolute;inset:0;pointer-events:none"></div>
            </div>
        </div>
    </div>

    <!-- Legenda -->
    <div id="fp-legend" class="mt-3" style="display:none">
        <div class="card">
            <div class="card-header"><i class="ti ti-list me-1"></i> Legenda dos ativos</div>
            <div class="card-body" id="fp-legend-body" style="display:flex;flex-wrap:wrap;gap:8px"></div>
        </div>
    </div>
</div>

<!-- Tooltip -->
<div id="fp-tooltip" class="fp-tooltip" style="display:none"></div>

<script>
window.FP_CONFIG = {
    floorplan_id : <?= $id ?>,
    ajax_url     : "<?= fp_web_dir('floorplan') ?>/front/ajax.php",
    saved        : <?= json_encode([], JSON_HEX_TAG) ?>,
    available    : <?= json_encode([], JSON_HEX_TAG) ?>,
    itemtypes    : {},
    readonly     : true,
    view_mode    : true,
};
window.FP_EXPORT_NAME = "<?= htmlspecialchars($fp->fields['name']) ?>";
</script>
<?php
// Carrega posições salvas e detalhes básicos
global $DB;
$saved = [];
foreach ($DB->request([
    'FROM'  => 'glpi_plugin_floorplan_items',
    'WHERE' => ['plugin_floorplan_floorplans_id' => $id],
]) as $row) {
    $saved[] = $row;
}
echo '<script>window.FP_CONFIG.saved = ' . json_encode($saved, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';</script>';
?>
<?php Html::footer(); ?>
