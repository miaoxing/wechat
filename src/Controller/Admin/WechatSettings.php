<?php

namespace Miaoxing\Wechat\Controller\Admin;

class WechatSettings extends \Miaoxing\Plugin\BaseController
{
    protected $controllerName = '微信功能设置';

    protected $actionPermissions = [
        'index,update' => '设置',
    ];

    public function indexAction()
    {
        $shareImage = &$this->setting('wechat.shareImage');
        wei()->event->trigger('postImageLoad', [&$shareImage]);

        return get_defined_vars();
    }

    public function updateAction($req)
    {
        $settings = (array)$req['settings'];
        wei()->event->trigger('preImageDataSave', [&$settings, ['wechat.shareImage']]);

        $this->setting->setValues($settings, ['wechat.']);
        return $this->suc();
    }
}
