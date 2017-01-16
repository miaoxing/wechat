<?php

namespace MiaoxingTest\Wechat\Controller\Admin;

class WechatAccountTest extends \Miaoxing\Plugin\Test\BaseControllerTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->wei->app->setNamespace('test');
    }
}
