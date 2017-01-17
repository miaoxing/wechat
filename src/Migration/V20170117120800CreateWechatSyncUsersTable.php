<?php

namespace Miaoxing\Wechat\Migration;

use Miaoxing\Plugin\BaseMigration;

class V20170117120800CreateWechatSyncUsersTable extends BaseMigration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->schema->table('wechatSyncUsers')
            ->id()->comment('待同步的用户ID')
            ->exec();
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->schema->dropIfExists('wechatSyncUsers');
    }
}
