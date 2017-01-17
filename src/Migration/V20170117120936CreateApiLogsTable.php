<?php

namespace Miaoxing\Wechat\Migration;

use Miaoxing\Plugin\BaseMigration;

class V20170117120936CreateApiLogsTable extends BaseMigration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->schema->table('apiLogs')
            ->id()
            ->int('appId')
            ->mediumInt('code')->comment('HTTP请求或接口返回的状态码')
            ->string('url')->comment('请求地址')
            ->text('options')->comment('请求参数')
            ->mediumText('req')
            ->mediumText('res')
            ->timestamp('createTime')
            ->timestamp('createUser')
            ->exec();
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->schema->dropIfExists('apiLogs');
    }
}
