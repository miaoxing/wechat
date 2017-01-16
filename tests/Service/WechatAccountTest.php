<?php

namespace MiaoxingTest\Wechat\Service;

class WechatAccountTest extends \Miaoxing\Plugin\Test\BaseTestCase
{
    public function testGetOauth2Url()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'applicationId' => 'applicationId',
            'authed' => false,
        ]);

        $url = $account->getOauth2Url('http://test.com', 'snsapi_base');

        $this->assertEquals('https://open.weixin.qq.com/connect/oauth2/authorize?appid=applicationId&redirect_uri=http%3A%2F%2Ftest.com&response_type=code&scope=snsapi_base#wechat_redirect', $url);
    }

    public function testGetOauth2UrlWhenAuthed()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'applicationId' => 'applicationId',
            'authed' => true,
        ]);

        $wechatComponentApi = $this->getServiceMock('wechatComponentApi', ['getAppId']);
        $wechatComponentApi->expects($this->once())
            ->method('getAppId')
            ->willReturn('wechatComponentAppId');

        $url = $account->getOauth2Url('http://test.com', 'snsapi_base');

        $this->assertEquals('https://open.weixin.qq.com/connect/oauth2/authorize?appid=applicationId&redirect_uri=http%3A%2F%2Ftest.com&response_type=code&scope=snsapi_base&component_appid=wechatComponentAppId#wechat_redirect', $url);
    }
}
