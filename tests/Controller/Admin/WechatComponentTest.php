<?php

namespace MiaoxingTest\Wechat\Controller\Admin;

class WechatComponentTest extends \Miaoxing\Plugin\Test\BaseControllerTestCase
{
    protected $statusCodes = [
        'auth' => 302,
    ];

    /**
     * @dataProvider providerForActions
     * @param mixed $action
     * @param mixed|null $code
     */
    public function testActions($action, $code = null)
    {
        $mock = $this->getServiceMock('wechatComponentApi', [
            'createPreAuthCode',
        ]);
        $mock->expects($this->any())
            ->method('createPreAuthCode')
            ->willReturn(['code' => 1, 'message' => '操作成功']);

        parent::testActions($action, $code);
    }

    public function testRet()
    {
        wei()->curUser->loginById(1);

        $account = wei()->wechatAccount->getCurrentAccount();
        $account->save([
            'type' => 0,
            'authed' => 0,
            'verified' => 0,
            'nickName' => '',
            'headImg' => '',
            'sourceId' => '',
            'weChatId' => '',
            'qrcodeUrl' => '',
            'applicationId' => '',
            'refreshToken' => '',
            'funcInfo' => '',
            'businessInfo' => '',
        ]);

        $mock = $this->getServiceMock('wechatComponentApi', [
            'queryAuth', 'getAuthorizerInfo',
        ]);

        $mock->expects($this->once())
            ->method('queryAuth')
            ->with('123')
            ->willReturn([
                'code' => 1,
                'message' => '操作成功',
                'authorization_info' => [
                    'authorizer_appid' => 'authorizer_appid',
                    'authorizer_access_token' => 'authorizer_access_token',
                    'expires_in' => 7200,
                    'authorizer_refresh_token' => 'authorizer_refresh_token',
                    'func_info' => [
                        [
                            'funcscope_category' => [
                                'id' => 2,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 3,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 4,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 5,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 6,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 7,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 8,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 11,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 12,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 13,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 10,
                            ],
                        ],
                    ],
                ],
            ]);

        $mock->expects($this->once())
            ->method('getAuthorizerInfo')
            ->with('authorizer_appid')
            ->willReturn([
                'code' => 1,
                'message' => '操作成功',
                'authorizer_info' => [
                    'nick_name' => '喵星',
                    'head_img' => 'http://wx.qlogo.cn/mmopen/yhbXTIYkEZmMZFgrvAPxJ2xJAOicUwdTjcTQ1e2WblkhbRl4GiaOtJxnBautWrwup5eNYyI0c3OyBfYpsfibRN9MIwxtS9CV8qG/0',
                    'service_type_info' => [
                        'id' => 2,
                    ],
                    'verify_type_info' => [
                        'id' => 0,
                    ],
                    'user_name' => 'gh_0bef5cee2335',
                    'alias' => 'miaoxingkeji',
                    'qrcode_url' => 'http://mmbiz.qpic.cn/mmbiz/3eeHGLUVVjhhwF4xNNPa2P0mWS7HNx3iaHhgvkVzVLNoezTib19xeaVBWaYPB2IVaibic7wUOicrcrVcjSVzq21CNIg/0',
                    'business_info' => [
                        'open_pay' => 1,
                        'open_shake' => 0,
                        'open_scan' => 0,
                        'open_card' => 1,
                        'open_store' => 1,
                    ],
                ],
                'authorization_info' => [
                    'authorizer_appid' => 'wx5bc057225e11e225',
                    'func_info' => [
                        [
                            'funcscope_category' => [
                                'id' => 2,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 3,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 4,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 5,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 6,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 7,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 8,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 11,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 12,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 13,
                            ],
                        ],
                        [
                            'funcscope_category' => [
                                'id' => 10,
                            ],
                        ],
                    ],
                ],
            ]);

        $ret = wei()->tester()
            ->controller('admin/wechatComponent')
            ->action('ret')
            ->request(['auth_code' => '123'])
            ->json()
            ->exec()
            ->response();

        $this->assertRetSuc($ret, '授权成功');

        $account->reload();
        $this->assertEquals(2, $account['type']);
        $this->assertEquals(1, $account['authed']);
        $this->assertEquals(1, $account['verified']);
        $this->assertEquals('喵星', $account['nickName']);
        $this->assertEquals('http://wx.qlogo.cn/mmopen/yhbXTIYkEZmMZFgrvAPxJ2xJAOicUwdTjcTQ1e2WblkhbRl4GiaOtJxnBautWrwup5eNYyI0c3OyBfYpsfibRN9MIwxtS9CV8qG/0', $account['headImg']);
        $this->assertEquals('gh_0bef5cee2335', $account['sourceId']);
        $this->assertEquals('miaoxingkeji', $account['weChatId']);
        $this->assertEquals('http://mmbiz.qpic.cn/mmbiz/3eeHGLUVVjhhwF4xNNPa2P0mWS7HNx3iaHhgvkVzVLNoezTib19xeaVBWaYPB2IVaibic7wUOicrcrVcjSVzq21CNIg/0', $account['qrcodeUrl']);
        $this->assertEquals('authorizer_appid', $account['applicationId']);
        $this->assertEquals('authorizer_refresh_token', $account['refreshToken']);
        $this->assertEquals('[{"funcscope_category":{"id":2}},{"funcscope_category":{"id":3}},{"funcscope_category":{"id":4}},{"funcscope_category":{"id":5}},{"funcscope_category":{"id":6}},{"funcscope_category":{"id":7}},{"funcscope_category":{"id":8}},{"funcscope_category":{"id":11}},{"funcscope_category":{"id":12}},{"funcscope_category":{"id":13}},{"funcscope_category":{"id":10}}]', $account['funcInfo']);
        $this->assertEquals('{"open_pay":1,"open_shake":0,"open_scan":0,"open_card":1,"open_store":1}', $account['businessInfo']);

        $this->assertEquals('authorizer_access_token', $account->createComponentApiService()->getAuthorizerAccessToken($account['applicationId'], 'authorizer_refresh_token'));
    }

    public function testRetAuthCodeEmpty()
    {
        $ret = wei()->tester()
            ->controller('admin/wechatComponent')
            ->action('ret')
            ->request(['auth_code' => null])
            ->json()
            ->exec()
            ->response();

        $this->assertRetErr($ret, -1, '授权码不能为空');
    }

    public function testRetAppIdNotEqual()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->save([
            'applicationId' => 'wx12312321',
        ]);

        $mock = $this->getServiceMock('wechatComponentApi', [
            'queryAuth',
        ]);

        $mock->expects($this->once())
            ->method('queryAuth')
            ->with('123')
            ->willReturn([
                'code' => 1,
                'message' => '操作成功',
                'authorization_info' => [
                    'authorizer_appid' => 2,
                ],
            ]);

        $ret = wei()->tester()
            ->controller('admin/wechatComponent')
            ->action('ret')
            ->request(['auth_code' => 123])
            ->json()
            ->exec()
            ->response();

        $this->assertRetErr($ret, -1, '绑定失败,授权公众号与原公众号不一致');
    }

    public function testRetQueryAuthErr()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->save([
            'applicationId' => 'wx12312321',
        ]);

        $mock = $this->getServiceMock('wechatComponentApi', [
            'queryAuth',
        ]);

        $mock->expects($this->once())
            ->method('queryAuth')
            ->with('123')
            ->willReturn([
                'code' => -1,
                'message' => '测试错误',
            ]);

        $ret = wei()->tester()
            ->controller('admin/wechatComponent')
            ->action('ret')
            ->request(['auth_code' => 123])
            ->json()
            ->exec()
            ->response();

        $this->assertRetErr($ret, -1, '测试错误');
    }

    public function testRetGetAuthorizerInfoErr()
    {
        $account = wei()->wechatAccount->getCurrentAccount();

        $mock = $this->getServiceMock('wechatComponentApi', [
            'queryAuth', 'getAuthorizerInfo',
        ]);

        $mock->expects($this->once())
            ->method('queryAuth')
            ->with('123')
            ->willReturn([
                'code' => 1,
                'message' => '操作成功',
                'authorization_info' => [
                    'authorizer_appid' => $account['applicationId'],
                ],
            ]);

        $mock->expects($this->once())
            ->method('getAuthorizerInfo')
            ->with($account['applicationId'])
            ->willReturn([
                'code' => -1,
                'message' => '测试错误',
            ]);

        $ret = wei()->tester()
            ->controller('admin/wechatComponent')
            ->action('ret')
            ->request(['auth_code' => 123])
            ->json()
            ->exec()
            ->response();

        $this->assertRetErr($ret, -1, '测试错误');
    }
}
