<?php

namespace Miaoxing\Wechat\Migration;

use Miaoxing\Plugin\BaseMigration;

class V20170117142947CreateMessageTable extends BaseMigration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->schema->table('message')
            ->id()
            ->int('userId')
            ->int('platformId')
            ->string('platformMsgId', 32)
            ->string('msgType', 8)
            ->text('content')
            ->tinyInt('source')
            ->bool('fromKeyword')
            ->int('replyMessageId')
            ->bool('starred')
            ->int('createTimestamp')
            ->exec();
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->schema->dropIfExists('message');
    }
}
