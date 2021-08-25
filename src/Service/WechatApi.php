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
     * @var array
     */
    protected $configs = [];

    /**
     * @var array
     */
    protected $defaultConfigs = [
        // @link https://developers.weixin.qq.com/doc/offiaccount/User_Management/User_Tag_Management.html
        'createTag' => 'cgi-bin/tags/create',
        'getTags' => [
            'path' => 'cgi-bin/tags/get',
            'method' => 'GET',
        ],
        'updateTag' => 'cgi-bin/tags/update',
        'deleteTag' => 'cgi-bin/tags/delete',
        'getTagUsers' => 'cgi-bin/user/tag/get',
        'batchTaggingMembers' => 'cgi-bin/tags/members/batchtagging',
        'batchUnTaggingMembers' => 'cgi-bin/tags/members/batchuntagging',
        'getTagIdList' => 'cgi-bin/tags/getidlist',
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

        [$message, $detail] = $this->parseMessage($message);

        return err([
            'code' => -abs($code),
            'message' => $this->messages[$code] ?? $message,
            'detail' => $detail,
        ]);
    }

    protected function parseMessage(string $message): array
    {
        [$message, $detail] = $this->explodeMessage($message, ' hint:');
        if (null !== $detail) {
            return [$message, $detail];
        }
        return $this->explodeMessage($message, ' rid:');
    }

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
                $config = ['path' => $config];
            }
            return $this->call(array_merge([
                'url' => $this->baseUrl . $config['path'],
                'dataType' => 'json',
                'method' => 'post',
                'data' => $args ? json_encode($args[0], JSON_UNESCAPED_UNICODE) : [],
            ], $config));
        }

        return parent::__call($name, $args);
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

    public function jsCode2Session(array $data): Ret
    {
        return $this->callWithoutToken([
            'dataType' => 'json',
            'url' => $this->baseUrl . 'sns/jscode2session',
            'data' => array_merge([
                'appid' => $this->appId,
                'secret' => $this->appSecret,
                'grant_type' => 'authorization_code',
            ], $data),
        ]);
    }
}
