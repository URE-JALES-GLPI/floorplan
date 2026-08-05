<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginFloorplanProfile extends CommonDBTM
{
    public static $rightname = 'profile';
    public const RIGHT_FLOORPLAN = 'plugin_floorplan';

    // -------------------------------------------------------
    // Helpers de itemtype
    // -------------------------------------------------------

    /**
     * Retorna todos os tipos de ativo disponíveis no GLPI
     * Usa $CFG_GLPI['asset_types'] — inclui automaticamente novos tipos
     */
    public static function getAllItemtypes(): array
    {
        global $CFG_GLPI;

        // Tipos base do asset_types + extras úteis para mapa
        $types = array_merge(
            $CFG_GLPI['asset_types'] ?? [],
            ['NetworkEquipment', 'Rack', 'Monitor', 'Computer', 'Phone', 'Printer', 'Peripheral']
        );

        // Uniquify e remove tipos sem nome amigável
        $result = [];
        foreach (array_unique($types) as $type) {
            if (!class_exists($type)) continue;
            try {
                $label = $type::getTypeName(1);
            } catch (\Throwable $e) {
                $label = $type;
            }
            $result[$type] = $label;
        }

        asort($result);
        return $result;
    }

    /**
     * Retorna os itemtypes permitidos para um perfil
     */
    public static function getAllowedItemtypes(int $profiles_id): array
    {
        global $DB;

        $rows = $DB->request([
            'SELECT' => ['itemtype'],
            'FROM'   => 'glpi_plugin_floorplan_profile_itemtypes',
            'WHERE'  => ['profiles_id' => $profiles_id],
        ]);

        $types = [];
        foreach ($rows as $row) {
            $types[] = $row['itemtype'];
        }
        return $types;
    }

    /**
     * Retorna itemtypes permitidos para o perfil ATIVO na sessão
     */
    public static function getAllowedItemtypesForCurrentProfile(): array
    {
        $profiles_id = (int)($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if (!$profiles_id) return [];
        $allowed = self::getAllowedItemtypes($profiles_id);
        // Se não configurou nada, libera tudo (admin)
        if (empty($allowed)) return array_keys(self::getAllItemtypes());
        return $allowed;
    }

    /**
     * Salva os itemtypes permitidos para um perfil
     */
    public static function saveAllowedItemtypes(int $profiles_id, array $itemtypes): void
    {
        global $DB;

        // Remove todos os existentes
        $DB->delete('glpi_plugin_floorplan_profile_itemtypes', ['profiles_id' => $profiles_id]);

        // Insere os novos
        $all = array_keys(self::getAllItemtypes());
        foreach ($itemtypes as $type) {
            if (!in_array($type, $all)) continue;
            $DB->insert('glpi_plugin_floorplan_profile_itemtypes', [
                'profiles_id' => $profiles_id,
                'itemtype'    => $type,
            ]);
        }
    }

    // -------------------------------------------------------
    // Install / Uninstall
    // -------------------------------------------------------

    public static function getAllRights(): array
    {
        return [
            [
                'itemtype' => self::class,
                'label'    => 'Plantas Baixas',
                'field'    => self::RIGHT_FLOORPLAN,
                'rights'   => [
                    READ   => __('Read'),
                    UPDATE => __('Update'),
                    DELETE => __('Delete'),
                ],
                'default' => 0,
            ],
        ];
    }

    public static function install(): bool
    {
        global $DB;
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_profiles']) as $profile) {
            self::addDefaultProfileInfos((int)$profile['id'], self::getDefaultRightsMap());
        }
        self::changeProfile();
        return true;
    }

    public static function uninstall(): bool
    {
        global $DB;
        foreach (self::getAllRights() as $right) {
            $DB->delete(ProfileRight::getTable(), ['name' => $right['field']]);
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
        }
        return true;
    }

    public static function addDefaultProfileInfos(int $profiles_id, array $rights): void
    {
        $profileRight = new ProfileRight();
        foreach ($rights as $right_name => $right_value) {
            if (!countElementsInTable(ProfileRight::getTable(), [
                'profiles_id' => $profiles_id,
                'name'        => $right_name,
            ])) {
                $profileRight->add([
                    'profiles_id' => $profiles_id,
                    'name'        => $right_name,
                    'rights'      => $right_value,
                ]);
            }
        }
    }

    public static function changeProfile(): void
    {
        global $DB;
        $active_profile_id = (int)($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($active_profile_id <= 0) return;

        foreach (self::getAllRights() as $right) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
        }

        $iterator = $DB->request([
            'SELECT' => ['name', 'rights'],
            'FROM'   => ProfileRight::getTable(),
            'WHERE'  => [
                'profiles_id' => $active_profile_id,
                'name'        => array_column(self::getAllRights(), 'field'),
            ],
        ]);

        foreach ($iterator as $row) {
            $_SESSION['glpiactiveprofile'][$row['name']] = (int)$row['rights'];
        }
    }

    private static function getDefaultRightsMap(): array
    {
        $rights = [];
        foreach (self::getAllRights() as $right) {
            $rights[$right['field']] = $right['default'];
        }
        return $rights;
    }

    private static function getProfileRightValue(int $profiles_id, string $right_name, int $default = 0): int
    {
        global $DB;
        $row = $DB->request([
            'SELECT' => ['rights'],
            'FROM'   => ProfileRight::getTable(),
            'WHERE'  => ['profiles_id' => $profiles_id, 'name' => $right_name],
        ])->current();
        return (is_array($row) && isset($row['rights'])) ? (int)$row['rights'] : $default;
    }

    // -------------------------------------------------------
    // Aba no Perfil
    // -------------------------------------------------------

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('id')) {
            return "<span class='d-inline-flex align-items-center gap-1'>"
                . "<i class='ti ti-map'></i><span>Plantas Baixas</span></span>";
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $CFG_GLPI;
        if (!($item instanceof Profile)) return false;
        if (!$item->canView()) return false;

        $profiles_id    = (int)$item->getID();
        self::addDefaultProfileInfos($profiles_id, self::getDefaultRightsMap());

        $current_rights  = self::getProfileRightValue($profiles_id, self::RIGHT_FLOORPLAN, 0);
        $allowed_types   = self::getAllowedItemtypes($profiles_id);
        $all_types       = self::getAllItemtypes();
        $canedit         = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        $form_action     = $CFG_GLPI['root_doc'] . '/plugins/floorplan/front/profile.form.php';

        echo "<form name='floorplan_profile_form' method='post' action='{$form_action}'>";
        echo "<div class='spaced'>";
        echo "<table class='tab_cadre_fixehov'>";

        // ── Seção 1: Permissão geral ──
        echo "<tr class='headerRow'><th colspan='2'>🗺️ Permissões — Plantas Baixas</th></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td width='40%'><strong>Nível de acesso</strong><br>
              <small>Controla o acesso ao menu Ferramentas → Plantas Baixas</small></td>";
        echo "<td>";

        if ($canedit) {
            $options = [
                0                         => '— Sem acesso —',
                READ                      => '🔍 Visualizar',
                READ | UPDATE             => '✏️ Visualizar e Editar',
                READ | UPDATE | DELETE    => '🗑️ Visualizar, Editar e Apagar',
            ];
            Dropdown::showFromArray('rights', $options, ['value' => $current_rights]);
        } else {
            $labels = [
                0                         => 'Sem acesso',
                READ                      => 'Visualizar',
                READ | UPDATE             => 'Visualizar e Editar',
                READ | UPDATE | DELETE    => 'Visualizar, Editar e Apagar',
            ];
            echo $labels[$current_rights] ?? 'Sem acesso';
        }
        echo "</td></tr>";

        // ── Seção 2: Tipos de ativo visíveis no mapa ──
        echo "<tr class='headerRow'><th colspan='2'>📦 Tipos de ativo visíveis no mapa</th></tr>";
        echo "<tr class='tab_bg_2'><td colspan='2'>";
        echo "<small class='text-muted'>Selecione quais tipos de ativo este perfil pode ver e adicionar nas plantas baixas.<br>
              Se nenhum for marcado, todos serão visíveis.</small>";
        echo "</td></tr>";

        // Grid de checkboxes
        echo "<tr class='tab_bg_1'><td colspan='2'>";
        echo "<div style='display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;padding:8px'>";

        foreach ($all_types as $type => $label) {
            $checked  = in_array($type, $allowed_types) ? 'checked' : '';
            $disabled = $canedit ? '' : 'disabled';
            $icon     = self::getIconForType($type);
            echo "<label style='display:flex;align-items:center;gap:8px;padding:6px 10px;
                               border:1px solid #dee2e6;border-radius:6px;cursor:pointer;
                               background:" . ($checked ? '#e8f4fd' : '#fff') . ";
                               transition:background .15s'
                        class='fp-type-label'>
                    <input type='checkbox' name='itemtypes[]' value='{$type}' {$checked} {$disabled}
                           style='width:16px;height:16px;cursor:pointer'>
                    <i class='{$icon}' style='color:#0d6efd;width:16px;text-align:center'></i>
                    <span>{$label}</span>
                  </label>";
        }

        echo "</div>";
        echo "</td></tr>";

        // Botões
        if ($canedit) {
            echo "<tr class='tab_bg_2'><td colspan='2' class='center' style='padding:12px'>";
            echo "<div style='display:flex;gap:8px;justify-content:center;align-items:center'>";
            echo "<button type='button' onclick='fpSelectAll(true)' class='btn btn-sm btn-outline-secondary'>
                    <i class='ti ti-check'></i> Marcar todos
                  </button>";
            echo "<button type='button' onclick='fpSelectAll(false)' class='btn btn-sm btn-outline-secondary'>
                    <i class='ti ti-x'></i> Desmarcar todos
                  </button>";
            echo Html::hidden('profiles_id', ['value' => $profiles_id]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo "<button type='submit' name='update' value='1' class='btn btn-primary'>
                    <i class='ti ti-device-floppy'></i> Salvar permissões
                  </button>";
            echo "</div>";
            echo "</td></tr>";
        }

        echo "</table></div>";
        Html::closeForm();

        // Estilo dos checkboxes
        echo "<script>
        function fpSelectAll(check) {
            document.querySelectorAll('input[name=\"itemtypes[]\"]').forEach(cb => {
                cb.checked = check;
                cb.closest('label').style.background = check ? '#e8f4fd' : '#fff';
            });
        }
        document.querySelectorAll('input[name=\"itemtypes[]\"]').forEach(cb => {
            cb.addEventListener('change', function() {
                this.closest('label').style.background = this.checked ? '#e8f4fd' : '#fff';
            });
        });
        </script>";

        return true;
    }

    /**
     * Ícone Tabler para cada tipo de ativo
     */
    public static function getIconForType(string $type): string
    {
        $map = [
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
        return $map[$type] ?? 'ti ti-box';
    }
}
