<?php

namespace Miaoxing\Wechat\Controller;

use Miaoxing\Wechat\Controller\Wechat;

class WechatReplies extends Wechat
{
    /**
     * 验证Token是GET请求,引到index方法
     */
    public function indexAction($req)
    {
        return parent::replyAction($req);
    }

    /**
     * 推送消息是POST请求,引到create方法
     */
    public function createAction($req)
    {
        return parent::replyAction($req);
    }
}
