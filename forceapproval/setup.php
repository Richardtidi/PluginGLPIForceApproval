<?php
/**
 * Plugin forceapproval - Setup
 * GLPI 10.x
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Metadados do plugin
 */
function plugin_version_forceapproval() {
   return [
      'name'           => 'Forçar Aprovação e Avaliação',
      'version'        => '1.1.0',
      'author'         => 'Richard Gabriel',
      'license'        => 'GPLv2+',
      'homepage'       => 'https://github.com/Richardtidi/PluginGLPIForceApproval',
      'minGlpiVersion' => '10.0'
   ];
}

/**
 * Instalação: cria tabela de configuração
 */
function plugin_forceapproval_install() {
   global $DB;

   $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_forceapproval_config` (
               `id` INT AUTO_INCREMENT PRIMARY KEY,
               `entities_id` INT DEFAULT 0,
               `itilcategories_id` INT DEFAULT 0,
               `enabled` TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
   $DB->queryOrDie($query, "Não foi possível criar a tabela glpi_plugin_forceapproval_config");

   return true;
}

/**
 * Desinstalação: remove tabela de configuração
 */
function plugin_forceapproval_uninstall() {
   global $DB;

   // Se preferir manter a tabela ao desinstalar, comente a linha abaixo.
   $DB->query("DROP TABLE IF EXISTS `glpi_plugin_forceapproval_config`");

   return true;
}

/**
 * Faz a chavinha de configuração aparecer na listagem de plugins
 * (GLPI usa esta função para localizar a página de config)
 */
function plugin_forceapproval_getConfigForm() {
   // Caminho relativo ao diretório do plugin
   return 'front/config.form.php';
}

/**
 * Hooks
 */
global $PLUGIN_HOOKS;

// GLPI exige CSRF compliance
$PLUGIN_HOOKS['csrf_compliant']['forceapproval'] = true;

// Mostra a chavinha de configuração (além da função getConfigForm acima)
$PLUGIN_HOOKS['config_page']['forceapproval'] = 'front/config.form.php';

// Executa nossa rotina de bloqueio após a inicialização
$PLUGIN_HOOKS['post_init']['forceapproval'] = 'plugin_forceapproval_force_redirect';
