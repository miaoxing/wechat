<?php

namespace Miaoxing\Wechat\Service;

use Miaoxing\Article\Service\Article;
use Miaoxing\Award\Service\Award;
use Miaoxing\Plugin\BaseModel;
use Miaoxing\Plugin\Service\User;
use Wei\WeChatApp;

class WeChatQrcode extends BaseModel
{
    /**
     * 扫描就可以获得奖励
     */
    const AWARD_RULE_ANY_SCAN = 0;

    /**
     * 首次关注才可以获得奖励
     */
    const AWARD_RULE_FIRST_SUBSCRIPTION = 1;

    /**
     * {@inheritdoc}
     */
    protected $enableTrash = true;

    /**
     * 扫描二维码对应的奖励
     *
     * @var Award
     */
    protected $award;

    /**
     * @var array
     */
    protected $sceneCache = [];

    protected $types = [
        0 => '全部',
        1 => '有关联用户',
        2 => '无关联用户',
    ];

    /**
     * {@inheritdoc}
     */
    protected $table = 'weChatQrcode';

    /**
     * {@inheritdoc}
     */
    protected $data = [
        'type' => 'text',
        'awardRule' => 0,
        'articleIds' => [],
    ];

    /**
     * @var Article[]|Article
     */
    protected $articles;

    public function getTypeToOption($selected = 0)
    {
        $html = '';
        foreach ($this->types as $key => $value) {
            $html .= '<option value="' . $key . '" ' . ($key == $selected ? 'selected' : '') . '>' . $value . '</option>';
        }

        return $html;
    }

    /**
     * Record|Repo: 获取当前/指定账号的下一个场景ID
     *
     * @return int
     */
    public function getNextSceneId()
    {
        return wei()->weChatQrcode()
                ->select('MAX(CAST(sceneId AS UNSIGNED))')
                ->andWhere('sceneId > 0')
                ->fetchColumn() + 1;
    }

    /**
     * Record: 获取二维码的奖励
     *
     * @return \Miaoxing\Award\Service\Award
     */
    public function getAward()
    {
        $this->award || $this->award = wei()->award()->findOrInitById($this['awardId']);

        return $this->award;
    }

    /**
     * Record: 为用户创建专属二维码
     *
     * @param \Miaoxing\Plugin\Service\User $user
     * @param string $name
     * @return $this
     */
    public function findOrCreateByUser(User $user, $name = '用户的二维码')
    {
        $this->findOrInit(['userId' => $user['id']]);
        if ($this->isNew()) {
            $this->save([
                'name' => $name,
                'sceneId' => $this->getNextSceneId(),
            ]);
        }

        return $this;
    }

    /**
     * 创建默认的二维码
     * @param string $name
     * @return $this
     */
    public function createDefault($name = '默认的二维码')
    {
        $this->findOrInit([
            'name' => $name,
            'sceneId' => $this->getNextSceneId(),
        ]);
        $this->save();

        return $this;
    }

    public function getUser()
    {
        $user = wei()->user()->findOrInitById($this['userId']);

        return $user->isNew() ? null : $user;
    }

    /**
     * Record: 获取生成二维码时的文件名称
     *
     * @return string
     */
    public function getFileName()
    {
        return $this['sceneId'];
    }

    /**
     * Repo: 生成二维码图片
     *
     * @param WeChatQrcode $weChatQrcode
     * @return resource
     */
    public function createImage(WeChatQrcode $weChatQrcode)
    {
        $account = wei()->wechatAccount->getCurrentAccount();
        $api = $account->createApiService();
        $url = $api->getPermanentQrCodeUrl($weChatQrcode['sceneId']);

        // 1. 获取微信二维码图片内容
        $qrcodeRes = $this->createImageFromUrl($url);

        // 2. 附加头像到中间
        if ($account && $account['headImg']) {
            $headRes = $this->createImageFromUrl($account['headImg']);

            // 缩放到90px
            $headRes = $this->resize($headRes, 90);

            $qrcodeRes = $this->insertCenter($qrcodeRes, $headRes);
            imagedestroy($headRes);
        }

        return $qrcodeRes;
    }

    /**
     * 展示二维码图片
     *
     * @param WeChatQrcode $weChatQrcode
     */
    public function displayImage(WeChatQrcode $weChatQrcode)
    {
        $qrcodeRes = $this->createImage($weChatQrcode);
        header('Content-type: image/jpeg');
        imagejpeg($qrcodeRes);
    }

    /**
     * 从URL地址中创建图片资源
     *
     * @param string $url
     * @return resource
     */
    public function createImageFromUrl($url)
    {
        $file = wei()->file->downloadOnDemand($url);
        $file = file_get_contents($file);

        return imagecreatefromstring($file);
    }

    /**
     * 将图片插入到指定图片的中心
     *
     * @param resource $dst
     * @param resource $src
     * @return resource
     */
    public function insertCenter($dst, $src)
    {
        $dstSize = imagesx($dst);
        $srcSize = imagesx($src);
        $srcPosition = ($dstSize - $srcSize) / 2;

        imagecopy($dst, $src, $srcPosition, $srcPosition, 0, 0, $srcSize, $srcSize);

        return $dst;
    }

    /**
     * 缩放图片到自定大小
     *
     * @param resource $img
     * @param int $size
     * @return resource
     */
    public function resize($img, $size)
    {
        $curSize = imagesx($img);
        if ($curSize == $size) {
            return $img;
        }

        $newImg = imagecreatetruecolor($size, $size);
        imagecopyresampled($newImg, $img, 0, 0, 0, 0, $size, $size, $curSize, $curSize);

        return $newImg;
    }

    public function getArticles()
    {
        if (!$this->articles) {
            $this->articles = wei()->article()->beColl();
            if ($this['articleIds']) {
                // 按原来的顺序排列
                $this->articles->orderBy('FIELD(id, ' . implode(', ',
                        $this['articleIds']) . ')')->findAll(['id' => $this['articleIds']]);
            }
        }

        return $this->articles;
    }

    public function toArticleArray()
    {
        $wei = $this->wei;
        $articles = [];
        foreach ($this->getArticles() as $article) {
            // 上传的封面图片,不以http开头
            if (!$wei->isStartsWith($article['thumb'], 'http')) {
                $article['thumb'] = $wei->request->getUrlFor($article['thumb']);
            }

            $articles[] = [
                'title' => $article['title'],
                'description' => $this->getArticles()->length() > 1 ? '' : $article['intro'],
                'picUrl' => $article['thumb'],
                'url' => $article->getUrlWithDecorator(),
            ];
        }

        return $articles;
    }

    public function toArrayWithArticles()
    {
        $data = $this->toArray();
        $data['articles'] = $this->getArticles()->toArray();

        return $data;
    }

    public function beforeSave()
    {
        parent::beforeSave();
        $this['articleIds'] = json_encode($this['articleIds']);
    }

    public function afterFind()
    {
        parent::afterFind();
        $this['articleIds'] = (array) json_decode($this['articleIds'], true);
    }

    public function hasReply()
    {
        return $this['articleIds'] || $this['content'];
    }

    public function generateReply(WeChatApp $app)
    {
        if ($this['type'] == 'text') {
            if ($this['content']) {
                return $this['content'];
            }
        } elseif ($this['articleIds']) {
            return $app->sendArticle($this->toArticleArray());
        }
        return false;
    }

    public function findAndCacheBySceneId($sceneId)
    {
        if (!isset($this->sceneCache[$sceneId])) {
            $this->sceneCache[$sceneId] = wei()->weChatQrcode()->find(['sceneId' => $sceneId]);
        }

        return $this->sceneCache[$sceneId];
    }
}
