<?php
/**
 * front/floorplan.form.php — Criar / Editar planta + mapa interativo
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_floorplan', READ);

global $CFG_GLPI;
$fp = new PluginFloorplanFloorplan();

// ---------- POST: salvar ----------
if (isset($_POST['add'])) {
    $fp->check(-1, CREATE, $_POST);
    $new_id = $fp->add($_POST);
    // Log para debug
    Toolbox::logDebug('FloorPlan add result: ' . var_export($new_id, true));
    Toolbox::logDebug('FloorPlan POST: ' . var_export($_POST, true));
    if ($new_id) {
        $url = Plugin::getWebDir('floorplan') . '/front/floorplan.form.php?id=' . (int)$new_id;
        header('Location: ' . $url);
        exit;
    }
    Html::back();

} elseif (isset($_POST['update'])) {
    $fp->check($_POST['id'], UPDATE, $_POST);
    $fp->update($_POST);
    Html::back();

} elseif (isset($_POST['delete'])) {
    $fp->check($_POST['id'], DELETE, $_POST);
    $fp->delete($_POST);
    Html::redirect(Plugin::getWebDir('floorplan') . '/front/floorplan.php');

} elseif (isset($_POST['purge'])) {
    $fp->check($_POST['id'], PURGE, $_POST);
    $fp->delete($_POST, true);
    Html::redirect(Plugin::getWebDir('floorplan') . '/front/floorplan.php');
}

// ---------- GET: exibir ----------
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $fp->check($id, READ);
    $fp->getFromDB($id);
} else {
    $fp->check(-1, CREATE);
}

$entity_id = (int)($_GET['entities_id'] ?? $fp->fields['entities_id'] ?? $_SESSION['glpiactive_entity'] ?? 0);
$canedit   = Session::haveRight('plugin_floorplan', UPDATE);
$is_new    = ($id <= 0);

Html::header(
    $is_new ? 'Nova Planta Baixa' : htmlspecialchars($fp->fields['name'] ?? 'Planta Baixa'),
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginFloorplanFloorplan'
);

$img_url = '';
if (!empty($fp->fields['image_path'])) {
    $img_url = Plugin::getWebDir('floorplan') . '/front/image.php?file=' . urlencode($fp->fields['image_path']);
}

?>
<div class="container-fluid mt-3 mb-5">

    <!-- Breadcrumb / título -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="floorplan.php" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left"></i> Voltar
        </a>
        <h2 class="mb-0">
            <i class="ti ti-map me-2 text-primary"></i>
            <?= $is_new ? 'Nova Planta Baixa' : htmlspecialchars($fp->fields['name'] ?? '') ?>
        </h2>
    </div>

    <form method="post" action="<?= $CFG_GLPI['root_doc'] ?>/plugins/floorplan/front/floorplan.form.php" enctype="multipart/form-data">
        <?php if ($id > 0): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>
        <?php echo Html::hidden('entities_id', ['value' => $entity_id]); ?>
        <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]); ?>

        <div class="row g-4">

            <!-- Coluna esquerda: dados -->
            <div class="col-md-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="ti ti-info-circle me-1"></i> Informações da Planta
                    </div>
                    <div class="card-body d-flex flex-column gap-3">

                        <!-- Nome -->
                        <div>
                            <label class="form-label fw-semibold" for="fp_name">
                                Nome <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="fp_name" name="name" required
                                   class="form-control"
                                   placeholder="Ex: Bloco A — Térreo"
                                   value="<?= htmlspecialchars($fp->fields['name'] ?? '') ?>">
                        </div>

                        <!-- Entidade automática da sessão -->
                        <?php echo Html::hidden('entities_id', ['value' => $entity_id]); ?>
                        <div>
                            <label class="form-label fw-semibold text-muted">
                                <i class="ti ti-building me-1"></i>Entidade
                            </label>
                            <div class="form-control bg-light text-muted" style="cursor:default">
                                <?php
                                $ent = new Entity();
                                $ent->getFromDB($entity_id);
                                echo htmlspecialchars($ent->fields['completename'] ?? $ent->fields['name'] ?? 'Entidade atual');
                                ?>
                            </div>
                        </div>

                        <!-- Comentário -->
                        <div>
                            <label class="form-label fw-semibold" for="fp_comment">Comentário</label>
                            <textarea id="fp_comment" name="comment" rows="4"
                                      class="form-control"
                                      placeholder="Descrição, andar, ala..."><?= htmlspecialchars($fp->fields['comment'] ?? '') ?></textarea>
                        </div>

                        <!-- Upload -->
                        <div>
                            <label class="form-label fw-semibold">
                                <i class="ti ti-upload me-1"></i> Planta Baixa
                            </label>
                            <div id="fp-drop-zone" class="fp-drop-zone <?= $img_url ? 'fp-drop-zone--has-image' : '' ?>">
                                <?php if ($img_url): ?>
                                    <img id="fp-preview-img" src="<?= $img_url ?>" alt="preview">
                                    <div class="fp-drop-overlay">
                                        <i class="ti ti-replace"></i> Substituir imagem
                                    </div>
                                <?php else: ?>
                                    <div class="fp-drop-placeholder" id="fp-drop-placeholder">
                                        <i class="ti ti-photo-up"></i>
                                        <span>Arraste uma imagem ou clique para selecionar</span>
                                        <small class="text-muted">PNG, JPG, SVG, PDF</small>
                                    </div>
                                    <img id="fp-preview-img" src="" alt="preview" style="display:none">
                                <?php endif; ?>
                                <input type="file" name="image_file" id="fp-file-input"
                                       accept="image/*,.pdf,.svg" style="display:none">
                            </div>
                            <?php if (!empty($fp->fields['image_path'])): ?>
                                <small class="text-muted mt-1 d-block">
                                    <i class="ti ti-file"></i> <?= htmlspecialchars($fp->fields['image_path']) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                    </div>
                    <div class="card-footer d-flex gap-2 justify-content-end">
                        <?php if ($is_new): ?>
                            <button type="submit" name="add" value="1" class="btn btn-success">
                                <i class="ti ti-plus"></i> Criar Planta
                            </button>
                        <?php else: ?>
                            <button type="submit" name="update" value="1" class="btn btn-primary">
                                <i class="ti ti-device-floppy"></i> Salvar
                            </button>
                            <?php if ($canedit): ?>
                            <button type="submit" name="purge" value="1" class="btn btn-outline-danger"
                                    onclick="return confirm('Excluir esta planta e todos os ativos mapeados?')">
                                <i class="ti ti-trash"></i> Excluir
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna direita: preview / mapa -->
            <div class="col-md-7">
                <?php if ($is_new): ?>
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center text-muted gap-3 py-5">
                            <i class="ti ti-map-2" style="font-size:4rem;opacity:.3"></i>
                            <p class="mb-0">Após criar a planta, o mapa interativo aparecerá aqui.<br>
                            Você poderá arrastar os ativos sobre a imagem.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    // Renderiza o mapa interativo
                    $fp->showInteractiveMap($canedit);
                    ?>
                <?php endif; ?>
            </div>

        </div><!-- /row -->
    </form>
</div>

<style>
.fp-drop-zone {
    border: 2px dashed #adb5bd;
    border-radius: 8px;
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
    overflow: hidden;
    min-height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.fp-drop-zone:hover,
.fp-drop-zone--drag { border-color: #0d6efd; background: rgba(13,110,253,.05); }
.fp-drop-placeholder {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
}
.fp-drop-placeholder i { font-size: 2.5rem; color: #adb5bd; }
.fp-drop-zone--has-image { padding: 0; border-style: solid; border-color: #0d6efd; }
.fp-drop-zone--has-image img { width:100%; max-height:220px; object-fit:cover; display:block; }
.fp-drop-overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,.55);
    color: #fff; font-size: 1rem;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    opacity: 0; transition: opacity .2s;
}
.fp-drop-zone--has-image:hover .fp-drop-overlay { opacity: 1; }
</style>

<script>
(function(){
    const zone     = document.getElementById('fp-drop-zone');
    const input    = document.getElementById('fp-file-input');
    const preview  = document.getElementById('fp-preview-img');
    const placeholder = document.getElementById('fp-drop-placeholder');

    if (!zone || !input) return;

    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('fp-drop-zone--drag'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('fp-drop-zone--drag'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('fp-drop-zone--drag');
        if (e.dataTransfer.files[0]) showPreview(e.dataTransfer.files[0]);
        input.files = e.dataTransfer.files;
    });

    input.addEventListener('change', () => { if (input.files[0]) showPreview(input.files[0]); });

    function showPreview(file) {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
            zone.classList.add('fp-drop-zone--has-image');
        };
        reader.readAsDataURL(file);
    }
})();
</script>

<?php
Html::footer();
