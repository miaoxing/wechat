<?php

namespace MiaoxingTest\Wechat\Service;

use Wei\Http;

class WechatComponentApiTest extends \Miaoxing\Plugin\Test\BaseTestCase
{
    public function testGetAuthorizerAccessToken()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $api = $account->createComponentApiService();

        // TODO 移除缓存
        unset($api->http);

        $mock = $this->getServiceMock('http', ['__invoke']);
        $mock->expects($this->any())
            ->method('__invoke')
            ->willReturn(new Http([
                'wei' => $this->wei,
                'result' => true,
                'response' => [
                    'authorizer_access_token' => 'authorizer_access_token',
                    'expires_in' => time() + 7200,
                    'authorizer_refresh_token' => 'authorizer_refresh_token',
                ],
            ]));

        $token = $api->getAuthorizerAccessToken('appId', 'refreshToken');

        $this->assertEquals('authorizer_access_token', $token);

        unset($api->http);
    }

    public function testGetAuthorizerAccessTokenFromCache()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $api = $account->createComponentApiService();

        $mock = $this->getServiceMock('cache', ['get']);
        $mock->expects($this->at(0))
            ->method('get')
            ->willReturn([
                'accessToken' => 'accessToken',
                'expireTime' => time() + 7200,
            ]);
        $api->cache = $mock;

        $token = $api->getAuthorizerAccessToken('appId', 'refreshToken');
        $this->assertEquals('accessToken', $token);
    }

    public function testGetAuthorizerAccessTokenFromCacheButExpired()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $api = $account->createComponentApiService();

        $mock = $this->getServiceMock('cache', ['get']);
        $mock->expects($this->at(0))
            ->method('get')
            ->willReturn([
                'accessToken' => 'accessToken',
                'expireTime' => time(),
            ]);
        $api->cache = $mock;

        $http = $this->getServiceMock('http', ['__invoke']);
        $http->expects($this->any())
            ->method('__invoke')
            ->willReturn(new Http([
                'wei' => $this->wei,
                'result' => true,
                'response' => [
                    'authorizer_access_token' => 'authorizer_access_token',
                    'expires_in' => time() + 7200,
                    'authorizer_refresh_token' => 'authorizer_refresh_token',
                ],
            ]));

        $token = $api->getAuthorizerAccessToken('appId', 'refreshToken');
        $this->assertEquals('authorizer_access_token', $token);

        unset($api->http);
    }
}
