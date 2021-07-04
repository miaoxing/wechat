<?php

namespace Miaoxing\Wechat\Service;

use Miaoxing\Plugin\BaseModel;
use Miaoxing\Plugin\Model\HasAppIdTrait;
use Miaoxing\Plugin\Model\ModelTrait;
use Miaoxing\User\Service\UserModel;
use Miaoxing\Wechat\Metadata\WechatUserTrait;

/**
 * @property UserModel $user
 */
class WechatUserModel extends BaseModel
{
    use ModelTrait;
    use HasAppIdTrait;
    use WechatUserTrait;

    public function user(): UserModel
    {
        return $this->belongsTo(UserModel::class);
    }
}
