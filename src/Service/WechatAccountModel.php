<?php

namespace Miaoxing\Wechat\Service;

use Miaoxing\Plugin\BaseModel;
use Miaoxing\Plugin\Model\HasAppIdTrait;
use Miaoxing\Plugin\Model\ModelTrait;
use Miaoxing\Wechat\Metadata\WechatAccountTrait;

class WechatAccountModel extends BaseModel
{
    use ModelTrait;
    use HasAppIdTrait;
    use WechatAccountTrait;

    public const TYPE_SUBSCRIPTION = 1;

    public const TYPE_SERVICE = 2;

    public const TYPE_MP = 3;

    /**
     * 获取当前账号的微信API服务
     *
     * @return WechatApi
     */
    public function createApiService(): WechatApi
    {
        $api = wei()->wechatApi;
        $api->setOption([
            'appId' => $this->applicationId,
            'appSecret' => $this->applicationSecret,
            'authed' => $this->isAuthed,
            'refreshToken' => $this->refreshToken,
            'wechatComponentApi' => $this->isAuthed ? $this->createComponentApiService() : null,
        ]);
        return $api;
    }

    /**
     * Record: 获取当前账号的微信第三方平台API服务
     *
     * @return WechatComponentApi
     */
    public function createComponentApiService(): WechatComponentApi
    {
        $api = wei()->wechatComponentApi;

        // 如果未设置appSecret,认为是未初始化
        if (!$api->getAppSecret()) {
            // 获取第三方平台的appId(允许指定某个应用使用特定的第三方平台)
            $account = static::findOrInitBy(['applicationId' => $api->getAppId()]);
            $api->setOption([
                'appSecret' => $account['applicationSecret'],
                'verifyTicket' => $account['verifyTicket'],
            ]);
        }

        return $api;
    }
}
