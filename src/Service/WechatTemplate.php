<?php

namespace Miaoxing\Wechat\Service;

use Miaoxing\App\Service\Logger;
use Miaoxing\Plugin\BaseService;
use Miaoxing\Plugin\Service\User;
use Miaoxing\WxaTemplate\Service\WxaTemplateFormModel;
use Wei\RetTrait;

/**
 * 微信模板消息
 *
 * @property Logger logger
 */
class WechatTemplate extends BaseService
{
    use RetTrait;

    /**
     * @var array
     */
    protected $request = [];

    /**
     * @var User
     */
    protected $user;

    /**
     * @var WxaTemplateFormModel
     */
    protected $form;

    /**
     * 创建一个模板消息对象
     *
     * @return $this
     */
    public function __invoke()
    {
        return new static(['wei' => $this->wei]);
    }

    /**
     * 设置模板ID
     *
     * @param string $templateId
     * @return $this
     */
    public function templateId($templateId)
    {
        $this->request['template_id'] = $templateId;

        return $this;
    }

    /**
     * 设置接受的用户
     *
     * @param User $user
     * @return $this
     */
    public function to(User $user)
    {
        $this->user = $user;
        $this->request['touser'] = $user['wechatOpenId'];

        return $this;
    }

    /**
     * 设置模板跳转链接
     *
     * @param string $url
     * @return $this
     */
    public function url($url)
    {
        $this->request['url'] = $url;

        return $this;
    }

    public function page($page)
    {
        $this->request['page'] = $page;

        return $this;
    }

    /**
     * 设置跳小程序所需数据，不需跳小程序可不用传该数据
     *
     * @param array $minProgram
     * @return $this
     */
    public function miniProgram(array $minProgram)
    {
        $this->request['miniprogram'] = $minProgram;

        return $this;
    }

    /**
     * 设置模板数据
     *
     * @param array $data
     * @return WechatTemplate
     */
    public function data(array $data)
    {
        foreach ($data as $name => $rows) {
            // 允许直接传入文案，不传颜色，如 ['first' => '恭喜你购买成功！']
            if (!is_array($rows)) {
                $data[$name] = ['value' => $rows];
            }

            // 空值则显示默认值
            if (!$this->wei->isPresent($data[$name]['value'])) {
                $data[$name]['value'] = '-';
            }
        }

        $this->request['data'] = $data;

        return $this;
    }

    /**
     * 小程序的FormID
     *
     * @param string $formId
     * @return $this
     */
    public function formId($formId)
    {
        $this->request['form_id'] = $formId;
        return $this;
    }

    public function form(WxaTemplateFormModel $form)
    {
        $this->form = $form;
        $this->formId($form->formId);

        return $this;
    }

    /**
     * 发送模板消息
     *
     * @return array
     */
    public function send()
    {
        $this->logger->debug('Send template message', $this->request);

        if (!$this->request['template_id']) {
            return $this->err('缺少模板编号', -2);
        }

        if (!$this->request['touser']) {
            return $this->err('缺少用户OpenID', -3);
        }

        $account = wei()->wechatAccount->getCurrentAccount();
        $api = $account->createApiService();
        if ($account->isService()) {
            return $this->sendServiceTemplate($account, $api);
        } else {
            return $this->sendWxaTemplate($account, $api);
        }
    }

    protected function sendServiceTemplate(WechatAccount $account, WechatApi $api)
    {
        if (!$account['verified']) {
            return $this->err('微信帐号未认证', -1);
        }

        if (!$this->user['isValid']) {
            return $this->err('用户未关注');
        }

        if (isset($this->request['page'])) {
            unset($this->request['page']);
        }

        $api->sendTemplate($this->request);
        return $api->getResult();
    }

    protected function sendWxaTemplate(WechatAccount $account, WechatApi $api)
    {
        if (!array_key_exists('form_id', $this->request)) {
            $form = wei()->wxaTemplate->getAvailableForm($this->user);
            $this->form($form ?: null);
        }

        if (!$this->request['form_id']) {
            return $this->err('没有可用的form_id');
        }

        if (isset($this->request['url'])) {
            unset($this->request['url']);
        }

        $ret = $api->sendWxaTemplate($this->request);

        if ($ret['code'] === 1 && $this->form) {
            $this->form->consume();
        }

        return $ret;
    }
}
