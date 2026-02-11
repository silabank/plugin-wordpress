<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Limpa opções do banco de dados
delete_option('woocommerce_silapay_settings');

// Remove transientes
global $wpdb;
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%silapay%'");