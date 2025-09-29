<?php
/**
 * Plugin forceapproval - Lógica principal
 * GLPI 10.x
 */

if (!defined('PLUGIN_FORCEAPPROVAL_WEBDIR')) {
    // Ajuste se o diretório do plugin for diferente
    define('PLUGIN_FORCEAPPROVAL_WEBDIR', '/glpi/plugins/forceapproval');
}

/**
 * Verifica se há satisfação pendente para um ticket do usuário
 */
function plugin_forceapproval_has_pending_satisfaction($ticket_id, $user_id) {
    global $DB;

    $ticket_id = (int)$ticket_id;
    $user_id   = (int)$user_id;
    if ($ticket_id <= 0 || $user_id <= 0) return false;

    $sql = "
        SELECT 1
        FROM `glpi_tickets` t
        INNER JOIN `glpi_ticketsatisfactions` s ON s.`tickets_id` = t.`id`
        WHERE t.`id` = {$ticket_id}
          AND t.`users_id_recipient` = {$user_id}
          AND (s.`date_answered` IS NULL OR s.`date_answered` = '')
        LIMIT 1
    ";
    $res = $DB->query($sql);
    return ($res && $DB->numrows($res) > 0);
}

/**
 * Há regras ativas na tabela de config?
 * - Se não houver nenhuma linha enabled=1, consideramos o plugin "desligado por configuração".
 */
function plugin_forceapproval_has_enabled_rules() {
    global $DB;
    $sql = "SELECT 1 FROM `glpi_plugin_forceapproval_config` WHERE `enabled` = 1 LIMIT 1";
    $res = $DB->query($sql);
    return ($res && $DB->numrows($res) > 0);
}

/**
 * Helper para montar filtro EXISTS de correspondência entidade/categoria
 */
function plugin_forceapproval_sql_exists_cfg($ticket_alias = 'glpi_tickets') {
    return "EXISTS (
                SELECT 1
                FROM `glpi_plugin_forceapproval_config` cfg
                WHERE cfg.`enabled` = 1
                  AND (cfg.`entities_id` = 0 OR cfg.`entities_id` = {$ticket_alias}.`entities_id`)
                  AND (cfg.`itilcategories_id` = 0 OR cfg.`itilcategories_id` = {$ticket_alias}.`itilcategories_id`)
            )";
}

/**
 * Confere se um ticket específico bate nas regras de configuração
 */
function plugin_forceapproval_is_ticket_enabled($ticket_id) {
    global $DB;

    $ticket_id = (int)$ticket_id;
    if ($ticket_id <= 0) return false;

    $sql = "SELECT t.`entities_id`, t.`itilcategories_id`
            FROM `glpi_tickets` t
            WHERE t.`id` = {$ticket_id}
            LIMIT 1";
    $res = $DB->query($sql);
    if (!$res || $DB->numrows($res) === 0) return false;

    $row = $DB->fetchAssoc($res);

    $esql = "SELECT 1
             FROM `glpi_plugin_forceapproval_config`
             WHERE `enabled` = 1
               AND (`entities_id` = 0 OR `entities_id` = ".(int)$row['entities_id'].")
               AND (`itilcategories_id` = 0 OR `itilcategories_id` = ".(int)$row['itilcategories_id'].")
             LIMIT 1";
    $chk = $DB->query($esql);
    return ($chk && $DB->numrows($chk) > 0);
}

/**
 * Obtém a URL do ticket mais antigo pendente (apenas dentro das entidades/categorias habilitadas)
 * - 1º prioridade: pendente de aprovação (status = 5)
 * - 2º prioridade: pendente de avaliação (status = 6 + satisfação não respondida)
 */
function plugin_forceapproval_get_pending_action($user_id) {
    global $DB, $CFG_GLPI;

    $user_id = (int)$user_id;

    // 1) Pendente de aprovação (status 5), filtrando por config
    $q1 = "SELECT t.`id`
           FROM `glpi_tickets` t
           WHERE t.`users_id_recipient` = {$user_id}
             AND t.`status` = 5
             AND " . plugin_forceapproval_sql_exists_cfg('t') . "
           ORDER BY t.`date` ASC
           LIMIT 1";
    $r1 = $DB->query($q1);
    if ($r1 && ($d1 = $DB->fetchAssoc($r1))) {
        return rtrim($CFG_GLPI['url_base'] ?? '/glpi', '/') .
               "/front/ticket.form.php?id=" . (int)$d1['id'] . "&forcetab=Ticket$3";
    }

    // 2) Pendente de avaliação (status 6 + satisfação não respondida), filtrando por config
    $q2 = "SELECT t.`id`
           FROM `glpi_tickets` t
           WHERE t.`users_id_recipient` = {$user_id}
             AND t.`status` = 6
             AND EXISTS (
                 SELECT 1
                 FROM `glpi_ticketsatisfactions` s
                 WHERE s.`tickets_id` = t.`id`
                   AND s.`date_answered` IS NULL
             )
             AND " . plugin_forceapproval_sql_exists_cfg('t') . "
           ORDER BY t.`date` ASC
           LIMIT 1";
    $r2 = $DB->query($q2);
    if ($r2 && ($d2 = $DB->fetchAssoc($r2))) {
        return rtrim($CFG_GLPI['url_base'] ?? '/glpi', '/') .
               "/front/ticket.form.php?id=" . (int)$d2['id'] . "&forcetab=Ticket$3";
    }

    // 3) Nada pendente (ou nada habilitado por config)
    return false;
}

/**
 * Hook principal de redireciono/bloqueio
 */
function plugin_forceapproval_force_redirect() {
    global $CFG_GLPI;

    if (!isset($_SESSION['glpiID'])) return;

    // Se não há regras habilitadas, não interfere no GLPI
    if (!plugin_forceapproval_has_enabled_rules()) {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($request_uri, PHP_URL_PATH);

    // 🚪 Permitir logout sem bloqueio
    if (strpos($path, '/front/logout.php') !== false) {
        return;
    }

    // Nunca bloquear a própria popup do plugin
    if (strpos($path, PLUGIN_FORCEAPPROVAL_WEBDIR . '/front/popup.php') !== false) {
        return;
    }

    // --- FLUXO ISSUE (FormCreator) ---
    // Bloquear "Meus chamados" (listagem da issue), mas somente se houver pendência dentro da config
    if (strpos($path, '/plugins/formcreator/front/issue.php') !== false
        && strpos($request_uri, 'from_forceapproval=1') === false) {

        $target_url = plugin_forceapproval_get_pending_action((int)$_SESSION['glpiID']);
        if ($target_url !== false) {
            $_SESSION['forceapproval_redirect_url'] = $target_url;
            $redirect_url = PLUGIN_FORCEAPPROVAL_WEBDIR . '/front/popup.php';
            echo "<script>window.location.href=" . json_encode($redirect_url) . ";</script>";
            exit();
        }
    }

    // Permitir acesso ao formulário de aprovação/recusa (issue.form.php)
    if (strpos($path, '/plugins/formcreator/front/issue.form.php') !== false) {
        $_SESSION['forceapproval_in_issue'] = true;
        return;
    }

    // Enquanto o usuário estiver dentro do fluxo do FormCreator para resolver pendência,
    // liberar apenas rotas necessárias
    if (!empty($_SESSION['forceapproval_in_issue'])) {
        $safe = [
            '/plugins/formcreator/front/issue.form.php',
            '/front/itilfollowup.form.php',
            '/front/ticketsatisfaction.form.php',
            '/ajax/', '/css/', '/js/', '/pics/', '/assets/'
        ];
        foreach ($safe as $s) {
            if (strpos($path, $s) !== false) {
                return;
            }
        }
    }

    // Forçar a aba de satisfação no ticket quando ainda não respondida
    if (strpos($path, '/front/ticket.form.php') !== false && strpos($request_uri, 'forcetab=') === false) {
        if (!empty($_GET['id'])) {
            $tid = (int)$_GET['id'];
            // Só aplicar se o ticket estiver habilitado na config
            if (plugin_forceapproval_is_ticket_enabled($tid)
                && plugin_forceapproval_has_pending_satisfaction($tid, (int)$_SESSION['glpiID'])) {

                $url = rtrim($CFG_GLPI['url_base'] ?? '/glpi', '/') .
                       "/front/ticket.form.php?id=" . $tid . "&forcetab=Ticket$3";
                echo "<script>window.location.href=" . json_encode($url) . ";</script>";
                exit();
            }
        }
    }

    // Bloqueio normal: apenas para interface helpdesk
    if (!empty($_SESSION['glpiactiveprofile']['interface']) &&
        $_SESSION['glpiactiveprofile']['interface'] === 'helpdesk') {

        $target_url = plugin_forceapproval_get_pending_action((int)$_SESSION['glpiID']);

        if ($target_url !== false) {
            $_SESSION['forceapproval_redirect_url'] = $target_url;
            $redirect_url = PLUGIN_FORCEAPPROVAL_WEBDIR . '/front/popup.php';
            echo "<script>window.location.href=" . json_encode($redirect_url) . ";</script>";
            exit();
        } else {
            // Limpa marcadores caso não haja mais pendências
            unset($_SESSION['forceapproval_redirect_url'], $_SESSION['forceapproval_in_issue']);
        }
    }
}
