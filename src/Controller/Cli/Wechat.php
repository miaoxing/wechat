<?php

namespace Miaoxing\Wechat\Controller\Cli;

use Miaoxing\Plugin\Service\User;
use Miaoxing\Wechat\Service\WechatApi;

/**
 * @property \Wei\Cache $cache
 */
class Wechat extends \miaoxing\plugin\BaseController
{
    /**
     * 一次性将所有关注者的OpenID同步到数据库
     *
     * 频率: 用户低于1W,每小时1次;高于1W,60分钟 / 用户万数 = 每小时几次,假如用户有12W,则每5分钟1次
     * 限制: getUserOpenIds每天500次
     */
    public function syncAllUserOpenIdsAction()
    {
        if ($this->isPhpunit()) {
            return $this->suc('单元测试下,不执行同步');
        }

        // 确保不超时
        set_time_limit(0);

        // 1. 获取默认微信账户,初始化变量
        $wei = wei();
        $account = $wei->wechatAccount->getCurrentAccount();
        $api = $account->createApiService();

        // 本次同步新增用户数
        $newCount = 0;

        // 缓存未命中数
        $cacheMiss = 0;

        // 记录开始时间
        $startTime = microtime(true);

        // 储存下次同步的OpenID
        $cacheKey = 'nextOpenId' . $account['id'];

        // 2. 通过上次最后同步的OpenID,获取这次要同步用户的OpenID
        $nextOpenId = $wei->cache->get($cacheKey) ?: null;
        $userOpenIds = $api->getUserOpenIds($nextOpenId);

        if (!$userOpenIds) {
            return $this->err($api->getResult());
        }

        // 记录下次要同步的OpenID
        $nextOpenId = $userOpenIds['count'] == 10000 ? $userOpenIds['next_openid'] : null;
        $wei->cache->set($cacheKey, $nextOpenId);

        if ($userOpenIds['count'] > 2000 && function_exists('fastcgi_finish_request')) {
            echo '用户超过2000个,请在日志查看同步结果';
            fastcgi_finish_request();
        }

        $this->logger->info(sprintf(
            'GetUserOpenIds: total %s, count %s',
            $userOpenIds['total'],
            $userOpenIds['count']
        ));

        // 3. 逐个检查用户是否存在,如果不存在,创建新用户
        foreach ($userOpenIds['data']['openid'] as $index => $openId) {
            $wei->cache->get(
                'wechatOpenId' . $openId,
                function () use ($wei, $openId, &$newCount, &$cacheMiss, $account) {
                    // 缓存未命中+1
                    ++$cacheMiss;

                    // 3.1 通过缓存,检查用户是否已经存在
                    $user = $wei->user()->findOrInit(['wechatOpenId' => $openId]);

                    // 3.2 用户不存在,则创建新用户
                    if ($user->isNew()) {
                        ++$newCount;
                        $user->save();
                    }

                    // 3.3 确保下次不会再同步该用户
                    return true;
                }
            );

            // 3.4 每同步50个用户,稍作休息0.1秒
            if ($index % 50 == 0) {
                $this->logger->info('Synced 50 users');
                time_nanosleep(0, 100000000);
            }
        }

        $result = [
            'total' => $userOpenIds['total'],
            'newCount' => $newCount,
            'cacheMiss' => $cacheMiss,
            'cacheHitRate' => round(($userOpenIds['total'] - $cacheMiss) / $userOpenIds['total'], 2),
            'startTime' => date('Y-m-d H:i:s', $startTime),
            'costSeconds' => round(microtime(true) - $startTime, 1),
        ];

        $this->logger->info('Sync all user open ids result', $result);

        return $this->suc($result);
    }

    /**
     * 同步新用户的信息
     *
     * 频率: 每分钟1次
     * @todo 改为队列异步去同步
     */
    public function syncNewUsersAction($req)
    {
        if ($this->isPhpunit()) {
            return $this->suc('单元测试下,不执行同步');
        }

        // 1. 初始化数据

        // 本次同步成功的用户数
        $syncCount = 0;

        // 本次要同步的用户数
        $num = $req['num'] ?: 30;

        $api = wei()->wechatAccount->getCurrentAccount()->createApiService();

        // 2. 取出待同步的用户
        $syncUsers = wei()->db('wechatSyncUsers')->desc('id')->limit($num)->fetchAll();
        if (!$syncUsers) {
            return $this->suc('同步完成,没有新用户');
        }
        /** @var \Miaoxing\Plugin\Service\User $users */
        $users = wei()->user()->findAll(['id' => wei()->coll->column($syncUsers, 'id')]);

        // 3. 同步用户
        foreach ($users as $user) {
            $ret = $this->syncUser($user, $api);
            if ($ret['code'] === 1) {
                ++$syncCount;
            }
            // 删除已同步的记录
            wei()->db->delete('wechatSyncUsers', ['id' => $user['id']]);
        }

        // 4. 返回同步结果
        return $this->suc([
            'total' => $users->length(),
            'syncCount' => $syncCount,
        ]);
    }

    /**
     * 循环同步所有的用户,未同步的用户优先同步
     *
     * 频率: 每分钟1次
     */
    public function loopSyncAllUsersAction($req)
    {
        if ($this->isPhpunit()) {
            return $this->suc('单元测试下,不执行同步');
        }

        // 1. 获取默认微信账户,初始化变量
        $wei = wei();
        $account = $wei->wechatAccount->getCurrentAccount();
        $api = $account->createApiService();

        // 本次要同步的用户数
        $num = $req['num'] ?: 30;

        // 本次同步成功的用户数
        $syncCount = 0;

        // 存储下次同步用户ID的缓存名称
        $cacheKey = 'syncUserId' . $account['id'];

        // 2. 获取要同步的用户列表
        $userId = (int) $this->cache->get($cacheKey);
        /** @var \Miaoxing\Plugin\Service\User|\Miaoxing\Plugin\Service\User $users */
        $users = wei()->user()->where('id > ?', $userId)->asc('id')->limit($num)->findAll();

        // 如果用户数量不等于查询的数量,说明已经没有新用户,需要从头开始同步
        $nextUserId = $users->length() == $num ? $users[$num - 1]['id'] : 0;
        $this->cache->set($cacheKey, $nextUserId);

        // 调用同步用户之前的事件
        wei()->event->trigger('preSyncUser', []);

        // 3. 同步用户
        foreach ($users as $user) {
            $ret = $this->syncUser($user, $api);
            if ($ret['code'] === 1) {
                ++$syncCount;
            }
        }

        // 4. 返回同步结果
        return $this->suc([
            'total' => $users->length(),
            'syncCount' => $syncCount,
            'nextUserId' => $nextUserId,
        ]);
    }

    /**
     * 同步一个指定的用户
     *
     * @param \Miaoxing\Plugin\Service\User $user
     * @param \Miaoxing\Wechat\Service\WechatApi $api
     * @return bool
     */
    protected function syncUser(User $user, WechatApi $api)
    {
        if (!$user['wechatOpenId'] || strlen($user['wechatOpenId']) != 28) {
            return ['code' => -1, 'message' => 'OpenID不合法'];
        }

        $userInfo = $api->getUserInfo($user['wechatOpenId']);

        // 获取失败,如Token不对,HTTP请求错误,由接口方去告警
        if (!$userInfo) {
            // 如果是OpenID无效,设置用户为无效
            // {"errcode":40003,"errmsg":"invalid openid hint: [xx]"}
            $ret = $api->getResult();
            if ($ret['code'] == -40003) {
                $user->save([
                    'wechatOpenId' => '', // 清空不正确的OpenID
                    'isValid' => false,
                    // TODO 临时记录 待确认无误后删除
                    'signature' => $user['wechatOpenId'],
                ]);
            }

            return $ret;
        }

        // 用户已经取消订阅
        if (!$userInfo['subscribe']) {
            $user->save(['isValid' => false]);

            return ['code' => -4, 'message' => '用户已取消关注'];
        }

        // 获取分组Id
        if ($userInfo['groupid']) {
            $group = wei()->group()->find(['wechatId' => $userInfo['groupid']]);
        }

        // 如果锁定了地区,不覆盖地区内容
        if (!$user->isStatus(User::STATUS_REGION_LOCKED)) {
            $user->setData([
                'country' => $userInfo['country'],
                'province' => $userInfo['province'],
                'city' => $userInfo['city'],
            ]);
        }

        // 保存用户资料
        $user->save([
            'isValid' => true,
            'nickName' => $userInfo['nickname'],
            'remarkName' => $userInfo['remark'],
            'gender' => $userInfo['sex'],
            'regTime' => date('Y-m-d H:i:s', $userInfo['subscribe_time']),
            'headImg' => $this->removePrefix($userInfo['headimgurl'], 'http:'),
            'groupId' => $group ? $group['id'] : ($user['groupId'] ?: 0),
        ]);

        return ['code' => 1, 'message' => '同步成功'];
    }

    /**
     * 检查是否在单元测试中
     *
     * @return bool
     */
    protected function isPhpunit()
    {
        $argv = $this->request->getServer('argv');

        return strpos($argv[0], 'phpunit') !== false;
    }

    /**
     * 移除字符串指定的前缀
     *
     * @param string $string
     * @param string $prefix
     * @return string
     */
    protected function removePrefix($string, $prefix)
    {
        if (substr($string, 0, strlen($prefix)) == $prefix) {
            return substr($string, strlen($prefix));
        } else {
            return $string;
        }
    }
}
