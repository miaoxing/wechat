<?php

namespace Miaoxing\Wechat\Controller\Admin;

class WechatAccount extends \miaoxing\plugin\BaseController
{
    protected $controllerName = '微信公众号管理';

    protected $actionPermissions = [
        'index' => '查看',
        'edit,update' => '编辑',
    ];

    public function indexAction($req)
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        return get_defined_vars();
    }

    public function editAction($req)
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        return get_defined_vars();
    }

    public function updateAction($req)
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $account->save($req);

        return $this->suc();
    }
}
