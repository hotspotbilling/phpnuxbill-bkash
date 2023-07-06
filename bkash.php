<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway bkash.com
 **/

function bkash_validate_config()
{
    global $config;
    if (empty($config['bkash_app_key']) || empty($config['bkash_app_secret']) || empty($config['bkash_username']) || empty($config['bkash_password'])) {
        sendTelegram("BKASH payment gateway not configured");
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup BKASH payment gateway, please tell admin"));
    }
}


function bkash_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'bKash - Tokenizer - Payment Gateway');
    //$ui->assign('channels', json_decode(file_get_contents('system/paymentgateway/channel_duitku.json'), true));
    $ui->display('bkash.tpl');
}


function bkash_save_config()
{
    global $admin;
    $bkash_app_key = _post('bkash_app_key');
    $bkash_app_secret = _post('bkash_app_secret');
    $bkash_username = _post('bkash_username');
    $bkash_password = _post('bkash_password');
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'bkash_app_key')->find_one();
    if ($d) {
        $d->value = $bkash_app_key;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'bkash_app_key';
        $d->value = $bkash_app_key;
        $d->save();
    }
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'bkash_app_secret')->find_one();
    if ($d) {
        $d->value = $bkash_app_secret;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'bkash_app_secret';
        $d->value = $bkash_app_secret;
        $d->save();
    }
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'bkash_username')->find_one();
    if ($d) {
        $d->value = $bkash_username;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'bkash_username';
        $d->value = $bkash_username;
        $d->save();
    }
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'bkash_password')->find_one();
    if ($d) {
        $d->value = $bkash_password;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'bkash_password';
        $d->value = $bkash_password;
        $d->save();
    }
    _log('[' . $admin['username'] . ']: bKash ' . Lang::T('Settings_Saved_Successfully'), 'Admin', $admin['id']);
    r2(U . 'paymentgateway/bKash', 's', Lang::T('Settings_Saved_Successfully'));
}

function bkash_create_transaction($trx, $user)
{
    global $config;
    $json = [
        'intent' => 'sale',
        'mode' => '0011',
        'payerReference' => $trx['id'],
        'currency' => 'BDT',
        'amount' => $trx['price'],
        'callbackURL' => U . 'order/view/'.$trx['id'].'/check',
        'merchantInvoiceNumber' => $trx['id'],
    ];
    $headers = ['Authorization: ' . bkash_get_token(), 'X-App-Key: ' . $config['bkash_app_key']];
    $result = json_decode(Http::postJsonData(bkash_get_server() . 'checkout/create', $json, $headers), true);
    if ($result['statusMessage'] != 'Successful') {
        sendTelegram("bKash payment failed\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction. ".$result['errorMessage']));
    }
    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();
    $d->gateway_trx_id = $result['paymentID'];
    $d->pg_url_payment = $result['bkashURL'];
    $d->pg_request = json_encode($result);
    $d->expired_date = date('Y-m-d H:i:s', strtotime('+ 4 HOURS'));
    $d->save();
    header('Location: ' . $result['bkashURL']);
    exit();
}

function bkash_get_status($trx, $user)
{
    global $config;
    Http::postJsonData(bkash_get_server() . 'checkout/execute', ['paymentID' => $trx['gateway_trx_id']], ['Authorization: ' . bkash_get_token(), 'X-App-Key: ' . $config['bkash_app_key']]);
    $result = json_decode(Http::postJsonData(bkash_get_server() . 'checkout/payment/status', ['paymentID' => $trx['gateway_trx_id']], ['Authorization: ' . bkash_get_token(), 'X-App-Key: ' . $config['bkash_app_key']]), true);
    if ($result['statusCode'] != '0000') {
        sendTelegram("bKash payment status failed\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . "order/view/" . $trx['id'], 'e', Lang::T("Failed to check status transaction. " . $result['errorMessage']));
    }
    if ($trx['status'] == 2) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has been paid.."));
    }
    if ($result['transactionStatus'] == 'Completed') {
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'],  'bKash')) {
            r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Failed to activate your Package, try again later."));
        }
        $trx->pg_paid_response = json_encode($result);
        $trx->payment_method = 'bKash';
        $trx->payment_channel = 'bKash';
        $trx->paid_date = date('Y-m-d H:i:s', $result['paid_at']);
        $trx->status = 2;
        $trx->save();

        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
    } else {
        print_r($result);
        die();
        r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still unpaid."));
    }
}

// Callback
function bkash_payment_notification()
{
    global $config;
    if ($_GET['status'] == 'success') {
        $paymentID = $_GET['paymentID'];
        //execute
        json_decode(Http::postJsonData(bkash_get_server() . 'checkout/execute', ['paymentID' => $paymentID], ['Authorization: ' . bkash_get_token(), 'X-App-Key: ' . $config['bkash_app_key']]), true);
        //query
        # Just let the user check the payment from user page
    } else {
        sendTelegram("BKASH payment " . $_GET['status'] . " for paymentID: " . $_GET['paymentID']);
    }
}

function bkash_get_token()
{
    global $config;
    $json = [
        'app_key' => $config['bkash_app_key'],
        'app_secret' => $config['bkash_app_secret']
    ];
    $exp = $config['bkash_token_expired'];
    $token = $config['bkash_token'];

    if (time() < $exp) {
        return $token;
    }
    if (!empty($config['bkash_refresh_token'])) {
        $json['refresh_token'] = $config['bkash_refresh'];
        $url = bkash_get_server() . 'checkout/token/refresh';
    } else {
        $url = bkash_get_server() . 'checkout/token/grant';
    }
    $headers = ['username: '.$config['bkash_username'], 'password: '. $config['bkash_password']];
    $result = json_decode(Http::postJsonData($url, $json, $headers), true);
    if ($result['statusMessage'] == 'Successful') {
        // if has refresh token from server, save it
        if (!empty($result['refresh_token'])) {
            $d = ORM::for_table('tbl_appconfig')->where('setting', 'bkash_refresh_token')->find_one();
            if ($d) {
                $d->value = $result['refresh_token'];
                $d->save();
            } else {
                $d = ORM::for_table('tbl_appconfig')->create();
                $d->setting = 'bkash_refresh_token';
                $d->value = $result['refresh_token'];
                $d->save();
            }
        }
        // save token
        $d = ORM::for_table('tbl_appconfig')->where('setting', 'bkash_token')->find_one();
        if ($d) {
            $d->value = $result['id_token'];
            $d->save();
        } else {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = 'bkash_token';
            $d->value = $result['id_token'];
            $d->save();
        }
        // save expire
        $d = ORM::for_table('tbl_appconfig')->where('setting', 'bkash_token_expired')->find_one();
        if ($d) {
            $d->value = time() + ($result['expires_in'] - 10);
            $d->save();
        } else {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = 'bkash_token_expired';
            $d->value = time() + ($result['expires_in'] - 10);
            $d->save();
        }
        return $result['id_token'];
    }else{
        sendTelegram("bKash payment failed\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction. ".$result['errorMessage']));
    }
}


function bkash_get_server()
{
    global $_app_stage;
    if ($_app_stage == 'Live') {
        return 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/';
    } else {
        return 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/';
    }
}
