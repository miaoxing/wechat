<?php

namespace Miaoxing\Wechat\Controller\Admin;

class WechatComponent extends \miaoxing\plugin\BaseController
{
    public function authAction()
    {
        // 1. 生成预授权码
        $api = wei()->wechatAccount->getCurrentAccount()->createComponentApiService();
        $ret = $api->createPreAuthCode();
        if ($ret['code'] !== 1) {
            return $this->err($ret);
        }

        // 2. 跳转到登录地址
        $url = $api->getLoginUrl($ret['pre_auth_code'], $this->url->full('admin/wechat-component/ret'));
        return $this->response->redirect($url);
    }

    public function retAction($req)
    {
        // 1. 校验授权码
        if (!$req['auth_code']) {
            return $this->err('授权码不能为空');
        }

        // 2. 使用授权码换取公众号的授权信息
        $authCode = $req['auth_code'];
        $api = wei()->wechatAccount->getCurrentAccount()->createComponentApiService();
        $authRet = $api->queryAuth($authCode);
        if ($authRet['code'] !== 1) {
            return $this->err($authRet);
        }

        // 3. 检查授权和原来的公众号是否为同一个
        $authInfo = $authRet['authorization_info'];
        $appId = $authInfo['authorizer_appid'];
        $account = wei()->wechatAccount->getCurrentAccount();
        if ($account['applicationId'] && $account['applicationId'] != $appId) {
            return $this->err('绑定失败,授权公众号与原公众号不一致');
        }

        // 4. 存储授权方令牌
        $api->setAuthorizerAccessTokenFromAuth($appId, $authInfo);

        // 5. 同步授权方的账户信息
        $ret = $api->getAuthorizerInfo($authInfo['authorizer_appid']);
        if ($ret['code'] !== 1) {
            return $this->err($ret);
        }

        // 授权方公众号类型，0代表订阅号，1代表由历史老帐号升级后的订阅号，2代表服务号
        // 本地,1表示订阅号,2表示服务号
        $type = $ret['authorizer_info']['service_type_info']['id'] == 2 ? 2: 1;

        // 授权方认证类型，-1代表未认证，0代表微信认证，1代表新浪微博认证，2代表腾讯微博认证，3代表已资质认证通过但还未通过名称认证，4代表已资质认证通过、还未通过名称认证，但通过了新浪微博认证，5代表已资质认证通过、还未通过名称认证，但通过了腾讯微博认证
        $verified = $ret['authorizer_info']['verify_type_info']['id'] == -1 ? 0 : 1;

        $account->save([
            'type' => $type,
            'authed' => true,
            'verified' => $verified,
            'nickName' => $ret['authorizer_info']['nick_name'],
            'headImg' => $ret['authorizer_info']['head_img'],
            'sourceId' => $ret['authorizer_info']['user_name'],
            'weChatId' => $ret['authorizer_info']['alias'],
            'qrcodeUrl' => $ret['authorizer_info']['qrcode_url'],
            'applicationId' => $authInfo['authorizer_appid'],
            'refreshToken' => $authInfo['authorizer_refresh_token'],
            'funcInfo' => json_encode($authInfo['func_info'], JSON_UNESCAPED_SLASHES),
            'businessInfo' => json_encode($ret['authorizer_info']['business_info'], JSON_UNESCAPED_SLASHES),
        ]);

        return $this->suc('授权成功');
    }
}
