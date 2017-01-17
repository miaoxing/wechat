<?php

namespace Miaoxing\Wechat\Migration;

use Miaoxing\Plugin\BaseMigration;

class V20170117113758CreateWechatAccountsTable extends BaseMigration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->schema->table('wechatAccounts')
            ->id()
            ->int('appId')
            ->tinyInt('type')
            ->string('sourceId', 32)->comment('微信原始ID')
            ->string('weChatId', 32)->comment('微信号')
            ->string('nickName', 16)
            ->string('headImg')
            ->string('qrcodeUrl')
            ->string('applicationId', 32)->comment('公众号的appId')
            ->string('applicationSecret', 64)
            ->string('token', 32)
            ->bool('verified')->comment('是否认证')
            ->bool('authed')->comment('是否已通过第三方平台授权')
            ->bool('transferCustomer')
            ->string('encodingAesKey', 64)->comment('密文密钥')
            ->string('refreshToken', 64)->comment('授权方的刷新令牌')
            ->string('verifyTicket', 128)->comment('component_verify_ticket')
            ->text('funcInfo')
            ->text('businessInfo')
            ->timestamps()
            ->userstamps()
            ->exec();
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->schema->dropIfExists('wechatAccounts');
    }
}
