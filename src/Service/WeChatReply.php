<?php

namespace Miaoxing\Wechat\Service;

use Miaoxing\Article\Service\Article;
use Miaoxing\Plugin\Service\User;
use Wei\WeChatApp;

/**
 * @property \Wei\BaseCache $cache
 */
class WeChatReply extends \Miaoxing\Plugin\BaseModel
{
    const EXACT_MATCH = 1;

    const PARTIAL_MATCH = 2;

    protected $autoId = true;

    protected $enableTrash = true;

    protected $matchTypeNames = [
        0 => '无',
        1 => '完全匹配',
        2 => '部分匹配',
    ];

    protected $data = [
        'type' => 'text',
    ];

    protected $reservedIds = ['subscribe', 'default', 'phone', 'scan'];

    /**
     * @var Article[]|Article
     */
    protected $articles;

    public function getMatchTypeName()
    {
        return $this->matchTypeNames[$this['matchType']];
    }

    protected function removeCaches()
    {
        $namespace = wei()->app->getNamespace();
        $this->cache->remove($namespace . ':weChatReplyKeywordList');
        $this->cache->remove($namespace . ':weChatReplySceneKeywords');
        $this->cache->remove($namespace . ':weChatReplyId' . $this['id']);
    }

    public function beforeSave()
    {
        parent::beforeSave();
        $this['articleIds'] = json_encode($this['articleIds']);
    }

    public function afterSave()
    {
        parent::afterSave();
        $this->removeCaches();
    }

    public function afterDestroy()
    {
        parent::afterDestroy();
        $this->removeCaches();
    }

    public function afterFind()
    {
        parent::afterFind();
        $this['articleIds'] = (array) json_decode($this['articleIds'], true);
    }

    public function getArticles()
    {
        if (!$this->articles) {
            $this->articles = wei()->article()->beColl();
            if ($this['articleIds']) {
                // 按原来的顺序排列
                $this->articles->orderBy('FIELD(id, ' . implode(', ', $this['articleIds']) . ')')->findAll(['id' => $this['articleIds']]);
            }
        }

        return $this->articles;
    }

    public function toJsonWithArticles()
    {
        return json_encode($this->toArrayWithArticles());
    }

    public function toArrayWithArticles()
    {
        $data = $this->toArray();
        $data['articles'] = $this->getArticles()->toArray();

        return $data;
    }

    public function isReserved()
    {
        return in_array($this['id'], $this->reservedIds);
    }

    /**
     * 判断是否为默认回复
     *
     * @return bool
     */
    public function isDefault()
    {
        return $this['id'] == 'default';
    }

    /**
     * 判断当前回复是否来自关键字
     *
     * @return bool
     */
    public function isFromKeyword()
    {
        // 未加载,说明未匹配到任何关键字
        if (!$this->loaded) {
            return false;
        }

        // 已加载,但id为空,说明未匹配到任何关键字
        if ($this->loaded && !$this['id']) {
            return false;
        }

        // 如果是默认回复,说明未匹配到任何关键字
        if ($this['id'] == 'default') {
            return false;
        }

        return true;
    }

    public function notReserved()
    {
        $placeholders = array_fill(0, count($this->reservedIds), '?');

        return $this->andWhere('id NOT IN(' . implode(', ', $placeholders) . ')', $this->reservedIds);
    }

    /**
     * 获取关键字列表,根据匹配类型区分
     *
     * @return array
     */
    public function getKeywordList()
    {
        $data = [static::EXACT_MATCH => [], static::PARTIAL_MATCH => []];

        $replies = $this->__invoke()->select('id, keywords, matchType')->notDeleted()->notReserved()->fetchAll();

        foreach ($replies as $reply) {
            $keywords = explode(' ', $reply['keywords']);
            foreach ($keywords as $keyword) {
                $data[$reply['matchType']][strtolower($keyword)] = $reply['id'];
            }
        }

        return $data;
    }

    /**
     * 从缓存获取关键字列表
     *
     * @return array
     */
    public function getKeywordListFromCache()
    {
        $namespace = wei()->app->getNamespace();

        return $this->cache->get($namespace . ':weChatReplyKeywordList', [$this, 'getKeywordList']);
    }

    /**
     * 根据提供的关键词搜索匹配的回复
     *
     * @param string $keyword
     * @return bool
     */
    public function findByKeyword($keyword)
    {
        if ($keyword !== false) {
            $data = $this->getKeywordListFromCache();

            // 如果有完全匹配,直接返回
            if (isset($data[static::EXACT_MATCH][$keyword])) {
                return $this->findByIdFromCache($data[static::EXACT_MATCH][$keyword]);
            }

            // 判断是否有部分匹配
            foreach ($data[static::PARTIAL_MATCH] as $partialKeyword => $id) {
                if (strpos($keyword, (string) $partialKeyword) !== false) {
                    return $this->findByIdFromCache($id);
                }
            }
        }

        return '';
    }

    /**
     * 默认回复
     * @return bool
     */
    public function findByDefault()
    {
        return $this->findByIdFromCache('default');
    }

    /**
     * 从缓存获取指定编号的回复
     *
     * @param string $id
     * @return false|WeChatReply
     */
    public function findByIdFromCache($id)
    {
        // 从缓存中拉取数据
        $namespace = wei()->app->getNamespace();
        $data = $this->cache->get($namespace . ':weChatReplyId' . $id, function () use ($id) {
            $reply = $this->findById($id);

            return $reply ? $reply->toArray() : false;
        });

        // 将数据存到记录对象中
        if ($data) {
            $this->isNew = false;
            $this->fromArray($data);

            return $this;
        } else {
            return false;
        }
    }

    /**
     * 从缓存获取所有场景关键字
     *
     * @return array 键名为关键字,值为回复编号
     */
    public function getSceneKeywordsFromCache()
    {
        $namespace = wei()->app->getNamespace();

        return $this->cache->get($namespace . ':weChatReplySceneKeywords', function () {
            $data = [];
            foreach ($this->findAll(['id' => $this->reservedIds]) as $reply) {
                $data[$reply['id']] = $reply['keywords'];
            }

            return $data;
        });
    }

    /**
     * 根据回复的类型,和提供的WeChatApp对象,生成消息并调用回复方法
     *
     * @param WeChatApp $app
     * @param array $search
     * @param array $replace
     * @return array
     */
    public function send(WeChatApp $app, $search = [], $replace = [])
    {
        if ($this['type'] == 'text') {
            if ($search) {
                $this['content'] = str_replace($search, $replace, $this['content']);
            }
            // 直接返回content字段,由weChatApp服务构造XML.如果内容为空,weChatApp会自动输入空字符串,这样符合微信的要求
            return $this['content'];
        } else {
            return $app->sendArticle($this->toArticleArray($search, $replace));
        }
    }

    /**
     * 生成WeChatApp要求的图文数组
     *
     * @param array $search
     * @param array $replace
     * @return array
     */
    protected function toArticleArray($search = [], $replace = [])
    {
        return $this->generateReplyArticles($this->getArticles(), $search, $replace);
    }

    /**
     * 生成回复的图文数组
     *
     * @param Article|Article[] $articles
     * @param array $search
     * @param array $replace
     * @return array
     */
    public function generateReplyArticles(Article $articles, $search = [], $replace = [])
    {
        $wei = $this->wei;
        $data = [];
        foreach ($articles as $article) {
            // 如果指定了关键词,替换描述内容里的关键词
            if ($search) {
                $article['title'] = str_replace($search, $replace, $article['title']);
                $article['intro'] = str_replace($search, $replace, $article['intro']);
            }

            // 上传的封面图片,不以http开头
            if (!$wei->isStartsWith($article['thumb'], 'http')) {
                $article['thumb'] = $wei->request->getUrlFor($article['thumb']);
            }

            $data[] = [
                'title' => $article['title'],
                'description' => $articles->length() > 1 ? '' : $article['intro'],
                'picUrl' => $article['thumb'],
                'url' => $article->getUrlWithDecorator(),
            ];
        }

        return $data;
    }

    /**
     * 更新用户的关注状态
     *
     * @param WeChatApp $app
     * @param User $user
     */
    public function updateSubscribeUser(WeChatApp $app, User $user)
    {
        // 将重新关注的用户置为有效
        if (!$user['isValid']) {
            $user['isValid'] = true;
        }

        if ($sceneId = $app->getScanSceneId()) {
            $user['source'] = $sceneId;
        }

        if ($user->isChanged()) {
            $user->save();
        }
    }
}
