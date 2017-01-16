<?php

namespace MiaoxingTest\Wechat\Middleware;

use Miaoxing\Wechat\Middleware\Auth;
use Miaoxing\Wechat\Service\WechatAccount;

class AuthTest extends \Miaoxing\Plugin\Test\BaseTestCase
{
    public function testLoginByOAuth2CodeWithNewUser()
    {
        wei()->request->set('code', 'Test');
        wei()->cache->remove('wechatOAuth2CodeTest');

        $mock = $this->getServiceMock('wechatAccount', ['getCurrentAccount']);
        $mock->expects($this->exactly(2))
            ->method('getCurrentAccount')
            ->willReturn(wei()->wechatAccount()->fromArray([
                'type' => WechatAccount::SERVICE,
                'verified' => true,
            ]));

        $openId = 'test' . mt_rand(1, 10000000);
        $mock = $this->getServiceMock('wechatApi', ['getOAuth2AccessToken']);
        $mock->expects($this->once())
            ->method('getOAuth2AccessToken')
            ->willReturn(wei()->http->setOption([
                'response' => [
                    'openid' => $openId,
                ],
            ]));

        $this->callMiddleware();

        // NOTE Memcache会把true转换为1
        $this->assertTrue(wei()->cache->get('wechatOAuth2CodeTest'));
        $this->assertTrue(wei()->curUser->isLogin());
        $this->assertEquals($openId, wei()->curUser['wechatOpenId']);
    }

    public function testLoginByOAuth2CodeWithExistingUser()
    {
        wei()->request->set('code', 'Test');
        wei()->cache->remove('wechatOAuth2CodeTest');

        $mock = $this->getServiceMock('wechatAccount', ['getCurrentAccount']);
        $mock->expects($this->exactly(2))
            ->method('getCurrentAccount')
            ->willReturn(wei()->wechatAccount()->fromArray([
                'type' => WechatAccount::SERVICE,
                'verified' => true,
            ]));

        $user = wei()->user()->where("wechatOpenId != ''")->find();
        $openId = $user['wechatOpenId'];
        $mock = $this->getServiceMock('wechatApi', ['getOAuth2AccessToken']);
        $mock->expects($this->once())
            ->method('getOAuth2AccessToken')
            ->willReturn(wei()->http->setOption([
                'response' => [
                    'openid' => $openId,
                ],
            ]));

        $response = $this->callMiddleware();

        $this->assertNotEmpty(wei()->cache->get('wechatOAuth2CodeTest'));
        $this->assertEquals(true, wei()->curUser->isLogin());
        $this->assertEquals($openId, wei()->curUser['wechatOpenId']);
    }

    public function testLoginByOAuth2CodeButGetOAuth2AccessTokenFail()
    {
        wei()->request->set('code', 'Test');
        wei()->cache->remove('wechatOAuth2CodeTest');

        $mock = $this->getServiceMock('wechatAccount', ['getCurrentAccount']);
        $mock->expects($this->exactly(2))
            ->method('getCurrentAccount')
            ->willReturn(wei()->wechatAccount()->fromArray([
                'type' => WechatAccount::SERVICE,
                'verified' => true,
            ]));

        // 模拟通过code获取用户OpenID失败
        $mock = $this->getServiceMock('wechatApi', ['getOAuth2AccessToken']);
        $mock->expects($this->once())
            ->method('getOAuth2AccessToken')
            ->willReturn(false);

        $response = $this->callMiddleware();

        $this->assertContains('很抱歉,微信授权失败,请返回再试', $response->getContent());
        $this->assertEquals(true, wei()->cache->get('wechatOAuth2CodeTest'));
    }

    public function testLoginByOAuth2CodeButCodeUsed()
    {
        wei()->request->set('code', 'Test');
        wei()->cache->set('wechatOAuth2CodeTest', true);

        $response = $this->callMiddleware();

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains('Redirecting to', $response->getContent());
    }

    /**
     * @dataProvider dataForNotVerifiedService
     * @param array $account
     */
    public function testLoginByOAuth2CodeButAccountIsNotVerifiedService(array $account)
    {
        wei()->request->set('code', 'Test');
        wei()->cache->remove('wechatOAuth2CodeTest');

        $mock = $this->getServiceMock('wechatAccount', ['getCurrentAccount']);
        $mock->expects($this->once())
            ->method('getCurrentAccount')
            ->willReturn(wei()->wechatAccount()->fromArray($account));

        $response = $this->callMiddleware();

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains('Redirecting to', $response->getContent());
        $this->assertFalse(wei()->cache->get('wechatOAuth2CodeTest'));
    }

    /**
     * @dataProvider dataForGetInvalidCodeAndRetry
     * @param string $url
     * @param string $redirectUrl
     */
    public function testGetInvalidCodeAndRetry($url, $redirectUrl)
    {
        // 1. 初始化请求的参数
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $queries);
        wei()->request->set($queries);

        wei()->request->set('code', 'Test');
        wei()->cache->remove('wechatOAuth2CodeTest');

        $request = $this->getServiceMock('request', ['getUrl']);
        $request->expects($this->once())
            ->method('getUrl')
            ->willReturn($url);

        $wechatAccount = $this->getServiceMock('wechatAccount', ['getCurrentAccount']);

        // 第一次验证是否为服务号
        // 第二次linkTo生成URL
        $wechatAccount->expects($this->exactly(3))
            ->method('getCurrentAccount')
            ->willReturn(wei()->wechatAccount()->fromArray([
                'type' => WechatAccount::SERVICE,
                'verified' => true,
            ]));

        $mock = $this->getServiceMock('wechatApi', ['getOAuth2AccessTokenByAuth']);
        $mock->expects($this->once())
            ->method('getOAuth2AccessTokenByAuth')
            ->willReturn([
                'errcode' => 40029,
                'errmsg' => 'invalid code',
            ]);

        $response = $this->callMiddleware();
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains(urlencode($redirectUrl), $response->getHeader('Location'));
    }

    public function testGetInvalidCodeAndNoRetry()
    {
        wei()->request->set([
            'code' => 'Test',
            'wechatRetries' => '0',
        ]);
        wei()->cache->remove('wechatOAuth2CodeTest');

        $wechatApi = $this->getServiceMock('wechatApi', ['getOAuth2AccessTokenByAuth']);
        $wechatApi->expects($this->once())
            ->method('getOAuth2AccessTokenByAuth')
            ->willReturn([
                'errcode' => 40029,
                'errmsg' => 'invalid code',
            ]);

        $response = $this->callMiddleware();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('很抱歉,微信授权失败,请返回再试', $response->getContent());
    }

    protected function callMiddleware()
    {
        $middleware = new \Miaoxing\Wechat\Middleware\Auth();
        $middleware(function () {
            throw new \Exception('Not call');
        });

        return $middleware->response;
    }

    public function dataForGetInvalidCodeAndRetry()
    {
        return [
            // 首次请求
            [
                'url' => 'http://test.com',
                'redirectUrl' => 'http://test.com?wechatRetries=1',
            ],
            // 失败后重试
            [
                'url' => 'http://test.com?code=Test&wechatRetries=1',
                'redirectUrl' => 'http://test.com?code=Test&wechatRetries=1&wechatRetries=0',
            ],
            // 第二次失败,wechatRetries=0,直接提示错误
            // 见testGetInvalidCodeAndNoRetry
        ];
    }

    public function dataForNotVerifiedService()
    {
        return [
            [
                [
                    // 订阅号
                    'type' => WechatAccount::SUBSCRIBE,
                ],
            ],
            [
                [
                    // 未认证服务号
                    'type' => WechatAccount::SERVICE,
                    'verified' => false,
                ],
            ],
        ];
    }
}
