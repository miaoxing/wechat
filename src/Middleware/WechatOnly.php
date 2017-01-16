<?php

namespace Miaoxing\Wechat\Middleware;

use Miaoxing\Plugin\Middleware\Base;
use Wei\RetTrait;

class WechatOnly extends Base
{
    use RetTrait;

    /**
     * {@inheritdoc}
     */
    public function __invoke($next)
    {
        if (!wei()->ua->isWeChat() && $this->request->isGet()) {
            if (wei()->setting('wechat.wechatOnly')) {
                return $this->err('请在微信中访问');
            }
        }

        return $next();
    }
}
