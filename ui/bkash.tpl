{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" autocomplete="off" role="form" action="{$_url}paymentgateway/bkash">
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">bKash Tokenizer Settings</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-2 control-label">APP Key</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="bkash_app_key" name="bkash_app_key"
                                value="{$_c['bkash_app_key']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">APP Secret</label>
                        <div class="col-md-6">
                            <input type="password" class="form-control" id="bkash_app_secret" name="bkash_app_secret"
                                value="{$_c['bkash_app_secret']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Username</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="bkash_username" name="bkash_username"
                                value="{$_c['bkash_username']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">password</label>
                        <div class="col-md-6">
                            <input type="password" class="form-control" id="bkash_password" name="bkash_password"
                                value="{$_c['bkash_password']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Url Callback</label>
                        <div class="col-md-6">
                            <input type="text" readonly class="form-control" onclick="this.select()"
                                value="{$_url}callback/bkash">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary waves-effect waves-light" type="submit">{$_L['Save']}</button>
                        </div>
                    </div>
                    <pre>/ip hotspot walled-garden
add dst-host=bkash.com
add dst-host=*.bkash.com
add dst-host=*.bka.sh
</pre>
                    <small id="emailHelp" class="form-text text-muted">Set Telegram Bot to get any error and
                        notification</small>
                </div>
            </div>

        </div>
    </div>
</form>
{include file="sections/footer.tpl"}
