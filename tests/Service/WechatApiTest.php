<?php

namespace MiaoxingTest\Wechat\Service;

use Wei\Http;

class WechatApiTest extends \Miaoxing\Plugin\Test\BaseTestCase
{
    public function testGetAccessTokenByAuth()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'authed' => false,
        ]);

        $http = $this->getServiceMock('http', ['__invoke']);

        $api = $account->createApiService();
        $api->http = $http;

        $http->expects($this->any())
            ->method('__invoke')
            ->willReturn(new Http([
                'wei' => $this->wei,
                'result' => true,
                'ch' => curl_init(),
                'response' => [
                    'access_token' => 'access_token',
                    'expires_in' => time() + 7200,
                ],
            ]));

        $api->removeAccessTokenByAuth();
        $token = $api->getAccessTokenByAuth();

        $this->assertEquals('access_token', $token);
    }

    public function testGetAccessTokenByAuthWhenAuthed()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'authed' => true,
        ]);

        $mock = $this->getServiceMock('wechatComponentApi', ['getAuthorizerAccessToken']);
        $mock->expects($this->any())
            ->method('getAuthorizerAccessToken')
            ->willReturn('getAuthorizerAccessToken');

        $api = $account->createApiService();
        unset($api->http);

        $api->removeAccessTokenByAuth();
        $token = $api->getAccessTokenByAuth();

        $this->assertEquals('getAuthorizerAccessToken', $token);
    }

    public function testGetOAuth2AccessTokenByAuthAndNotAuthed()
    {
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
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->setData([
            'authed' => false,
        ]);
        $api = $account->createApiService();

        $http = $this->getServiceMock('http', ['__invoke']);
        $api->http = $http;

        // 先获取凭证
        $http->expects($this->at(0))
            ->method('__invoke')
            ->with([
                'url' => 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential',
                'dataType' => 'json',
                'throwException' => false,
                'data' => [
                    'appid' => $account['applicationId'],
                    'secret' => $account['applicationSecret'],
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
                'timeout' => 10000,
                'dataType' => 'json',
                'url' => 'https://api.weixin.qq.com/cgi-bin/user/get',
                'data' => [
                    'access_token' => 'access_token',
                    'next_openid' => null,
                ],
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
        $statsD = $this->getServiceMock('statsD', ['increment']);
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
                'data' => [
                    'appid' => $account['applicationId'],
                    'secret' => $account['applicationSecret'],
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
                'timeout' => 10000,
                'dataType' => 'json',
                'url' => 'https://api.weixin.qq.com/cgi-bin/user/get',
                'data' => [
                    'access_token' => 'access_token_new',
                    'next_openid' => null,
                ],
            ])
            ->willReturn(new Http([
                'wei' => $this->wei,
                'result' => true,
                'ch' => curl_init(),
                'response' => [
                    'count' => 1,
                ],
            ]));

        $api->removeAccessTokenByAuth();
        $http = $api->getUserOpenIds();

        $this->assertRetSuc($api->getResult());
        $this->assertSame(1, $http['count']);
    }

    public function testGetTokenLock()
    {
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

        wei()->cache->remove($lockKey);

        $this->assertFalse($token);
    }
}
