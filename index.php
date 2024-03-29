<?php
/**
 * Plugin Name: WooCommerce ForumPay Payment Gateway Plugin
 * Plugin URI: https://forumpay.com
 * Description: Extends WooCommerce with ForumPay gateway.
 * Version: 1.3.3
 * Author: Limitlex
 **/


add_action('plugins_loaded', 'woocommerce_forumpay_init', 0);

function woocommerce_forumpay_init()
{

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Gateway class
     */
    class WC_Forumpay extends WC_Payment_Gateway
    {
        protected $spmsg = array();
        public function __construct()
        {

            $this->id = 'forumpay';
            $this->method_title = __('Forumpay', 'forumpay');
            $this->method_description = "Pay with Crypto (by ForumPay)";
            $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo-forumpay-1.svg';

            $this->has_fields = false;
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->pos_id = $this->settings['pos_id'];
            $this->api_url = $this->settings['api_url'] ?? 'https://api.forumpay.com/pay/v2';
            $this->api_user = $this->settings['api_user'];
            $this->api_key = $this->settings['api_key'];
            $this->accept_zero_confirmations = $this->settings['accept_zero_confirmations'] == 'yes' ? true : false;
            $this->currency = get_woocommerce_currency();

            add_action('valid-forumpay-request', array(&$this, 'successful_request'));

            if (version_compare(WOOCOMMERCE_VERSION, '3.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }

            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page_forumpay'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_forumpay_response'));

        }

        function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'forumpay'),
                    'type' => 'checkbox',
                    'label' => __('Enable ForumPay Payment Module.', 'forumpay'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'forumpay'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'forumpay'),
                    'default' => __('Forumpay', 'forumpay')),
                'description' => array(
                    'title' => __('Description:', 'forumpay'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'forumpay'),
                    'default' => __('Pay with Crypto (by ForumPay)', 'forumpay')),
                'api_url' => array(
                    'title' => __('Environment', 'forumpay'),
                    'description' => __('ForumPay environment'),
                    'type' => 'select',
                    'default' => 'Production',
                    'options' => array(
                        'https://api.forumpay.com/pay/v2' => 'Production',
                        'https://sandbox.forumpay.com/api/v2' => 'Sandbox',
                    ),
                ),
                'api_user' => array(
                    'title' => __('API User', 'forumpay'),
                    'type' => 'text',
                    'description' => __('Enter API User Given by ForumPay')),

                'api_key' => array(
                    'title' => __('API Secret', 'forumpay'),
                    'type' => 'text',
                    'description' => __('Enter API Secret Given by ForumPay')),

                'pos_id' => array(
                    'title' => __('POS ID', 'forumpay'),
                    'type' => 'text',
                    'description' => __('Enter your webshop identifier (POS ID)')),

                'accept_zero_confirmations' => array(
                    'title' => __('Accept Zero Confirmations', 'forumpay'),
                    'type' => 'checkbox',
                    'label' => __('Enable Accept Zero Confirmations.', 'forumpay'),
                    'default' => 'no'),
            );

        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options()
        {
            echo '<h3>' . __('ForumPay Payment Gateway', 'forumpay') . '</h3>';
            echo '<p>' . __('Pay with Crypto (by ForumPay)') . '</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';

        }

        function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }

        }

        /**
         * Receipt Page
         **/
        function receipt_page_forumpay($order_id)
        {
            echo $this->generate_forumpay_form($order_id);
        }

        public function generate_forumpay_form($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $amount = $order->get_total();

            $base_path = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__));

            $cForumPayParam = array();
            $CurrencyList = $this->api_call('/GetCurrencyList/', $cForumPayParam);
            if (!$CurrencyList) {
                echo "<p>Could not perform API Call, please check ForumPay plugin settings.</p>";
                return false;
            }

            $apibase = get_site_url() . '/index.php?wc-api=wc_forumpay';

            $qrurl = $apibase . '&act=getqr';
            $sturl = $apibase . '&act=getst';
            $rateurl = $apibase . '&act=getrate';
            $return_url = $this->get_return_url($order);
            $cancel_url = $this->get_return_url($order);

            $extahtm = '';
            $extahtm .= '<snap id="forumpay-qrurl" data="' . $qrurl . '"></snap>';
            $extahtm .= '<snap id="forumpay-rateurl" data="' . $rateurl . '"></snap>';
            $extahtm .= '<snap id="forumpay-sturl" data="' . $sturl . '"></snap>';
            $extahtm .= '<snap id="forumpay-orderid" data="' . $order_id . '"></snap>';
            $extahtm .= '<snap id="forumpay-returl" data="' . $return_url . '"></snap>';
            $extahtm .= '<snap id="forumpay-cancelurl" data="' . $cancel_url . '"></snap>';
            $extahtm .= "<link rel='stylesheet'  href='" . $base_path . "/css/forumpay.css' />";
            $extahtm .= '<script type="text/javascript" src="' . $base_path . '/js/forumpay.js"></script>';

            $logoimg = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo-forumpay-1.svg';
            $loadimg = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/page-load.gif';

            $sCurrencyList = '';

            foreach ($CurrencyList as $Currency) {
                $sCurrencyList .= '<option value=' . $Currency['currency'] . '>' . $Currency['description'] . ' (' . $Currency['currency'] . ')</option>';

            }
            $templatehtml = '<div class="forumpay-main">
	<div class="forumpay-row forumpay-row-img">
 <img src="' . $logoimg . '"  alt="Pay with Crypto (by ForumPay)" />
</div>
<div>
    <div class="forumpay-row">
        <div class="forumpay-col1">Order No</div>
        <div class="forumpay-col2">' . $order_id . '</div>
    </div>
    <div class="forumpay-row">
        <div class="forumpay-col1">' . __('Order amount') . '</div>
        <div class="forumpay-col2">' . $amount . ' ' . $this->currency . '</div>
    </div>
</div>

<div class="forumpay-row forumpay-title" id="forumpay-ccy-div">
    <select name="ChangeCurrency" onChange="forumpaychangecurrency(this.value)">
    <option value="0">' . __('Select Cryptocurrency') . '</option>' . $sCurrencyList . '
    </select>
</div>

<div class="fp-details" style="display: none" id="fp-details-div">

    <details>
        <summary>Details</summary>

        <div class="forumpay-rowsm">
            <div class="forumpay-col1">' . __('Rate') . ':</div>
            <div class="forumpay-col2">
            <snap id="forumpay-exrate"> </snap>
            </div>
        </div>

        <div class="forumpay-rowsm">
            <div class="forumpay-col1">' . __('Exchange amount') . ':</div>
            <div class="forumpay-col2">
            <snap id="forumpay-examt"> </snap>
            </div>
        </div>

        <div class="forumpay-rowsm">
            <div class="forumpay-col1">' . __('Network processing fee') . ':</div>
            <div class="forumpay-col2">
            <snap id="forumpay-netpfee"> </snap>
            </div>
        </div>
    </details>

    <div class="forumpay-row total">
        <div class="forumpay-col1">' . __('Total') . ':</div>
        <div class="forumpay-col2">
        <snap id="forumpay-tot"> </snap>
        </div>
    </div>

    <div class="forumpay-rowsm" id="forumpay-wtime-div">
        <div class="forumpay-col1">' . __('Expected time to wait') . ':</div>
        <div class="forumpay-col2">
        <snap id="forumpay-waittime"> </snap>
        </div>
    </div>

    <div class="forumpay-rowsm" id="forumpay-txfee-div">
        <div class="forumpay-col1">' . __('TX fee set to') . ':</div>
        <div class="forumpay-col2">
        <snap id="forumpay-txfee"> </snap>
    </div>
</div>

<div class="forumpay-row forumpay-qr" style="display: none" id="qr-img-div">
		 <img src="" id="forumpay-qr-img" style="width: 75%">
</div>
<div class="forumpay-row forumpay-addr">
  <snap id="forumpay-addr"></snap>
</div>

<div class="forumpay-row" id="forumpay-btn-div">
    <button type="submit" id="forumpay-payment-btn" class="paybtn" onclick="forumpaygetqrcode()">
        Start payment
    </button>
</div>

</div>

<div class="forumpay-st" id="forumpay-payst-div" style="display: none">
  <p>' . __('Status') . ':</p>
  <snap id="forumpay-payst"> </snap>
</div>

<div class="forumpay-err" id="forumpay-err-div" style="display: none">
  <p>' . __('Error') . ':</p>
  <snap id="forumpay-err"> </snap>
</div>

</div>
<div id="forumpay-loading" style="display: none">
  <img id="forumpay-loading-image" src="' . $loadimg . '" alt="Loading..." />
</div>' . $extahtm;

            return $templatehtml;

        }

        public function api_get_all_payments($orderid) {
            if (!$orderid) {
                throw new Exception('Could not get payments, orderid field is mandatory.');
            }

            $ForumPayParam = array(
                "pos_id" => $this->pos_id,
                "reference_no" => $orderid
            );

            $payres = $this->api_call('/GetTransactions/', $ForumPayParam);

            if (array_key_exists('invoices', $payres)) {
                return $payres['invoices'];
            }

            return [];
        }

        public function api_cancel_payment($payment_id, $currency, $address) {
            $ForumPayParam = array(
                "pos_id" => $this->pos_id,
                "payment_id" => $payment_id,
                "currency" => $currency,
                "address" => $address,
                "reason" => 'other',
                "comment" => 'New payment created, canceling all other'
            );

            $payres = $this->api_call('/CancelPayment/', $ForumPayParam);

            return $payres;
        }

        public function api_call($ForumPay_Method, $ForumPay_Params)
        {
            $rest_url = $this->api_url . $ForumPay_Method;
            $ForumPay_Qr = http_build_query($ForumPay_Params);

            $curl = curl_init(trim($rest_url));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_USERPWD, $this->api_user . ":" . $this->api_key);
            if (!empty($ForumPay_Qr)) {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $ForumPay_Qr);
            }
            $response = curl_exec($curl);

            curl_close($curl);

            return json_decode($response, true);
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {

            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true),
            );

        }

        function check_forumpay_response()
        {
            if ($_GET['wc-api'] == 'wc_forumpay') {
                $this->forumpay_api();
            }
        }

        function forumpay_api()
        {
            global $woocommerce;

            $Forumpay = new WC_Forumpay();
            if ($_REQUEST['act'] == 'webhook') {
                $ipnres = file_get_contents('php://input');

                $ipnrear = json_decode($ipnres, true);
                if (!$ipnrear) {
                    echo "JSON body data missing";
                    exit;
                }

                $ForumPayParam = array(
                    "pos_id" => $ipnrear['pos_id'],
                    "payment_id" => $ipnrear['payment_id'],
                    "address" => $ipnrear['address'],
                    "currency" => $ipnrear['currency'],
                );

                $payres = $this->api_call('/CheckPayment/', $ForumPayParam);

                if (($payres['status'] == 'Confirmed') || ($payres['status'] == 'Cancelled')) {
                    $orderid = $payres['reference_no'];
                    $order = new WC_Order($orderid);

                    if ($payres['status'] == 'Confirmed') {
                        $order->payment_complete();
                        $order->add_order_note('ForumPay Payment Successful');
                        $woocommerce->cart->empty_cart();
                    } else if ($payres['status'] == 'Cancelled') {

                        //check if this is the only payment

                        $this->cancelWooCommerceOrder($orderid, $order);
                    }

                    echo "OK";
                } else {
                    echo "Transaction is pending";
                }

                exit;
            }

            if ($_REQUEST['act'] == 'getrate') {

                $orderid = $_REQUEST['orderid'];
                $order = new WC_Order($orderid);
                $currency_code = $this->currency;
                $total = $order->get_total();
                $azc = $this->accept_zero_confirmations ? 'true' : 'false';

                $ForumPayParam = array(
                    "pos_id" => $this->pos_id,
                    "invoice_currency" => $currency_code,
                    "invoice_amount" => $total,
                    "currency" => $_REQUEST['currency'],
                    "reference_no" => $orderid,
                    "accept_zero_confirmations" => $azc,
                );

                $payres = $this->api_call('/GetRate/', $ForumPayParam);

                if ($payres['err']) {
                    $data['errmgs'] = $payres['err'];
                    $data['status'] = 'No';
                } else {
                    $data['status'] = 'Yes';
                    $data['ordamt'] = $payres['invoice_amount'] . ' ' . $payres['invoice_currency'];
                    $data['exrate'] = '1 ' . $payres['currency'] . ' = ' . $payres['rate'] . ' ' . $payres['invoice_currency'];
                    $data['examt'] = $payres['amount_exchange'];
                    $data['netpfee'] = $payres['network_processing_fee'];
                    $data['amount'] = $payres['amount'] . ' ' . $payres['currency'];
                    $data['payment_id'] = $payres['payment_id'];
                    $data['txfee'] = $payres['fast_transaction_fee'] . ' ' . $payres['fast_transaction_fee_currency'];
                    $data['fast_transaction_fee'] = $payres['fast_transaction_fee'];
                    $data['fast_transaction_fee_currency'] = $payres['fast_transaction_fee_currency'];
                    $data['waittime'] = $payres['wait_time'];
                }

                echo json_encode($data, true);
                exit;
            }

            if ($_REQUEST['act'] == 'getqr') {

                $orderid = $_REQUEST['orderid'];
                $order = new WC_Order($orderid);

                $currency_code = $this->currency;

                $total = $order->get_total();
                $payer_ip_address = trim($order->get_customer_ip_address());
                $payer_user_agent = $order->get_customer_user_agent();
                $payer_email = $order->get_billing_email();
                $azc = $this->accept_zero_confirmations ? 'true' : 'false';

                if (filter_var($payer_ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    $payer_ip_address = null;
                }

                $ForumPayParam = array(
                    "pos_id" => $this->pos_id,
                    "invoice_currency" => $currency_code,
                    "invoice_amount" => $total,
                    "currency" => $_REQUEST['currency'],
                    "reference_no" => $orderid,
                    "payer_ip_address" => $payer_ip_address,
                    "payer_user_agent" => $payer_user_agent,
                    "payer_email" => $payer_email,
                    "accept_zero_confirmations" => $azc,
                );

                $payres = $this->api_call('/StartPayment/', $ForumPayParam);

                if ($payres['err']) {
                    $data['errmgs'] = $payres['err'];
                    $data['status'] = 'No';
                } else {
                    $data['status'] = 'Yes';
                    $data['ordamt'] = $payres['invoice_amount'] . ' ' . $payres['invoice_currency'];
                    $data['exrate'] = '1 ' . $payres['currency'] . ' = ' . $payres['rate'] . ' ' . $payres['invoice_currency'];
                    $data['examt'] = $payres['amount_exchange'];
                    $data['netpfee'] = $payres['network_processing_fee'];

                    $data['addr'] = $payres['address'];
                    $data['qr_img'] = $payres['qr_img'];
                    $data['amount'] = $payres['amount'] . ' ' . $payres['currency'];
                    $data['payment_id'] = $payres['payment_id'];
                    $data['txfee'] = $payres['fast_transaction_fee'] . ' ' . $payres['fast_transaction_fee_currency'];
                    $data['waittime'] = $payres['wait_time'];
                }



                if ($data['status'] === 'Yes') {
                    //cancel all other existing payments
                    $existingPayments = $this->api_get_all_payments($orderid);
                    foreach ($existingPayments as $existingPayment) {
                        if (
                            $existingPayment['payment_id'] === $data['payment_id']
                            || $existingPayment['status'] !== 'Waiting'
                        ) {
                            //newly created
                            continue;
                        }

                        $response = $this->api_cancel_payment($existingPayment['payment_id'], $existingPayment['currency'], $existingPayment['address']);
                    }
                }

                echo json_encode($data, true);
                exit;
            }

            if ($_REQUEST['act'] == 'getst') {

                $orderid = $_REQUEST['orderid'];
                $order = new WC_Order($orderid);

                $currency_code = $this->currency;
                $total = $order->get_total();

                $ForumPayParam = array(
                    "pos_id" => $this->pos_id,
                    "payment_id" => $_REQUEST['paymentid'],
                    "address" => $_REQUEST['addr'],
                    "currency" => $_REQUEST['currency'],
                );

                $payres = $this->api_call('/CheckPayment/', $ForumPayParam);

                $data['status'] = $payres['status'];

                if (($payres['status'] == 'Confirmed') || ($payres['status'] == 'Cancelled')) {

                    if ($payres['status'] == 'Confirmed') {
                        $order->payment_complete();
                        $order->add_order_note('ForumPay Payment Successful');
                        $woocommerce->cart->empty_cart();
                    }

                    if ($payres['status'] == 'Cancelled') {
                        $this->cancelWooCommerceOrder($orderid, $order, $payres['status']);
                    }
                }

                echo json_encode($data, true);
            }
            exit;
        }

        public function cancelWooCommerceOrder($orderid, WC_Order $order)
        {
            $existingPayments = $this->api_get_all_payments($orderid);

            $allPaymentsCancelled = true;
            foreach ($existingPayments as $existingPayment) {
                if ($existingPayment['status'] !== 'Cancelled') {
                    $allPaymentsCancelled = false;
                    break;
                }
            }

            if ($allPaymentsCancelled) {
                $order->update_status('failed', 'Failed, Payment Status : Cancelled');
            }
        }
    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_forumpay_gateway($methods)
    {
        $methods[] = 'WC_Forumpay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_forumpay_gateway');

}
