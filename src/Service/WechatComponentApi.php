<?php

namespace Miaoxing\Wechat\Service;

use Miaoxing\Plugin\BaseService;
use Wei\Http;

/**
 * 公众号第三方平台服务
 *
 * @method Http http(array $options = [])
 * @property \Wei\Cache $cache
 * @property \Miaoxing\App\Service\Logger $logger
 * @link https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419318587&token=&lang=zh_CN
 */
class WechatComponentApi extends BaseService
{
    /**
     * @var string
     */
    protected $baseUrl = 'https://api.weixin.qq.com/cgi-bin/component/';

    /**
     * @var string
     */
    protected $appId;

    /**
     * @var string
     */
    protected $appSecret;

    /**
     * @var string
     */
    protected $verifyTicket;

    /**
     * @var string
     */
    protected $accessToken;

    /**
     * @var array
     */
    protected $messages = [
        0 => '操作成功',
    ];

    /**
     * @return string
     */
    public function getAppId()
    {
        return $this->appId;
    }

    /**
     * @return string
     */
    public function getAppSecret()
    {
        return $this->appSecret;
    }

    /**
     * @return string
     */
    public function getVerifyTicket()
    {
        return $this->verifyTicket;
    }

    /**
     * @return string
     */
    protected function getAccessTokenCacheKey()
    {
        return 'wechat:componentAccessToken:' . $this->getAppId();
    }

    /**
     * 获取Access Token凭证
     *
     * @return array|false
     */
    protected function getCredential()
    {
        $cacheKey = $this->getAccessTokenCacheKey();

        return $this->cache->get($cacheKey);
    }

    /**
     * 设置Access Token凭证
     *
     * @param array $credential
     * @return $this
     */
    protected function setCredential(array $credential)
    {
        $cacheKey = $this->getAccessTokenCacheKey();
        $this->cache->set($cacheKey, $credential);

        return $this;
    }

    /**
     * 获取Access Token并执行指定的回调
     *
     * @param callable $fn
     * @return array
     */
    protected function auth(callable $fn)
    {
        // 获取Access Token
        if (!$this->accessToken) {
            $credential = $this->getCredential();
            // 缓存丢失或超时则重新申请
            if (!$credential || $credential['expireTime'] - time() < 60) {
                $ret = $this->getAccessToken();
                if ($ret['code'] !== 1) {
                    return $ret;
                }

                // 存储到缓存,方便下个请求获取
                $this->setCredential([
                    'accessToken' => $ret['component_access_token'],
                    'expireTime' => time() + $ret['expires_in'],
                ]);
            }

            $this->accessToken = $credential['accessToken'];
        }

        $ret = $fn();
        $this->logger->info('第三方平台调用结果', $ret);

        return $ret;
    }

    /**
     * 获取Access Token
     *
     * @return array
     */
    public function getAccessToken()
    {
        return $this->callHttp([
            'url' => 'api_component_token',
            'data' => [
                'component_appid' => $this->getAppId(),
                'component_appsecret' => $this->getAppSecret(),
                'component_verify_ticket' => $this->getVerifyTicket(),
            ],
        ]);
    }

    /**
     * 获取预授权码
     *
     * @return array
     */
    public function createPreAuthCode()
    {
        return $this->auth(function () {
            return $this->callHttp([
                'url' => 'api_create_preauthcode?component_access_token=' . $this->accessToken,
                'data' => [
                    'component_appid' => $this->getAppId(),
                ],
            ]);
        });
    }

    /**
     * 生成授权地址
     *
     * @param string $preAuthCode
     * @param string $redirectUri
     * @return string
     */
    public function getLoginUrl($preAuthCode, $redirectUri)
    {
        return 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?' . http_build_query([
            'component_appid' => $this->appId,
            'pre_auth_code' => $preAuthCode,
            'redirect_uri' => $redirectUri,
        ]);
    }

    /**
     * 使用授权码换取公众号的授权信息
     *
     * @param string $authCode
     * @return array
     */
    public function queryAuth($authCode)
    {
        return $this->auth(function () use ($authCode) {
            return $this->callHttp([
                'url' => 'api_query_auth?component_access_token=' . $this->accessToken,
                'data' => [
                    'component_appid' => $this->getAppId(),
                    'authorization_code' => $authCode,
                ],
            ]);
        });
    }

    /**
     * 获取授权方的账户信息
     *
     * @param string $authorizerAppId
     * @return array
     */
    public function getAuthorizerInfo($authorizerAppId)
    {
        return $this->auth(function () use ($authorizerAppId) {
            return $this->callHttp([
                'url' => 'api_get_authorizer_info?component_access_token=' . $this->accessToken,
                'data' => [
                    'component_appid' => $this->getAppId(),
                    'authorizer_appid' => $authorizerAppId,
                ],
            ]);
        });
    }

    /**
     * 获取（刷新）授权公众号的令牌
     *
     * @param string $authorizerAppId
     * @param string $authorizerRefreshToken
     * @return array
     */
    public function authorizerToken($authorizerAppId, $authorizerRefreshToken)
    {
        return $this->auth(function () use ($authorizerAppId, $authorizerRefreshToken) {
            return $this->callHttp([
                'url' => 'api_authorizer_token?component_access_token=' . $this->accessToken,
                'data' => [
                    'component_appid' => $this->getAppId(),
                    'authorizer_appid' => $authorizerAppId,
                    'authorizer_refresh_token' => $authorizerRefreshToken,
                ],
            ]);
        });
    }

    /**
     * @param string $appId
     * @return string
     */
    protected function getAuthorizerAccessTokenCacheKey($appId)
    {
        return 'authorizer-access-token-' . $appId;
    }

    /**
     * 获取授权方令牌,如果过期,自动刷新
     *
     * @param string $appId
     * @param string $refreshToken
     * @return string
     */
    public function getAuthorizerAccessToken($appId, $refreshToken)
    {
        $cacheKey = $this->getAuthorizerAccessTokenCacheKey($appId);
        $credential = $this->cache->get($cacheKey);
        if (!$credential || $credential['expireTime'] - time() < 60) {
            $ret = $this->authorizerToken($appId, $refreshToken);
            if ($ret['code'] !== 1) {
                return $ret;
            } else {
                $credential = [
                    'accessToken' => $ret['authorizer_access_token'],
                    'expireTime' => time() + $ret['expires_in'],
                    'refreshToken' => $ret['authorizer_refresh_token'],
                ];
                $this->cache->set($cacheKey, $credential);
            }
        }

        return $credential['accessToken'];
    }

    /**
     * 设置授权方令牌
     *
     * @param string $appId
     * @param array $authInfo
     * @return $this
     */
    public function setAuthorizerAccessTokenFromAuth($appId, array $authInfo)
    {
        $this->cache->set($this->getAuthorizerAccessTokenCacheKey($appId), [
            'accessToken' => $authInfo['authorizer_access_token'],
            'expireTime' => time() + $authInfo['expires_in'],
        ]);

        return $this;
    }

    /**
     * 移除授权方令牌缓存
     *
     * @param string $appId
     * @return $this
     */
    public function removeAuthorizerAccessToken($appId)
    {
        $this->cache->remove($this->getAuthorizerAccessTokenCacheKey($appId));

        return $this;
    }

    /**
     * 通过OAuth2.0的code获取网页授权access_token
     *
     * @param array $data
     * @return Http
     */
    public function getOAuth2AccessToken(array $data)
    {
        return $this->auth(function () use ($data) {
            $http = $this->http([
                'method' => 'get',
                'dataType' => 'json',
                'throwException' => false,
                'url' => 'https://api.weixin.qq.com/sns/oauth2/component/access_token',
                'data' => $data + [
                        'code' => '', // 需传入参数
                        'appid' => '', // 需传入参数
                        'grant_type' => 'authorization_code',
                        'component_appid' => $this->appId,
                        'component_access_token' => $this->accessToken,
                    ],
            ]);

            return $this->parseResponse($http);
        });
    }

    /**
     * 调用HTTP接口
     *
     * @param array $options
     * @return array
     */
    protected function callHttp(array $options)
    {
        $options += [
            'method' => 'post',
            'dataType' => 'json',
            'throwException' => false,
        ];
        $options['url'] = $this->baseUrl . $options['url'];
        $options['data'] = json_encode($options['data'], JSON_UNESCAPED_UNICODE);

        $http = $this->http($options);

        return $this->parseResponse($http, $options);
    }

    /**
     * 解析返回结果
     *
     * @param Http $http
     * @param array $options
     * @return array
     */
    protected function parseResponse(Http $http, $options = [])
    {
        // 1. 处理HTTP请求失败
        if (!$http->isSuccess()) {
            $this->logError($http, $options);

            return ['code' => -1, 'message' => '很抱歉,请求失败,请重试'];
        }

        // 2. 处理调用凭证过期
        $res = $http->getResponse();
        if (isset($res['errcode']) && $res['errcode'] == 40001) {
            $this->setCredential([]);
        }

        // 3. 处理其他错误
        if (isset($res['errcode']) && $res['errcode'] !== 0) {
            $this->logError($http, $options);
            $message = isset($this->messages[$res['errcode']]) ? $this->messages[$res['errcode']] : '很抱歉,请求失败,请重试';

            return ['code' => -abs($http['errcode']), 'message' => $message];
        }

        return ['code' => 1, 'message' => '操作成功'] + $res;
    }

    /**
     * 记录错误日志
     *
     * @param Http $http
     * @param array $options
     */
    protected function logError(Http $http, $options = [])
    {
        $this->logger->alert('微信第三方平台业务出错', [
            'options' => $options,
            'status' => $http->getErrorStatus(),
            'response' => $http->getResponseText(),
            'exception' => (string) $http->getErrorException(),
        ]);
    }
}
