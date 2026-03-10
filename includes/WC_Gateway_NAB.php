<?php

namespace NabGateway;

use WC_Payment_Gateway;
use CyberSource\ExternalConfiguration;
use CyberSource\Api\UnifiedCheckoutCaptureContextApi;
use CyberSource\ApiClient;
use CyberSource\ApiException;
use CyberSource\Api\PaymentsApi;
use CyberSource\Model\Ptsv2paymentsClientReferenceInformation;
use CyberSource\Model\Ptsv2paymentsOrderInformationAmountDetails;
use CyberSource\Model\Ptsv2paymentsOrderInformationBillTo;
use CyberSource\Model\Ptsv2paymentsOrderInformation;
use CyberSource\Model\Ptsv2paymentsTokenInformation;
use CyberSource\Model\CreatePaymentRequest;

if (! defined('ABSPATH')) {
    exit;
}

class WC_Gateway_NAB extends WC_Payment_Gateway
{
    private static $instance = null;
    private const PROD_URL = 'nabgateway-api.nab.com.au';
    private const TEST_URL = 'nabgateway-api-test.nab.com.au';
    private const CAPTURE_CHECKOUT_CONTEXT = '/up/v1/capture-contexts';


    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->id                 = 'nab_rest';
        $this->method_title       = 'NAB Payment Gateway (REST)';
        $this->method_description = 'Pay using NAB SecurePay REST API';
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled     = $this->get_option('enabled');
        $this->title       = $this->get_option('title');
        $this->org_id   = $this->get_option('org_id');
        $this->api_key     = $this->get_option('api_key');
        $this->api_secret  = $this->get_option('api_secret');
        $this->test_mode   = $this->get_option('test_mode') === 'yes';


        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [$this, 'process_admin_options'],
        );

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);


        $merchant_id = $this->org_id;
        $api_key     = $this->api_key;
        $shared_secret = $this->api_secret;
        $host = $this->test_mode ? self::TEST_URL : self::PROD_URL;
        $this->commonElement = new ExternalConfiguration(merchantID: $merchant_id, apiKeyID: $api_key, secretKey: $shared_secret, host: $host);
    }


    public function nab_payment_endpoint(WP_REST_Server $wp_rest_server)
    {
        register_rest_route(
            ‘nab / v1’,
            ‘ / payment’,
            [
                ‘methods’ => "POST",
                ‘callback’ => [$this,'nab_payment_update_status_hook'],
                ‘permission_callback’ => ‘__return_true’,
            ]
        );
    }

    public function nab_payment_update_status_hook() {}

    public function get_base_url()
    {
        return "https://" . ($this->test_mode ? self::TEST_URL : self::PROD_URL);
    }

    public function init_form_fields()
    {
        $this->form_fields = [

            'enabled' => [
                'type'    => 'checkbox',
                'label'   => 'Enable NAB Gateway',
                'default' => 'no',
            ],

            'title' => [
                'type'    => 'text',
                'title'   => 'Title',
                'default' => 'Credit Card (NAB)',
            ],

            'org_id' => [
                'type'        => 'text',
                'title'       => 'Organization ID',
                'description' => 'Organization id of your Account',
                'desc_tip'    => true,
            ],

            'api_key' => [
                'type'        => 'text',
                'title'       => 'API Key',
                'description' => 'NAB REST API Key',
                'desc_tip'    => true,
            ],

            'api_secret' => [
                'type'        => 'password',
                'title'       => 'API Secret',
                'description' => 'NAB REST API Secret',
                'desc_tip'    => true,
            ],

            'test_mode' => [
                'type'    => 'checkbox',
                'label'   => 'Enable Sandbox Mode',
                'default' => 'yes',
            ],
        ];
    }

    public function ajax_get_capture_context()
    {
        check_ajax_referer('nab_checkout_nonce');

        $order_id = absint($_POST['order_id']) ;

        try {
            $jwt = $this->nab_get_capture_context_jwt($order_id);

            wp_send_json_success(['jwt' => $jwt]);
            wp_die();

        } catch (Exception $e) {

            wp_send_json_error([
                'message' => $e->getMessage(),
            ], 500);
            wp_die();
        }
    }

    public function nab_verify_payment()
    {
        check_ajax_referer('nab_checkout_nonce');

        $order_id = intval($_POST['order_id']);
        $jwt = $_POST['jwt'];

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Invalid order']);
        }
        $transaction_id = '';
        // $order->payment_complete($transaction_id);

        // $order->add_order_note('NAB payment captured successfully. Transaction ID: ' . $transaction_id);

        // wc_reduce_stock_levels($order->get_id());

        // WC()->cart->empty_cart();


        $res = $this->PaymentWithFlexToken($jwt, $order);

        $this->nab_log("nab payment logs", $res);

        if ($res) {
            // $this->updatePaymentStatus($res->id);
            $payment_id = $res->id;
            $order->set_transaction_id($payment_id);
            $order->save();
        }

        wp_send_json_success([
            'redirect' => $order->get_checkout_order_received_url(),
        ]);
    }

    public function payment_scripts()
    {
        global $wp_query;

        if (
            ! isset($wp_query->virtual_page)
            || $wp_query->virtual_page->getUrl() !== 'nab-payment'
        ) {
            return;
        }

        wp_enqueue_style(
            'nab-checkout-style',
            plugin_dir_url(__FILE__) . 'css/style.css',
            [],
            '1.0.1',
            'all'
        );

        wp_enqueue_script(
            'nab-checkout',
            plugin_dir_url(__FILE__) . 'js/nab-checkout.js',
            ['jquery'],
            '1.1.4',
            true,
        );
        $order_id = $_GET['order_id'];
        $order = wc_get_order($order_id);
        $totalAmount = number_format((float) $order->get_total(), 2, '.', '');

        wp_localize_script('nab-checkout', 'nab_ajax', [
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('nab_checkout_nonce'),
            'order_id'  => isset($_GET['order_id']) ? absint($_GET['order_id']) : 0,
            'order_key' => isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '',
            'redirect' => $order->get_checkout_order_received_url(),
            'total' => $totalAmount,
        ]);

    }

    public function nab_get_capture_context_jwt($order_id)
    {
        $order = wc_get_order($order_id);
        $totalAmount = number_format((float) $order->get_total(), 2, '.', '');
        $currency    = $order->get_currency();
        $buildingNumberBill = trim($order->get_billing_address_1());
        $buildingNumberShip = trim($order->get_shipping_address_1());

        if (empty($buildingNumberBill)) {
            $buildingNumberBill = "1";
        }

        if (empty($buildingNumberShip)) {
            $buildingNumberShip = "1";
        }
        $payload = [
            "clientVersion" => "0.30",
            "targetOrigins" => [ get_site_url() ],
            "allowedCardNetworks" => ["VISA", "MASTERCARD"],
            "allowedPaymentTypes" => ["PANENTRY"],
            "country" => $order->get_billing_country(),
            "locale"  => "en_" . $order->get_billing_country(),

            "captureMandate" => [
                "billingType" => "FULL",
                "requestEmail" => true,
                "requestPhone" => true,
                "requestShipping" => true,
                "shipToCountries" => ["US", "GB"],
                "showAcceptedNetworkIcons" => true,
            ],

            "completeMandate" => [
                "type" => "AUTH",
            ],

            "data" => [
                "orderInformation" => [
                    "billTo" => [
                        "country" => $order->get_billing_country(),
                        "firstName" => $order->get_billing_first_name(),
                        "lastName" => $order->get_billing_last_name(),
                        "phoneNumber" => $order->get_billing_phone(),
                        "address1" => $order->get_billing_address_1(),
                        "address2" => $order->get_billing_address_2(),
                        "postalCode" => $order->get_billing_postcode(),
                        "locality" => $order->get_billing_city(),
                        "administrativeArea" => $order->get_billing_state(),
                        "buildingNumber" => $buildingNumberBill,
                        "email" => $order->get_billing_email(),
                    ],
                    "shipTo" => [
                        "country" => $order->get_shipping_country(),
                        "firstName" => $order->get_shipping_first_name(),
                        "lastName" => $order->get_shipping_last_name(),
                        "address1" => $order->get_shipping_address_1(),
                        "address2" => $order->get_shipping_address_2(),
                        "postalCode" => $order->get_shipping_postcode(),
                        "locality" => $order->get_shipping_city(),
                        "administrativeArea" => $order->get_shipping_state(),
                        "buildingNumber" => $buildingNumberShip,
                    ],
                    "amountDetails" => [
                        "totalAmount" => (string) $totalAmount,
                        "currency"    => $currency,
                    ],
                ],
                "clientReferenceInformation" => [
                    "code" => (string) $order_id,
                ],
            ],
        ];
        $payload_json = json_encode($payload);

        $commonElement = $this->commonElement;
        $config = $commonElement->ConnectionHost();
        $merchantConfig = $commonElement->merchantConfigObject();
        $apiClient = new ApiClient($config, $merchantConfig);
        $apiInstance = new UnifiedCheckoutCaptureContextApi($apiClient);

        try {
            $apiResponse = $apiInstance->generateUnifiedCheckoutCaptureContext($payload_json);
            $captureContext = $apiResponse[0];
            return $captureContext;
        } catch (ApiException $e) {

            $this->nab_log('CyberSource API Exception', [
                'message' => $e->getMessage(),
                'response' => $e->getResponseBody(),
                'code' => $e->getCode(),
            ]);

            return false;

        } catch (\Exception $e) {

            $this->nab_log('General Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    private function nab_log($message, $context = [])
    {
        $log_file = plugin_dir_path(__FILE__) . '../nab-debug.log';

        $date = date('Y-m-d H:i:s');

        $log_entry = "[$date] " . $message;

        if (!empty($context)) {
            $log_entry .= "\nContext: " . print_r($context, true);
        }

        $log_entry .= "\n----------------------------------------\n";

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order->update_status('pending', 'Awaiting NAB payment');
        // wc_reduce_stock_levels($order_id);
        // WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_payment_page_url($order),
        ];
    }

    private function get_payment_page_url($order)
    {
        return add_query_arg(
            [
                'order_id' => $order->get_id(),
                'key'      => $order->get_order_key(),
            ],
            home_url('/nab-payment')
        );
    }

    private function PaymentWithFlexToken($jwt, $order): object|bool
    {

        $totalAmount = number_format((float) $order->get_total(), 2, '.', '');
        $currency    = $order->get_currency();
        $buildingNumberBill = trim($order->get_billing_address_1());

        if (empty($buildingNumberBill)) {
            $buildingNumberBill = "1";
        }

        $clientReferenceInformationArr = [
            "code" => (string) $order->get_id(),
        ];
        $clientReferenceInformation = new Ptsv2paymentsClientReferenceInformation($clientReferenceInformationArr);

        $orderInformationAmountDetailsArr = [
            "totalAmount" => $totalAmount,
            "currency" => $currency,
        ];
        $orderInformationAmountDetails = new Ptsv2paymentsOrderInformationAmountDetails($orderInformationAmountDetailsArr);


        $orderInformationBillToArr = [
            "country" => $order->get_billing_country(),
            "firstName" => $order->get_billing_first_name(),
            "lastName" => $order->get_billing_last_name(),
            "phoneNumber" => $order->get_billing_phone(),
            "address1" => $order->get_billing_address_1(),
            "address2" => $order->get_billing_address_2(),
            "postalCode" => $order->get_billing_postcode(),
            "locality" => $order->get_billing_city(),
            "administrativeArea" => $order->get_billing_state(),
            "buildingNumber" => $buildingNumberBill,
            "email" => $order->get_billing_email(),
        ];
        $orderInformationBillTo = new Ptsv2paymentsOrderInformationBillTo($orderInformationBillToArr);

        $orderInformationArr = [
            "amountDetails" => $orderInformationAmountDetails,
            "billTo" => $orderInformationBillTo,
        ];
        $orderInformation = new Ptsv2paymentsOrderInformation($orderInformationArr);

        $tokenInformationArr = [
            "transientTokenJwt" => $jwt,
        ];
        $tokenInformation = new Ptsv2paymentsTokenInformation($tokenInformationArr);

        $requestObjArr = [
            "clientReferenceInformation" => $clientReferenceInformation,
            "orderInformation" => $orderInformation,
            "tokenInformation" => $tokenInformation,
        ];
        $requestObj = new CreatePaymentRequest($requestObjArr);


        $commonElement = $this->commonElement;
        $config = $commonElement->ConnectionHost();
        $merchantConfig = $commonElement->merchantConfigObject();

        $api_client = new ApiClient($config, $merchantConfig);
        $api_instance = new PaymentsApi($api_client);

        try {
            [$apiResponse,$status] = $api_instance->createPayment($requestObj);
            if ($status == 201) {
                return $apiResponse;
            }
        } catch (ApiException $e) {
            $errorCode = $e->getCode();

            $this->nab_log('CyberSource API Exception', [
                'message' => $e->getMessage(),
                'response' => $e->getResponseBody(),
                'code' => $e->getCode(),
            ]);
        }
        return false;
    }

    //TODO:  set up a webhook for checking a payment is completed or failed
    private function updatePaymentStatus($id): void {}
}
