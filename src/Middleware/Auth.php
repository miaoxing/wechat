<?php

namespace Miaoxing\Wechat\Middleware;

use Wei\Request;

/**
 * @property \Wei\Session $session
 * @property \Wei\Ret $ret
 * @property \Miaoxing\App\Service\Logger $logger
 */
class Auth extends \Miaoxing\Plugin\Middleware\Base
{
    /**
     * {@inheritdoc}
     *
     * 根据URL请求参数初始化微信OpenID
     */
    public function __invoke($next)
    {
        $req = $this->request;

        $res = $this->initUserByWechatOpenId($req);
        if ($res) {
            return $res;
        }

        $res = $this->initUserByWechatOAuth2Code($req);
        if ($res) {
            return $res;
        }

        // 获取完用户登录态后,如果存在wechatOpenId或OAuth2.0的code参数,做次跳转,移除该参数
        if (isset($req['wechatOpenId']) || isset($req['code'])) {
            // TODO 移除逻辑,放到safeUrl中
            $removeKeys = ['wechatOpenId', 'timestamp', 'flag', 'code', 'state'];
            $queries = array_diff_key($req->getParameterReference('get'), array_flip($removeKeys));
            $newUrl = $req->getUrlFor($req->getBaseUrl() . $req->getPathInfo());
            if ($queries) {
                $newUrl .= '?' . http_build_query($queries);
            }
            return $this->response->redirect($newUrl);
        }

        // 如果未登录,在微信浏览器中,且有认证服务号,跳转到OAuth2.0地址获取用户登录态
        if (!wei()->curUser['id'] && wei()->ua->isWeChat()) {
            $account = wei()->wechatAccount->getCurrentAccount();
            if ($account->isVerifiedService()) {
                $url = $this->getRedirectUrl();
                return $this->response->redirect($url);
            }
        }

        return $next();
    }

    /**
     * 根据URL中的wechatOpenId,签名等参数,初始化微信用户
     *
     * @param Request $req
     * @return void|\Wei\Response
     */
    protected function initUserByWechatOpenId(Request $req)
    {
        // 1. URL中带有微信OpenID才做检查
        if (!isset($req['wechatOpenId'])) {
            return false;
        }

        // 2. 签名校验失败,提示错误信息
        if (!wei()->safeUrl->verify('wechatOpenId')) {
            // safeUrl校验出错,如超时,让用户重新获取新的地址
            return $this->ret->err('很抱歉,微信登录参数有误,请返回微信重新操作');
        }

        // 3. 如果URL通过验证,检查用户存在才设置登录态
        $user = wei()->user()->find(['wechatOpenId' => $req['wechatOpenId']]);
        if ($user) {
            wei()->curUser->loginByRecord($user);
        }
        // 用户不存在的话,忽略
    }

    /**
     * 通过微信OAuth2的code参数,初始化用户
     *
     * @param Request $req
     * @return false|\Wei\Response
     */
    protected function initUserByWechatOAuth2Code(Request $req)
    {
        // 1. 获取OAuth请求的code
        $code = $this->getOAuthCode();
        if (!$code) {
            return false;
        }

        // 2. 通过OAuth2.0的code,获取用户OpenID
        // TODO 允许失败不告警,重试完仍然失败才告警
        $api = wei()->wechatAccount->getCurrentAccount()->createApiService();
        $ret = $api->getOAuth2AccessTokenByAuth(['code' => $req['code']]);

        // 3. 如果错误是invalid code,且还有重试次数,重新跳转获得新的code
        if ($ret['errcode'] == 40029 && $retries = $this->getRetries()) {
            $url = $this->getRedirectUrl(['wechatRetries' => $retries - 1]);
            return $this->response->redirect($url);
        }

        // 4. 重试后还是失败,返回提醒给用户
        if (!isset($ret['openid'])) {
            return $this->ret->err('很抱歉,微信授权失败,请返回再试');
        }

        // 5. 重试成功,做个日志记录
        if (isset($ret['openid']) && isset($req['wechatRetries'])) {
            $this->logger->warning('微信获取Code重试成功', [
                'leftWechatRetries' => $this->getRetries(),
            ]);
        }

        // 6. 获取OpenID成功,创建或获取用户,并设置登录态
        wei()->curUser->loginBy(['wechatOpenId' => $ret['openid']]);
        $this->session->set([
            'accessToken' => $ret['access_token'], // 用于微信地址
            'scope' => $ret['scope'],
            'code' => $req['code'],
            'state' => $req['state'],
        ]);
    }

    /**
     * 获取OAuth请求的code
     *
     * @return false|string
     */
    protected function getOAuthCode()
    {
        // 1. 如果请求不带code,不再处理
        if (!$this->request['code']) {
            return false;
        }

        // 2. 检查是否为认证服务号
        $account = wei()->wechatAccount->getCurrentAccount();
        if (!$account->isVerifiedService()) {
            $this->logger->info('Got OAuth code, but wechat account is not verified service');
            return false;
        }

        // 3. 如果code已经处理过,不再处理
        if (!wei()->cache->add('wechatOAuth2Code' . $this->request['code'], true, 86400 * 3)) {
            return false;
        }

        return $this->request['code'];
    }

    /**
     * @param array $params
     * @return string
     */
    protected function getRedirectUrl(array $params = [])
    {
        return wei()->linkTo->getFullUrl([
            'value' => wei()->url->append($this->request->getUrl(), $params),
            'decorator' => 'oauth2Base'
        ]);
    }

    /**
     * 获取重试次数
     *
     * @return int
     */
    protected function getRetries()
    {
        return isset($this->request['wechatRetries']) ? (int)$this->request['wechatRetries'] : 2;
    }
}
