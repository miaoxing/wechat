<?php

namespace Miaoxing\Wechat\Controller\Admin;

class WechatMenu extends Base
{
    protected $controllerName = '微信菜单管理';

    protected $actionPermissions = [
        'index' => '列表',
        'create' => '添加',
        'update,bulkUpdate' => '编辑',
        'destroy' => '删除',
        'publishWechatMenu' => '发布',
        'deleteWechatMenu' => '删除微信菜单',
    ];

    public function indexAction($req)
    {
        switch ($req['_format']) {
            case 'json':
                $menus = wei()->weChatMenu();

                $menus->where(['categoryId' => $req['categoryId']]);
                // 分页
                $menus->limit($req['rows'])->page($req['page']);

                // 排序
                $menus->asc('sort');

                $menus->andWhere(['parentId' => 0]);

                // 先获取一级菜单,再获取下级菜单
                $data = [];
                foreach ($menus as $menu) {
                    $children = $menu->getChildren();
                    $data[] = $menu->toArray() + [
                            'hasChild' => (bool) $children->length(),
                        ];
                    foreach ($children as $child) {
                        $data[] = $child->toArray() + [
                                'hasChild' => false,
                            ];
                    }
                }

                return $this->json('读取列表成功', 1, [
                    'data' => $data,
                    'page' => $req['page'],
                    'rows' => $req['rows'],
                    'records' => $menus->count(),
                ]);

            default:
                return get_defined_vars();
        }
    }

    public function createAction($req)
    {
        return $this->updateAction($req);
    }

    public function updateAction($req)
    {
        $menu = wei()->weChatMenu()->findOrInitById($req['id']);
        $menu->save($req);

        return $this->suc();
    }

    /**
     * 批量更新菜单
     */
    public function bulkUpdateAction($req)
    {
        foreach ((array) $req['menus'] as $menu) {
            $record = wei()->weChatMenu()->findOneById(isset($menu['id']) ? $menu['id'] : null);
            $record->save((array) $menu);
        }

        return $this->suc();
    }

    public function destroyAction($req)
    {
        $menu = wei()->weChatMenu()->findOneById($req['id']);
        $menu->destroy();

        return $this->suc();
    }

    /**
     * 发布微信菜单
     * @param $req
     * @return \Wei\Response
     */
    public function publishWechatMenuAction($req)
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $api = $account->createApiService();

        // 1. 发布默认菜单
        $menuDefaultCategory = wei()->wechatMenuCategory()->curApp()->andWhere(['isDefault' => 1])->enabled()->find();
        if (!$menuDefaultCategory) {
            return $this->err('发布失败，没有默认菜单');
        }

        $buttons = $menuDefaultCategory->getFirstLevelMenus()->toButtons();
        $result = $api->createMenu($buttons);
        if (!$result) {
            return $this->err('发布默认菜单失败,微信返回：' . $api->getMainMessage(), $api->getCode());
        }

        // 2. 发布个性化菜单
        $menuCategories = wei()->wechatMenuCategory()->curApp()->andWhere(['isDefault' => 0])->enabled()->asc('sort')->findAll();
        foreach ($menuCategories as $menuCategory) {
            $buttons = $menuCategory->getFirstLevelMenus()->toButtons() + [
                    'matchrule' => [],
                ];

            $conditionKeys = wei()->wechatMenuCategory->getConditionKeys();
            foreach ($conditionKeys as $key) {
                switch ($key) {
                    case 'groupId':
                        if ($menuCategory[$key]) {
                            $buttons['matchrule'] += ['group_id' => $menuCategory->getGroup()->get('wechatId')];
                        }
                        break;
                    case 'clientPlatformType':
                        if ($menuCategory[$key]) {
                            $buttons['matchrule'] += ['client_platform_type' => $menuCategory['clientPlatformType']];
                        }
                        break;
                    default:
                        if ($menuCategory[$key]) {
                            $buttons['matchrule'] += [$key => $menuCategory[$key]];
                        }
                        break;
                }
            }

            $result = $api->addConditionalMenu($buttons);
            if (!$result) {
                return $this->err('发布个性化菜单失败,微信返回：' . $api->getMainMessage(), $api->getCode());
            }
        }

        return $this->suc('菜单发布成功!');
    }

    /**
     * 删除微信菜单
     * @param $req
     * @return \Wei\Response
     */
    public function deleteWechatMenuAction($req)
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $api = $account->createApiService();
        $result = $api->deleteMenu();

        if (!$result) {
            return $this->err('删除失败,微信返回消息：' . $api->getMainMessage(), $api->getCode());
        } else {
            return $this->suc('删除菜单成功!');
        }
    }

    public function testAction()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $api = $account->createApiService();
        $result = $api->getMenu();
        print_r($result->getResponse());
        die;
    }

    /**
     * 加载微信菜单
     * @param $req
     * @return \Wei\Response
     */
    public function loadFromWeChatAction($req)
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $api = $account->createApiService();

        $now = date('Y-m-d H:i:s');
        $db = wei()->db;
        $table = $db->getTable('weChatMenu');
        $result = $api->getMenu();

        if (!$result) {
            return $this->err('微信返回消息：' . $api->getMessage(), $api->getCode());
        }

        // 构造存入数据库的二维数组
        $menus = [];
        $sort = 1;

        foreach ($result['menu']['button'] as $button) {
            $parentId = wei()->seq();
            $menus[] = [
                'id' => $parentId,
                'parentId' => 0,
                'name' => $button['name'],
                'linkTo' => json_encode($this->createLinkToFromWeChatButton($button)),
                'sort' => $sort++,
                'accountId' => $req['accountId'],
                'createTime' => $now,
                'createUser' => $this->curUser['id'],
                'updateTime' => $now,
                'updateUser' => $this->curUser['id'],
            ];

            $subSort = 1;
            foreach ($button['sub_button'] as $subButton) {
                $menus[] = [
                    'id' => wei()->seq(),
                    'parentId' => $parentId,
                    'name' => $subButton['name'],
                    'linkTo' => json_encode($this->createLinkToFromWeChatButton($subButton)),
                    'sort' => $subSort++,
                    'accountId' => $req['accountId'],
                    'createTime' => $now,
                    'createUser' => $this->curUser['id'],
                    'updateTime' => $now,
                    'updateUser' => $this->curUser['id'],
                ];
            }
        }

        // 取出原来的菜单,记录到日志
        $origMenus = wei()->weChatMenu()->fetchAll(['accountId' => $req['accountId']]);
        $this->logger->info($origMenus);

        // 清空原来的菜单
        wei()->weChatMenu()->delete(['accountId' => $req['accountId']]);

        // 菜单数据插入数据表
        $db->insertBatch($table, $menus);

        return $this->suc();
    }

    protected function createLinkToFromWeChatButton($button)
    {
        switch ($button['type']) {
            case 'click':
                return [
                    'type' => 'keyword',
                    'keyword' => $button['key'],
                ];
                break;

            case 'view':
                return [
                    'type' => 'url',
                    'url' => $button['url'],
                ];
                break;

            default:
                return [];
        }
    }
}
