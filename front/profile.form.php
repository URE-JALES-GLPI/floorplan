<?php
/**
 * front/profile.form.php — Salva permissões do FloorPlan
 */

include('../../../inc/includes.php');

Session::checkRight('profile', UPDATE);

global $DB;

if (isset($_POST['update'])) {
    $profiles_id = (int)($_POST['profiles_id'] ?? 0);
    $rights      = (int)($_POST['rights'] ?? 0);
    $itemtypes   = $_POST['itemtypes'] ?? [];

    if ($profiles_id > 0) {
        // Salva nível de acesso
        $profileRight = new ProfileRight();
        $existing = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => ProfileRight::getTable(),
            'WHERE'  => ['profiles_id' => $profiles_id, 'name' => PluginFloorplanProfile::RIGHT_FLOORPLAN],
        ])->current();

        if ($existing) {
            $profileRight->update(['id' => (int)$existing['id'], 'rights' => $rights]);
        } else {
            $profileRight->add(['profiles_id' => $profiles_id, 'name' => PluginFloorplanProfile::RIGHT_FLOORPLAN, 'rights' => $rights]);
        }

        // Salva tipos de ativo permitidos
        PluginFloorplanProfile::saveAllowedItemtypes($profiles_id, $itemtypes);

        PluginFloorplanProfile::changeProfile();
        Session::addMessageAfterRedirect('Permissões salvas com sucesso.');
    }
}

Html::back();
