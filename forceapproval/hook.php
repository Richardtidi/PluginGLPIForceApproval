<?php
/**
 * Plugin forceapproval - Lógica principal
 */

if (!defined('PLUGIN_FORCEAPPROVAL_WEBDIR')) {
    define('PLUGIN_FORCEAPPROVAL_WEBDIR', '/glpi/plugins/forceapproval');
}

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

function plugin_forceapproval_get_pending_action($user_id) {
    global $DB, $CFG_GLPI;

    // 1. Buscar chamado mais antigo pendente de aprovação (status 5)
    $q1 = "SELECT id
           FROM `glpi_tickets`
           WHERE `users_id_recipient` = " . (int)$user_id . "
             AND `status` = 5
           ORDER BY `date` ASC
           LIMIT 1";
    $r1 = $DB->query($q1);
    if ($d1 = $DB->fetchAssoc($r1)) {
        return $CFG_GLPI['url_base'] .
               "/front/ticket.form.php?id=" . (int)$d1['id'] . "&forcetab=Ticket$3";
    }

    // 2. Buscar chamado mais antigo pendente de avaliação (status 6 + satisfação não respondida)
    $q2 = "SELECT glpi_tickets.id
           FROM `glpi_tickets`
           WHERE `users_id_recipient` = " . (int)$user_id . "
             AND `status` = 6
             AND EXISTS (
                 SELECT 1 FROM `glpi_ticketsatisfactions`
                 WHERE `tickets_id` = glpi_tickets.id
                   AND `date_answered` IS NULL
             )
           ORDER BY glpi_tickets.date ASC
           LIMIT 1";
    $r2 = $DB->query($q2);
    if ($d2 = $DB->fetchAssoc($r2)) {
        return $CFG_GLPI['url_base'] .
               "/front/ticket.form.php?id=" . (int)$d2['id'] . "&forcetab=Ticket$3";
    }

    // 3. Nada pendente
    return false;
}

function plugin_forceapproval_force_redirect() {
    global $CFG_GLPI;

    if (!isset($_SESSION['glpiID'])) return;

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($request_uri, PHP_URL_PATH);

    // 🚪 Permitir logout sem bloqueio
    if (strpos($path, '/front/logout.php') !== false) {
        return;
    }

    // Nunca bloquear popup
    if (strpos($path, PLUGIN_FORCEAPPROVAL_WEBDIR . '/front/popup.php') !== false) {
        return;
    }

    // --- FLUXO ISSUE (FormCreator) ---
    if (strpos($path, '/plugins/formcreator/front/issue.php') !== false
        || strpos($path, '/plugins/formcreator/front/issue.form.php') !== false) {
        $_SESSION['forceapproval_in_issue'] = true;
        return;
    }

    if (!empty($_SESSION['forceapproval_in_issue'])) {
        $safe = [
            '/plugins/formcreator/front/issue.php',
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

    // Forçar aba de satisfação (quando ainda não respondida)
    if (strpos($path, '/front/ticket.form.php') !== false && strpos($request_uri, 'forcetab=') === false) {
        if (!empty($_GET['id']) && plugin_forceapproval_has_pending_satisfaction((int)$_GET['id'], (int)$_SESSION['glpiID'])) {
            $url = rtrim($CFG_GLPI['url_base'] ?? '/glpi', '/') .
                   "/front/ticket.form.php?id=" . (int)$_GET['id'] . "&forcetab=Ticket$3";
            echo "<script>window.location.href=" . json_encode($url) . ";</script>";
            exit();
        }
    }

    // Bloqueio normal
    if (!empty($_SESSION['glpiactiveprofile']['interface']) &&
        $_SESSION['glpiactiveprofile']['interface'] === 'helpdesk') {

        $target_url = plugin_forceapproval_get_pending_action((int)$_SESSION['glpiID']);

        if ($target_url !== false) {
            $_SESSION['forceapproval_redirect_url'] = $target_url;
            $redirect_url = PLUGIN_FORCEAPPROVAL_WEBDIR . '/front/popup.php';
            echo "<script>window.location.href=" . json_encode($redirect_url) . ";</script>";
            exit();
        } else {
            unset($_SESSION['forceapproval_redirect_url'], $_SESSION['forceapproval_in_issue']);
        }
    }
}
?>
