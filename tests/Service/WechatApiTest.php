<?php

namespace MiaoxingTest\Wechat\Service;

use Miaoxing\Wechat\Service\WechatApi;
use Miaoxing\Wechat\Service\WechatComponentApi;
use Wei\ArrayCache;
use Wei\Http;
use Wei\StatsD;

class WechatApiTest extends \Miaoxing\Plugin\Test\BaseTestCase
{
    public function testGetAccessToken()
    {
        $http = $this->getServiceMock(Http::class, ['__invoke']);

        $api = new WechatApi([
            'appId' => 'x',
            'appSecret' => 'y',
            'cache' => new ArrayCache(),
            'http' => $http,
        ]);

        $http->expects($this->once())
            ->method('__invoke')
            ->with([
                'url' => 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential',
                'method' => 'GET',
                'data' => [
                    'appid' => 'x',
                    'secret' => 'y',
                ],
                'throwException' => false,
                'dataType' => 'json',
            ])
            ->willReturn(new Http([
                'wei' => $this->wei,
                'result' => true,
                'ch' => curl_init(),
                'response' => [
                    'access_token' => 'access_token',
                    'expires_in' => time() + 7200,
                ],
            ]));

        $ret = $api->getAccessToken();
        $this->assertRetSuc($ret);
        $this->assertSame('access_token', $ret['accessToken']);
    }

    public function testGetAccessTokenWhenAuthed()
    {
        $componentApi = $this->getServiceMock(WechatComponentApi::class, ['getAuthorizerAccessToken']);
        $componentApi->expects($this->once())
            ->method('getAuthorizerAccessToken')
            ->with('x', 'refreshToken')
            ->willReturn('getAuthorizerAccessToken');

        $api = new WechatApi([
            'appId' => 'x',
            'appSecret' => 'y',
            'refreshToken' => 'refreshToken',
            'authed' => true,
            'cache' => new ArrayCache(),
            'wechatComponentApi' => $componentApi,
        ]);

        $ret = $api->getAccessToken();
        $this->assertRetSuc($ret);
        $this->assertEquals('getAuthorizerAccessToken', $ret['accessToken']);
    }

    public function testGetOAuth2AccessTokenByAuthAndNotAuthed()
    {
        $this->markTestSkipped('待升级');

        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'authed' => false,
            'applicationId' => 'accountAppId',
            'applicationSecret' => 'accountAppSecret',
        ]);
        $api = $account->createApiService();
        unset($api->http);

        $mock = $this->getServiceMock('http', ['__invoke']);
        $mock->expects($this->once())
            ->method('__invoke')
            ->with([
                'url' => 'https://api.weixin.qq.com/sns/oauth2/access_token',
                'dataType' => 'json',
                'throwException' => false,
                'data' => [
                    'code' => 'code',
                    'appid' => 'accountAppId',
                    'secret' => 'accountAppSecret',
                    'grant_type' => 'authorization_code',
                ],
            ])
            ->willReturn(new Http([
                'wei' => $this->wei,
                'result' => true,
                'response' => [
                    'openid' => 'openid',
                ],
            ]));

        $ret = $api->getOAuth2AccessTokenByAuth(['code' => 'code']);

        $this->assertEquals('openid', $ret['openid']);
    }

    public function testGetOAuth2AccessTokenByAuthAndAuthed()
    {
        $this->markTestSkipped('待升级');

        $wechatComponentApi = $this->getServiceMock('wechatComponentApi', ['getOAuth2AccessToken']);
        $wechatComponentApi->expects($this->once())
            ->method('getOAuth2AccessToken')
            ->with(['code' => 'code', 'appid' => 'accountAppId'])
            ->willReturn([
                'code' => 1,
                'message' => '操作成功',
                'openid' => 'openid',
            ]);

        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'authed' => true,
            'applicationId' => 'accountAppId',
            'applicationSecret' => 'accountAppSecret',
        ]);
        $api = $account->createApiService();

        $ret = $api->getOAuth2AccessTokenByAuth(['code' => 'code']);
        $this->assertEquals([
            'code' => 1,
            'message' => '操作成功',
            'openid' => 'openid',
        ], $ret);
    }

    public function testCredentialInvalidAndRetry()
    {
        $http = $this->getServiceMock(Http::class, ['__invoke']);

        $api = new WechatApi([
            'appId' => 'x',
            'appSecret' => 'y',
            'cache' => new ArrayCache(),
            'http' => $http,
        ]);

        // 先获取凭证
        $http->expects($this->at(0))
            ->method('__invoke')
            ->with([
                'url' => 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential',
                'dataType' => 'json',
                'throwException' => false,
                'method' => 'GET',
                'data' => [
                    'appid' => 'x',
                    'secret' => 'y',
                ],
            ])
            ->willReturn(new Http([
                'wei' => $this->wei,
                'ch' => curl_init(),
                'result' => true,
                'response' => [
                    'access_token' => 'access_token',
                    'expires_in' => time() + 7200,
                ],
            ]));

        // 首次请求凭证无效
        $http->expects($this->at(1))
            ->method('__invoke')
            ->with([
                'dataType' => 'json',
                'url' => 'https://api.weixin.qq.com/cgi-bin/tags/create?access_token=access_token',
                'throwException' => false,
                'method' => 'POST',
                'data' => '{"tag":{"name":"tag1"}}',
            ])
            ->willReturn(new Http([
                'wei' => $this->wei,
                'ch' => curl_init(),
                'result' => true,
                'response' => [
                    'errcode' => 40001,
                    'errmsg' => 'invalid credential, access_token is invalid or not latest',
                ],
            ]));

        // 上报凭证无效
        $statsD = $this->getServiceMock(StatsD::class, ['increment']);
        $statsD->expects($this->once())
            ->method('increment')
            ->with('wechat.credentialInvalid');

        // 重新获取凭证
        $http->expects($this->at(2))
            ->method('__invoke')
            ->with([
                'url' => 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential',
                'dataType' => 'json',
                'throwException' => false,
                'method' => 'GET',
                'data' => [
                    'appid' => 'x',
                    'secret' => 'y',
                ],
            ])
            ->willReturn(new Http([
                'wei' => $this->wei,
                'result' => true,
                'ch' => curl_init(),
                'response' => [
                    'access_token' => 'access_token_new',
                    'expires_in' => time() + 7200,
                ],
            ]));

        // 再次调用接口获取用户信息
        $http->expects($this->at(3))
            ->method('__invoke')
            ->with([
                'dataType' => 'json',
                'url' => 'https://api.weixin.qq.com/cgi-bin/tags/create?access_token=access_token&access_token=access_token_new',
                'throwException' => false,
                'method' => 'POST',
                'data' => '{"tag":{"name":"tag1"}}',
            ])
            ->willReturn(new Http([
                'wei' => $this->wei,
                'result' => true,
                'ch' => curl_init(),
                'response' => [
                    'tag' => [
                        'id' => 101,
                        'name' => 'tag1',
                    ],
                ],
            ]));

        $ret = $api->createTag(['tag' => ['name' => 'tag1']]);

        $this->assertRetSuc($ret);
        $this->assertSame(101, $ret['tag']['id']);
    }

    public function testGetTokenLock()
    {
        $this->markTestSkipped('待升级');

        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'authed' => false,
        ]);
        $api = $account->createApiService();

        $http = $this->getServiceMock('http', ['__invoke']);
        $api->http = $http;

        $http->expects($this->never())
            ->method('__invoke');

        // 模拟加锁
        $lockKey = 'wechat:getToken:' . $account['applicationId'];
        wei()->cache->set($lockKey, 1);

        $api->removeAccessTokenByAuth();
        $token = $api->getToken();

        wei()->cache->delete($lockKey);

        $this->assertFalse($token);
    }
}
