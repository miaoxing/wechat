<?php

namespace Miaoxing\Wechat\Controller\Wechat;

abstract class Base extends \Miaoxing\Plugin\BaseController
{
    public function __construct(array $options = array())
    {
        parent::__construct($options);

        // 日志直接输入到屏幕,方便调试
        if ($this->request['output']) {
            wei()->logger = new \Wei\Logger(array(
                'file' => 'php://output',
                'fileDetected' => true
            ));
            $this->response->flush();
        }
    }
}
