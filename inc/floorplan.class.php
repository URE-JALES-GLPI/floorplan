<?php
/**
 * inc/floorplan.class.php
 * Classe principal do plugin FloorPlan
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

require_once __DIR__ . '/compat.php';

class PluginFloorplanFloorplan extends CommonDBTM {

    static string $rightname = 'entity';

    // ----------------------------------------------------------------
    // Metadados
    // ----------------------------------------------------------------

    static function getTypeName($nb = 0) {
        return _n('Planta Baixa', 'Plantas Baixas', $nb, 'floorplan');
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item->getType() === 'Entity') {
            $count = countElementsInTable(
                'glpi_plugin_floorplan_floorplans',
                ['entities_id' => $item->getID()]
            );
            return self::createTabEntry(self::getTypeName($count), $count);
        }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() === 'Entity') {
            self::showForEntity($item);
        }
        return true;
    }

    // ----------------------------------------------------------------
    // Menu
    // ----------------------------------------------------------------

    static function getMenuName() {
        return self::getTypeName(2);
    }

    static function getMenuContent() {
        $menu = parent::getMenuContent();
        $menu['icon'] = 'fas fa-map-marked-alt';
        return $menu;
    }

    // ----------------------------------------------------------------
    // Upload de imagem
    // ----------------------------------------------------------------

    /**
     * Faz upload da planta baixa e retorna o caminho relativo salvo
     */
    static function handleImageUpload(array $file): ?string {
        $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'application/pdf'];
        $allowed_exts  = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'pdf'];

        if (!in_array($file['type'], $allowed_types)) {
            Session::addMessageAfterRedirect(__('Tipo de arquivo não permitido.', 'floorplan'), false, ERROR);
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts)) {
            Session::addMessageAfterRedirect(__('Extensão não permitida.', 'floorplan'), false, ERROR);
            return null;
        }

        $upload_dir = GLPI_PLUGIN_DOC_DIR . '/floorplan/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename = uniqid('fp_', true) . '.' . $ext;
        $dest     = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Session::addMessageAfterRedirect(__('Erro ao salvar o arquivo.', 'floorplan'), false, ERROR);
            return null;
        }

        return $filename;
    }

    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    function prepareInputForAdd($input) {
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $filename = self::handleImageUpload($_FILES['image_file']);
            if ($filename) {
                $input['image_path'] = $filename;
                $input['image_type'] = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            }
        }
        return $input;
    }

    function prepareInputForUpdate($input) {
        return $this->prepareInputForAdd($input);
    }

    function cleanDBonPurge() {
        // Remove posições de ativos associadas
        $item = new PluginFloorplanItem();
        $item->deleteByCriteria(['plugin_floorplan_floorplans_id' => $this->getID()]);

        // Remove arquivo de imagem
        if (!empty($this->fields['image_path'])) {
            $path = GLPI_PLUGIN_DOC_DIR . '/floorplan/images/' . $this->fields['image_path'];
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    // ----------------------------------------------------------------
    // Listagem para uma Entidade
    // ----------------------------------------------------------------

    static function showForEntity(Entity $entity) {
        $entity_id = $entity->getID();
        $canedit   = $entity->canUpdateItem();

        echo "<div class='floorplan-entity-list'>";

        // Botão de nova planta
        if ($canedit) {
            echo "<div class='fp-toolbar'>";
            echo "<a href='" . fp_web_dir('floorplan') . "/front/floorplan.form.php?entities_id={$entity_id}'
                     class='btn btn-primary fp-btn-new'>
                     <i class='fas fa-plus'></i> " . __('Nova Planta', 'floorplan') . "
                  </a>";
            echo "</div>";
        }

        // Lista de plantas da entidade
        global $DB;
        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_floorplan_floorplans',
            'WHERE' => ['entities_id' => $entity_id],
            'ORDER' => 'name ASC',
        ]);

        if (count($iterator) === 0) {
            echo "<p class='fp-empty'>" . __('Nenhuma planta cadastrada para esta entidade.', 'floorplan') . "</p>";
        } else {
            echo "<div class='fp-card-grid'>";
            foreach ($iterator as $row) {
                $edit_url = fp_web_dir('floorplan') . "/front/floorplan.form.php?id={$row['id']}";
                echo "
                <div class='fp-card'>
                    <div class='fp-card-thumb'>
                        <img src='" . self::getImageUrl($row['image_path'], $row['image_type']) . "'
                             alt='" . htmlspecialchars($row['name']) . "'>
                    </div>
                    <div class='fp-card-info'>
                        <h3>" . htmlspecialchars($row['name']) . "</h3>";
                if (!empty($row['comment'])) {
                    echo "<p>" . htmlspecialchars($row['comment']) . "</p>";
                }
                echo "  <a href='{$edit_url}' class='btn btn-sm btn-outline-secondary'>
                            <i class='fas fa-map-marked-alt'></i> " . __('Abrir Mapa', 'floorplan') . "
                        </a>
                    </div>
                </div>";
            }
            echo "</div>";
        }

        echo "</div>";
    }

    // ----------------------------------------------------------------
    // URL pública da imagem
    // ----------------------------------------------------------------

    static function getImageUrl(?string $filename, ?string $type): string {
        if (empty($filename)) {
            return fp_web_dir('floorplan') . '/css/no-image.svg';
        }
        // Serve via front/image.php para controle de acesso
        return fp_web_dir('floorplan') . '/front/image.php?file=' . urlencode($filename);
    }

    // ----------------------------------------------------------------
    // Formulário principal (create / edit)
    // ----------------------------------------------------------------

    function showForm($ID, $options = []) {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $entity_id = $options['entities_id'] ?? $this->fields['entities_id'] ?? 0;
        $canedit   = $this->canUpdateItem();

        echo "<div class='fp-form-grid'>";

        // Nome
        echo "<div class='fp-field'>";
        echo "<label for='name'>" . __('Nome', 'floorplan') . "</label>";
        echo Html::input('name', ['value' => $this->fields['name'] ?? '', 'required' => true]);
        echo "</div>";

        // Entidade
        echo "<div class='fp-field'>";
        echo "<label>" . __('Entidade', 'floorplan') . "</label>";
        Entity::dropdown(['value' => $entity_id, 'name' => 'entities_id']);
        echo "</div>";

        // Comentário
        echo "<div class='fp-field fp-field--full'>";
        echo "<label for='comment'>" . __('Comentário', 'floorplan') . "</label>";
        echo "<textarea name='comment' rows='3'>" . htmlspecialchars($this->fields['comment'] ?? '') . "</textarea>";
        echo "</div>";

        // Upload de imagem
        echo "<div class='fp-field fp-field--full'>";
        echo "<label for='image_file'>" . __('Planta Baixa (imagem/PDF)', 'floorplan') . "</label>";
        echo "<input type='file' name='image_file' id='image_file' accept='image/*,.pdf,.svg'>";
        if (!empty($this->fields['image_path'])) {
            echo "<p class='fp-current-image'>" . __('Arquivo atual:', 'floorplan') . " <strong>" . htmlspecialchars($this->fields['image_path']) . "</strong></p>";
        }
        echo "</div>";

        echo "</div>"; // fp-form-grid

        $this->showFormButtons($options);

        // Se já existe e tem imagem, mostra o mapa interativo
        if ($ID > 0 && !empty($this->fields['image_path'])) {
            $this->showInteractiveMap($canedit);
        }

        return true;
    }

    // ----------------------------------------------------------------
    // Mapa interativo
    // ----------------------------------------------------------------

    function showInteractiveMap(bool $canedit = false) {
        $fp_id     = $this->getID();
        $img_url   = self::getImageUrl($this->fields['image_path'], $this->fields['image_type']);
        $is_pdf    = ($this->fields['image_type'] === 'pdf');
        $ajax_url  = fp_web_dir('floorplan') . '/front/ajax.php';

        // Tipos de ativo: pega do CFG_GLPI e filtra pelo perfil ativo
        global $CFG_GLPI;

        // Mapa de ícones Tabler por tipo
        $icon_map = [
            'Computer'         => 'ti ti-desktop',
            'Monitor'          => 'ti ti-device-tv',
            'NetworkEquipment' => 'ti ti-network',
            'Printer'          => 'ti ti-printer',
            'Phone'            => 'ti ti-phone',
            'Peripheral'       => 'ti ti-keyboard',
            'Rack'             => 'ti ti-server',
            'SoftwareLicense'  => 'ti ti-license',
            'Certificate'      => 'ti ti-certificate',
            'Unmanaged'        => 'ti ti-question-mark',
            'Appliance'        => 'ti ti-cpu',
        ];

        // Tipos permitidos para o perfil ativo
        $allowed_types = PluginFloorplanProfile::getAllowedItemtypesForCurrentProfile();

        // Monta array de itemtypes filtrado
        $itemtypes = [];
        foreach ($allowed_types as $type) {
            if (!class_exists($type)) continue;
            try {
                $label = $type::getTypeName(1);
            } catch (\Throwable $e) {
                $label = $type;
            }
            $itemtypes[$type] = [
                'label' => $label,
                'icon'  => $icon_map[$type] ?? 'ti ti-box',
            ];
        }

        // Carrega posições salvas
        global $DB;
        $saved = [];
        $iter  = $DB->request([
            'FROM'  => 'glpi_plugin_floorplan_items',
            'WHERE' => ['plugin_floorplan_floorplans_id' => $fp_id],
        ]);
        foreach ($iter as $row) {
            $saved[] = $row;
        }

        // Adiciona custom assets dinamicamente do banco
        $custom_defs = $DB->request([
            'SELECT' => ['system_name', 'label'],
            'FROM'   => 'glpi_assets_assetdefinitions',
            'WHERE'  => ['is_active' => 1],
            'ORDER'  => 'label ASC',
        ]);
        foreach ($custom_defs as $def) {
            $custom_type = 'Glpi\\CustomAsset\\' . $def['system_name'];
            if (!isset($itemtypes[$custom_type])) {
                $itemtypes[$custom_type] = [
                    'label' => $def['label'],
                    'icon'  => 'ti ti-box',
                ];
            }
        }

        // Carrega ativos disponíveis (da mesma entidade)
        $entity_id = $this->fields['entities_id'];
        $available = [];

        foreach ($itemtypes as $type => $type_info) {
            // Custom Assets do GLPI 11 usam tabela compartilhada glpi_assets_assets
            if (strpos($type, 'CustomAsset') !== false) {
                $parts       = explode('\\', str_replace('\\\\', '\\', $type));
                $system_name = end($parts);
                $def = $DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => 'glpi_assets_assetdefinitions',
                    'WHERE'  => ['system_name' => $system_name],
                ])->current();
                if (!$def) continue;
                $iter2 = $DB->request([
                    'SELECT' => ['id', 'name'],
                    'FROM'   => 'glpi_assets_assets',
                    'WHERE'  => [
                        'assets_assetdefinitions_id' => $def['id'],
                        'entities_id' => $entity_id,
                        'is_deleted'  => 0,
                    ],
                    'ORDER' => 'name ASC',
                    'LIMIT' => 200,
                ]);
            } else {
                // Assets nativos (Computer, Monitor, etc.)
                if (!class_exists($type)) continue;
                try {
                    $obj   = new $type();
                    $iter2 = $DB->request([
                        'SELECT' => ['id', 'name'],
                        'FROM'   => $obj->getTable(),
                        'WHERE'  => ['entities_id' => $entity_id, 'is_deleted' => 0],
                        'ORDER'  => 'name ASC',
                        'LIMIT'  => 200,
                    ]);
                } catch (\Throwable $e) {
                    continue;
                }
            }

            foreach ($iter2 as $asset) {
                if (empty($asset['name'])) continue;
                $available[] = [
                    'itemtype' => $type,
                    'items_id' => $asset['id'],
                    'name'     => $asset['name'],
                    'icon'     => $type_info['icon'],
                    'label'    => $type_info['label'],
                ];
            }
        }

        $saved_json     = json_encode($saved, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        $available_json = json_encode($available, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        $itemtypes_json = json_encode($itemtypes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        $readonly       = $canedit ? 'false' : 'true';

        echo <<<HTML
<div class="fp-map-section" id="fp-map-section">
    <div class="fp-map-header">
        <h2><i class="fas fa-map-marked-alt"></i> Mapa de Ativos</h2>
        <div class="fp-map-controls">
            <button class="fp-btn" title="Zoom +" onclick="fpMap.zoom(0.15)"><i class="ti ti-zoom-in"></i></button>
            <button class="fp-btn" title="Zoom -" onclick="fpMap.zoom(-0.15)"><i class="ti ti-zoom-out"></i></button>
            <button class="fp-btn" title="Resetar" onclick="fpMap.resetZoom()"><i class="ti ti-maximize"></i></button>
            <button class="fp-btn" title="Exportar" onclick="fpMap.exportImage()" style="background:#22c55e;border-color:#22c55e;color:#fff"><i class="ti ti-download"></i></button>
        </div>
    </div>

    <div class="fp-map-layout">
        <!-- Área do mapa -->
        <main class="fp-map-container" id="fp-map-container">
            <div class="fp-map-canvas" id="fp-map-canvas">
HTML;

        if ($is_pdf) {
            echo "<canvas id='fp-pdf-canvas'></canvas>";
            echo "<script>window.FP_PDF_URL = " . json_encode($img_url) . ";</script>";
        } else {
            echo "<img src='" . htmlspecialchars($img_url) . "' id='fp-bg-image' alt='Planta Baixa' draggable='false'>";
        }

        echo <<<HTML
                <!-- Ícones dos ativos serão injetados aqui pelo JS -->
                <div id="fp-pins-layer"></div>
            </div>
        </main>
    </div><!-- /fp-map-layout -->

    <div class="fp-map-footer" id="fp-map-footer" style="display:none">
        <button class="btn btn-success" id="fp-save-btn" onclick="fpMap.savePositions()">
            <i class="fas fa-save"></i> Salvar posições
        </button>
        <span id="fp-save-status"></span>
    </div>
</div>

<script>
window.FP_CONFIG = {
    floorplan_id : {$fp_id},
    ajax_url     : "{$ajax_url}",
    saved        : {$saved_json},
    available    : {$available_json},
    itemtypes    : {$itemtypes_json},
    readonly     : {$readonly}
};
</script>
HTML;
    }
}
