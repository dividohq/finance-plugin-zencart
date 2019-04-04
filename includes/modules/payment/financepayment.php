<?php
/**
 * finance payment method class
 */
require_once dirname(__FILE__) . '/financepayment/lib/divido/Divido.php';
class financepayment extends base {
  /**
   * $code determines the internal 'code' name used to designate "this" payment module
   *
   * @var string
   */
  var $code;
  /**
   * $title is the displayed name for this payment method
   *
   * @var string
   */
  var $title;
  /**
   * $description is a soft name for this payment method
   *
   * @var string
   */
  var $description;
  /**
   * $enabled determines whether this module shows or not... in catalog.
   *
   * @var boolean
   */
  var $enabled;
  /**
   * log file folder
   *
   * @var string
   */
  var $_logDir = '';
  /**
   * vars
   */
  var $gateway_mode;
  var $reportable_submit_data;
  var $payment;
  var $auth_code;
  var $transaction_id;
  var $order_status;
  var $status_arr;
  var $awaiting_status_name;
  /**
   * @var string the currency enabled in this gateway's merchant account
   */
  private $gateway_currency;


  /**
   * Constructor
   */
  function __construct() {
    global $order,$db;

    $this->code = 'financepayment';
    if (IS_ADMIN_FLAG === true) {
      $this->title = MODULE_PAYMENT_FINANCEPAYMENT_TEXT_ADMIN_TITLE; // Payment module title in Admin

    } else {
      $this->title = MODULE_PAYMENT_FINANCEPAYMENT_TEXT_CATALOG_TITLE; // Payment module title in Catalog
    }
    $this->description = MODULE_PAYMENT_FINANCEPAYMENT_TEXT_DESCRIPTION;
    $this->enabled = ((MODULE_PAYMENT_FINANCEPAYMENT_STATUS == 'True') ? true : false);
    $this->sort_order = MODULE_PAYMENT_FINANCEPAYMENT_SORT_ORDER;

    $this->_logDir = defined('DIR_FS_LOGS') ? DIR_FS_LOGS : DIR_FS_SQL_CACHE;
    $this->awaiting_status_name = 'Awaiting Finance response';
    $this->checkApiKeyValidation();
  }

  function checkApiKeyValidation() {
    global $db;
    $plans = $this->getAllPlans();
    $plans_s = 'array(';
    $status_arr = array();
    foreach ($plans as $key => $value) {
      $plans_s .= "\'".$value->text."\',";
      $status_arr[] = $value->text;
    }
    $this->status_arr = $status_arr;
    if(IS_ADMIN_FLAG === true && strpos($_SERVER['PHP_SELF'],'modules.php')) {
      echo '<script type="text/javascript" src="/includes/modules/payment/financepayment/js/admin.js"></script>';
    }
    if(IS_ADMIN_FLAG === true && MODULE_PAYMENT_FINANCEPAYMENT_APIKEY != MODULE_PAYMENT_FINANCEPAYMENT_APIKEY_HIDDEN && strpos($_SERVER['PHP_SELF'],'modules.php')) {
      $plans_s .=')';
      if(!empty($plans)) {
        $this->removeOtherFields();
        $this->addOtherOptions($plans_s);
      } else {
        $this->removeOtherFields();
      }
      $db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '".MODULE_PAYMENT_FINANCEPAYMENT_APIKEY."' WHERE configuration_key = 'MODULE_PAYMENT_FINANCEPAYMENT_APIKEY_HIDDEN'");
    }
  }
  function awaitingStatusExists()
  { 
    global $db;
    $res = $db->Execute("SELECT orders_status_id FROM ".TABLE_ORDERS_STATUS." WHERE orders_status_name ='".$this->awaiting_status_name."'");
    if(!empty($res->fields) && $res->fields['orders_status_id'] > 0)
      return $res->fields['orders_status_id'];
    else
      return false;
  }

  function _doStatusUpdate($oID,$status,$comments,$customer_notified,$current_status)
  {
    if(MODULE_PAYMENT_FINANCEPAYMENT_USE_ACTIVATIONCALL != 'True')
      return false;
    global $db,$messageStack;
    $order = new Order((int)$oID);
    if ($order->info['payment_module_code'] != $this->code) {
        return;
    }
    $orderPaymanet = $db->execute(
        'SELECT * FROM `'.DB_PREFIX.'finance_requests`
        WHERE `order_id` = "'.(int)$oID.'"
        AND transaction_id != ""'
    );
    if ($status == MODULE_PAYMENT_FINANCEPAYMENT_ACTIVATION_STATUS && !empty($orderPaymanet->fields)) {

        $request_data = array(
            'merchant' => MODULE_PAYMENT_FINANCEPAYMENT_APIKEY,
            'application' => $orderPaymanet->fields['transaction_id'],
            'deliveryMethod' => $order->info['shipping_method'],
            'trackingNumber' => '1234',
        );
        Divido::setMerchant(MODULE_PAYMENT_FINANCEPAYMENT_APIKEY);

        $response = Divido_Activation::activate($request_data);

        if (isset($response->status) && $response->status == 'ok') {
            return true;
        }
        if (isset($response->error)) {
            $messageStack->add_session($response->error, 'caution');
        } else {
          $messageStack->add_session(MODULE_PAYMENT_FINANCEPAYMENT_TEXT_ACTIVATION_CALL_ERROR, 'caution');
        }
    }
  }

  function updatePlans($id,$plans)
  { 
      $plans = explode(',', $plans);
      $plans_str = array();
      foreach ($plans as $key => $value) {
        $plans_str[] = $this->status_arr[$value];
      }
      $plans = implode(',', $plans_str);
      global $db;
      $result = $db->Execute("select * from `".DB_PREFIX."finance_product` where products_id = '".(int)$id."'");
      if ($result->RecordCount()) {
          $db->Execute('UPDATE '.DB_PREFIX.'finance_product SET `plans` = "'.$plans.'" WHERE `products_id` = '.(int)$id);
      } else {
          $db->Execute('INSERT INTO '.DB_PREFIX.'finance_product (`plans`,`products_id`, `display`) VALUES ("'.$plans.'","'.$id.'","")');
      }

  }
  
  /**
   * compute HMAC-MD5
   *
   * @param string $key
   * @param string $data
   * @return string
   */
  function hmac ($key, $data)
  {
  }
  // end code from lance (resume authorize.net code)

  /**
   * Inserts the hidden variables in the HTML FORM required for SIM
   * Invokes hmac function to calculate fingerprint.
   *
   * @param string $loginid
   * @param string $txnkey
   * @param float $amount
   * @param string $sequence
   * @param float $currency
   * @return string
   */
  function InsertFP ($loginid, $txnkey, $amount, $sequence, $currency = "") {
  }

  // class methods
  /**
   * Calculate zone matches and flag settings to determine whether this module should display to customers or not
   */
  function update_status() {
  }
  /**
   * JS validation which does error-checking of data-entry if this module is selected for use
   * (Number, Owner Lengths)
   *
   * @return string
   */
  function javascript_validation() {
    return false;
  }
  /**
   * Display Credit Card Information Submission Fields on the Checkout Payment Page
   *
   * @return array
   */
  function selection() {
    global $order;
    if(empty($this->getCartPlans($order,true)))
      return false;
    if ($this->gateway_mode == 'offsite') {
      $selection = array('id' => $this->code,
                         'module' => $this->title);
    } else {
      $selection = array('id' => $this->code,
                         'module' => '<span class="financepayment_title">'.MODULE_PAYMENT_FINANCEPAYMENT_PAYMENT_TITLE.'</span><br>
                         <script>
                         var dividoKey = "'.$this->getJsKey().'";
                         $(document).ready(function() {
                          $(\'input[name="payment"]\').on("click",function() {
                            showPop($(this));
                         })
                           setTimeout(function() {
                              showPop($(\'input[name="payment"]:checked\'));
                           })
                         })
                         function showPop(ths){
                            console.log("booom",ths.val(),ths.is("checked"));
                            if(ths.val() == "financepayment") {
                              $("#divido-checkout").slideDown();
                            } else {
                              $("#divido-checkout").slideUp();
                            }
                         }
                         </script>
                         <input type="hidden" name="divido_total" value="'.$order->info["total"].'">
                         <script type="text/javascript" src="https://cdn.divido.com/calculator/v2.1/production/js/template.divido.js"></script>
                         <div id="divido-checkout" style="display:none;">
    <div data-divido-widget data-divido-prefix="'.MODULE_PAYMENT_FINANCEPAYMENT_PREFIX.'" data-divido-suffix="'.MODULE_PAYMENT_FINANCEPAYMENT_SUFIX.'" data-divido-title-logo data-divido-amount="'.$order->info["total"].'" data-divido-apply="true" data-divido-apply-label="Apply Now" data-divido-plans = "'.$this->getCartPlans($order,true).'"></div></div>',
                        );
    }
    return $selection;
  }
  /**
   * Evaluates the Credit Card Type for acceptance and the validity of the Credit Card Number & Expiration Date
   *
   */
  function pre_confirmation_check() {
  }
  /**
   * Display Credit Card Information on the Checkout Confirmation Page
   *
   * @return array
   */
  function confirmation() {
    global $order , $currencies;
    if (isset($_POST['payment'])) {
      $total_dicount = $GLOBALS['ot_coupon']->deduction+$GLOBALS['ot_group_pricing']->deduction+$GLOBALS['ot_gv']->deduction;
      $_SESSION['finance_deposit'] = $_POST['divido_deposit'];
      $_SESSION['finance_plan'] = $_POST['divido_plan'];
      $_SESSION['finance_total'] = $_POST['divido_total'];
      $_SESSION['total_dicount'] = $total_dicount;
      $_SESSION['finance_total'] = ($_SESSION['finance_total'] == $order->info['total']) ? $_SESSION['finance_total']-$total_dicount : $_SESSION['finance_total'];
      $confirmation = array('title' => MODULE_PAYMENT_FINANCEPAYMENT_TEXT_DETAILS,
                            'fields' => array(array('title' => MODULE_PAYMENT_FINANCEPAYMENT_TEXT_DEPOSIT,
                                                    'field' => $_SESSION['finance_deposit']
                                                    ),
                                              array('title' => MODULE_PAYMENT_FINANCEPAYMENT_TEXT_PLAN,
                                                    'field' => $this->getPlanTextById($_SESSION['finance_plan'])
                                                    ),
                                              array('title' => MODULE_PAYMENT_FINANCEPAYMENT_TEXT_TOTAL,
                                                    'field' => $currencies->format($_SESSION['finance_total'], true, $order->info['currency'], $order->info['currency_value'])
                                                    ),
                                              )
                                );
    } else {
      $confirmation = array(); //array('title' => $this->title);
    }
    return $confirmation;
  }
  /**
   * Build the data and actions to process when the "Submit" button is pressed on the order-confirmation screen.
   * This sends the data to the payment gateway for processing.
   * (These are hidden fields on the checkout confirmation page)
   *
   * @return string
   */
  function process_button() {
  }
  /**
   * Store the CC info to the order and process any results that come back from the payment gateway
   *
   */
  function before_process() {
    global $messageStack, $order;
    $this->payment = $_POST;
    if (!(isset($_SESSION['finance_deposit']) && isset($_SESSION['finance_plan']) && isset($_SESSION['finance_total']))) {
        zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
    }
    if ($this->enabled == false) {
      $messageStack->add_session('checkout_payment', MODULE_PAYMENT_FINANCEPAYMENT_TEXT_MODULE_DISABLED , 'error');
      zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
    }
    if ((int)$order->info['total'] != (int)$_SESSION['finance_total']) {
        $messageStack->add_session('checkout_payment', MODULE_PAYMENT_FINANCEPAYMENT_TEXT_PAYMENT_MISMATCH , 'error');
        zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
    }

    $response = $this->getConfirmation();
    if ($response['status']) {
      header("HTTP/1.1 302 Object Moved");
      zen_redirect($response['url']);
    } else {
      $messageStack->add_session('checkout_payment', $response['message'], 'error');
      zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
    }
  }

  function getConfirmation()
  {
      global $order, $order_totals;
      Divido::setApiKey(MODULE_PAYMENT_FINANCEPAYMENT_APIKEY);
      $deposit = $_SESSION['finance_deposit'];
      $finance = $_SESSION['finance_plan'];
      $cart = $_SESSION['cart'];
      $customer = $order->customer;
      $address = $order->billing;
      $country = $order->billing['country']['iso_code_2'];

      $language = strtoupper($_SESSION['languages_code']);
      $currency = $_SESSION['currency'];

      $cart_id = $_SESSION['cartID'];

      $firstname = $customer['firstname'];
      $lastname = $customer['lastname'];
      $email = $customer['email_address'];
      $telephone = $customer['telephone'];
      $postcode  = $customer['postcode'];

      $products  = array();
      foreach ($order->products as $product) {
          $products[] = array(
              'type' => 'product',
              'text' => $product['name'],
              'quantity' => $product['qty'],
              'value' => $product['final_price'],
          );
      }

      $sub_total = $_SESSION['finance_total'];

      $shiphandle = $order->info['shipping_cost'];
      $disounts = $_SESSION['total_dicount'];

      $products[] = array(
          'type'     => 'product',
          'text'     => 'Shipping & Handling',
          'quantity' => 1,
          'value'    => $shiphandle,
      );

      $products[] = array(
          'type'     => 'product',
          'text'     => 'Discount',
          'quantity' => 1,
          'value'    => "-".$disounts,
      );

      $deposit_amount = zen_round(($deposit / 100) * $sub_total-$disounts, 2);

      $response_url = zen_href_link('finance_main_handler.php', 'type=financepayment&response=1', 'SSL', true,true, true);
      $redirect_url = zen_href_link('finance_main_handler.php', 'type=financepayment&confirmation=1&cartID='.$cart_id, 'SSL', true,true, true);
      $checkout_url = zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false);
      $order->info['payment_method'] = $this->title;
      $order->info['payment_module_code'] = $this->code;
      $order_id = $order->create($order_totals);
      // store the product info to the order
    $order->create_add_products($order_id);
      $order->info['order_status'] = MODULE_PAYMENT_FINANCEPAYMENT_AWAITING_STATUS;
      $salt = uniqid('', true);
      $hash = hash('sha256', $cart_id.$salt);

      $request_data = array(
          'merchant' => MODULE_PAYMENT_FINANCEPAYMENT_APIKEY,
          'deposit'  => $deposit_amount,
          'finance'  => $finance,
          'country'  => $country,
          'language' => $language,
          'currency' => $currency,
          'metadata' => array(
              'cart_id' => $cart_id,
              'cart_hash' => $hash,
              'order_id' => $order_id,
          ),
          'customer' => array(
              'title'         => '',
              'first_name'    => $firstname,
              'middle_name'   => '',
              'last_name'     => $lastname,
              'country'       => $country,
              'postcode'      => $postcode,
              'email'         => $email,
              'mobile_number' => '',
              'phone_number'  => $telephone,
              'address' => array(
                  'text' => $address['street_address']." ".$address['suburb'].
                      " ".$address['city']." ".$address['postcode'],
              ),
          ),
          'products' => $products,
          'response_url' => htmlspecialchars_decode($response_url),
          'redirect_url' => htmlspecialchars_decode($redirect_url),
          'checkout_url' => htmlspecialchars_decode($checkout_url),
      );
      $response = Divido_CreditRequest::create($request_data);
      if ($response->status == 'ok') {
        $_SESSION['order_id'] = $order_id;
        $this->saveHash($cart_id,$hash,$sub_total,$order_id,$response->id);
        unset($_SESSION['cartID']);
        unset($_SESSION['cart']);
          $data = array(
              'status' => true,
              'url'    => $response->url,
          );
          $this->transaction_id = $response->id;
      } else {
          $data = array(
              'status'  => false,
              'message' => $response->error,
          );
      }
      return $data;
  }

  public function saveHash($cart_id, $salt, $total,$order_id = '',$transaction_id = '')
  {
    global $db;
    $extra = '';
    if($order_id == '') {
      $result = $db->Execute(
          'SELECT * FROM `'.DB_PREFIX.'finance_requests` WHERE `cart_id` = "'.(int)$cart_id.'"'
      );
      $where = 'WHERE `cart_id` = '.(int)$cart_id;
    } else {
      $result = $db->Execute(
          'SELECT * FROM `'.DB_PREFIX.'finance_requests` WHERE `order_id` = "'.(int)$order_id.'"'
      );
      $where = 'WHERE `order_id` = '.(int)$order_id;
      if($transaction_id != '')
        $extra = ' ,`transaction_id` = "'.$transaction_id.'" ';

    }

      if ($result->RecordCount()) {
          $db->Execute('UPDATE '.DB_PREFIX.'finance_requests SET `hash` = "'.$salt.'",
          `total` = "'.$total.'"'.$extra.''.$where) ;
      } else {
          $db->Execute('INSERT INTO '.DB_PREFIX.'finance_requests (`hash`,`total`,`cart_id`,`order_id`,`transaction_id`) VALUES ("'.$salt.'","'.$total.'","'.(int)$cart_id.'","'.$order_id.'","'.$transaction_id.'")');
      }
  }

  /**
   * Post-processing activities
   *
   * @return boolean
   */
  function after_process() {
  }

  function getConfigValue($key)
  {
    global $db;
    $res = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = '".$key."'");
    return $res->fields['configuration_value'];
  }
  /**
   * Check to see whether module is installed
   *
   * @return boolean
   */
  function check() {
    global $db;
    if (!isset($this->_check)) {
      $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_FINANCEPAYMENT_STATUS'");
      $this->_check = $check_query->RecordCount();
    }
    return $this->_check;
  }
  /**
   * Install the payment module and its configuration settings
   *
   */
  function install() {
    global $db, $messageStack;
    if (defined('MODULE_PAYMENT_FINANCEPAYMENT_STATUS')) {
      $messageStack->add_session('financepayment module already installed.', 'error');
      zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=financepayment', 'NONSSL'));
      return 'failed';
    }
    $db->Execute('CREATE TABLE IF NOT EXISTS `'.DB_PREFIX.'finance_product` (`id_finance_product` int(11) NOT NULL AUTO_INCREMENT, `products_id` int(11) NOT NULL, `display` text NOT NULL, `plans` text NOT NULL, PRIMARY KEY  (`id_finance_product`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8');

    $db->Execute('CREATE TABLE IF NOT EXISTS `'.DB_PREFIX.'finance_requests` ( `id_finance_requests` int(11) NOT NULL AUTO_INCREMENT, `cart_id` int(11) NOT NULL, `hash` text NOT NULL, `total` text NOT NULL, `order_id` TEXT NOT NULL, `transaction_id` Text NOT NULL, PRIMARY KEY  (`id_finance_requests`) ) ENGINE= InnoDB DEFAULT CHARSET=utf8');

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_FINANCEPAYMENT_SORT_ORDER', '0', 'Sort order of displaying payment options to the customer. Lowest is displayed first.', '6', '0', now())");
    //payment status MODULE_PAYMENT_FINANCEPAYMENT_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Finance Payment Module', 'MODULE_PAYMENT_FINANCEPAYMENT_STATUS', 'True', 'Do you want to accept Finance Payment payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

    //API key MODULE_PAYMENT_FINANCEPAYMENT_APIKEY
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API KEY', 'MODULE_PAYMENT_FINANCEPAYMENT_APIKEY', '', 'The API KEY used for the Finance payment service', '6', '0', now())");
    //API key hidden field MODULE_PAYMENT_FINANCEPAYMENT_APIKEY
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,use_function,set_function, date_added) values ('', 'MODULE_PAYMENT_FINANCEPAYMENT_APIKEY_HIDDEN', '', '', '6', '0','financepayment->hiddenField','zen_draw_hidden_field(MODULE_PAYMENT_FINANCEPAYMENT_APIKEY_HIDDEN, ', now())");
  }

  function addOtherOptions($plans)
  {
    global $db;
    // Activation status call MODULE_PAYMENT_FINANCEPAYMENT_ACTIVATION_STATUS
    $res = $db->Execute('SELECT * FROM '.TABLE_CONFIGURATION.' WHERE configuration_key ="MODULE_PAYMENT_FINANCEPAYMENT_ACTIVATION_STATUS"');
    if(!empty($res->fields))
      return false;
    //MODULE_PAYMENT_FINANCEPAYMENT_USE_ACTIVATIONCALL
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,set_function, date_added) values ('Enable/Disable activation call functionality', 'MODULE_PAYMENT_FINANCEPAYMENT_USE_ACTIVATIONCALL', 'True', 'Use Finance activation call functionality', '6', 'False', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_FINANCEPAYMENT_ACTIVATION_STATUS', '2', 'Order status to make Finance Payment activation call', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
    
    //payment title MODULE_PAYMENT_FINANCEPAYMENT_PAYMENT_TITLE
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Finance Payment module\'s title', 'MODULE_PAYMENT_FINANCEPAYMENT_PAYMENT_TITLE', 'Finance module', 'The Title used for the Finance payment service', '6', '0', now())");

    //Finance plan MODULE_PAYMENT_FINANCEPAYMENT_PLAN
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,set_function, date_added) values ('Finance plan', 'MODULE_PAYMENT_FINANCEPAYMENT_PLAN', '', 'Finance plan available to the customer', '6', '0','zen_cfg_select_multioption(".$plans.", ', now())");

    //Widget on product page MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_WIDGET
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,set_function, date_added) values ('Widget on product page', 'MODULE_PAYMENT_FINANCEPAYMENT_WIDGET', 'True', 'Show Finance payment widget on product page', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

    //Calculator MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_CALCULATOR
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,set_function, date_added) values ('Calculator on product page', 'MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_CALCULATOR', 'False', 'Show Finance payment calculator on product page', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

    //Prefix MODULE_PAYMENT_FINANCEPAYMENT_PREFIX
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Prefix', 'MODULE_PAYMENT_FINANCEPAYMENT_PREFIX', 'Finance From', 'Prefix of the Finance payment module', '6', '0', now())");
    //Sufix MODULE_PAYMENT_FINANCEPAYMENT_SUFIX
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sufix', 'MODULE_PAYMENT_FINANCEPAYMENT_SUFIX', 'with', 'Sufix of the Finance payment module', '6', '0', now())");

    //Require whole cart MODULE_PAYMENT_FINANCEPAYMENT_WHOLE_CART
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,set_function, date_added) values ('Require whole cart to be available on finance', 'MODULE_PAYMENT_FINANCEPAYMENT_WHOLE_CART', 'false', 'Require whole cart to be available on finance', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

    //Minimum cart value MODULE_PAYMENT_FINANCEPAYMENT_MIN_CART
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Cart amount minimum', 'MODULE_PAYMENT_FINANCEPAYMENT_MIN_CART', '0', 'Cart amount minimum for the Finance payment module', '6', '0', now())");

    //Product selection MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_SELECTION
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,set_function, date_added) values ('Product Selection', 'MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_SELECTION', 'All Products', 'Product Selection', '6', '0', 'zen_cfg_select_option(array(\'All Products\',\'Selected Products\', \'Products above minimum value\',), ', now())");

    //Product selection min product for product above MODULE_PAYMENT_FINANCEPAYMENT_MIN_PRODUCT
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Product price minimum', 'MODULE_PAYMENT_FINANCEPAYMENT_MIN_PRODUCT', '0', 'Product price minimum', '6', '0', now())");

    //Accepted status MODULE_PAYMENT_FINANCEPAYMENT_ACCEPTED_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function, date_added) values ('ACCEPTED', 'MODULE_PAYMENT_FINANCEPAYMENT_ACCEPTED_STATUS', '1', 'Status for Accepted', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    //DEPOSITE-PAID status MODULE_PAYMENT_FINANCEPAYMENT_DEPOSIT-PAID_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function, date_added) values ('DEPOSIT-PAID', 'MODULE_PAYMENT_FINANCEPAYMENT_DEPOSIT-PAID_STATUS', '2', 'Status for Deposite-paid', '6', '0','zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    //SIGNED status MODULE_PAYMENT_FINANCEPAYMENT_SIGNED_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function, date_added) values ('SIGNED', 'MODULE_PAYMENT_FINANCEPAYMENT_SIGNED_STATUS', '2', 'Status for SIGNED', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    //READY status MODULE_PAYMENT_FINANCEPAYMENT_READY_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function, date_added) values ('READY', 'MODULE_PAYMENT_FINANCEPAYMENT_READY_STATUS', '2', 'Status for READY', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    //ACTION-LENDER status MODULE_PAYMENT_FINANCEPAYMENT_ACTION-LENDER_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function, date_added) values ('ACTION-LENDER', 'MODULE_PAYMENT_FINANCEPAYMENT_ACTION-LENDER_STATUS', '2', 'Status for ACTION-LENDER', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    //CANCELED status MODULE_PAYMENT_FINANCEPAYMENT_CANCELED_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function, date_added) values ('CANCELED', 'MODULE_PAYMENT_FINANCEPAYMENT_CANCELED_STATUS', '1', 'Status for CANCELED', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    //COMPLETED status MODULE_PAYMENT_FINANCEPAYMENT_COMPLETED_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function,date_added) values ('COMPLETED', 'MODULE_PAYMENT_FINANCEPAYMENT_COMPLETED_STATUS', '2', 'Status for COMPLETED', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    //DECLINED status MODULE_PAYMENT_FINANCEPAYMENT_DECLINED_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('DECLINED', 'MODULE_PAYMENT_FINANCEPAYMENT_DECLINED_STATUS', '1', 'Status for DECLINED', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    //DEFERRED status MODULE_PAYMENT_FINANCEPAYMENT_DEFERRED_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function, date_added) values ('DEFERRED', 'MODULE_PAYMENT_FINANCEPAYMENT_DEFERRED_STATUS', '1', 'Status for DEFERRED', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    //REFERRED status MODULE_PAYMENT_FINANCEPAYMENT_REFERRED_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function, date_added) values ('REFERRED', 'MODULE_PAYMENT_FINANCEPAYMENT_REFERRED_STATUS', '1', 'Status for REFERRED', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    //FULFILLED status MODULE_PAYMENT_FINANCEPAYMENT_FULFILLED_STATUS
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function, date_added) values ('FULFILLED', 'MODULE_PAYMENT_FINANCEPAYMENT_FULFILLED_STATUS', '2', 'Status for FULFILLED', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
    $this->addFinanceAwaitingStatus();
  }

  function addFinanceAwaitingStatus()
  {
    global $db;
    $awaiting_status_id = $this->awaitingStatusExists();
    if (!$awaiting_status_id) {
      $languages = zen_get_languages();
      for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
        $language_id = $languages[$i]['id'];
          $sql_data_array = array('orders_status_name' => zen_db_prepare_input($this->awaiting_status_name));
          $next_id = $db->Execute("select max(orders_status_id)
                                         as orders_status_id from " . TABLE_ORDERS_STATUS . "");

          $orders_status_id = $next_id->fields['orders_status_id'] + 1;

          $insert_sql_data = array('orders_status_id' => $orders_status_id,
                                   'language_id' => $language_id);

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          zen_db_perform(TABLE_ORDERS_STATUS, $sql_data_array);
      }
    }
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  set_function, use_function, date_added) values ('Awaiting status', 'MODULE_PAYMENT_FINANCEPAYMENT_AWAITING_STATUS', '".$awaiting_status_id."', 'Status for AWAITING', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
  }

  function removeAwaitingStatus()
  { 
    global $db;
    $db->Execute("delete from " . TABLE_ORDERS_STATUS . "
                      where orders_status_name = '" . zen_db_prepare_input($this->awaiting_status_name) . "'");
  }
  /**
   * Remove the module and all its settings
   *
   */
  function removeOtherFields()
  {
    global $db;
    $this->removeAwaitingStatus();
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('MODULE_PAYMENT_FINANCEPAYMENT_ACTIVATION_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_AWAITING_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_PAYMENT_TITLE', 'MODULE_PAYMENT_FINANCEPAYMENT_PLAN','MODULE_PAYMENT_FINANCEPAYMENT_WIDGET','MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_CALCULATOR','MODULE_PAYMENT_FINANCEPAYMENT_PREFIX','MODULE_PAYMENT_FINANCEPAYMENT_SUFIX','MODULE_PAYMENT_FINANCEPAYMENT_WHOLE_CART', 'MODULE_PAYMENT_FINANCEPAYMENT_MIN_CART', 'MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_SELECTION','MODULE_PAYMENT_FINANCEPAYMENT_MIN_PRODUCT', 'MODULE_PAYMENT_FINANCEPAYMENT_ACCEPTED_STATUS', 'MODULE_PAYMENT_FINANCEPAYMENT_DEPOSIT-PAID_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_SIGNED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_READY_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_ACTION-LENDER_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_CANCELED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_COMPLETED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_DECLINED_STATUS', 'MODULE_PAYMENT_FINANCEPAYMENT_DEFERRED_STATUS', 'MODULE_PAYMENT_FINANCEPAYMENT_REFERRED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_FULFILLED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_USE_ACTIVATIONCALL')");
  }
  function remove() {
    global $db;
    $this->removeAwaitingStatus();
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
  }

  function updateOrderStatus($order_id,$new_order_status,$status_comment = null,$trans_id = null)
  {
    if(!$order_id > 0 || !$new_order_status > 0)
      return false;
    global $db;
    $sql_data_array = array('orders_id' => $order_id,
                                'orders_status_id' => (int)$new_order_status,
                                'date_added' => 'now()',
                                'comments' => '',
                                'customer_notified' => 0
                             );
    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
    $db->Execute("update " . TABLE_ORDERS  . "
                  set orders_status = '" . (int)$new_order_status . "'
                  where orders_id = '" . (int)$order_id . "'");
    return true;

  }
  /**
   * Internal list of configuration keys used for configuration of the module
   *
   * @return array
   */
  function keys() {
    return array('MODULE_PAYMENT_FINANCEPAYMENT_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_APIKEY','MODULE_PAYMENT_FINANCEPAYMENT_PAYMENT_TITLE','MODULE_PAYMENT_FINANCEPAYMENT_SORT_ORDER','MODULE_PAYMENT_FINANCEPAYMENT_USE_ACTIVATIONCALL','MODULE_PAYMENT_FINANCEPAYMENT_ACTIVATION_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_PLAN','MODULE_PAYMENT_FINANCEPAYMENT_WIDGET','MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_CALCULATOR','MODULE_PAYMENT_FINANCEPAYMENT_PREFIX','MODULE_PAYMENT_FINANCEPAYMENT_SUFIX','MODULE_PAYMENT_FINANCEPAYMENT_WHOLE_CART','MODULE_PAYMENT_FINANCEPAYMENT_MIN_CART','MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_SELECTION','MODULE_PAYMENT_FINANCEPAYMENT_MIN_PRODUCT','MODULE_PAYMENT_FINANCEPAYMENT_AWAITING_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_ACCEPTED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_DEPOSIT-PAID_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_SIGNED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_READY_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_ACTION-LENDER_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_CANCELED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_COMPLETED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_DECLINED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_DEFERRED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_REFERRED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_FULFILLED_STATUS','MODULE_PAYMENT_FINANCEPAYMENT_APIKEY_HIDDEN');
  }

  //get Finance plans for payment
public function getGlobalSelectedPlans()
{
    $all_plans = $this->getAllPlans();
    $selected_plans = explode(', ', MODULE_PAYMENT_FINANCEPAYMENT_PLAN);
    if (!$selected_plans) {
        return array();
    }

    $plans = array();
    foreach ($all_plans as $plan) {
        if (in_array($plan->text, $selected_plans)) {
            $plans[$plan->id] = $plan;
        }
    }

    return $plans;
}

public function getSelectedPlansString($products_id,$product_price = 0)
{
    $plans = $this->getProductPlans($product_price,$products_id);
    $plans_str = array();
    foreach ($plans as $key => $value) {
      $plans_str[] = $value->id;
    }
    return implode(',', $plans_str);
}
public function getJsKey()
    {
        $key_parts = explode('.', MODULE_PAYMENT_FINANCEPAYMENT_APIKEY);
        $js_key    = strtolower(array_shift($key_parts));
        return $js_key;
    }

public function getPlans($default_plans = false)
    {
        if ($default_plans) {
            $plans = $this->getGlobalSelectedPlans();
        } else {
            $plans = $this->getAllPlans();
        }

        return $plans;
    }
public function getPlanTextById($id)
{
  if($id == '')
    return '';
  $plan = $this->getGlobalSelectedPlans();
  return isset($plan[$id]) ? $plan[$id]->text : '';
}

public function getAllPlans()
{
    if (!MODULE_PAYMENT_FINANCEPAYMENT_APIKEY) {
        return array();
    }

    Divido::setMerchant(MODULE_PAYMENT_FINANCEPAYMENT_APIKEY);

    $response = Divido_Finances::all();
    if ($response->status != 'ok') {
        return array();
    }

    $plans = $response->finances;

    $plans_plain = array();
    foreach ($plans as $plan) {
        $plan_copy = new stdClass();
        $plan_copy->id                 = $plan->id;
        $plan_copy->text               = $plan->text;
        $plan_copy->country            = $plan->country;
        $plan_copy->min_amount         = $plan->min_amount;
        $plan_copy->min_deposit        = $plan->min_deposit;
        $plan_copy->max_deposit        = $plan->max_deposit;
        $plan_copy->interest_rate      = $plan->interest_rate;
        $plan_copy->deferral_period    = $plan->deferral_period;
        $plan_copy->agreement_duration = $plan->agreement_duration;

        $plans_plain[$plan->id] = $plan_copy;
    }

    return $plans_plain;
}
public function getProductPlans($product_price, $products_id)
{
    if(!$this->enabled)
        return array();
    $settings = $this->getProductSettings($products_id);
    $product_selection = MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_SELECTION;
    $price_threshold   = MODULE_PAYMENT_FINANCEPAYMENT_MIN_PRODUCT;

    $plans = $this->getPlans(true);

    if ($product_selection == 'All Products') {
        return $plans;
    }
    if ($product_selection == 'Products above minimum value' && $price_threshold > $product_price) {
        return null;
    } elseif ($product_selection == 'Products above minimum value') {
        return $plans;
    }

    $available_plans = $this->getPlans(false);
    $selected_plans  = $settings->fields['plans'];
    if(count($selected_plans) > 0) {
      $plans = array();
      foreach ($available_plans as $plan) {
          if (strpos(' '.$selected_plans,$plan->text)) {
              $plans[$plan->id] = $plan;
          }
      }
    }

    if (empty($plans)) {
        return null;
    }

    return $plans;
}

  public function getCartPlans($order,$string = false)
  {
      $plans = array();
      if(!$this->enabled)
        return $plans;
      $s_plans = array();
      if($order->delivery != $order->billing)
        return $s_plans;
      foreach ($order->products as $product) {
          $product_plans = $this->getProductPlans($product['final_price']*$product['qty'], $product['id']);
          if ($product_plans) {
              $plans = array_merge($plans, $product_plans);
          }else if(MODULE_PAYMENT_FINANCEPAYMENT_WHOLE_CART == 'True' && !$product_plans) {
            return array();
          }
      }
      if(MODULE_PAYMENT_FINANCEPAYMENT_WHOLE_CART == 'True' && (int)MODULE_PAYMENT_FINANCEPAYMENT_MIN_CART > $order->info['total']) {
          return array();
      }
      if($string) {
        foreach ($plans as $key => $value) {
          $s_plans[] = $value->id;
        }
        return implode(',', $s_plans);
      }
      return $plans;
  }

public static function getProductSettings($products_id)
{
    global $db;
    $query = "select * from `".DB_PREFIX."finance_product` where products_id = '".(int)$products_id."'";
    return $db->Execute($query);
}
  /**
   * Calculate validity of response
   */
  function calc_md5_response($trans_id = '', $amount = '') {
  }
  /**
   * Used to do any debug logging / tracking / storage as required.
   */
  function _debugActions($response, $mode, $order_time= '', $sessID = '') {
  }
  /**
   * Check and fix table structure if appropriate
   */
  function tableCheckup() {
  }
  function getProductOptionsAdmin($products_id)
  {
    $before_s = '';
    if($products_id) { 
      $before_s = '<tr><td colspan="2">'.zen_draw_separator("pixel_black.gif", "100%", "3").'</td></tr><tr>';
      $selected_plans = $this->getProductSettings($products_id);
      $selected_plans = $selected_plans->fields['plans'];
      foreach ($this->status_arr as $key => $value) {
        $name = 'MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_FINANACE_PLANS';
        $string .= '<br><input type="checkbox" name="'.$name.'" value="' . $key . '"';
        if (strpos(' '.$selected_plans,$value)) $string .= ' CHECKED';
        $string .= ' id="' . strtolower($value . '-' . $name) . '"> ' . '<label for="' . strtolower($value . '-' . $name) . '" class="inputSelect">' . $value . '</label>' . "\n";
       }
      $before_s .='<td class="main">Selected Plans for this product</td><td class="main">'.zen_draw_separator('pixel_trans.gif', '24', '15').'&nbsp;' .$string.'</td></tr>
      <input type="hidden" name="financepayment" id="financepayment" value="'.$selected_plans.'">
      <script>
        $(document).ready(function() {
          $("input[name=\'MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_FINANACE_PLANS\']").on("click",function() {
            var plans = [];
            $("input[name=\'MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_FINANACE_PLANS\']:checked").each(function() {
              plans.push($(this).val());
            });
            plans = plans.join(",");
            $("input#financepayment").val(plans);
          })
        })
      </script>
      ';
    }
    return $before_s;
  }

  public function hiddenField($value) {
    return '';
  }
}
