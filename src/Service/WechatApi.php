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
     * @var string|null
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
        42001 => 'access_token 超时',
        46003 => '不存在菜单数据',
        // 卡券需展示具体的错误信息
        //47001 => '解析JSON/XML内容错误',
        48001 => 'api功能未授权',
        // 卡券
        40079 => '有效期中的时间不合法',
        //41011 => '必填字段不完整或不合法，参考相应接口',
    ];

    /**
     * 指定返回的 errcode 对应的日志等级
     *
     * @var array
     */
    protected $logLevels = [
        // 没有关注该服务号
        43004 => 'info',
    ];

    /**
     * @var array
     */
    protected $configs = [];

    /**
     * @var array
     */
    protected $defaultConfigs = [
        'getToken' => [
            'url' => 'cgi-bin/token?grant_type=client_credential',
            'method' => 'GET',
            'accessToken' => false,
            'data' => [
                'appid' => '',
                'secret' => '',
            ],
        ],
        'getSnsOAuth2AccessToken' => [
            'url' => 'sns/oauth2/access_token?grant_type=authorization_code',
            'method' => 'GET',
            'accessToken' => false,
            'data' => [
                'appid' => '',
                'secret' => '',
            ],
        ],
        'getSnsUserInfo' => [
            'url' => 'sns/userinfo',
            'accessToken' => false,
        ],
        // @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
        'createTag' => 'cgi-bin/tags/create',
        'getTags' => [
            'url' => 'cgi-bin/tags/get',
            'method' => 'GET',
        ],
        'updateTag' => 'cgi-bin/tags/update',
        'deleteTag' => 'cgi-bin/tags/delete',
        'getTagUsers' => 'cgi-bin/user/tag/get',
        'batchTaggingMembers' => 'cgi-bin/tags/members/batchtagging',
        'batchUnTaggingMembers' => 'cgi-bin/tags/members/batchuntagging',
        'getTagIdList' => 'cgi-bin/tags/getidlist',
        // 小程序
        'snsJsCode2Session' => [
            'url' => 'sns/jscode2session?grant_type=authorization_code',
            'method' => 'GET',
            'accessToken' => false,
            'data' => [
                'appid' => '',
                'secret' => '',
            ],
        ],
        'sendSubscribeMessage' => 'cgi-bin/message/subscribe/send',
    ];

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
    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /**
     * @param array|string $options
     * @return Ret
     */
    public function get($options): Ret
    {
        return $this->request($options + ['method' => 'GET']);
    }

    /**
     * @param array|string $options
     * @return Ret
     */
    public function post($options): Ret
    {
        return $this->request($options);
    }

    /**
     * @param string $name
     * @param array $args
     * @return mixed|Ret
     * @throws \ReflectionException
     */
    public function __call(string $name, array $args)
    {
        $config = $this->configs[$name] ?? $this->defaultConfigs[$name] ?? null;
        if ($config) {
            if (is_string($config)) {
                $config = ['url' => $config];
            }

            // @experimental
            if (isset($config['data']['secret'])) {
                $config['data']['appid'] = $this->getAppId();
                $config['data']['secret'] = $this->getAppSecret();
            }

            $config['data'] = array_merge($config['data'] ?? [], $args ? $args[0] : []);

            // TODO 部分接口转换为开放平台

            return $this->request(array_merge(['url' => $config['url']], $config));
        }

        return parent::__call($name, $args);
    }

    /**
     * 获取 Access token
     *
     * @return Ret|array{accessToken?: string}
     */
    public function getAccessToken(): Ret
    {
        if ($this->accessToken) {
            return suc(['accessToken' => $this->accessToken]);
        }

        // 根据帐号是否授权获取不同的 Access token
        if ($this->authed) {
            $this->accessToken = $this->wechatComponentApi->getAuthorizerAccessToken(
                $this->getAppId(),
                $this->getRefreshToken()
            );
            return suc(['accessToken' => $this->accessToken]);
        }

        $credential = $this->getCredentialFromCache();
        if (!$credential || $credential['expireTime'] - time() < 60) {
            $ret = $this->getToken();
            if ($ret->isErr()) {
                return $ret;
            }

            $credential = [
                'accessToken' => $ret['access_token'],
                'expireTime' => time() + $ret['expires_in'],
            ];
            $this->setCredentialToCache($credential);
        }

        $this->accessToken = $credential['accessToken'];
        return suc(['accessToken' => $this->accessToken]);
    }

    /**
     * @param array|string $options
     * @param int $retries
     * @return Ret
     * @internal retries 改为使用 HTTP 服务的配置
     */
    protected function request($options, int $retries = 0): Ret
    {
        // 1. 默认附加 Access token
        if (($options['accessToken'] ?? null) !== false) {
            // 1.1 获取 Access token
            if (!$this->accessToken && ($ret = $this->getAccessToken())->isErr()) {
                return $ret;
            }
            // 1.2 附加并调用
            $options['url'] = $this->url->append($options['url'], ['access_token' => $this->accessToken]);
        }

        // 2. 发送请求
        $http = $this->http($this->prepareHttpOptions($options));

        // 3. 成功直接返回
        $ret = $this->parseResponse($http);
        if ($ret->isSuc()) {
            return $ret;
        }

        // 4. 处理接口返回错误
        $this->logError($http, $ret);

        // 如果是 Access token 无效或过期，清除缓存数据，然后重试一次
        if (in_array($http['errcode'], [40001, 40014, 42001], true)) {
            $this->removeAccessToken();
            if ($retries < 2) {
                $this->statsD->increment('wechat.credentialInvalid');
                return $this->request($options, ++$retries);
            }
        }
        return $ret;
    }

    /**
     * @param string|array $options
     * @return array
     */
    protected function prepareHttpOptions($options): array
    {
        $options['throwException'] = false;
        $options['method'] ?? $options['method'] = 'POST';
        $options['dataType'] ?? $options['dataType'] = 'json';

        if (substr($options['url'], 0, 8) !== 'https://') {
            $options['url'] = $this->baseUrl . $options['url'];
        }

        if ($options['method'] !== 'GET' && isset($options['data'])) {
            $options['data'] = json_encode($options['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (isset($options['accessToken'])) {
            unset($options['accessToken']);
        }

        return $options;
    }

    /**
     * 将 HTTP 请求返回的内容，转换为 Ret 对象
     *
     * @param Http $http
     * @return Ret
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

        [$message, $detail] = $this->parseMessage($message);

        return err([
            'code' => -abs($code),
            'message' => $this->messages[$code] ?? $message,
            'detail' => $detail,
        ]);
    }

    /**
     * 移除错误消息中的 `hint: xxx` 和 `rid: xxx`
     *
     * @param string $message
     * @return array
     * @internal
     */
    protected function parseMessage(string $message): array
    {
        [$message, $detail] = $this->explodeMessage($message, ' hint:');
        if (null !== $detail) {
            return [$message, $detail];
        }
        return $this->explodeMessage($message, ' rid:');
    }

    /**
     * @param string $message
     * @param string $separator
     * @return array
     * @internal
     */
    protected function explodeMessage(string $message, string $separator): array
    {
        $pos = strrpos($message, $separator);
        if ($pos !== false) {
            return [rtrim(substr($message, 0, $pos), ', '), trim(substr($message, $pos))];
        }
        return [$message, null];
    }

    /**
     * 告警微信接口失败
     *
     * @param Http $http
     * @param Ret $ret
     * @internal
     */
    protected function logError(Http $http, Ret $ret)
    {
        $this->logger->log(
            $this->logLevels[$http['errcode']] ?? 'warning',
            '微信接口失败：' . $ret['message'],
            [
                'url' => $http->getUrl(),
                'data' => $http->getData(),
                'res' => $http->getResponse(),
                'ret' => $ret->toArray(),
                'credential' => $this->getCredentialFromCache(),
            ]
        );
    }

    /**
     * 移除 Access token 和相应的缓存
     *
     * @return $this
     * @internal
     */
    protected function removeAccessToken(): self
    {
        $this->accessToken = null;

        if ($this->authed) {
            $this->wechatComponentApi->removeAuthorizerAccessToken($this->getAppId());
            return $this;
        }

        $this->removeCredentialFromCache();
        return $this;
    }

    /**
     * @return mixed
     */
    protected function getCredentialFromCache()
    {
        return $this->cache->get($this->getCredentialCacheKey());
    }

    /**
     * @param array $credential
     * @return bool
     */
    protected function setCredentialToCache(array $credential): bool
    {
        return $this->cache->set($this->getCredentialCacheKey(), $credential);
    }

    /**
     * @return bool
     */
    protected function removeCredentialFromCache(): bool
    {
        return $this->cache->remove($this->getCredentialCacheKey());
    }

    /**
     * @return string
     */
    protected function getCredentialCacheKey(): string
    {
        return 'wechat:credential:' . $this->getAppId();
    }
}
