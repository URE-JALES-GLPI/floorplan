<?php
/**
 * Compatibilidade FloorPlan entre GLPI 11 e GLPI 12.
 *
 * Mesmo esquema de kanpro/inc/compat.php (este plugin também usa layout
 * legado inc/). Helpers guardados por function_exists/method_exists. No
 * GLPI 12: Plugin::getWebDir() foi removido, Session::getNewCSRFToken() e
 * Session::checkCSRF() viraram no-ops (só emitem deprecation) e o CSRF
 * passou a ser validado pelo CheckCsrfListener (Sec-Fetch-Site/Origin).
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

if (!function_exists('fp_glpi_version')) {
    function fp_glpi_version(): string
    {
        return defined('GLPI_VERSION') ? (string) GLPI_VERSION : '';
    }
}

if (!function_exists('fp_glpi_major')) {
    function fp_glpi_major(): int
    {
        $v = fp_glpi_version();
        if ($v === '') {
            return 0;
        }
        return (int) explode('.', $v)[0];
    }
}

if (!function_exists('fp_is_glpi12_plus')) {
    function fp_is_glpi12_plus(): bool
    {
        return fp_glpi_major() >= 12;
    }
}

if (!function_exists('fp_web_dir')) {
    /**
     * URL base do plugin (ex.: "/glpi/plugins/floorplan").
     * GLPI <=11: usa Plugin::getWebDir(); GLPI 12 (método removido): monta via root_doc.
     */
    function fp_web_dir(string $plugin = 'floorplan'): string
    {
        if (method_exists('Plugin', 'getWebDir')) {
            try {
                return (string) Plugin::getWebDir($plugin);
            } catch (\Throwable $e) {
                // cai para o fallback abaixo
            }
        }
        global $CFG_GLPI;
        $root = (is_array($CFG_GLPI) && isset($CFG_GLPI['root_doc'])) ? (string) $CFG_GLPI['root_doc'] : '';
        if (defined('GLPI_ROOT')) {
            $base = (string) GLPI_ROOT;
            if (@is_dir($base . '/marketplace/' . $plugin)) {
                return $root . '/marketplace/' . $plugin;
            }
        }
        return $root . '/plugins/' . $plugin;
    }
}

if (!function_exists('fp_csrf_token')) {
    /**
     * Token CSRF da página. No GLPI 12 não há token (retorna ''); o CSRF é
     * tratado pelo listener do core. No GLPI 11 mantém o comportamento atual.
     */
    function fp_csrf_token(): string
    {
        if (fp_is_glpi12_plus()) {
            return '';
        }
        if (method_exists('Session', 'getNewCSRFToken')) {
            try {
                return (string) Session::getNewCSRFToken();
            } catch (\Throwable $e) {
                return '';
            }
        }
        return '';
    }
}

if (!function_exists('fp_check_csrf')) {
    /**
     * Validação CSRF explícita. No GLPI 12 é no-op (o CheckCsrfListener cuida);
     * no GLPI 11 delega para Session::checkCSRF() preservando o token.
     */
    function fp_check_csrf(array $data): void
    {
        if (fp_is_glpi12_plus()) {
            return;
        }
        Session::checkCSRF($data, true);
    }
}
