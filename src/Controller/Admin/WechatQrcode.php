<?php

namespace Miaoxing\Wechat\Controller\Admin;

use ZipArchive;

class WechatQrcode extends Base
{
    protected $controllerName = '微信二维码管理';

    protected $actionPermissions = [
        'index' => '列表',
        'new,create' => '添加',
        'edit,update' => '编辑',
        'destroy' => '删除',
        'show' => '查看',
    ];

    protected $guestPages = [
        'admin/qrcode/sync',
    ];

    public function indexAction($req)
    {
        switch ($req['_format']) {
            case 'json':
                $qrcodes = wei()->weChatQrcode();
                $qrcodes->select('weChatQrcode.*')->leftJoin('user', 'user.id = weChatQrcode.userId');
                // 类型
                if (isset($req['type']) && $req['type'] != 0) {
                    $qrcodes->where(($req['type'] == 1 ? 'userId != 0' : 'userId = 0'));
                }

                if ($req['search']) {
                    $qrcodes->andWhere('user.name LIKE ?', ['%' . $req['search'] . '%']);
                }

                // 分页
                $qrcodes->limit($req['rows'])->page($req['page']);

                // 排序
                $qrcodes->desc('id');

                $data = [];
                foreach ($qrcodes->findAll() as $qrcode) {
                    $user = $qrcode->getUser();
                    $data[] = $qrcode->toArray() + [
                            'user' => $user ? $user->toArray() : '',
                            'award' => $qrcode->getAward()->toArray() + [
                                    'contents' => $qrcode->getAward()->getContents(),
                                    'stat' => $qrcode->getAward()->getStats(),
                                ],
                        ];
                }

                return $this->json('读取列表成功', 1, [
                    'data' => $data,
                    'page' => $req['page'],
                    'rows' => $req['rows'],
                    'records' => $qrcodes->count(),
                ]);

            default:
                return get_defined_vars();
        }
    }

    public function newAction($req)
    {
        return $this->editAction($req);
    }

    public function createAction($req)
    {
        return $this->updateAction($req);
    }

    public function editAction($req)
    {
        $qrcode = wei()->weChatQrcode()->findOrInitById($req['id']);

        if ($qrcode->isNew()) {
            $qrcode['sceneId'] = $qrcode->getNextSceneId();
        }

        $data = $qrcode->toArrayWithArticles();

        $tags = [];
        foreach (wei()->userTag->getAll() as $userTag) {
            $tags[] = ['id' => $userTag->id, 'text' => $userTag->name];
        }

        return get_defined_vars();
    }

    public function updateAction($req)
    {
        $validator = wei()->validate([
            'data' => $req,
            'rules' => [
                'sceneId' => [
                ],
            ],
            'names' => [
                'sceneId' => '场景编号',
            ],
        ]);
        if (!$validator->isValid()) {
            return $this->err($validator->getFirstMessage());
        }

        $qrcode = wei()->weChatQrcode()->findOrInitById($req['id']);

        $award = $qrcode->getAward();
        $award->save([
            'name' => '扫描二维码,公众号:' . $qrcode['accountId'] . ',场景:' . $req['sceneId'],
            'source' => 'weChatQrcode',
            'once' => 1,
            'awards' => $req['awards'],
        ]);

        $qrcode['awardId'] = $award['id'];
        $qrcode->save($req);

        return $this->suc();
    }

    public function destroyAction($req)
    {
        $weChatQrcode = wei()->weChatQrcode()->findOneById($req['id']);
        $weChatQrcode->destroy();

        return $this->suc();
    }

    public function showAction($req)
    {
        $weChatQrcode = wei()->weChatQrcode()->findOrInit(['sceneId' => $req['sceneId']]);
        $image = $this->url('admin/wechat-qrcode/showImage', ['sceneId' => $weChatQrcode['sceneId']]);

        return $this->suc([
            'data' => $weChatQrcode->toArray() + ['image' => $image],
        ]);
    }

    public function showImageAction($req)
    {
        $weChatQrcode = wei()->weChatQrcode()->findOrInit(['sceneId' => $req['sceneId']]);

        $qrcodeService = wei()->weChatQrcode;
        $qrcodeService->displayImage($weChatQrcode);
    }

    public function batchDownloadAction($req)
    {
        $qrcodes = wei()->weChatQrcode()->findAll(['sceneId' => $req['sceneIds']]);
        $qrcodeService = wei()->weChatQrcode;

        // 1. 创建目录
        $dir = 'upload/' . $this->app->getId() . '/' . date('Ymd') . '/' . date('His') . '-qrcodes';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // 2. 写入二维码
        foreach ($qrcodes as $qrcode) {
            $qrcodeRes = $qrcodeService->createImage($qrcode);
            $name = $qrcode->getFileName();
            imagejpeg($qrcodeRes, $dir . '/' . $name . '.jpg');
        }

        // 3. 保存为压缩包
        $zip = new ZipArchive();
        $zipName = $dir . '/qrcodes-' . date('Y-m-d-H-i-s') . '.zip';
        $zip->open($zipName, ZipArchive::CREATE);
        foreach (glob($dir . '/*') as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        // 4. 弹出下载
        return $this->response->download($zipName, [
            'type' => 'application/zip',
        ]);
    }

    /**
     * 二维码数据统计
     * @param $req
     * @return array|\Wei\Response
     */
    public function showDetailAction($req)
    {
        $startTime = $req['startTime'] ?: date('Y-m-d', time() - 7 * 86400);
        $endTime = $req['endTime'] ?: date('Y-m-d', time());
        $daysLen = (int) ((strtotime($endTime) - strtotime($startTime)) / 86400);

        // 1. 获取统计和每天的数据
        $qrcode = wei()->weChatQrcode()->findOne(['sceneId' => $req['sceneId']]);
        $sql1 = ', (select count(*) from wechatQrcodeLogs where appId=' . wei()->app->getId() .
            ' and wechatQrcodeId=' . $qrcode['id'] . ' and type=logType and createTime <= date_add(dateDay, interval 1 day)) as allCount';
        $sql2 = ', (select count(distinct(userId)) from wechatQrcodeLogs where appId=' . wei()->app->getId() .
            ' and wechatQrcodeId=' . $qrcode['id'] . ' and type=logType and createTime <= date_add(dateDay, interval 1 day)) as allHeadCount';
        $qrcodeLogs = wei()->wechatQrcodeLog()
            ->select('DATE_FORMAT(createTime,"%Y-%m-%d") as dateDay, type as logType, count(*) as addCount, count(distinct(userId)) as addHeadCount' . $sql1 . $sql2)
            ->curApp()
            ->andWhere(['wechatQrcodeId' => $qrcode['id']])
            ->andWhere('createTime >= ? and createTime < ?', [$startTime, $endTime])
            ->groupBy('dateDay,logType')
            ->desc('id')
            ->fetchAll();

        // 2. 初始化数据
        $details = [];
        for ($i = 1; $i <= $daysLen; ++$i) {
            $day = date('Y-m-d', strtotime($endTime) - $i * 86400);
            $details[$day] = [
                'statDate' => $day,
                'addValidCount' => 0,
                'allValidCount' => 0,
                'addTotalHeadCount' => 0,
                'allTotalHeadCount' => 0,
                'addCancelHeadCount' => 0,
                'allCancelHeadCount' => 0,
                'addTotalCount' => 0,
                'allTotalCount' => 0,
                'addCancelCount' => 0,
                'allCancelCount' => 0,
            ];
        }

        // 3. 统计数据
        foreach ($qrcodeLogs as $qrcodeLog) {
            if ($qrcodeLog['logType'] == 1) { // 关注
                $details[$qrcodeLog['dateDay']]['addTotalCount'] = $qrcodeLog['addCount'];
                $details[$qrcodeLog['dateDay']]['allTotalCount'] = $qrcodeLog['allCount'];

                $details[$qrcodeLog['dateDay']]['addTotalHeadCount'] = $qrcodeLog['addHeadCount'];
                $details[$qrcodeLog['dateDay']]['allTotalHeadCount'] = $qrcodeLog['allHeadCount'];

                $details[$qrcodeLog['dateDay']]['addValidCount'] += $qrcodeLog['addCount'];
                $details[$qrcodeLog['dateDay']]['allValidCount'] += $qrcodeLog['allCount'];
            } else {
                if ($qrcodeLog['logType'] == 0) { // 取关
                    $details[$qrcodeLog['dateDay']]['addCancelCount'] = $qrcodeLog['addCount'];
                    $details[$qrcodeLog['dateDay']]['allCancelCount'] = $qrcodeLog['allCount'];

                    $details[$qrcodeLog['dateDay']]['addCancelHeadCount'] = $qrcodeLog['addHeadCount'];
                    $details[$qrcodeLog['dateDay']]['allCancelHeadCount'] = $qrcodeLog['allHeadCount'];

                    $details[$qrcodeLog['dateDay']]['addValidCount'] -= $qrcodeLog['addCount'];
                    $details[$qrcodeLog['dateDay']]['allValidCount'] -= $qrcodeLog['allCount'];
                }
            }
        }

        // 4. 检验数据
        $newTotalData = wei()->wechatQrcodeLog()
            ->curApp()
            ->select('count(*) as allCount, count(distinct(userId)) as allHeadCount')
            ->andWhere(['wechatQrcodeId' => $qrcode['id']])
            ->andWhere(['type' => 1])
            ->andWhere('createTime < ?', [$endTime])
            ->fetch();

        $newCancelData = wei()->wechatQrcodeLog()
            ->curApp()
            ->select('count(*) as allCount, count(distinct(userId)) as allHeadCount')
            ->andWhere(['wechatQrcodeId' => $qrcode['id']])
            ->andWhere(['type' => 0])
            ->andWhere('createTime < ?', [$endTime])
            ->fetch();

        $lastTotalCount = $newTotalData['allCount'];
        $lastCancelCount = $newCancelData['allCount'];
        $lastTotalHeadCount = $newTotalData['allHeadCount'];
        $lastCancelHeadCount = $newCancelData['allHeadCount'];
        $afterCheckDetails = [];
        foreach ($details as $detail) {
            // 次数
            $detail['allTotalCount'] = $detail['allTotalCount'] ?: $lastTotalCount;
            $detail['allCancelCount'] = $detail['allCancelCount'] ?: $lastCancelCount;
            //人数
            $detail['allTotalHeadCount'] = $detail['allTotalHeadCount'] ?: $lastTotalHeadCount;
            $detail['allCancelHeadCount'] = $detail['allCancelHeadCount'] ?: $lastCancelHeadCount;
            //积累数
            $detail['allValidCount'] = $detail['allTotalCount'] - $detail['allCancelCount'];

            $lastTotalCount = $detail['allTotalCount'] - $detail['addTotalCount'];
            $lastCancelCount = $detail['allCancelCount'] - $detail['addCancelCount'];
            $lastTotalHeadCount = $detail['allTotalHeadCount'] - $detail['addTotalHeadCount'];
            $lastCancelHeadCount = $detail['allCancelHeadCount'] - $detail['addCancelHeadCount'];

            $afterCheckDetails[] = $detail;
        }
        $details = array_reverse($afterCheckDetails);

        // 5. 计算趋势图
        $statDates = wei()->coll->column($details, 'statDate');
        $columns = [
            'addValidCount',
            'allValidCount',
            'addTotalHeadCount',
            'allTotalHeadCount',
            'addCancelHeadCount',
            'allCancelHeadCount',
            'addTotalCount',
            'allTotalCount',
            'addCancelCount',
            'allCancelCount',
        ];

        $chart = wei()->chart;
        $charts = [];
        foreach ($columns as $column) {
            $charts[$column] = $chart->getColumnValues($details, $column);
        }

        return get_defined_vars();
    }
}
