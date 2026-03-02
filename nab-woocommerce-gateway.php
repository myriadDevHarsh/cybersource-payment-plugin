<?php

/**
 * Plugin Name: WooCommerce NAB Payment Gateway
 * Plugin URI: https://example.com
 * Description: Accept payments via NAB Payment Gateway in WooCommerce.
 * Version: 1.0.0
 * Author: Harshvardhan Zala
 * Text Domain: wc-nab-gateway
 */
define('NAB_PLUGIN_DIR', plugin_dir_path(__FILE__));

if (file_exists(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
    require_once plugin_dir_path(__FILE__) . 'includes/ExternalConfiguration.php';
}

use NabGateway\WC_Gateway_NAB;
use scr\core\Page;
use scr\core\Controller;
use scr\core\TemplateLoader;

if (! defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'wc_nab_gateway_init', 11);

function wc_nab_gateway_init()
{
    if (! class_exists('WC_Payment_Gateway')) {
        return;
    }

    $controller = new Controller(new TemplateLoader());

    /*
    |--------------------------------------------------------------------------
    | Register Virtual Pages
    |--------------------------------------------------------------------------
    */
    add_action('gm_virtual_pages', function ($ctrl) {

        $ctrl->addPage(
            new Page(
                'nab-payment',
                'Payment Page',
                plugin_dir_path(__FILE__) . 'scr/core/view/template.php'
            )
        );

    });

    /*
    |--------------------------------------------------------------------------
    | Hook Controller
    |--------------------------------------------------------------------------
    */
    add_action('init', [$controller, 'init']);

    add_filter(
        'do_parse_request',
        [$controller, 'dispatch'],
        PHP_INT_MAX,
        2
    );

    /*
    |--------------------------------------------------------------------------
    | WooCommerce
    |--------------------------------------------------------------------------
    */
    add_action('wp_ajax_nab_get_capture_context', [WC_Gateway_NAB::get_instance(), 'ajax_get_capture_context']);
    add_action('wp_ajax_nab_verify_payment', [WC_Gateway_NAB::get_instance(), 'nab_verify_payment']);
    // add_action('wp_ajax_nopriv_nab_get_capture_context', [WC_Gateway_NAB::get_instance(), 'ajax_get_capture_context']);

    add_filter('woocommerce_payment_gateways', function ($methods) {
        $methods[] = WC_Gateway_NAB::class;
        return $methods;
    });
}
