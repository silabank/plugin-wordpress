<?php
/**
 * Plugin Name: Silapay Checkout
 * Plugin URI: https://silapay.pro/
 * Description: Gateway simples para Silapay com Cartão, PIX e Boleto
 * Version: 1.0.0
 * Author: Silapay
 * License: GPL v2 or later
 * Text Domain: silapay-checkout
 * WC requires at least: 4.0
 */

defined('ABSPATH') || exit;

// Verifica WooCommerce
add_action('plugins_loaded', 'silapay_checkout_init');

function silapay_checkout_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo 'Silapay Checkout requer WooCommerce.';
            echo '</p></div>';
        });
        return;
    }
    
    // Inclui a classe
    require_once plugin_dir_path(__FILE__) . 'includes/class-silapay-checkout.php';
    require_once plugin_dir_path(__FILE__) . 'includes/webhook.php';
    
    // Adiciona gateway
    add_filter('woocommerce_payment_gateways', function($gateways) {
        $gateways[] = 'WC_Silapay_Checkout';
        return $gateways;
    });
}

// Link de configuração
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=silapay_checkout') . '">Configurações</a>';
    array_unshift($links, $settings_link);
    return $links;
});