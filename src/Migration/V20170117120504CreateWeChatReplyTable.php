<?php

namespace Miaoxing\Wechat\Migration;

use Miaoxing\Plugin\BaseMigration;

class V20170117120504CreateWeChatReplyTable extends BaseMigration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->schema->table('weChatReply')
            ->string('id', 20)
            ->int('accountId')
            ->string('type', 16)
            ->string('keywords')
            ->tinyInt('matchType')
            ->text('content')->comment('文本回复内容')
            ->string('articleIds')->comment('图文回复的文章编号')
            ->string('replies', 1024)
            ->timestampsV1()
            ->userstampsV1()
            ->softDeletableV1()
            ->primary('id')
            ->exec();
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->schema->dropIfExists('weChatReply');
    }
}
