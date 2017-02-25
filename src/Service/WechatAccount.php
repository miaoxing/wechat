<?php

namespace Miaoxing\Wechat\Service;

use Miaoxing\Plugin\Service\User;
use Miaoxing\Wechat\Service\WechatApi;
use Miaoxing\Wechat\Service\WechatComponentApi;

/**
 * @property \Wei\Request $request
 * @property \Miaoxing\Wechat\Service\WechatComponentApi $wechatComponentApi
 * @property \Wei\Logger $logger
 */
class WechatAccount extends \miaoxing\plugin\BaseModel
{
    const PLATFORM_ID = 1;

    const SUBSCRIBE = 1;

    const SERVICE = 2;

    protected $autoId = true;

    protected $data = [
        'type' => 1,
        'verified' => 0,
    ];

    protected $types = [
        1 => '订阅号',
        2 => '服务号',
    ];

    protected $currentAccount;

    /**
     * 第三方平台的微信帐号
     *
     * @var WechatAccount
     */
    protected $componentAccount;

    protected $table = 'wechatAccounts';

    protected $providers = [
        'db' => 'app.db',
    ];

    /**
     * 获取当前应用的微信公众号
     *
     * @return WechatAccount
     */
    public function getCurrentAccount()
    {
        $this->currentAccount || $this->currentAccount = wei()->wechatAccount()->cache()->curApp()->findOrInit(false);

        return $this->currentAccount;
    }

    /**
     * 获取第三方平台的微信帐号
     *
     * @return WechatAccount
     */
    public function getComponentAccount()
    {
        $api = wei()->wechatComponentApi;
        $this->componentAccount || $this->componentAccount = wei()->wechatAccount()->cache()->findOrInit(['applicationId' => $api->getAppId()]);

        return $this->componentAccount;
    }

    public function beforeCreate()
    {
        $this['token'] = $this->generateNonceStr(32);
        parent::beforeCreate();
    }

    /**
     * 判断当前账号是否为订阅号
     *
     * @return bool
     */
    public function isSubscribe()
    {
        return $this['type'] == static::SUBSCRIBE;
    }

    /**
     * 判断当前账号是否为服务号
     *
     * @return bool
     */
    public function isService()
    {
        return $this['type'] == static::SERVICE;
    }

    /**
     * 判断当前账号是否为认证服务号
     *
     * @return bool
     */
    public function isVerifiedService()
    {
        return $this['verified'] && $this->isService();
    }

    /**
     * 判断当前账号是否由第三方授权
     *
     * @return mixed
     */
    public function isAuthed()
    {
        return $this['authed'];
    }

    /**
     * @return string
     */
    public function getTypeName()
    {
        return $this->types[$this['type']];
    }

    /**
     * Record: 获取当前账号的微信API服务
     *
     * @return WechatApi
     */
    public function createApiService()
    {
        $api = wei()->wechatApi;
        $api->setOption([
            'appId' => $this['applicationId'],
            'appSecret' => $this['applicationSecret'],
            'authed' => $this['authed'],
            'refreshToken' => $this['refreshToken'],
            'wechatComponentApi' => $this['authed'] ? $this->createComponentApiService() : null,
        ]);

        return $api;
    }

    /**
     * Record: 获取当前账号的微信第三方平台API服务
     *
     * @return WechatComponentApi
     */
    public function createComponentApiService()
    {
        $api = wei()->wechatComponentApi;

        // 如果未设置appSecret,认为是未初始化
        if (!$api->getAppSecret()) {
            // 获取第三方平台的appId(允许指定某个应用使用特定的第三方平台)
            $account = wei()->wechatAccount()->cache()->findOrInit(['applicationId' => $api->getAppId()]);
            $api->setOption([
                'appSecret' => $account['applicationSecret'],
                'verifyTicket' => $account['verifyTicket'],
            ]);
        }

        return $api;
    }

    /**
     * 获取微信自定义回复的配置
     *
     * @return array
     */
    public function getWechatAppOptions()
    {
        $account = $this['authed'] ? wei()->wechatAccount->getComponentAccount() : $this;

        return [
            'appId' => $account['applicationId'],
            'token' => $account['token'],
            'encodingAesKey' => $account['encodingAesKey'],
        ];
    }

    /**
     * 生成网页授权地址
     *
     * @param string $url
     * @param string $scope
     * @return string
     */
    public function getOauth2Url($url, $scope)
    {
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this['applicationId'] .  '&redirect_uri=' . urlencode($url) . '&response_type=code&scope=' . $scope;
        if ($this->isAuthed()) {
            $url .= '&component_appid=' . wei()->wechatComponentApi->getAppId();
        }

        return $url . '#wechat_redirect';
    }

    /**
     * 发送模板消息
     * @param \Miaoxing\Plugin\Service\User $toUser
     * @param $tplId
     * @param array $data
     * @param string $url
     * @return array
     */
    public function sendTemplate(User $toUser, $tplId, array $data, $url = '')
    {
        $this->logger->debug('send template message', [
            'user' => $toUser['id'],
            'tplId' => $tplId,
            'url' => $url,
            'data' => $data,
        ]);

        if (!$this->isVerifiedService()) {
            return [
                'code' => -1,
                'message' => '没有开通该服务',
            ];
        }

        if (!$tplId) {
            return [
                'code' => -2,
                'message' => '缺少模板编号',
            ];
        }

        if (!$toUser['isValid'] || !$toUser['wechatOpenId']) {
            return [
                'code' => -3,
                'message' => '用户未关注',
            ];
        }

        $api = $this->createApiService();
        $api->sendTemplate([
            'touser' => $toUser['wechatOpenId'],
            'template_id' => $tplId,
            'url' => $url,
            'data' => $data,
        ]);

        return $api->getResult();
    }

    /**
     * @param array $jsApiList
     * @param null $url
     * @return array
     *
     * @todo 移到wechatApi
     */
    public function getConfigData(array $jsApiList, $url = null)
    {
        $api = $this->createApiService();
        $jsApiTicket = $api->getApiTicketFromCache('jsapi');

        $timestamp = time();
        $nonceStr = $this->generateNonceStr(32);
        $url || $url = $this->request->getUrl();

        $signData = [
            'jsapi_ticket' => $jsApiTicket,
            'noncestr' => $nonceStr,
            'timestamp' => $timestamp,
            'url' => $url,
        ];
        $signature = sha1($api->generateSign($signData));

        return [
            'beta' => true, // 开启内测接口,用于微信硬件
            'debug' => $this->wei->isDebug(),
            'appId' => $this['applicationId'],
            'timestamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'signature' => $signature,
            'jsApiList' => $jsApiList,
        ];
    }

    public function getConfigJson(array $jsApiList)
    {
        return json_encode($this->getConfigData($jsApiList));
    }

    /**
     * 生成指定长度的随机字符串
     *
     * @param int $length
     * @return string
     */
    protected function generateNonceStr($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; ++$i) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }

        return $str;
    }

    public function afterSave()
    {
        parent::afterSave();
        $this->clearTagCache();
    }

    public function afterDestroy()
    {
        parent::afterDestroy();
        $this->clearTagCache();
    }
}
