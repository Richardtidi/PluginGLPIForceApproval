<?php
/**
 * Plugin forceapproval - Lógica principal
 */

if (!defined('PLUGIN_FORCEAPPROVAL_WEBDIR')) {
    define('PLUGIN_FORCEAPPROVAL_WEBDIR', '/glpi/plugins/forceapproval');
}

/**
 * Verifica se há satisfação pendente para um ticket específico do usuário atual
 */
function plugin_forceapproval_has_pending_satisfaction($ticket_id, $user_id) {
    global $DB;

    $ticket_id = (int)$ticket_id;
    $user_id   = (int)$user_id;

    if ($ticket_id <= 0 || $user_id <= 0) {
        return false;
    }

    // Confirma que o ticket pertence ao solicitante logado E possui satisfação não respondida
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
    if ($res && $DB->numrows($res) > 0) {
        return true;
    }
    return false;
}

function plugin_forceapproval_get_pending_action($user_id) {
    global $DB, $CFG_GLPI;

    // ETAPA 1: Prioridade -> Verificar chamados para APROVAR (Status = 5)
    $query_approval = "SELECT COUNT(id) AS ticket_count FROM `glpi_tickets`
                       WHERE `users_id_recipient` = '" . (int)$user_id . "' AND `status` = 5";
    $result_approval = $DB->query($query_approval);
    if ($data_approval = $DB->fetchAssoc($result_approval)) {
        if ((int)$data_approval['ticket_count'] > 0) {
            return $CFG_GLPI['url_base'] . '/front/ticket.php?is_deleted=0&as_map=0&browse=0&criteria[0][link]=AND&criteria[0][field]=12&criteria[0][searchtype]=equals&criteria[0][value]=5&itemtype=Ticket&start=0';
        }
    }

    // ETAPA 2: Verificar chamados para AVALIAR (Status = 6 e sem avaliação)
    $query_survey = "SELECT glpi_tickets.id
                     FROM `glpi_tickets`
                     WHERE `users_id_recipient` = '" . (int)$user_id . "'
                       AND `status` = 6
                       AND EXISTS (
                           SELECT 1 FROM `glpi_ticketsatisfactions`
                           WHERE `glpi_ticketsatisfactions`.`tickets_id` = `glpi_tickets`.`id`
                             AND `glpi_ticketsatisfactions`.`date_answered` IS NULL
                       )
                     ORDER BY glpi_tickets.id ASC
                     LIMIT 1";
    $result_survey = $DB->query($query_survey);
    if ($data_survey = $DB->fetchAssoc($result_survey)) {
        return $CFG_GLPI['url_base'] . "/front/ticket.form.php?id=" . (int)$data_survey['id'] . "&forcetab=Ticket$3";
    }

    return false;
}

function plugin_forceapproval_force_redirect() {
    global $CFG_GLPI;

    if (!isset($_SESSION) || !isset($_SESSION['glpiID'])) { return; }

    // ====== NOVO BLOCO: redirecionar ticket.form.php para a aba "Satisfação" quando existir pesquisa pendente ======
    // Fazemos isso ANTES do early-return dos padrões liberados para não pular esta regra.
    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
    if ($request_uri !== '') {
        // Detecta que estamos em /front/ticket.form.php
        if (strpos($request_uri, '/front/ticket.form.php') !== false) {
            // Evita loop: se já tem forcetab na URL, não mexe
            if (strpos($request_uri, 'forcetab=') === false) {
                // Obtém o ID do ticket da query string
                $ticket_id = null;
                if (isset($_GET['id'])) {
                    $ticket_id = (int)$_GET['id'];
                } else {
                    // fallback bruto caso o servidor não popular $_GET por algum motivo
                    $parts = parse_url($request_uri);
                    if (!empty($parts['query'])) {
                        parse_str($parts['query'], $qs);
                        if (isset($qs['id'])) {
                            $ticket_id = (int)$qs['id'];
                        }
                    }
                }

                if (!empty($ticket_id)) {
                    // Se houver satisfação pendente para ESTE ticket do usuário logado, força abertura da aba de satisfação
                    if (plugin_forceapproval_has_pending_satisfaction($ticket_id, (int)$_SESSION['glpiID'])) {
                        $url_base = rtrim($CFG_GLPI['url_base'] ?? '/glpi', '/');
                        $redirect = $url_base . "/front/ticket.form.php?id={$ticket_id}&forcetab=Ticket$3";
                        @session_write_close();
                        echo "<script type='text/javascript'>window.location.href = " . json_encode($redirect) . ";</script>";
                        exit();
                    }
                }
            }
        }
    }
    // ====== FIM DO NOVO BLOCO ======

    if ($request_uri !== '') {
        $allowed_patterns = [
            '/plugins/forceapproval/', '/front/logout.php', '/front/ticket', '/front/followup',
            '/front/solution', '/front/itilfollowup', '/front/formvalidation.php',
            '/front/massiveaction.php', '/ajax/'
        ];
        foreach ($allowed_patterns as $pattern) {
            if (strpos($request_uri, $pattern) !== false) { return; }
        }
    }
    
    if (isset($_SESSION['glpiactiveprofile']['interface']) && $_SESSION['glpiactiveprofile']['interface'] === 'helpdesk') {
        $target_url = plugin_forceapproval_get_pending_action((int)$_SESSION['glpiID']);
        if ($target_url !== false) {
            $_SESSION['forceapproval_redirect_url'] = $target_url;
            $redirect_url = PLUGIN_FORCEAPPROVAL_WEBDIR . '/front/popup.php';
            
            @session_write_close();

            echo "<script type='text/javascript'>window.location.href = '$redirect_url';</script>";
            exit();
        }
    }
}
?>
