<?php
/**
 * finance_main_handler.php callback handler for Finance payment module notifications
 */
require('includes/application_top.php');
require(DIR_WS_MODULES . 'payment/financepayment.php');
//require(DIR_WS_MODULES . 'payment/financepayment/lib/divido/Divido.php');

$finance = new financepayment();
$payment = new FinanceApi();
$env     = $payment->getFinanceEnv(MODULE_PAYMENT_FINANCEPAYMENT_APIKEY);

if(isset($_POST['action']) && $_POST['action'] == 'getCalculatorWidget' && $_POST['products_id'] > 0) {
    $price = zen_get_products_base_price((int)$_POST['products_id']);
  $plans = $finance->getSelectedPlansString((int)$_POST['products_id'],(int)$price);
  $widgets = array();
  if($plans != '') {
    $widgets['js'] = $finance->getJsKey();
    $widgets['jsSrc'] = "https://cdn.divido.com/calculator/v2.1/production/js/template.$env.js";
    if(MODULE_PAYMENT_FINANCEPAYMENT_PRODUCT_CALCULATOR == 'True') {
      $widgets['calculator'] = '<div data-'.$env.'-widget data-'.$env.'-prefix="'.MODULE_PAYMENT_FINANCEPAYMENT_PREFIX.'" data-'.$env.'-suffix="'.MODULE_PAYMENT_FINANCEPAYMENT_SUFIX.'"  data-'.$env.'-amount="'.$price.'" data-'.$env.'-apply="true" data-'.$env.'-apply-label="Apply Now" data-'.$env.'-plans ="'.$plans.'"></div>';
    }
    if(MODULE_PAYMENT_FINANCEPAYMENT_WIDGET == 'True') {
      $widgets['widget'] = '<div data-'.$env.'-widget data-'.$env.'-mode="popup" data-'.$env.'-prefix="'.MODULE_PAYMENT_FINANCEPAYMENT_PREFIX.'" data-'.$env.'-suffix="'.MODULE_PAYMENT_FINANCEPAYMENT_SUFIX.'" data-'.$env.'-amount="'.$price.'" data-'.$env.'-apply="true" data-'.$env.'-apply-label="Apply Now" data-'.$env.'-plans ="'.$plans.'"></div>';
    }
  }
 // var_dump($widgets);

  die(json_encode($widgets));
}

require_once(DIR_WS_CLASSES . 'order.php');
require(DIR_WS_CLASSES . 'payment.php');
require_once(DIR_WS_CLASSES . 'shopping_cart.php');
require(DIR_WS_CLASSES . 'order_total.php');
global $db,$order,$messageStack,$zco_notifier,$order_totals;

/**
*Order status change according to the response from the Payment server.
*/
if (isset($_GET['type']) && $_GET['type'] == 'financepayment' && isset($_GET['response'])) {  
  $input = file_get_contents('php://input');
  $data  =  json_decode($input);
  if (!isset($data->status) || !isset($data->metadata->cart_id)) {
      die;
  }
  $cart_id   = $data->metadata->cart_id;
  $order_id = $data->metadata->order_id;
  $result = $db->Execute('SELECT * FROM `'.DB_PREFIX.'finance_requests` WHERE `order_id` = "'.(int)$order_id.'"'
  );
  if (!$result) {
      die;
  }
  $hash = hash('sha256', $result->fields['cart_id'].$result->fields['hash']);
  if ($hash !== $data->metadata->cart_hash && $order_id != $result->fields['order_id']) {
      die;
  }
  $order = new Order($order_id);
  $status = "MODULE_PAYMENT_FINANCEPAYMENT_".$data->status."_STATUS";
  $status = $finance->getConfigValue($status);
  if (!$status) {
      die;
  }
  $total = $order->info['total'];

  if ($total != $result->fields['total']) {
      $status = '';
  }
  if($order_id) {
    if ($order->info['order_status'] != MODULE_PAYMENT_FINANCEPAYMENT_AWAITING_STATUS) {
        if ($status != $order->info['order_status']) {
            $finance->updateOrderStatus($order_id,$status);
        }
    } elseif ($status != $order['current_state']) {
        $finance->updateOrderStatus($order_id,$status);
    }
  }
} elseif (isset($_GET['type']) && $_GET['type'] == 'financepayment' && isset($_GET['confirmation']) && isset($_GET['cartID']) !='') {
    $cart_id = $_GET['cartID'];
    $result = $db->Execute('SELECT * FROM `'.DB_PREFIX.'finance_requests` WHERE `cart_id` = "'.(int)$cart_id.'"'
  );
    $order_id = $_SESSION['order_id'];
    if($order_id != $result->fields['order_id']) {
      $messageStack->add_session('Your session has been expired.', 'error');
      zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
    }
    $cart = new shoppingCart();
    $cart->restore_contents();
    $finance = new financepayment();
    if(!$order)
      $order = new Order($result->fields['order_id']);
    if (!(is_object($order))) {
        $_SESSION['cartID'] == $cart->cartID;
        $_SESSION['cart'] = $cart;
        $messageStack->add_session('Your order could not be created, Please try again.', 'error');
        zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
    }
    $current_order_state = $db->Execute("select orders_status_id
                                from " . TABLE_ORDERS_STATUS . "
                                where orders_status_name = '" . $order->info['orders_status'] . "'");
    if ($current_order_state->fields['orders_status_id'] == MODULE_PAYMENT_FINANCEPAYMENT_AWAITING_STATUS) {
        $_SESSION['cartID'] == $cart->cartID;
        $_SESSION['cart'] = $cart;
        zen_redirect(zen_href_link(FILENAME_SHOPPING_CART));
    }
    $order_total_modules = new order_total;
    $payment_modules = new payment($finance->code);
    $payment_modules->after_order_create($order_id);
    $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_PAYMENT_MODULES_AFTER_ORDER_CREATE');
    // store the product info to the order
    $_SESSION['order_number_created'] = $order_id;
    $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS');
    //send email notifications
    $order->send_order_email($order_id, 2);
    $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_AFTER_SEND_ORDER_EMAIL');

    // clear slamming protection since payment was accepted
    if (isset($_SESSION['payment_attempt'])) unset($_SESSION['payment_attempt']);

    /**
     * Calculate order amount for display purposes on checkout-success page as well as adword campaigns etc
     * Takes the product subtotal and subtracts all credits from it
     */
      $ototal = $order_subtotal = $credits_applied = 0;
      for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
        if ($order_totals[$i]['code'] == 'ot_subtotal') $order_subtotal = $order_totals[$i]['value'];
        if (${$order_totals[$i]['code']}->credit_class == true) $credits_applied += $order_totals[$i]['value'];
        if ($order_totals[$i]['code'] == 'ot_total') $ototal = $order_totals[$i]['value'];
        if ($order_totals[$i]['code'] == 'ot_tax') $otax = $order_totals[$i]['value'];
        if ($order_totals[$i]['code'] == 'ot_shipping') $oshipping = $order_totals[$i]['value'];
      }
      $commissionable_order = ($order_subtotal - $credits_applied);
      $commissionable_order_formatted = $currencies->format($commissionable_order);
      $_SESSION['order_summary']['order_number'] = $order_id;
      $_SESSION['order_summary']['order_subtotal'] = $order_subtotal;
      $_SESSION['order_summary']['credits_applied'] = $credits_applied;
      $_SESSION['order_summary']['order_total'] = $ototal;
      $_SESSION['order_summary']['commissionable_order'] = $commissionable_order;
      $_SESSION['order_summary']['commissionable_order_formatted'] = $commissionable_order_formatted;
      $_SESSION['order_summary']['coupon_code'] = urlencode($order->info['coupon_code']);
      $_SESSION['order_summary']['currency_code'] = $order->info['currency'];
      $_SESSION['order_summary']['currency_value'] = $order->info['currency_value'];
      $_SESSION['order_summary']['payment_module_code'] = $order->info['payment_module_code'];
      $_SESSION['order_summary']['shipping_method'] = $order->info['shipping_method'];
      $_SESSION['order_summary']['orders_status'] = $order->info['orders_status'];
      $_SESSION['order_summary']['tax'] = $otax;
      $_SESSION['order_summary']['shipping'] = $oshipping;
      $products_array = array();
      foreach ($order->products as $key=>$val) {
        $products_array[urlencode($val['id'])] = urlencode($val['model']);
      }
      $_SESSION['order_summary']['products_ordered_ids'] = implode('|', array_keys($products_array));
      $_SESSION['order_summary']['products_ordered_models'] = implode('|', array_values($products_array));
      $zco_notifier->notify('NOTIFY_CHECKOUT_PROCESS_HANDLE_AFFILIATES');
      zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, 'confirm', 'SSL'));
} 
