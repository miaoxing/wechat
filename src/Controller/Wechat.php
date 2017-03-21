<?php

namespace Miaoxing\Wechat\Controller;

use Miaoxing\Wechat\Service\WechatAccount;
use Wei\Request;
use Wei\WeChatApp;

class Wechat extends \miaoxing\plugin\BaseController
{
    public function replyAction($req)
    {
        // 记录请求日志
        $content = $this->request->getContent();
        $this->logger->debug('Wechat reply request', [
            'url' => $this->request->getUrl(),
            'content' => $content,
        ]);

        // 1. 获取当前回复对应的微信账户,初始化回复
        $account = wei()->wechatAccount->getCurrentAccount();
        $reply = wei()->weChatReply();
        $app = wei()->weChatApp->setOption($account->getWechatAppOptions());

        // 2. 重新解析请求数据
        $ret = $app->parse();
        if ($ret['code'] !== 1) {
            if ($content) {
                $this->logger->warning($ret['message'], $ret + ['content' => $content]);
            }

            return $this->err($ret);
        }

        if ($app->isVerifyToken()) {
            return $app->run();
        }

        // 3. 处理公众号第三方平台的推送
        if ($app->getAttr('InfoType')) {
            return $this->runComponent($app, $account);
        }

        // 4. 根据OpenID初始化微信平台的用户
        $openId = $app->getFromUserName();
        if (!$openId) {
            return $this->err('OpenID不能为空');
        }
        $user = wei()->curUser->loginBy(['wechatOpenId' => $openId]);

        // 5. 发送前,将信息记录到数据库
        $app->setOption('beforeSend', function (WeChatApp $app, &$response) use ($user, $reply, $account) {
            $this->logger->debug('Wechat reply response', $response);

            // 记录用户输入的信息
            if (!in_array(strtolower($app->getMsgType()), ['event'])) {
                wei()->message()->saveData([
                    'userId' => $user['id'],
                    'msgType' => $app->getMsgType(),
                    'platformId' => WechatAccount::PLATFORM_ID,
                    'platformMsgId' => $app->getMsgId(),
                    'content' => $app->getMsgType() === 'text' ? $app->getContent() : json_encode($app->getAttrs()),
                    'source' => 1,
                    'fromKeyword' => (int) $reply->isFromKeyword(),
                    'createTimestamp' => $app->getCreateTime(),
                ]);
            }

            // 没有匹配到任何规则为空数组
            if ($response) {
                // 如果返回的是文本消息,更新里面的URL地址
                if ($response['MsgType'] == 'text') {
                    $response['Content'] = $this->updateMessageUrl($response['Content']);
                }

                // 只保存回复的文本消息到数据库
                if ($response['MsgType'] == 'text' && $response['Content']) {
                    wei()->message()->saveData([
                        'platformId' => WechatAccount::PLATFORM_ID,
                        'userId' => $user['id'],
                        'msgType' => $response['MsgType'],
                        'content' => $response['Content'],
                        'createTimestamp' => $response['CreateTime'],
                    ]);
                }
            }
        });

        // 6. 各种场景的回复

        // 关注
        $app->subscribe(function (WeChatApp $app) use ($user, $reply) {
            // 将重新关注的用户置为有效
            if (!$user['isValid']) {
                $user['isValid'] = true;
            }

            if ($sceneId = $app->getScanSceneId()) {
                $user->save([
                    'isValid' => true,
                    'source' => $sceneId,
                ]);
            }

            if ($user->isChanged()) {
                $user->save();
            }

            // 关注回复
            if ($reply->findByIdFromCache('subscribe')) {
                return $reply->send($app, '{关注顺序}', $user['id']);
            }
        });

        // 扫描
        $app->scan(function (WeChatApp $app) use ($user, $reply) {
            // 记录用户来源
            $sceneId = $app->getScanSceneId();
            $user->save(['source' => $sceneId]);

            // 扫码回复
            if ($reply->findByIdFromCache('scan')) {
                return $reply->send($app);
            }
        });

        // 取消关注,如果取消关注,将有效位置为0
        $app->unsubscribe(function () use ($user) {
            $user->save(['isValid' => 0, 'unsubscribeTime' => date('Y-m-d H:i:s')]);
        });

        // 关键字回复和默认回复
        $keyword = $app->getKeyword();
        $app->defaults(function (WeChatApp $app) use ($reply, $keyword) {
            if ($reply->findByKeyword($keyword) || $reply->findByDefault()) {
                return $reply->send($app);
            }
        });

        // 从缓存获取所有场景关键字
        $sceneKeywords = wei()->weChatReply()->getSceneKeywordsFromCache();

        // 输入手机号码
        if (isset($sceneKeywords['phone'])) {
            $app->match('/^1[34578][\d]{9}$/', function (WeChatApp $app) use ($user, $reply) {
                // 记录用户手机号码
                $user->save(['mobile' => $app->getContent()]);
                $reply = $reply->findByIdFromCache('phone');

                return $reply->send($app);
            });
        }

        // 屏蔽一些事件,不返回默认回复
        $app->on('user_get_card', function () {
        });
        $app->on('user_del_card', function () {
        });
        $app->on('user_consume_card', function () {
        });
        $app->on('wifiConnected', function () {
        });

        // 7. 是否开启多客服
        if ($account['transferCustomer']) {
            $app->transferCustomer(function (WeChatApp $app) use ($reply, $keyword) {
                if ($reply->findByKeyword($keyword)) {
                    return $reply->send($app);
                }

                return $app->sendTransferCustomerService();
            });
        }

        // 8. 触发事件,可以用来自定义消息回复,重写消息回复等

        // 允许捕获所有消息
        $this->event->trigger('wechatMessage', [$app, $user, $account]);

        // 也允许只捕获某种事件
        if (in_array($app->getMsgType(), ['event'])) {
            if ($app->getEvent() == 'subscribe' or $app->getEvent() == 'unsubscribe') {
                wei()->logger->info('测试惠氏接收关注事件', ['event' => $app->getEvent()]);
            }

            $event = $this->classify($app->getEvent());
            $this->event->trigger('wechat' . $event, [$app, $user, $account]);
        }

        // 如果是订阅且包含扫描,同时调用扫描事件
        if ($app->getEvent() == 'subscribe' && $app->getScanSceneId()) {
            $this->event->trigger('wechatScan', [$app, $user, $account]);
        }

        return $app->run();
    }

    /**
     * 获取JS-SDK配置
     *
     * @param Request $req
     * @return string
     */
    public function jsConfigAction($req)
    {
        $account = wei()->wechatAccount->getCurrentAccount();

        $url = $req['url'] ?: $req->getReferer();
        $config = $account->getConfigData([], $url);

        return $this->suc([
            'config' => $config,
        ]);
    }

    /**
     * 单独处理来自第三方平台的通知
     *
     * @param WeChatApp $app
     * @param \Miaoxing\Wechat\Service\WechatAccount $account
     * @return string
     */
    protected function runComponent(WeChatApp $app, WechatAccount $account)
    {
        $this->logger->info('收到第三方平台通知', $app->getAttrs());

        switch ($app->getAttr('InfoType')) {
            case 'component_verify_ticket':
                $account->save(['verifyTicket' => $app->getAttr('ComponentVerifyTicket')]);
                break;

            case 'unauthorized':
                $account = wei()->wechatAccount()->find(['applicationId' => $app->getAttr('AuthorizerAppid')]);
                /** @var \Miaoxing\Wechat\Service\WechatAccount $account */
                if ($account) {
                    $account->save(['authed' => false]);
                } else {
                    $this->logger->info('取消授权但AppId不存在', $app->getAttrs());
                }
                break;

            case 'authorized':
                // 授权成功,无需处理
                break;

            default:
                $this->logger->warning('未识别事件', $app->getAttrs());
        }

        // 不调用$app->run(),避免返回默认信息
        return 'success';
    }

    /**
     * 为回复文字中的URL加上wechatOpenId和默认服务号授权地址
     *
     * @param string $text
     * @return string
     */
    protected function updateMessageUrl($text)
    {
        $text = preg_replace_callback('/<a(.*)href="([^"]*)"(.*)>/', function ($matches) {
            // Step1 附加WeChatOpenId参数, 并对WeChatOpenId进行签名
            $url = wei()->linkTo->generateInternalUrl($matches[2]);

            // Step2 获取默认的服务号,生成微信OpenID授权网页地址
            $account = wei()->wechatAccount->getCurrentAccount();
            if ($account->isVerifiedService()) {
                $url = wei()->linkTo->generateOauth2BaseUrl($url);
            }

            // Step3 替换原来的URL地址
            return str_replace($matches[2], $url, $matches[0]);
        }, $text);

        return $text;
    }

    /**
     * 将事件转换为类名格式
     *
     * @param string $event
     * @return string
     */
    protected function classify($event)
    {
        $event = strtolower($event);

        return str_replace(' ', '', ucwords(strtr($event, '_-', '  ')));
    }
}
