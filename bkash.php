
<?php


/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway paypal.com
 *
 * created by @ibnux <me@ibnux.com>
 *
 **/


function paypal_validate_config()
{
    global $config;
    if (empty($config['paypal_client_id']) || empty($config['paypal_secret_key'])) {
        sendTelegram("PayPal payment gateway not configured");
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup Paypal payment gateway, please tell admin"));
    }
}

function paypal_show_config()
{
    global $ui;
    $ui->assign('_title', 'Paypal - Payment Gateway');
    $ui->assign('currency', json_decode(file_get_contents('system/paymentgateway/paypal_currency.json'), true));
    $ui->display('paypal.tpl');
}


function paypal_save_config()
{
    global $admin, $_L;
    $paypal_client_id = _post('paypal_client_id');
    $paypal_secret_key = _post('paypal_secret_key');
    $paypal_currency = _post('paypal_currency');
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'paypal_secret_key')->find_one();
    if ($d) {
        $d->value = $paypal_secret_key;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'paypal_secret_key';
        $d->value = $paypal_secret_key;
        $d->save();
    }
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'paypal_client_id')->find_one();
    if ($d) {
        $d->value = $paypal_client_id;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'paypal_client_id';
        $d->value = $paypal_client_id;
        $d->save();
    }
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'paypal_currency')->find_one();
    if ($d) {
        $d->value = $paypal_currency;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'paypal_currency';
        $d->value = $paypal_currency;
        $d->save();
    }
    _log('[' . $admin['username'] . ']: Paypal ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);

    r2(U . 'paymentgateway/paypal', 's', $_L['Settings_Saved_Successfully']);
}

function paypal_create_transaction($trx, $user)
{
    global $config;
    $json = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => $config['paypal_currency'],
                    'value' => strval($trx['price'])
                ]
            ]
        ],
        "application_context" => [
            "return_url" => U . "order/view/" . $trx['id'] . '/check',
            "cancel_url" => U . "order/view/" . $trx['id'],
        ]
    ];
    //die(json_encode($json,JSON_PRETTY_PRINT));

    $result = json_decode(
        Http::postJsonData(
            paypal_get_server() . 'checkout/orders',
            $json,
            [
                'Prefer: return=minimal',
                'PayPal-Request-Id: paypal_' . $trx['id'],
                'Authorization: Bearer ' . paypalGetAccessToken()
            ]
        ),
        true
    );
    if (!$result['id']) {
        sendTelegram("paypal_create_transaction FAILED: \n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . 'order/package', 'e', Lang::T("Failed to create Paypal transaction."));
    }
    $urlPayment = "";
    foreach ($result['links'] as $link) {
        if ($link['rel'] == 'approve') {
            $urlPayment = $link['href'];
            break;
        }
    }
    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();
    $d->gateway_trx_id = $result['id'];
    $d->pg_url_payment = $urlPayment;
    $d->pg_request = json_encode($result);
    $d->expired_date = date('Y-m-d H:i:s', strtotime("+ 6 HOUR"));
    $d->save();
    header('Location: ' . $urlPayment);
    exit();
}

/*
*/

function paypal_payment_notification()
{
    // Not yet implemented
}

function paypal_get_status($trx, $user)
{
    $result = json_decode(Http::getData(paypal_get_server() . 'checkout/orders/' . $trx['gateway_trx_id'], ['Authorization: Bearer ' . paypalGetAccessToken()]), true);
    if (in_array($result['status'], ['APPROVED', 'COMPLETED']) && $trx['status'] != 2) {
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'Paypal')) {
            r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Failed to activate your Package, try again later."));
        }
        $trx->pg_paid_response = json_encode($result);
        $trx->payment_method = 'PAYPAL';
        $trx->payment_channel = 'paypal';
        $trx->paid_date = date('Y-m-d H:i:s', strtotime($result['updated']));
        $trx->status = 2;
        $trx->save();
        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
    } else if ($result['status'] == 'VOIDED') {
        $trx->pg_paid_response = json_encode($result);
        $trx->status = 3;
        $trx->save();
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction expired."));
    } else {
        sendTelegram("xendit_get_status: unknown result\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . "order/view/" . $trx['id'], 'w', "Transaction status :" . $result['status']);
    }
}

function paypalGetAccessToken()
{
    global $config;
    $result = Http::postData(str_replace('v2', 'v1', paypal_get_server()) . 'oauth2/token', [
        "grant_type" => "client_credentials"
    ], [], $config['paypal_client_id'] . ":" . $config['paypal_secret_key']);
    $json = json_decode($result, true);
    return $json['access_token'];
}


function paypal_get_server()
{
    global $_app_stage;
    if ($_app_stage == 'Live') {
        return 'https://api-m.paypal.com/v2/';
    } else {
        /**
         * Wallet Number: 01877722345
         * OTP: 123456
         * Pin: 12121
         */
        return 'https://api-m.sandbox.paypal.com/v2/';
    }
}
