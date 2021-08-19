<?php

namespace Miaoxing\Wechat\Service;

use Miaoxing\Plugin\BaseService;
use Wei\Http;
use Wei\Ret;

/**
 * @mixin \HttpMixin
 * @mixin \CacheMixin
 * @mixin \LoggerMixin
 * @mixin \UrlMixin
 * @mixin \StatsDMixin
 * @mixin \WechatComponentApiMixin
 */
class WechatApi extends BaseService
{
    /**
     * @var string
     */
    protected $appId;

    /**
     * @var string
     */
    protected $appSecret;

    /**
     * 是否通过第三方平台授权
     *
     * @var bool
     */
    protected $authed = false;

    /**
     * 授权方的刷新令牌,通过第三方平台调用接口时需提供
     *
     * @var string
     */
    protected $refreshToken;

    /**
     * 全局唯一票据
     *
     * @var string
     */
    protected $accessToken;

    /**
     * @var string
     */
    protected $baseUrl = 'https://api.weixin.qq.com/';

    /**
     * @var array
     */
    protected $messages = [
        0 => '操作成功',
        40001 => '获取access_token时AppSecret错误，或者access_token无效',
        41002 => '缺少appid参数',
        40003 => '不合法的OpenID',
        40006 => '不合法的文件大小',
        41004 => '缺少secret参数',
        40013 => '不合法的APPID',
        40014 => '不合法的access_token',
        40015 => '不合法的菜单类型',
        40016 => '不合法的按钮个数',
        40017 => '不合法的按钮个数',
        40018 => '不合法的按钮名字长度',
        40019 => '不合法的按钮KEY长度',
        40020 => '不合法的按钮URL长度',
        40021 => '不合法的菜单版本号',
        40022 => '不合法的子菜单级数',
        40023 => '不合法的子菜单按钮个数',
        40024 => '不合法的子菜单按钮类型',
        40025 => '不合法的子菜单按钮名字长度',
        40026 => '不合法的子菜单按钮KEY长度',
        40027 => '不合法的子菜单按钮URL长度',
        40028 => '不合法的自定义菜单使用用户',
        40029 => '不合法的oauth_code',
        46003 => '不存在菜单数据',
        // 卡券需展示具体的错误信息
        //47001 => '解析JSON/XML内容错误',
        48001 => 'api功能未授权',
        // 卡券
        40079 => '有效期中的时间不合法',
        //41011 => '必填字段不完整或不合法，参考相应接口',
    ];

    /**
     * 指定返回的errcode对应的日志等级
     *
     * @var array
     */
    protected $logLevels = [
        43004 => 'info',
        -1000 => 'info',
    ];

    /**
     * @return Ret
     */
    public function initAccessToken(): Ret
    {
        if ($this->accessToken) {
            return suc();
        }

        $credential = $this->getCredentialFromCache();
        if (!$credential || $credential['expireTime'] - time() < 60) {
            $ret = $this->getToken();
            if ($ret->isErr()) {
                return $ret;
            }

            $this->accessToken = $ret['access_token'];
            $this->setCredentialToCache([
                'accessToken' => $ret['access_token'],
                'expireTime' => time() + $ret['expires_in'],
            ]);
        } else {
            $this->accessToken = $credential['accessToken'];
        }

        return suc();
    }

    /**
     * @param array|string $options
     * @return Ret
     */
    public function call($options): Ret
    {
        // 1. 获取Access token
        if (!$this->accessToken) {
            $ret = $this->initAccessToken();
            if ($ret->isErr()) {
                return $ret;
            }
        }

        // 2. 附加并调用
        $options['url'] = $this->url->append($options['url'], ['access_token' => $this->accessToken]);
        return $this->callWithoutToken($options);
    }

    /**
     * @param $options
     * @param bool $retried
     * @return Ret
     */
    public function callWithoutToken($options, bool $retried = false): Ret
    {
        // 1. 发送请求
        $options['throwException'] = false;
        $http = $this->http($options);

        // 2. 成功直接返回
        $ret = $this->parseResponse($http);
        if ($ret->isSuc()) {
            return $ret;
        }

        // 3. 处理接口返回错误
        // 如果是 Access token 无效或过期,清除缓存数据，然后重试一次
        if ($http['errcode'] == 40001 || $http['errcode'] == 42001) {
            $this->removeAccessTokenByAuth();
            if (!$retried) {
                $this->statsD->increment('wechat.credentialInvalid');
                return $this->callWithoutToken($options, true);
            }
        }
        return $ret;
    }

    /**
     * @param Http $http
     * @return Ret
     * @todo 可能换名称
     */
    protected function parseResponse(Http $http): Ret
    {
        if (!$http->isSuccess()) {
            return $http->toRet();
        }

        if (isset($http['errcode']) && $http['errcode'] !== 0) {
            $code = $http['errcode'];
            $message = $http['errmsg'];
        } elseif (isset($http['base_resp'])) {
            // 硬件接口的返回
            $code = $http['base_resp']['errcode'];
            $message = $http['base_resp']['errmsg'];
        } elseif (isset($http['error_code'])) {
            // 硬件 Open API 的返回
            $code = $http['error_code'];
            $message = $http['error_msg'];
        } else {
            return suc($http->getResponse());
        }

        $parts = explode(' hint ', $message);
        return err([
            'code' => -abs($code),
            'message' => $this->messages[$code] ?? rtrim($parts[0], ','),
            'detail' => $parts[1] ?? null,
        ]);
    }

    /**
     * 告警微信接口失败
     *
     * @param Http $http
     * @param array $credential
     * @todo
     */
    protected function logError(Http $http, array $credential = [])
    {
        $credential['currentTime'] = time();

        // 移除结尾的请求ID,使相同信息合并成一条
        $message = explode(' hint', $http['errmsg'])[0];
        $message = rtrim($message, ',');
        $level = isset($this->logLevels[$http['errcode']]) ? $this->logLevels[$http['errcode']] : 'warning';
        $this->logger->log($level, '微信接口失败: ' . $http['errcode'] . ' ' . $message, [
            'url' => $http->getUrl(),
            'data' => $http->getData(),
            'res' => $http->getResponse(),
            'ret' => $this->getResult(),
            'credential' => $credential,
        ]);
    }

    /**
     * 根据帐号是否授权获取不同的Access token
     *
     * @return string
     */
    public function getAccessTokenByAuth()
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        if ($this->authed) {
            $this->accessToken = $this->wechatComponentApi->getAuthorizerAccessToken(
                $this->getAppId(),
                $this->getRefreshToken()
            );
        } else {
            $this->getAccessToken();
        }

        return $this->accessToken;
    }

    /**
     * 根据帐号是否授权移除相应的Access token
     *
     * @return $this
     */
    public function removeAccessTokenByAuth()
    {
        $this->accessToken = null;
        if ($this->authed) {
            $this->wechatComponentApi->removeAuthorizerAccessToken($this->getAppId());
        } else {
            $this->removeAccessToken();
        }

        return $this;
    }

    /**
     * 获取Access token,如果过期,自动刷新
     *
     * @return string
     */
    public function getAccessToken()
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $cacheKey = $this->getAccessTokenCacheKey();
        $credential = $this->cache->get($cacheKey);

        if (!$credential || $credential['expireTime'] - time() < 60) {
            if (!$http = $this->getToken()) {
                return false;
            }

            $this->accessToken = $http['access_token'];
            $this->cache->set($cacheKey, [
                'accessToken' => $http['access_token'],
                'expireTime' => time() + $http['expires_in'],
            ]);
        } else {
            $this->accessToken = $credential['accessToken'];
        }

        return $this->accessToken;
    }

    /**
     * 移除Access token的值
     */
    public function removeAccessToken()
    {
        $this->cache->remove($this->getAccessTokenCacheKey());

        return $this;
    }

    protected function getCredentialFromCache()
    {
        return $this->cache->get($this->getAccessTokenCacheKey());
    }

    protected function setCredentialToCache($credential)
    {
        return $this->cache->set($this->getAccessTokenCacheKey(), $credential);
    }

    /**
     * @return string
     */
    protected function getAccessTokenCacheKey(): string
    {
        return 'wechat:accessToken:' . $this->getAppId();
    }

    /**
     * 获取token
     *
     * @return Ret
     */
    public function getToken(): Ret
    {
        // 加锁防止重复生成token
        $lockKey = 'wechat:getToken:' . $this->getAppId();
        if (!$this->cache->add($lockKey, 1, $this->http->getOption('timeout') / 1000)) {
            return err('网络缓慢，请稍后再试');
        }

        $ret = $this->callWithoutToken([
            'url' => $this->baseUrl . 'cgi-bin/token?grant_type=client_credential',
            'dataType' => 'json',
            'data' => [
                'appid' => $this->getAppId(),
                'secret' => $this->getAppSecret(),
            ],
        ]);
        $this->cache->remove($lockKey);

        return $ret;
    }

    /**
     * 获取全新的api_ticket
     *
     * @param string $type 如jsapi,wx_card
     * @return false|Http
     */
    public function getApiTicket($type)
    {
        return $this->auth(function () use ($type) {
            return $this->http->getJson(
                $this->baseUrl . 'cgi-bin/ticket/getticket',
                ['access_token' => $this->accessToken, 'type' => $type]
            );
        });
    }

    /**
     * 从缓存中获取api_ticket
     *
     * @param string $type
     * @return string
     */
    public function getApiTicketFromCache($type)
    {
        $cacheKey = 'ticket' . $type . $this->getAppId();
        $credential = $this->cache->get($cacheKey);
        if (!$credential || $credential['expireTime'] - time() < 60) {
            if (!$http = $this->getApiTicket($type)) {
                return false;
            }

            // 存储到缓存,方便下个请求获取
            $credential = [
                'ticket' => $http['ticket'],
                'expireTime' => time() + $http['expires_in'],
            ];
            $this->cache->set($cacheKey, $credential);
        }

        if (!$credential['ticket']) {
            $this->logger->alert(sprintf('生成 %s ticket失败', $type));
        }

        return $credential['ticket'];
    }

    /**
     * 获取AppId参数
     *
     * @return string
     */
    public function getAppId(): string
    {
        return $this->appId;
    }

    /**
     * 获取AppSecret参数
     *
     * @return string
     */
    public function getAppSecret(): string
    {
        return $this->appSecret;
    }

    /**
     * 获取刷新令牌
     *
     * @return string
     */
    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function createGroup(array $groups)
    {
        $groups = json_encode($groups, JSON_UNESCAPED_UNICODE);

        return $this->auth(function () use ($groups) {
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/groups/create?access_token=' . $this->accessToken,
                $groups
            );
        });
    }

    /**
     * 查询所有分组
     * @return false|Http
     */
    public function batchGetGroup()
    {
        return $this->auth(function () {
            return $this->http->postJson($this->baseUrl . 'cgi-bin/groups/get?access_token=' . $this->accessToken);
        });
    }

    /**
     * 查询用户所在的groupid
     * @param $openId
     * @return false|Http
     */
    public function getUserGroupId($openId)
    {
        return $this->auth(function () use ($openId) {
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/groups/getid?access_token=' . $this->accessToken,
                json_encode(
                    [
                        'openid' => $openId,
                    ],
                    JSON_UNESCAPED_UNICODE
                )
            );
        });
    }

    /**
     * 修改分组
     * @param $groupId
     * @param $name
     * @return false|Http
     */
    public function updateGroup(array $groups)
    {
        $groups = json_encode($groups, JSON_UNESCAPED_UNICODE);

        return $this->auth(function () use ($groups) {
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/groups/update?access_token=' . $this->accessToken,
                $groups
            );
        });
    }

    /**
     * 移动用户分组
     * @param $openId
     * @param $toGroupId
     * @return false|Http
     */
    public function updateMemberGroup($openId, $toGroupId)
    {
        return $this->auth(function () use ($openId, $toGroupId) {
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/groups/members/update?access_token=' . $this->accessToken,
                json_encode(
                    [
                        'openid' => $openId,
                        'to_groupid' => $toGroupId,
                    ],
                    JSON_UNESCAPED_UNICODE
                )
            );
        });
    }

    /**
     * 批量修改用户分组
     * @param array $openIdList
     * @param $toGroupId
     * @return false|Http
     */
    public function updateBatchMemberGroup(array $openIdList, $toGroupId)
    {
        return $this->auth(function () use ($openIdList, $toGroupId) {
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/groups/members/batchupdate?access_token=' . $this->accessToken,
                json_encode(
                    [
                        'openid_list' => $openIdList,
                        'to_groupid' => $toGroupId,
                    ],
                    JSON_UNESCAPED_UNICODE
                )
            );
        });
    }

    /**
     * 删除分组
     * @param $groupId
     * @return false|Http
     */
    public function deleteGroup($groupId)
    {
        return $this->auth(function () use ($groupId) {
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/groups/delete?access_token=' . $this->accessToken,
                json_encode(
                    [
                        'group' => [
                            'id' => $groupId,
                        ],
                    ],
                    JSON_UNESCAPED_UNICODE
                )
            );
        });
    }

    /**
     * 创建菜单
     *
     * @param array $buttons
     * @return false|Http
     */
    public function createMenu(array $buttons)
    {
        $buttons = json_encode($buttons, JSON_UNESCAPED_UNICODE);

        return $this->auth(function () use ($buttons) {
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/menu/create?access_token=' . $this->accessToken,
                $buttons
            );
        });
    }

    public function addConditionalMenu(array $buttons)
    {
        $buttons = json_encode($buttons, JSON_UNESCAPED_UNICODE);

        return $this->auth(function () use ($buttons) {
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/menu/addconditional?access_token=' . $this->accessToken,
                $buttons
            );
        });
    }

    /**
     * 获取自定义菜单
     *
     * @return false|Http
     */
    public function getMenu()
    {
        return $this->auth(function () {
            return $this->http->getJson($this->baseUrl . 'cgi-bin/menu/get?access_token=' . $this->accessToken);
        });
    }

    /**
     * 删除自定义菜单
     *
     * @return false|Http
     */
    public function deleteMenu()
    {
        return $this->auth(function () {
            return $this->http->getJson($this->baseUrl . 'cgi-bin/menu/delete?access_token=' . $this->accessToken);
        });
    }

    /**
     * 获取用户基本信息
     *
     * @param string $openId
     * @return Ret
     */
    public function getUserInfo($openId): Ret
    {
        return $this->auth(function () use ($openId) {
            // 1. 调用接口,获取用户信息
            $userInfo = $this->http([
                'url' => 'https://api.weixin.qq.com/cgi-bin/user/info',
                'data' => [
                    'access_token' => $this->accessToken,
                    'openid' => $openId,
                    'lang' => 'zh_CN',
                ],
            ]);

            // 2. 处理微信可能返回错误JSON的情况
            $responseText = $userInfo->getResponseText();
            $response = json_decode($responseText, true);

            // 有的用户会返回错误的JSON格式,导致PHP出现JSON_ERROR_CTRL_CHAR错误
            // {"subscribe":1,"openid":"o0Mh3tzELt7MrnjqRlzNJ9SXNgMg","nickname":"dyx","sex":2,"language":"zh_CN","city":"bý","province":"VÛ]Ý","country":"","headimgurl":"http:\/\/wx.qlogo.cn\/mmopen\/4ibDOGnEicR5lJbicvZOD5Lonvu3kMYphDMJByD9UYCMOzCXwdVxzDpvnlhJJqjDichASpHUYjd51lLNXicoPdib6ibNnxpsUbFgPN3\/0","subscribe_time":1391064765}
            if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
                // 删除返回数据中的错误控制符
                $responseText = preg_replace('/[\x00-\x1F\x7F]/', '', $responseText);
                $response = json_decode($responseText, true);
            }

            // 3. 如果仍然解析不出来,确实是数据错误
            if ($response === null) {
                throw new \Exception('返回不是有效的JSON数据,数据是' . $userInfo->getResponseText(), -1);
            }

            // 4. 将解析成功的数据,设置回去
            $userInfo->setOption('response', $response);

            return $userInfo;
        });
    }

    /**
     * 用户点击确认授权之后,可以通过该接口获取用户资料
     *
     * @param string $openId
     * @param string $accessToken
     * @return Ret
     */
    public function getSnsUserInfo(string $openId, string $accessToken): Ret
    {
        return $this->callWithoutToken([
            'dataType' => 'json',
            'url' => $this->baseUrl . 'sns/userinfo',
            'data' => [
                'access_token' => $accessToken,
                'openid' => $openId,
                'lang' => 'zh_CN',
            ],
        ]);
    }

    /**
     * 创建临时二维码
     *
     * @param int $sceneId
     * @return Ret
     */
    public function createTemporaryQrCode($sceneId): Ret
    {
        return $this->auth(function () use ($sceneId) {
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/qrcode/create?access_token=' . $this->accessToken,
                json_encode([
                    'expire_seconds' => 1800,
                    'action_name' => 'QR_SCENE',
                    'action_info' => [
                        'scene' => [
                            'scene_id' => $sceneId,
                        ],
                    ],
                ])
            );
        });
    }

    /**
     * 创建永久二维码
     *
     * @param $sceneId
     * @return false|Http
     */
    public function createPermanentQrCode($sceneId)
    {
        return $this->auth(function () use ($sceneId) {
            if (filter_var($sceneId, FILTER_VALIDATE_INT) && $sceneId > 0 && $sceneId <= 100000) {
                // 正整数
                $data = [
                    'action_name' => 'QR_LIMIT_SCENE',
                    'action_info' => [
                        'scene' => [
                            'scene_id' => $sceneId,
                        ],
                    ],
                ];
            } else {
                // 字符串
                $data = [
                    'action_name' => 'QR_LIMIT_STR_SCENE',
                    'action_info' => [
                        'scene' => [
                            'scene_str' => $sceneId,
                        ],
                    ],
                ];
            }

            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/qrcode/create?access_token=' . $this->accessToken,
                json_encode($data)
            );
        });
    }

    public function getPermanentQrCodeUrl($sceneId)
    {
        $qrCode = $this->createPermanentQrCode($sceneId);
        if (!$qrCode) {
            throw new \Exception($this->message, $this->code);
        } else {
            return $this->getQrCodeUrl($qrCode);
        }
    }

    /**
     * 通过请求返回值,获取二维码地址
     *
     * @param array|Http $qrCode
     * @return string
     */
    public function getQrCodeUrl($qrCode)
    {
        return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($qrCode['ticket']);
    }

    /**
     * 根据参数创建二维码
     *
     * @param array $data
     * @return false|Http
     */
    public function createQrCode(array $data)
    {
        return $this->auth(function () use ($data) {
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/qrcode/create?access_token=' . $this->accessToken,
                json_encode($data)
            );
        });
    }

    /**
     * 获取帐号的关注者的 OpenID 列表
     *
     * @param string|null $nextOpenId
     * @return Ret
     */
    public function userGet(string $nextOpenId = null): Ret
    {
        return $this->call([
            'dataType' => 'json',
            'url' => $this->baseUrl . 'cgi-bin/user/get',
            'data' => [
                'next_openid' => $nextOpenId,
            ],
        ]);
    }

    /**
     * 根据帐号是否授权获取不同的网页授权access_token
     *
     * @param array $data
     * @return Ret
     */
    public function getOAuth2AccessTokenByAuth(array $data): Ret
    {
        if ($this->authed) {
            if (!isset($data['appid']) || !$data['appid']) {
                $data['appid'] = $this->appId;
            }
            return $this->wechatComponentApi->getOAuth2AccessToken($data);
        } else {
            return $this->getOAuth2AccessToken($data);
        }
    }

    /**
     * 通过OAuth2.0的code获取网页授权access_token
     *
     * @param array $data
     * @return Ret
     */
    public function getOAuth2AccessToken(array $data): Ret
    {
        return $this->callWithoutToken([
            'url' => $this->baseUrl . 'sns/oauth2/access_token',
            'dataType' => 'json',
            'throwException' => false,
            'data' => array_merge([
                'code' => '', // 需传入参数
                'appid' => $this->appId,
                'secret' => $this->appSecret,
                'grant_type' => 'authorization_code',
            ], $data),
        ]);
    }

    /**
     * 微信支付发货接口
     *
     * @param array $data
     * @return array
     */
    public function ship($data)
    {
        return $this->auth(function () use ($data) {
            return $this->http->postJson(
                $this->baseUrl . 'pay/delivernotify?access_token=' . $this->accessToken,
                json_encode($data)
            );
        });
    }

    /**
     * 根据提供的数组,生成签名字符串
     *
     * @param array $data
     * @param bool $urlEncoded
     * @return string
     */
    public function generateSign(array $data, $urlEncoded = false)
    {
        ksort($data);
        $data = http_build_query($data, null, '&', PHP_QUERY_RFC3986);
        !$urlEncoded && $data = urldecode($data);

        return $data;
    }

    /**
     * 根据提供的数组,生成sha1签名字符串
     *
     * @param array $data
     * @param bool $urlEncoded
     * @return string
     */
    public function generateSha1Sign(array $data, $urlEncoded = false)
    {
        return sha1($this->generateSign($data, $urlEncoded));
    }

    /**
     * 对数组的值进行字符串的字典序排序,并生成sha1签名字符串
     *
     * @param array $data
     * @return string
     */
    public function generateValueSha1Sign(array $data)
    {
        sort($data, SORT_STRING);

        return sha1(implode($data));
    }

    /**
     * 发送文本消息
     *
     * @param string $openId
     * @param string $content
     * @return false|Http
     */
    public function sendText($openId, $content)
    {
        return $this->send([
            'touser' => $openId,
            'msgtype' => 'text',
            'text' => [
                'content' => $content,
            ],
        ]);
    }

    /**
     * @param array $data
     * @return false|Http
     */
    public function send(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/message/custom/send?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * @param array $data
     * @return false|Http
     */
    public function sendByOpenId(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/message/mass/send?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * 上传图文消息素材
     *
     * @param array $data
     * @return Http|false
     */
    public function uploadNews(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/media/uploadnews?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * 上传图文消息内的图片获取URL
     *
     * @param string $file
     * @return false|Http
     */
    public function uploadImg($file)
    {
        return $this->auth(function () use ($file) {
            $http = $this->http([
                'method' => 'post',
                'dataType' => 'json',
                'timeout' => 30000,
                'url' => $this->baseUrl . 'cgi-bin/media/uploadimg?access_token=' . $this->accessToken,
                'files' => [
                    'media' => $file,
                ],
            ]);

            return $http;
        });
    }

    /**
     * @param string $url
     * @return bool|false|Http
     */
    public function uploadImgFromUrl($url)
    {
        // 1. 下载文件
        $file = $this->downloadFile($url);
        if (!$file) {
            return false;
        }

        // 2. 上传到微信获取URL
        $http = $this->uploadImg($file);
        if (!$http) {
            $this->logger->alert('上传图片失败', [
                'ret' => $this->getResult(),
                'url' => $url,
            ]);

            return false;
        }

        return $http;
    }

    /**
     * 新增临时素材
     *
     * @param string $type 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
     * @param string $file
     * @return Http|false
     */
    public function uploadMedia($type, $file)
    {
        return $this->auth(function () use ($type, $file) {
            $http = $this->http([
                'method' => 'post',
                'dataType' => 'json',
                'url' => $this->baseUrl . 'cgi-bin/media/upload?type=' . $type . '&access_token=' . $this->accessToken,
                'files' => [
                    'media' => $file,
                ],
            ]);

            return $http;
        });
    }

    /**
     * 新增永久素材
     *
     * @param string $type
     * @param string $file
     * @return array
     */
    public function addMaterial($type, $file)
    {
        return $this->auth(function () use ($type, $file) {
            $http = $this->http([
                'method' => 'post',
                'dataType' => 'json',
                'url' => $this->baseUrl . 'cgi-bin/material/add_material?type=' . $type . '&access_token='
                    . $this->accessToken,
                'files' => [
                    'media' => $file,
                ],
            ]);
            return $http;
        });
    }

    /**
     * @param string $url
     * @return array|bool
     */
    public function uploadMediaFromUrl($url)
    {
        // 1. 下载文件
        $file = $this->downloadFile($url);
        if (!$file) {
            return false;
        }

        // 2. 上传到微信临时素材
        $http = $this->uploadMedia('image', $file);
        if (!$http) {
            $this->logger->alert('上传图片失败', [
                'ret' => $this->getResult(),
                'url' => $url,
            ]);

            return false;
        }

        return $http;
    }

    /**
     * @param $mediaId
     * @return false|Http
     */
    public function getMediaUrl($mediaId)
    {
        return $this->auth(function () use ($mediaId) {
            $url = 'http://file.api.weixin.qq.com/cgi-bin/media/get?media_id='
                . $mediaId . '&access_token=' . $this->accessToken;
            return $url;
        });
    }

    /**
     * @param string $url
     * @return false|string
     */
    protected function downloadFile($url)
    {
        $parts = parse_url($url);
        if (isset($parts['host'])) {
            $file = wei()->cdn->download($url);
            if (!$file) {
                $this->code = -1;
                $this->message = '下载远程文件失败,请稍后再试';

                return false;
            }
        } else {
            $file = ltrim($url, '/');
            if (!is_file($file)) {
                $this->code = -2;
                $this->message = '图片文件不存在,地址是:' . $url;

                return false;
            }
        }

        return $file;
    }

    /**
     * 预览群发消息
     *
     * @param array $data
     * @return false|Http
     */
    public function previewMassMessage(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/message/mass/preview?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * 根据OpenID列表群发【订阅号不可用，服务号认证后可用】
     *
     * @param array|string $data 允许直接传入字符串,不用JSON序列化数据
     * @return false|Http
     */
    public function sendMassMessage($data)
    {
        return $this->auth(function () use ($data) {
            if (!is_string($data)) {
                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            }

            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/message/mass/send?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * 删除群发【订阅号与服务号认证后均可用】
     *
     * @param array $data
     * @return false|Http
     */
    public function deleteMassMessage($data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/message/mass/delete?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * @param User $user
     * @param $type
     * @param $data
     * @return false|Http
     */
    public function sendByUser(\Miaoxing\Plugin\Service\User $user, $type, $data)
    {
        $openId = $user['wechatOpenId'];

        switch ($type) {
            case 'text':
                return $this->sendText($openId, $data['content']);

            default:
                $this->code = -1;
                $this->message = sprintf('不支持消息类型: %s', $type);

                return false;
        }
    }

    /**
     * 向用户发送模板消息
     *
     * topcolor默认为微信的绿色,可不填
     * data的color可不填,留空为黑色,如果是深蓝色,推荐使用#173177
     *
     * ```php
     * $wechatApi->sendTemplate([
     *   'touser' => 'OPENID',
     *   'template_id' => 'ngqIpbwh8bUfcSsECmogfXcV14J0tQlEpBO27izEYtY',
     *   'url' => 'http://weixin.qq.com/download',
     *   'topcolor' => '#44b549',
     *   'data' => [
     *     "first" => [
     *       'value' => '您好，您已成功消费。',
     *       'color' => '#0A0A0A' // 留空是黑色
     *     ],
     *   ]
     * ]);
     * ```
     *
     * @param array $data
     * @return false|Http
     * @todo 通过队列,如celery实现异步
     */
    public function sendTemplate(array $data)
    {
        return $this->auth(function () use ($data) {
            $data += [
                'topcolor' => '#44b549',
            ];
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/message/template/send?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    public function sendWxaTemplate(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            return $this->http->postJson(
                $this->baseUrl . 'cgi-bin/message/wxopen/template/send?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * 拉取门店列表
     *
     * @param int $offset
     * @param int $count
     * @return false|Http
     */
    public function batchGetCardLocation($offset = 0, $count = 0)
    {
        return $this->auth(function () use ($offset, $count) {
            $data = json_encode(['offset' => $offset, 'count' => $count], JSON_UNESCAPED_UNICODE);
            $result = $this->http->postJson(
                $this->baseUrl . 'card/location/batchget?access_token=' . $this->accessToken,
                $data
            );

            return $result;
        });
    }

    /**
     * 批量导入门店信息
     *
     * @param array $data
     * @return false|Http
     */
    public function batchAddCardLocation(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            $result = $this->http->postJson(
                $this->baseUrl . 'card/location/batchadd?access_token=' . $this->accessToken,
                $data
            );

            return $result;
        });
    }

    /**
     * 创建卡券
     *
     * @param array $data
     * @return false|Http
     */
    public function createCard(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            $result = $this->http->postJson($this->baseUrl . 'card/create?access_token=' . $this->accessToken, $data);

            return $result;
        });
    }

    /**
     * 更新卡券
     *
     * @param array $data
     * @return Ret
     */
    public function updateCard(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            $result = $this->http->postJson($this->baseUrl . 'card/update?access_token=' . $this->accessToken, $data);

            return $result;
        });
    }

    public function updateCardRet(array $data)
    {
        return $this->processRet($this->updateCard($data));
    }

    /**
     * 删除指定卡券
     *
     * @param string $cardId
     * @return false|Http
     */
    public function deleteCard($cardId)
    {
        return $this->auth(function () use ($cardId) {
            $data = json_encode(['card_id' => $cardId], JSON_UNESCAPED_UNICODE);
            $result = $this->http->postJson($this->baseUrl . 'card/delete?access_token=' . $this->accessToken, $data);

            return $result;
        });
    }

    /**
     * 更改卡券code
     *
     * @param string $cardId
     * @param string $code
     * @param string $newCode
     * @return false|Http
     */
    public function updateCardCode($cardId, $code, $newCode)
    {
        $data = json_encode([
            'code' => (string) $code,
            'card_id' => (string) $cardId,
            'new_code' => (string) $newCode,
        ], JSON_UNESCAPED_UNICODE);

        return $this->auth(function () use ($data) {
            $result = $this->http->postJson(
                $this->baseUrl . 'card/code/update?access_token=' . $this->accessToken,
                $data
            );

            return $result;
        });
    }

    /**
     * 消耗卡券code
     *
     * @param string $code
     * @param string $cardId
     * @return false|Http
     */
    public function consumeCardCode($code, $cardId = '')
    {
        $data = json_encode([
            'code' => $code,
            'card_id' => $cardId,
        ], JSON_UNESCAPED_UNICODE);

        return $this->auth(function () use ($data) {
            return $this->http->postJson(
                $this->baseUrl . 'card/code/consume?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * 查询动态码
     *
     * @param array $data
     * @return false|Http
     */
    public function getCardCode(array $data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/code/get?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    /**
     * 增加卡券测试白名单
     *
     * @param array $data
     * @return false|Http
     */
    public function createCardTestWhitelist($data)
    {
        return $this->auth(function () use ($data) {
            return $this->http->postJson(
                $this->baseUrl . 'card/testwhitelist/set?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * @param string $encryptCode
     * @return false|Http
     */
    public function decryptCardCode($encryptCode)
    {
        return $this->auth(function () use ($encryptCode) {
            $data = json_encode([
                'encrypt_code' => $encryptCode,
            ]);

            return $this->http->postJson(
                $this->baseUrl . 'card/code/decrypt?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * 批量查询卡列表
     *
     * @param int $offset
     * @param int $count
     * @return false|Http
     */
    public function batchGetCard($offset = 0, $count = 10)
    {
        return $this->auth(function () use ($offset, $count) {
            $data = json_encode([
                'offset' => $offset,
                'count' => $count,
            ]);

            return $this->http->postJson($this->baseUrl . 'card/batchget?access_token=' . $this->accessToken, $data);
        });
    }

    /**
     * 查询卡券详情
     *
     * @param string $id
     * @return false|Http
     */
    public function getCard($id)
    {
        return $this->auth(function () use ($id) {
            $data = json_encode([
                'card_id' => $id,
            ]);

            return $this->http->postJson($this->baseUrl . 'card/get?access_token=' . $this->accessToken, $data);
        });
    }

    public function getCardRet($id)
    {
        return $this->processRet($this->getCard($id));
    }

    public function createCardQrCode(array $data)
    {
        return $this->auth(function () use ($data) {
            return $this->http->postJson(
                $this->baseUrl . 'card/qrcode/create?access_token=' . $this->accessToken,
                json_encode($data)
            );
        });
    }

    /**
     * 获取在线客服接待信息
     */
    public function getOnLineList()
    {
        return $this->auth(function () {
            return $this->http([
                'method' => 'post',
                'contentType' => 'application/json; charset=utf-8',
                'url' => $this->baseUrl . 'cgi-bin/customservice/getonlinekflist?access_token=' . $this->accessToken,
                'dataType' => 'json',
            ]);
        });
    }

    /**
     * 从多个客服帐号中,获取接待数最少的客服
     *
     * @param array $customServices 完整客服帐号的数组,帐号格式为：帐号前缀@公众号微信号
     * @return string|false
     */
    public function getMinAcceptedCustomService(array $customServices)
    {
        if (!$customServices) {
            return false;
        }

        if (count($customServices) === 1) {
            return $customServices[0];
        }

        $onlineList = $this->getOnLineList();
        if (!$onlineList) {
            return false;
        }

        // 将会话数最少的客服排在前面
        $onlineList = wei()->coll->orderBy($onlineList['kf_online_list'], 'accepted_case', SORT_ASC);
        foreach ($onlineList as $customService) {
            if (in_array($customService['kf_account'], $customServices)) {
                return $customService['kf_account'];
            }
        }

        return false;
    }

    /**
     * 获取图文群发总数据
     *
     * @param array $data
     * @return false|Http
     * @todo 随着API增加,需要简单统一方案
     */
    public function getDataCubeArticleTotal($data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return wei()->http->postJson(
                'https://api.weixin.qq.com/datacube/getarticletotal?access_token=' . $this->accessToken,
                $data
            );
        });
    }

    /**
     * 利用deviceid更新设备属性
     *
     * @param array $data
     * @return false|Http
     */
    public function deviceAuthorizeDevice(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'device/authorize_device?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    /**
     * 获取设备二维码
     *
     * @param array $data
     * @return false|Http
     */
    public function deviceCreateQrcode(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'device/create_qrcode?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    /**
     * 获取deviceid和二维码
     *
     * @param array $data
     * @return false|Http
     */
    public function deviceGetQrcode(array $data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'device/getqrcode?access_token=' . $this->accessToken,
                'method' => 'get',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    /**
     * 强制绑定用户和设备
     *
     * @param array $data
     * @return false|Http
     */
    public function deviceCompelBind(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'device/compel_bind?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    /**
     * 强制解绑用户和设备
     *
     * @param array $data
     * @return false|Http
     */
    public function deviceCompelUnbind(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'device/compel_unbind?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    /**
     * 绑定用户和设备
     *
     * @param array $data
     * @return false|Http
     */
    public function deviceBind(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'device/bind?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    /**
     * 解绑用户和设备
     *
     * @param array $data
     * @return false|Http
     */
    public function deviceUnbind(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'device/unbind?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    /**
     * 应用端查询设备的运行状态。
     *
     * @param array $data
     * @return false|Http
     */
    public function getDeviceStatus(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'hardware/mydevice/platform/get_device_status?access_token='
                    . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    /**
     * 应用端控制设备的运行状态。
     *
     * @param array $data
     * @return false|Http
     */
    public function ctrlDevice(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'hardware/mydevice/platform/ctrl_device?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    /**
     * 创建门店
     *
     * @param array $data
     * @return array
     */
    public function addPoi(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'cgi-bin/poi/addpoi?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    public function updatePoi(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'cgi-bin/poi/updatepoi?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    public function delPoi(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'cgi-bin/poi/delpoi?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    public function getPoi(array $data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'cgi-bin/poi/getpoi?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    public function updateMemberCardUser($data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'card/membercard/updateuser?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    public function getMemberCardUserInfo($data)
    {
        return $this->auth(function () use ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);

            return $this->http([
                'url' => $this->baseUrl . 'card/membercard/userinfo/get?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => $data,
            ]);
        });
    }

    public function getWxCategory()
    {
        return $this->auth(function () {
            return $this->http([
                'url' => $this->baseUrl . 'cgi-bin/poi/getwxcategory?access_token=' . $this->accessToken,
                'method' => 'get',
                'dataType' => 'json',
            ]);
        });
    }

    public function modifyCardStock($data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/modifystock?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function setCardSelfConsumeCell($data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/selfconsumecell/set?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function getStoreList($data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'wxa/get_store_list?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function addPayGiftCard($data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/paygiftcard/add?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function updatePayGiftCard($data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/paygiftcard/update?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function deletePayGiftCard($data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/paygiftcard/delete?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function getCardPayCoinsInfo()
    {
        return $this->auth(function () {
            return $this->http([
                'url' => $this->baseUrl . 'card/pay/getcoinsinfo?access_token=' . $this->accessToken,
                'dataType' => 'json',
            ]);
        });
    }

    public function getCardPayPayPrice(array $data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/pay/getpayprice?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function confirmCardPay(array $data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/pay/confirm?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function getGiftCardOrder(array $data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/giftcard/order/get?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function updateGeneralCardUser(array $data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/generalcard/updateuser?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    public function getUserCardList(array $data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'card/user/getcardlist?access_token=' . $this->accessToken,
                'method' => 'post',
                'dataType' => 'json',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    /**
     * 生成指定长度的随机字符串
     *
     * @param int $length
     * @return string
     */
    public function generateNonceStr($length = 32)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; ++$i) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }

        return $str;
    }

    public function jsCode2Session(array $data): Ret
    {
        return $this->callWithoutToken([
            'dataType' => 'json',
            'url' => $this->baseUrl . '/sns/jscode2session',
            'data' => array_merge([
                'appid' => $this->appId,
                'secret' => $this->appSecret,
                'grant_type' => 'authorization_code',
            ], $data),
        ]);
    }

    public function getWxaCode($data)
    {
        return $this->auth(function () use ($data) {
            return $this->http([
                'url' => $this->baseUrl . 'wxa/getwxacode?access_token=' . $this->accessToken,
                'data' => json_encode($data),
                'method' => 'post',
                'throwException' => true,
            ]);
        });
    }

    public function getTags()
    {
        return $this->auth(function () {
            return $this->http([
                'url' => $this->baseUrl . 'cgi-bin/tags/get?access_token=' . $this->accessToken,
                'method' => 'get',
                'throwException' => true,
            ]);
        });
    }
}
