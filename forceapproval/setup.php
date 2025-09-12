<?php
/**
 * Plugin forceapproval - Configuração inicial
 */
function plugin_version_forceapproval() {
    return [
        'name'           => 'Forçar Aprovação e Avaliação',
        'version'        => '1.0.0',
        'author'         => 'Richard Gabriel',
        'license'        => 'GPLv2+',
        'minGlpiVersion' => '10.0.0'
    ];
}

function plugin_forceapproval_install() { return true; }
function plugin_forceapproval_uninstall() { return true; }

global $PLUGIN_HOOKS;
$PLUGIN_HOOKS['csrf_compliant']['forceapproval'] = true;
$PLUGIN_HOOKS['post_init']['forceapproval'] = 'plugin_forceapproval_force_redirect';