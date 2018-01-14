<?php

namespace Miaoxing\Wechat\Service;

use Miaoxing\App\Service\Logger;
use Miaoxing\Plugin\BaseService;
use Miaoxing\Plugin\Service\User;
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
            if (is_string($rows)) {
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
     * 发送模板消息
     *
     * @return array
     */
    public function send()
    {
        $this->logger->debug('Send template message', $this->request);

        $account = wei()->wechatAccount->getCurrentAccount();
        if (!$account->isVerifiedService()) {
            return $this->err('没有开通该服务', -1);
        }

        if (!$this->request['template_id']) {
            return $this->err('缺少模板编号', -2);
        }

        if (!$this->user['isValid'] || !$this->user['wechatOpenId']) {
            return $this->err('用户未关注', -3);
        }

        $api = $account->createApiService();
        $api->sendTemplate($this->request);

        return $api->getResult();
    }
}
