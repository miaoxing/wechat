<?php

namespace Miaoxing\Wechat;

use Miaoxing\Wechat\Service\WechatTemplate;
use Wei\Event;

/**
 * @property Event $event
 */
trait NotifyTrait
{
    public function notify(WechatTemplate $template, $event)
    {
        $ret = $this->event->trigger($this->getBaseName() . ucfirst($event));
        if ($ret) {
            return $ret;
        }

        return $template->send();
    }

    protected function getBaseName()
    {
        $parts = explode('\\', get_class($this));

        return end($parts);
    }
}
