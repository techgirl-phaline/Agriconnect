
<?php
/*
 * AgriConnect manual M-Pesa payment configuration.
 * Enter the real recipient details before accepting payments.
 */

define("MPESA_PAYMENT_METHOD", "send_money");

define("MPESA_RECIPIENT_NAME", "AgriConnect");

/*
 * Replace the empty value with the actual M-Pesa
 * phone number that will receive customer payments.
 *
 * Example format: 254712345678
 */
define("MPESA_RECIPIENT_PHONE", "");
