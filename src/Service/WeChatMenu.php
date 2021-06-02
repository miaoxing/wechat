<?php

namespace Miaoxing\Wechat\Service;

use Miaoxing\Plugin\BaseService;

/**
 * @property \Miaoxing\LinkTo\Service\LinkTo $linkTo
 */
class WeChatMenu extends BaseService
{
    protected $autoId = true;

    protected $firstLevelMaxItems = 3;

    protected $secondLevelMaxItems = 5;

    protected $data = [
        'linkTo' => [],
    ];

    /**
     * @return WeChatMenu[]|WeChatMenu
     */
    public function getChildren()
    {
        return wei()->weChatMenu()->asc('sort')->andWhere(['parentId' => $this['id']]);
    }

    public function beforeCreate()
    {
        $this['sort'] = (int) wei()->weChatMenu()->desc('sort')->findOrInit(['parentId' => (int) $this['parentId']])->get('sort') + 1;
    }

    /**
     * 转换为微信自定义菜单要求的JSON数组格式
     *
     * Collection方法
     *
     * @return array
     * @link http://mp.weixin.qq.com/wiki/index.php?title=%E8%87%AA%E5%AE%9A%E4%B9%89%E8%8F%9C%E5%8D%95%E5%88%9B%E5%BB%BA%E6%8E%A5%E5%8F%A3
     */
    public function toButtons()
    {
        $data = [];

        $index = 0;
        /** @var $menu WeChatMenu */
        foreach ($this->data as $menu) {
            if ($this->firstLevelMaxItems >= $index) {
                $data[] = $menu->toButton();
                ++$index;
            } else {
                break;
            }
        }

        return ['button' => $data];
    }

    public function toButton()
    {
        $button = [];
        $button['name'] = $this['name'];

        $children = $this->getChildren()->enabled()->limit($this->secondLevelMaxItems);

        if ($children->size()) {
            foreach ($children as $child) {
                $button['sub_button'][] = $child->toButton();
            }
        } else {
            switch ($this['linkTo']['type']) {
                case 'keyword':
                    $button += ['type' => 'click', 'key' => $this['linkTo']['value']];
                    break;

                case 'miniProgram':
                    list($appId, $path, $url) = explode('+', $this['linkTo']['value']);
                    $button += [
                        'type' => 'miniprogram',
                        'appid' => $appId,
                        'pagepath' => $path ?: '/pages/index/index',
                        'url' => $url ?: wei()->url->full(''),
                    ];
                    break;

                default:
                    $button += ['type' => 'view', 'url' => wei()->linkTo->getFullUrl($this['linkTo'])];
            }
        }

        return $button;
    }

    public function afterFind()
    {
        parent::afterFind();
        $this['linkTo'] = $this->linkTo->decode($this['linkTo']);
    }

    public function beforeSave()
    {
        parent::beforeSave();
        $this['linkTo'] = $this->linkTo->encode($this['linkTo']);
    }
}
