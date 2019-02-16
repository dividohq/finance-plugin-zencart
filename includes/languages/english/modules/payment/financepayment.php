<?php
  define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_ADMIN_TITLE', 'Finance payment');
  define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_CATALOG_TITLE', 'Finance payment');  // Payment option title as displayed to the customer


  if (MODULE_PAYMENT_FINANCEPAYMENT_STATUS == 'True') {
    define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_DESCRIPTION', 'Description of Finance Payment module');
  } else {
    define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_DESCRIPTION', 'Description of Finance Payment module');
  }

  define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_DEPOSIT', 'Finance payment deposit % : ');
  define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_PLAN', 'Finance Payment selected plan: ');
  define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_TOTAL', 'Finance payment total value: ');
  define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_DETAILS', 'Finance details ');
  define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_MODULE_DISABLED', 'This payment method is not active, Please try another payment method.');
  define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_PAYMENT_MISMATCH', 'Order amount mismatch error.');
  define('MODULE_PAYMENT_FINANCEPAYMENT_TEXT_ACTIVATION_CALL_ERROR', 'There was some error during activation api call.');