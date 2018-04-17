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
            $args = array_unshift($data, $event);
        } elseif (func_get_arg(2)) {
            $args = [$template, $data];
        } else {
            $args = [$template];
        }

        $ret = $this->event->until($event, $args);
        if ($ret) {
            return $ret;
        }

        return $template->send();
    }
}
