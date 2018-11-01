<?php

namespace Miaoxing\Wechat;

use Miaoxing\Article\Service\Article;
use Miaoxing\Plugin\BaseController;
use Miaoxing\Plugin\Service\User;
use Miaoxing\Wechat\Service\WechatAccount;
use Miaoxing\Wechat\Service\WeChatQrcode;
use Wei\WeChatApp;

class Plugin extends \Miaoxing\Plugin\BasePlugin
{
    protected $name = '微信公众平台';

    protected $description = '包括公众号,回复,自定义菜单等';

    protected $adminNavId = 'wechat';

    public function onAdminNavGetNavs(&$navs, &$categories, &$subCategories)
    {
        $categories['wechat'] = [
            'name' => '微信',
            'sort' => 700,
        ];

        $subCategories['wechat-account'] = [
            'parentId' => 'wechat',
            'name' => '公众号',
            'icon' => 'fa fa-wechat',
            'sort' => 1000,
        ];

        $subCategories['wechat-stat'] = [
            'parentId' => 'wechat',
            'name' => '统计',
            'icon' => 'fa fa-bar-chart',
            'sort' => 500,
        ];

        $navs[] = [
            'parentId' => 'wechat-account',
            'url' => 'admin/wechat-account',
            'name' => '公众号管理',
            'sort' => 1000,
        ];

        // TODO 拆分wechat-reply插件
        if (wei()->wechatAccount->getCurrentAccount()['type'] != WechatAccount::TYPE_WXA) {
            $navs[] = [
                'parentId' => 'wechat-account',
                'url' => 'admin/wechat-reply/index',
                'name' => '回复管理',
                'sort' => 900,
            ];
        }

        $subCategories['wechat-setting'] = [
            'parentId' => 'wechat',
            'name' => '设置',
            'icon' => 'fa fa-gear',
            'sort' => 0,
        ];

        $navs[] = [
            'parentId' => 'wechat-setting',
            'url' => 'admin/wechat-settings',
            'name' => '功能设置',
            'sort' => 0,
        ];
    }

    public function onLinkToGetLinks(&$links, &$types, &$decorators)
    {
        $types['keyword'] = [
            'name' => '关键字',
            'input' => 'text',
            'sort' => 1100,
            'placeholder' => '请输入微信回复的关键字',
        ];

        $types['miniProgram'] = [
            'name' => '小程序',
            'input' => 'text',
            'sort' => 1100,
            'placeholder' => '小程序appid+路径,如:wx123+/pages/index/index',
        ];

        $decorators['oauth2Base'] = [
            'name' => '微信OpenID授权',
        ];

        // 暂不支持
        /*
            $decorators['oauth2UserInfo'] = [
            'name' => '微信用户信息授权',
        ];*/
    }

    public function onPreControllerInit(BaseController $controller)
    {
        $controller->middleware(\Miaoxing\Wechat\Middleware\Auth::class);
    }

    public function onUserGetPlatform($platforms)
    {
        $platforms[] = [
            'name' => '微信',
            'value' => 'wechat',
        ];
    }

    public function onPreUserSearch(User $users, $req)
    {
        if ($req['platform'] == 'wechat') {
            $users->andWhere("wechatOpenId != ''");
        }
    }

    public function onBodyStart()
    {
        if ($this->app->getControllerAction() != 'index/index') {
            return;
        }
        $this->displayShareImage();
    }

    public function displayShareImage()
    {
        if ($shareImage = wei()->setting('wechat.shareImage')) {
            $this->event->trigger('postImageLoad', [&$shareImage]);
            $this->view->display('@wechat/wechat/bodyStart.php', get_defined_vars());
        }
    }

    public function onWechatSubscribe(WeChatApp $app)
    {
    }

    /**
     * 扫描二维码关注后的操作
     *
     * @param WeChatApp $app
     * @param User $user
     */
    public function onWechatScan(WeChatApp $app, User $user)
    {
        $sceneId = $app->getScanSceneId();
        if (!$sceneId) {
            return;
        }

        /** @var WeChatQrcode $qrcode */
        $qrcode = wei()->weChatQrcode->findAndCacheBySceneId($sceneId);
        if (!$qrcode || !$qrcode->hasReply()) {
            return;
        }

        $app->subscribe(function (WeChatApp $app) use ($user, $qrcode) {
            wei()->weChatReply->updateSubscribeUser($app, $user);

            // 扫码的关注回复
            if ($qrcode['type'] == 'text') {
                if ($qrcode['content']) {
                    return $qrcode['content'];
                }
            } elseif ($qrcode['articleIds']) {
                return $app->sendArticle($qrcode->toArticleArray());
            }

            // 关注回复
            $reply = wei()->weChatReply();
            if ($reply->findByIdFromCache('subscribe')) {
                return $reply->send($app, '{关注顺序}', $user['id']);
            }
        });
    }

    public function onArticlesShowGetHeadImg()
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        if ($account['headImg']) {
            return $account['headImg'];
        }
    }

    public function onAfterArticlesShowRender(Article $article)
    {
        if (!$article->isSafe()) {
            return;
        }
        $this->display();
    }

    public function onScript()
    {
        $this->display();
    }
}
