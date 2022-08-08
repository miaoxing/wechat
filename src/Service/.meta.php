<?php

namespace Miaoxing\Wechat\Service;

use Wei\Ret;

class WechatApi
{
    /**
     * 获取凭证
     *
     * @param array{appid?: string, secret?: string} $data
     * @return Ret|array{access_token: string, expires_in: int}
     */
    public function getToken(array $data = []): Ret
    {
        return suc();
    }

    /**
     * 通过 OAuth2.0 的 code 获取网页授权 access_token
     *
     * @param array{code: string, appid?: string} $data
     * @return Ret|array{access_token: string, expires_in: int, refresh_token: string, openid: string, scope: string}
     */
    public function getSnsOAuth2AccessToken(array $data): Ret
    {
        return suc();
    }

    /**
     * @param array{access_token: string, openid: string, lang: string} $data
     * @return Ret|array{openid: string, nickname: string, sex: int, province: string, city: string, country: string, headimgurl: string, privilege: array<string>, unionid: string}
     */
    public function getSnsUserInfo(array $data): Ret
    {
        return suc();
    }

    /**
     * @param array{tag: array{name: string}} $data
     * @return Ret|array{tag?: array{id: int, name: string}, code: int, message: string, detail?: string}
     */
    public function createTag(array $data): Ret
    {
        return suc();
    }

    /**
     * @return Ret|array{tags?: array<array{id: int, name: string, count: int}>, code: int, message: string, detail?: string}
     */
    public function getTags(): Ret
    {
        return suc();
    }

    /**
     * @param array{tag: array{id: int, name: string}} $data
     * @return Ret
     */
    public function updateTag(array $data): Ret
    {
        return suc();
    }

    /**
     * @param array{tag: array{id: int}} $data
     * @return Ret
     */
    public function deleteTag(array $data): Ret
    {
        return suc();
    }

    /**
     * @param array{tagid: int, next_openid?: string} $data
     * @return Ret|array{count: int, data: array{openid: array<string>}, next_openid: string}
     */
    public function getTagUsers(array $data): Ret
    {
        return suc();
    }

    /**
     * @param array{openid_list: array<string>, tagid: int} $data
     * @return Ret
     */
    public function batchTaggingMembers(array $data): Ret
    {
        return suc();
    }

    /**
     * @param array{openid_list: array<string>, tagid: int} $data
     * @return Ret
     */
    public function batchUnTaggingMembers(array $data): Ret
    {
        return suc();
    }

    /**
     * @param array{openid: string} $data
     * @return Ret|array{tagid_list: array<int>}
     */
    public function getTagIdList(array $data): Ret
    {
        return suc();
    }

    /**
     * 获取用户列表
     *
     * @param array{next_openid?: string} $data
     * @return Ret|array{total: int, count: int, data: array{openid: string[]}, next_openid: string}
     */
    public function userGet(array $data = []): Ret
    {
        return suc();
    }

    /**
     * 获取用户基本信息
     *
     * @param array{openid: string, lang?: string} $data
     * @return Ret|array{}
     */
    public function userInfo(array $data): Ret
    {
        return suc();
    }

    /**
     * 获取小程序登录凭证校验
     *
     * @param array{js_code: string} $data
     * @return Ret|array{openid: string, session_key: string, unionid?: string, errcode: int, errmsg: string}
     */
    public function snsJsCode2Session(array $data): Ret
    {
        return suc();
    }

    /**
     * 发送订阅消息
     *
     * @param array{template_id: string, touser: string, miniprogram_state: string, data: array} $data
     * @return Ret|array{openid: string, session_key: string, unionid?: string, errcode: int, errmsg: string}
     */
    public function sendSubscribeMessage(array $data): Ret
    {
        return suc();
    }

    /**
     * 获取小程序码，永久有效，数量暂无限制
     *
     * @param array $data
     * @return Ret
     */
    public function getWxaCodeUnlimited(array $data): Ret
    {
        return suc();
    }

    /**
     * @param array{button:array<array{type:string, name:string, key:string, url:string, media_id:string, appid:string, pagepath: string, sub_button: array<array{type:string, name:string, key:string, url:string, media_id:string, appid:string, pagepath: string}>}>} $data
     * @return Ret
     */
    public function createMenu(array $data): Ret
    {
        return suc();
    }
}
