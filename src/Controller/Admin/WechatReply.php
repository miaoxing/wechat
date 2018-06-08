<?php

namespace Miaoxing\Wechat\Controller\Admin;

class WechatReply extends Base
{
    protected $controllerName = '微信回复管理';

    protected $actionPermissions = [
        'index' => '列表',
        'new,create' => '添加',
        'edit,update' => '编辑',
        'destroy' => '删除',
    ];

    public function indexAction($req)
    {
        switch ($req['_format']) {
            case 'json':
                $replies = wei()->weChatReply();

                // 不显示已删除和保留的编号(关注时回复,默认回复)
                $replies->notDeleted()->notReserved();

                // 分页
                $replies->limit($req['rows'])->page($req['page']);

                // 只显示当前公众号
                //$replies->andWhere(['accountId' => $req['accountId']]);

                // 排序
                $replies->desc('id');

                $data = [];

                foreach ($replies as $reply) {
                    $data[] = $reply->toArray() + [
                            'matchTypeName' => $reply->getMatchTypeName(),
                            'articles' => $reply->getArticles()->toArray(),
                        ];
                }

                return $this->json('读取列表成功', 1, [
                    'data' => $data,
                    'page' => $req['page'],
                    'rows' => $req['rows'],
                    'records' => $replies->count(),
                ]);

            default:
                return get_defined_vars();
        }
    }

    public function newAction($req)
    {
        return $this->editAction($req);
    }

    public function editAction($req)
    {
        // TODO 数量多的时候,可以通过策略模式拆分为每个一个类
        $formConfigs = [
            'default' => [
                'showScene' => true,
                'hideKeywords' => true,
                'showPlainKeywords' => true,
                'hideMatchType' => true,
                'defaultData' => [
                    'keywords' => '默认回复',
                ],
            ],
            'subscribe' => [
                'showScene' => true,
                'hideKeywords' => true,
                'showPlainKeywords' => true,
                'hideMatchType' => true,
                'defaultData' => [
                    'keywords' => '关注时回复',
                ],
            ],
            'phone' => [
                'showScene' => true,
                'hideKeywords' => true,
                'hideMatchType' => true,
                'defaultData' => [
                    'keywords' => '输入手机号码',
                ],
            ],
            'scan' => [
                'showScene' => true,
                'hideKeywords' => true,
                'showPlainKeywords' => true,
                'hideMatchType' => true,
                'defaultData' => [
                    'keywords' => '扫码',
                ],
            ],
        ];

        $reply = wei()->weChatReply()->findOrInit(['id' => $req['id']])->fromArray($req);

        $formConfig = isset($formConfigs[$req['id']]) ? $formConfigs[$req['id']] : [];

        if ($reply->isNew() && isset($formConfig['defaultData'])) {
            $reply->fromArray($formConfig['defaultData']);
        }

        return get_defined_vars();
    }

    public function createAction($req)
    {
        return $this->updateAction($req);
    }

    public function updateAction($req)
    {
        // 去掉多余空格
        $keywords = explode(' ', $req['keywords']);
        $keywords = implode(' ', array_filter(array_unique($keywords)));
        $req['keywords'] = $keywords;

        $reply = wei()->weChatReply()->findId($req['id']);
        $reply->save($req);

        return $this->suc();
    }

    public function destroyAction($req)
    {
        wei()->weChatReply()->findOneById($req['id'])->destroy();

        return $this->suc();
    }
}
