<?php

namespace Miaoxing\Wechat;

use Miaoxing\Wechat\Service\WechatTemplate;
use Wei\Event;

/**
 * @property Event $event
 */
trait NotifyTrait
{
    public function notify(WechatTemplate $template, $event, $data = null)
    {
        if (is_array($data)) {
            array_unshift($data, $template);
            $args = $data;
        } elseif (3 === func_num_args()) {
            $args = [$template, $data];
        } else {
            $args = [$template];
        }

        $ret = wei()->event->until($event, $args);
        if ($ret) {
            return $ret;
        }

        return $template->send();
    }
}
