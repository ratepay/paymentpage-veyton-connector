<?php

class PiRatepayPayment
{

    // Veyton Payment Settings
    public $external = true;
    public $TARGET_URL;
    public $TARGET_PARAMS = array();

    public function __construct()
    {
        try {
            global $page;
            $this->_checkoutDir = dirname(__FILE__) . "/checkout/";
            $this->_webRoot = _SYSTEM_BASE_URL . _SRV_WEB . _SRV_WEB_PLUGINS . get_class($this) . '/';
            $this->fillData();
            $this->handlePageAction($page->page_name, $page->page_action);
        } catch (Exception $e) {
            print_r($e);
        }
    }

    /**
     * fills all data neccasary for RatePAY.
     *
     */
    protected function fillData()
    {
        global $language, $currency, $page;

        $this->data['payment_icon'] = $this->getIcon();
        $this->data['payment_name'] = 'RatePAY';
        $this->data['img_path'] = $this->_webRoot . 'images/';
        $this->data['country_code'] = _STORE_COUNTRY;
        $this->data['language_code'] = $language->code;
        $this->data['currency_code'] = $currency->code;
        if ($_SESSION['customer']->customers_id > 0) {
            $dob = '';
            if (!empty($_SESSION['customer']->customer_default_address['customers_dob'])) {
                $dob = explode('.', $_SESSION['customer']->customer_default_address['customers_dob']);
                $dob = $dob[2] . "-" . $dob[1] . "-" . $dob[0];
            }


            $this->data['ratepay']['customer'] = array(
                'first_name' => $_SESSION['customer']->customer_default_address['customers_firstname'],
                'last_name' => $_SESSION['customer']->customer_default_address['customers_lastname'],
                'title' => '',
                'email' => $_SESSION['customer']->customer_info['customers_email_address'],
                'date_of_birth' => $dob,
                'gender' => $_SESSION['customer']->customer_default_address['customers_gender'],
                'phone' => $_SESSION['customer']->customer_default_address['customers_phone'],
                'fax' => $_SESSION['customer']->customer_default_address['customers_fax'],
                'mobile' => '',
                'company_name' => $_SESSION['customer']->customer_default_address['customers_company'],
                'vat_id' => $_SESSION['customer']->customer_info['customers_vat_id'],
                'nationality' => $_SESSION['customer']->customer_default_address['customers_country_code'],
                'billing_address' => array(
                    'street' => $_SESSION['customer']->customer_payment_address['customers_street_address'],
                    'street_number' => '',
                    'zip' => $_SESSION['customer']->customer_payment_address['customers_postcode'],
                    'city' => $_SESSION['customer']->customer_payment_address['customers_city'],
                    'country' => $_SESSION['customer']->customer_payment_address['customers_country_code']
                ),
                'shipping_address' => array(
                    'street' => $_SESSION['customer']->customer_shipping_address['customers_street_address'],
                    'street_number' => '',
                    'zip' => $_SESSION['customer']->customer_shipping_address['customers_postcode'],
                    'city' => $_SESSION['customer']->customer_shipping_address['customers_city'],
                    'country' => $_SESSION['customer']->customer_shipping_address['customers_country_code']
                )
            );
        }
        $this->handlePageAction($page->page_name, $page->page_action);
    }

    /**
     * sepperates street and streetnumber.
     *
     * @param  String $address
     * @return array
     */
    protected function splitAddress($address)
    {
        $addressMatches = array();
        preg_match_all("([0-9]+(?:[a-zA-Z][a-zA-Z]?(?![a-zA-Z]))?)", $address, $addressMatches);
        return array(
            str_replace(' ' . $addressMatches[0][0], '', $address),
            @$addressMatches[0][0],
            ""
        );
    }

    /**
     * Returns the icon for the payment module.
     *
     * @return string
     */
    protected function getIcon()
    {
        return 'ratepayLogo.png';
    }

    /**
     * Performs various actions depending on where in the checkout process
     * the end customer is.
     *
     * @param  string  $page
     * @param  string  $action
     * @return void
     */
    protected function handlePageAction($page, $action)
    {
        if ($page == 'checkout') {
            if ($action == 'payment' || $action == 'confirmation') {
                $this->data['spec_html'] = $this->setPaymentData();
                $payment = new payment();
                $payment->_getPossiblePayment();
            }
            if ($action == 'payment' && $_REQUEST['ratepayFailure'] == true) {
                $_SESSION['ratepay_not_allowed'] = true;
                $_SESSION['selected_payment'] = "";
                $this->setPaymentData('notAccepted');
            }
            if ($action == 'shipping') {
                $this->updateData();
            }
        }
    }

    /**
     * set payment data and error messages
     *
     * @param  string  $error
     */
    protected function setPaymentData($error = false)
    {
        $this->_hideRatepayPayment();
        $this->data['ratepay_payment_name'] = sprintf("<img src='%s/ratepayLogo.png' alt = 'RatePAY' style='margin:3px 0;'/>", $this->data['img_path']);
        define("TEXT_PAYMENT_RATEPAY", sprintf("<img src='%s/ratepayLogo.png' alt = 'RatePAY' style='margin:3px 0;'/>", $this->data['img_path']));
        if ($error == 'notAccepted' || $_SESSION['ratepay_not_allowed'] == true) {
            $this->data['ratepay_error'] = sprintf("Leider ist eine Bezahlung mit RatePAY nicht m&ouml;glich. Diese Entscheidung ist von RatePAY "
                    . "auf der Grundlage einer automatisierten Datenverarbeitung getroffen worden. Einzelheiten erfahren Sie in der "
                    . "<a href='%s' target='_blank'>RatePAY-Datenschutzerkl&auml;rung.</a>", PIRATEPAYPAYMENT_AGB);
        } else {
            $this->data['ratepay_error'] = false;
        }

        if (!isset($_SESSION['ratepay']['customer'])) {
            $this->updateData();
        }
    }

    private function _hideRatepayPayment()
    {
        $this->data['hide_ratepay'] = false;
        $totalSum = $_SESSION['cart']->total['plain'];
        $basketMax = floatval(str_replace(',', '.', PIRATEPAYPAYMENT_BASKET_MAX));
        $basketMin = floatval(str_replace(',', '.', PIRATEPAYPAYMENT_BASKET_MIN));
        $billing = $_SESSION['customer']->customer_payment_address;
        $shipping = $_SESSION['customer']->customer_shipping_address;
        if ($totalSum > $basketMax || $totalSum < $basketMin)
            $this->data['hide_ratepay'] = true;
        if ($billing['customers_country_code'] != "DE")
            $this->data['hide_ratepay'] = true;
        if ($shipping['customers_country_code'] != "DE")
            $this->data['hide_ratepay'] = true;
        if (count(array_diff($billing, $shipping)))
            $this->data['hide_ratepay'] = true;
    }

    /**
     * Executed if no session data is available or if the shipping address is changed.
     *
     * @return void
     */
    protected function updateData()
    {
        $_SESSION['ratepay']['customer'] = array(
            'first_name' => $_SESSION['customer']->customer_default_address['customers_firstname'],
            'last_name' => $_SESSION['customer']->customer_default_address['customers_lastname'],
            'title' => '',
            'email' => $_SESSION['customer']->customer_info['customers_email_address'],
            'date_of_birth' => $_SESSION['customer']->customer_default_address['customers_dob'],
            'gender' => $_SESSION['customer']->customer_default_address['customers_gender'],
            'phone' => $_SESSION['customer']->customer_default_address['customers_phone'],
            'fax' => $_SESSION['customer']->customer_default_address['customers_fax'],
            'mobile' => '',
            'company_name' => $_SESSION['customer']->customer_default_address['customers_company'],
            'vat_id' => $_SESSION['customer']->customer_info['customers_vat_id'],
            'nationality' => $_SESSION['customer']->customer_default_address['customers_country_code'],
            'billing_address' => array(
                'street' => $_SESSION['customer']->customer_payment_address['customers_street_address'],
                'street_number' => '',
                'zip' => $_SESSION['customer']->customer_payment_address['customers_postcode'],
                'city' => $_SESSION['customer']->customer_payment_address['customers_city'],
                'country' => $_SESSION['customer']->customer_payment_address['customers_country_code']
            ),
            'shipping_address' => array(
                'street' => $_SESSION['customer']->customer_shipping_address['customers_street_address'],
                'street_number' => '',
                'zip' => $_SESSION['customer']->customer_shipping_address['customers_postcode'],
                'city' => $_SESSION['customer']->customer_shipping_address['customers_city'],
                'country' => $_SESSION['customer']->customer_shipping_address['customers_country_code']
            )
        );
    }

    /**
     * Sets new Customer Data changed at the RatePAY paypage
     *
     * @param  array  $customer
     * @return void
     */
    public static function setNewCustomerData($customer)
    {
        $_SESSION['ratepay']['orderdata']['delivery']['customers_gender'] = $customer['gender'];
        $_SESSION['ratepay']['orderdata']['delivery']['customers_dob'] = $customer['date_of_birth'];
        $_SESSION['ratepay']['orderdata']['delivery']['customers_phone'] = $customer['phone'];
        $_SESSION['ratepay']['orderdata']['delivery']['customers_fax'] = $customer['fax'];
        $_SESSION['ratepay']['orderdata']['delivery']['customers_company'] = $customer['company_name'];
        $_SESSION['ratepay']['orderdata']['delivery']['customers_firstname'] = $customer['first_name'];
        $_SESSION['ratepay']['orderdata']['delivery']['customers_lastname'] = $customer['last_name'];
        $_SESSION['ratepay']['orderdata']['delivery']['customers_street_address'] = $customer['shipping_address']['street'] . " " . $customer['shipping_address']['street_number'];
        $_SESSION['ratepay']['orderdata']['delivery']['customers_postcode'] = $customer['shipping_address']['zip'];
        $_SESSION['ratepay']['orderdata']['delivery']['customers_city'] = $customer['shipping_address']['city'];
        $_SESSION['ratepay']['orderdata']['delivery']['customers_country'] = $customer['billing_address']['country'];

        $_SESSION['ratepay']['orderdata']['billing']['customers_gender'] = $customer['gender'];
        $_SESSION['ratepay']['orderdata']['billing']['customers_dob'] = $customer['date_of_birth'];
        $_SESSION['ratepay']['orderdata']['billing']['customers_phone'] = $customer['phone'];
        $_SESSION['ratepay']['orderdata']['billing']['customers_fax'] = $customer['fax'];
        $_SESSION['ratepay']['orderdata']['billing']['customers_company'] = $customer['company_name'];
        $_SESSION['ratepay']['orderdata']['billing']['customers_firstname'] = $customer['first_name'];
        $_SESSION['ratepay']['orderdata']['billing']['customers_lastname'] = $customer['last_name'];
        $_SESSION['ratepay']['orderdata']['billing']['customers_street_address'] = $customer['billing_address']['street'] . " " . $customer['billing_address']['street_number'];
        $_SESSION['ratepay']['orderdata']['billing']['customers_postcode'] = $customer['billing_address']['zip'];
        $_SESSION['ratepay']['orderdata']['billing']['customers_city'] = $customer['billing_address']['city'];
        $_SESSION['ratepay']['orderdata']['billing']['customers_country'] = $customer['billing_address']['country'];
    }
    
    private function _getOrderId()
    {
        global $db;
        return $db->GetOne('SELECT MAX(orders_id) + 1 from xt_orders');
    }

    /**
     * Assembles all the necessary information and sends it to RatePAY
     *
     * Redirects the user in case of error and displays error message.
     *
     * @param  array  &$order_data
     * @return void
     */
    public function goToPaypage($order_data, $payment_module_data)
    {
        global $xtLink, $checkout;
        $itemArray = array();
        $orderID = $this->_getOrderId();
        foreach ($_SESSION['cart']->show_content as $article) {
            array_push($itemArray, array(
                'article_number' => $article['products_model'],
                'article_name'   => $article['products_name'],
                'quantity'       => $article['products_quantity'],
                'unit_price'     => $article['products_price']['plain_otax'],
                'total_price'    => $article['products_final_price']['plain_otax'],
                'tax'            => $article['products_final_tax']['plain']
            ));
        }
        foreach ($_SESSION['cart']->show_sub_content as $article) {
            array_push($itemArray, array(
                'article_number' => $article['products_model'],
                'article_name'   => $article['products_name'] . " " . $article['products_key'],
                'quantity'       => $article['products_quantity'],
                'unit_price'     => $article['products_price']['plain_otax'],
                'total_price'    => $article['products_final_price']['plain_otax'],
                'tax'            => $article['products_final_tax']['plain']
            ));
        }
        $taxTotal = 0;
        foreach ($_SESSION['cart']->tax as $tax) {
            $taxTotal += $tax['tax_value']['plain'];
        }
        $_SESSION['ratepay']['orderdata'] = $order_data;
        $params = array(
            'jsonrpc' => '2.0',
            'method' => 'initialisation',
            'params' => array(array(
                    'profile_id' => PIRATEPAYPAYMENT_PROFILE_ID,
                    'security_code' => PIRATEPAYPAYMENT_SECRET,
                    'success_url' => $xtLink->_link(array('page' => 'checkout', 'paction' => 'success', 'params' => 'ratepaySuccess=true')),
                    'failure_url' => $xtLink->_link(array('page' => 'checkout', 'paction' => 'payment', 'params' => 'ratepayFailure=true')),
                    'amount' => $_SESSION['cart']->total['plain'],
                    'order_id' => $orderID,
                    'currency' => $this->data['currency_code'],
                    'tax' => $taxTotal,
                    'items' => $itemArray,
                    'customer' => $this->data['ratepay']['customer'],
                    'merchant' => array(
                        'name' => PIRATEPAYPAYMENT_MERCHANT_NAME,
                        'street' => PIRATEPAYPAYMENT_MERCHANT_STREET,
                        'zip' => PIRATEPAYPAYMENT_MERCHANT_ZIP,
                        'city' => PIRATEPAYPAYMENT_MERCHANT_CITY,
                        'phone' => PIRATEPAYPAYMENT_MERCHANT_PHONE,
                        'fax' => PIRATEPAYPAYMENT_MERCHANT_FAX,
                        'email' => PIRATEPAYPAYMENT_MERCHANT_MAIL,
                        'factorbank' => PIRATEPAYPAYMENT_BANK_CREDIT,
                        'bank_location' => PIRATEPAYPAYMENT_BANK_LOCATION
                    ),
                    'flags' => array(
                        'edit_customer' => PIRATEPAYPAYMENT_EDIT_CUSTOMER == 1 ? "true" : "false",
                        'disable_items' => PIRATEPAYPAYMENT_DISABLE_ITEM == 1 ? "true" : "false",
                    )
            )),
            'id' => md5(PIRATEPAYPAYMENT_PROFILE_ID . PIRATEPAYPAYMENT_SECRET . $orderID)
        );
        $ch = curl_init(PiRatepayPayment::_getRequestUrl() . 'api/1.0/rppaypageapi.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        $return = curl_exec($ch);
        $decoded = json_decode($return);
        if (!isset($decoded->result->token)) {
            $xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'payment', 'params' => 'ratepayFailure=true')));
        }
        $payment = $checkout->_getPayment();
        $_SESSION['ratepay']['payment'] = $payment[$_SESSION['selected_payment']];
        $_SESSION['ratepay']['orderID'] = $orderID;
        $_SESSION['ratepay']['moduleData'] = $payment_module_data;
        $_SESSION['ratepay']['transactionToken'] = $decoded->result->token;
        $_SESSION['ratepay']['amount'] = $_SESSION['cart']->total_physical['plain'];
        $_SESSION['ratepay']['currency'] = $this->data['currency_code'];
        $_SESSION['ratepay']['resulturl'] = PiRatepayPayment::_getRequestUrl() . "paypage/payment/show/lang/de/token/"
                . $decoded->result->token;
    }

    public function checkRatepayResult()
    {
        global $xtLink;
        $xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'success')));
    }

    /**
     * Checks if customer has changed data at paypage and sets the new customer data
     *
     * @return void
     */
    public static function checkCustomerData()
    {
        try {
            $customerDataParams = array(
                'jsonrpc' => '2.0',
                'method' => 'customerdata',
                'params' => array(array(
                        'amount' => $_SESSION['ratepay']['amount'],
                        'currency' => $_SESSION['ratepay']['currency'],
                        'profile_id' => PIRATEPAYPAYMENT_PROFILE_ID,
                        'security_code' => PIRATEPAYPAYMENT_SECRET,
                        'token' => $_SESSION['ratepay']['transactionToken']
                )),
                'id' => md5(PIRATEPAYPAYMENT_PROFILE_ID . PIRATEPAYPAYMENT_SECRET . $_SESSION['ratepay']['orderID'])
            );
            $ch = curl_init(PiRatepayPayment::_getRequestUrl() . 'api/1.0/rppaypageapi.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customerDataParams));
            $return = curl_exec($ch);
            $decoded = json_decode($return);
            $customer = (array) $decoded->result->customer;
            $customer['shipping_address'] = (array) $customer['shipping_address'];
            $customer['billing_address'] = (array) $customer['billing_address'];
            $dob = explode(" ", $customer['date_of_birth']);
            $dob = explode("-", $dob[0]);
            $dob = $dob[2] . "." . $dob[1] . "." . $dob[0];
            $customer['date_of_birth'] = $dob;
            $customer['gender'] = strtolower($customer['gender']);
            $_SESSION['ratepay']['customer']['gender'] = strtolower($_SESSION['ratepay']['customer']['gender']);
            $result = array_diff($customer, $_SESSION['ratepay']['customer']);
            if (!empty($result)) {
                PiRatepayPayment::setNewCustomerData($customer);
            }

            if ($decoded->result->success == "successful") {
                PiRatepayPayment::finalizeOrder();
            } else {
                global $xtLink;
                $xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'payment', 'params' => 'ratepayFailure=true')));
            }
        } catch (Exception $e) {
            print_r($e);
        }
    }

    /**
     * Finalizes order at Ratepay and saves in in the db.
     *
     * @return void
     */
    public static function finalizeOrder()
    {
        try {
            $finalizationParams = array(
                'jsonrpc' => '2.0',
                'method' => 'finalisation',
                'params' => array(array(
                        'amount' => $_SESSION['ratepay']['amount'],
                        'currency' => $_SESSION['ratepay']['currency'],
                        'profile_id' => PIRATEPAYPAYMENT_PROFILE_ID,
                        'security_code' => PIRATEPAYPAYMENT_SECRET,
                        'token' => $_SESSION['ratepay']['transactionToken']
                )),
                'id' => md5(PIRATEPAYPAYMENT_PROFILE_ID . PIRATEPAYPAYMENT_SECRET . $_SESSION['ratepay']['orderID'])
            );
            $ch = curl_init(PiRatepayPayment::_getRequestUrl() . 'api/1.0/rppaypageapi.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($finalizationParams));
            $return = curl_exec($ch);
            $decoded = json_decode($return);
            if ($decoded->result->success == "successful") {
                $order_data = $_SESSION['ratepay']['orderdata'];
                $order = new order();
                if (empty($_SESSION['last_order_id'])) {
                    $processed_data = $order->_setOrder($order_data, 'complete', 'insert');
                    $_SESSION['last_order_id'] = $processed_data['orders_id'];
                } else {
                    $processed_data = $order->_setOrder($order_data, 'complete', 'update', $_SESSION['last_order_id']);
                }
                $order = new order($_SESSION['last_order_id'], $_SESSION['customer']->customers_id);
                $_SESSION['success_order_id'] = $_SESSION['last_order_id'];
                $order->_updateOrderStatus(PIRATEPAYPAYMENT_STATUS_SUCCESS);
                $order->_sendOrderMail($_SESSION['last_order_id']);
                unset($_SESSION['last_order_id']);
                unset($_SESSION['selected_shipping']);
                unset($_SESSION['selected_payment']);
                unset($_SESSION['conditions_accepted']);
                $_SESSION['cart']->_resetCart();
            } else {
                global $xtLink;
                $xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'payment', 'params' => 'ratepayFailure=true')));
            }
        } catch (Exception $e) {
            print_r($e);
        }
    }

    function pspSuccess()
    {
        return true;
    }

    function pspRedirect($order_data = '')
    {
        $url = $_SESSION['ratepay']['resulturl'];
        return $url;
    }

    /**
     * Finalizes order at Ratepay and saves in in the db.
     *
     * @return void
     */
    private static function _getRequestUrl()
    {
        if (PIRATEPAYPAYMENT_SANDBOX) {
            return 'https://paymentpage-int.ratepay.com/';
        } else {
            return 'https://paymentpage.ratepay.com/';
        }
    }

}

?>
