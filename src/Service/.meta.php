<?php

namespace Miaoxing\Wechat\Service;

use Wei\Ret;

class WechatApi
{
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
}

