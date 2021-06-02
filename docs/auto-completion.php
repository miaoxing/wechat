<?php

/**
 * @property    Miaoxing\Wechat\Service\SafeUrl $safeUrl
 * @method      mixed safeUrl($url, $keys = [])
 */
class SafeUrlMixin {
}

/**
 * @property    Miaoxing\Wechat\Service\WeChatMenu $weChatMenu
 */
class WeChatMenuMixin {
}

/**
 * @property    Miaoxing\Wechat\Service\WeChatQrcode $weChatQrcode
 */
class WeChatQrcodeMixin {
}

/**
 * @property    Miaoxing\Wechat\Service\WeChatReply $weChatReply
 */
class WeChatReplyMixin {
}

/**
 * @property    Miaoxing\Wechat\Service\WechatAccount $wechatAccount
 */
class WechatAccountMixin {
}

/**
 * @property    Miaoxing\Wechat\Service\WechatAccountModel $wechatAccountModel
 * @method      Miaoxing\Wechat\Service\WechatAccountModel wechatAccountModel() 返回当前对象
 */
class WechatAccountModelMixin {
}

/**
 * @property    Miaoxing\Wechat\Service\WechatApi $wechatApi
 */
class WechatApiMixin {
}

/**
 * @property    Miaoxing\Wechat\Service\WechatComponentApi $wechatComponentApi 公众号第三方平台服务
 */
class WechatComponentApiMixin {
}

/**
 * @property    Miaoxing\Wechat\Service\WechatTemplate $wechatTemplate 微信模板消息
 * @method      Miaoxing\Wechat\Service\WechatTemplate wechatTemplate() 创建一个模板消息对象
 */
class WechatTemplateMixin {
}

/**
 * @property    Miaoxing\Wechat\Service\WechatUserModel $wechatUserModel
 * @method      Miaoxing\Wechat\Service\WechatUserModel wechatUserModel() 返回当前对象
 */
class WechatUserModelMixin {
}

/**
 * @mixin SafeUrlMixin
 * @mixin WeChatMenuMixin
 * @mixin WeChatQrcodeMixin
 * @mixin WeChatReplyMixin
 * @mixin WechatAccountMixin
 * @mixin WechatAccountModelMixin
 * @mixin WechatApiMixin
 * @mixin WechatComponentApiMixin
 * @mixin WechatTemplateMixin
 * @mixin WechatUserModelMixin
 */
class AutoCompletion {
}

/**
 * @return AutoCompletion
 */
function wei()
{
    return new AutoCompletion;
}

/** @var Miaoxing\Wechat\Service\SafeUrl $safeUrl */
$safeUrl = wei()->safeUrl;

/** @var Miaoxing\Wechat\Service\WeChatMenu $weChatMenu */
$weChatMenu = wei()->weChatMenu;

/** @var Miaoxing\Wechat\Service\WeChatQrcode $weChatQrcode */
$weChatQrcode = wei()->weChatQrcode;

/** @var Miaoxing\Wechat\Service\WeChatReply $weChatReply */
$weChatReply = wei()->weChatReply;

/** @var Miaoxing\Wechat\Service\WechatAccount $wechatAccount */
$wechatAccount = wei()->wechatAccount;

/** @var Miaoxing\Wechat\Service\WechatAccountModel $wechatAccount */
$wechatAccount = wei()->wechatAccountModel;

/** @var Miaoxing\Wechat\Service\WechatAccountModel|Miaoxing\Wechat\Service\WechatAccountModel[] $wechatAccounts */
$wechatAccounts = wei()->wechatAccountModel();

/** @var Miaoxing\Wechat\Service\WechatApi $wechatApi */
$wechatApi = wei()->wechatApi;

/** @var Miaoxing\Wechat\Service\WechatComponentApi $wechatComponentApi */
$wechatComponentApi = wei()->wechatComponentApi;

/** @var Miaoxing\Wechat\Service\WechatTemplate $wechatTemplate */
$wechatTemplate = wei()->wechatTemplate;

/** @var Miaoxing\Wechat\Service\WechatUserModel $wechatUser */
$wechatUser = wei()->wechatUserModel;

/** @var Miaoxing\Wechat\Service\WechatUserModel|Miaoxing\Wechat\Service\WechatUserModel[] $wechatUsers */
$wechatUsers = wei()->wechatUserModel();
