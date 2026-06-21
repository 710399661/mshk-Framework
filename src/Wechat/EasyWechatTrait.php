<?php

/**
 * Copyright (C) 2020 Tencent Cloud.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Discuz\Wechat;

use App\Common\CacheKey;
use App\Models\NotificationTpl;
use Illuminate\Support\Collection;

/**
 * Trait EasyWechatTrait
 *
 * @package Discuz\Wechat
 * @property EasyWechatManage
 * @method createOffiaccountDriver()
 * @method createMiniProgramDriver()
 */
trait EasyWechatTrait
{
    protected $easyWechatFactory;

    /**
     * @var Collection
     */
    protected static $miniProgramTemplates = null;

    private function getFactory()
    {
        return $this->easyWechatFactory ?? $this->easyWechatFactory = app('easyWechat');
    }

    /**
     * @param array $merge
     * @return \EasyWeChat\OfficialAccount\Application
     */
    public function offiaccount($merge = [])
    {
        return $this->getFactory()->service('offiaccount')->build($merge);
    }

    /**
     * @param array $merge
     * @return \EasyWeChat\MiniProgram\Application
     */
    public function miniProgram($merge = [])
    {
        return $this->getFactory()->service('miniProgram')->build($merge);
    }

    /*
    |--------------------------------------------------------------------------
    | 方法
    |--------------------------------------------------------------------------
    */

    protected function getMiniProgramKeys(NotificationTpl $item)
    {
        $app = $this->miniProgram();
        if (is_null($app)) {
            return [];
        }
        $this->cache = app('cache');

        /**
         * 由于小程序查询所有模板接口存在限制
         * 这里先判断缓存中是否已存在数据，减少查询次数
         */
        if (! $this->cache->has(CacheKey::NOTICE_MINI_PROGRAM_TEMPLATES)) {
            $response = $app->subscribe_message->getTemplates();
            if (! isset($response['errcode']) || $response['errcode'] != 0 || count($response['data']) == 0) {
                $errMsg = $response['errmsg'] ?? '';
                NotificationTpl::writeError($item, $response['errcode'], $errMsg, []);
                return [];
            }

            // set cache
            $collect = collect($response['data']);
            $this->cache->put(CacheKey::NOTICE_MINI_PROGRAM_TEMPLATES, $collect, 604800);
        }

        $templateCollect = $this->cache->get(CacheKey::NOTICE_MINI_PROGRAM_TEMPLATES);
        $template = $templateCollect->where('priTmplId', $item->template_id)->first();
        if (is_null($template)) {
            return [];
        }

        $content = $template['content'];
        $regex = '/{{(?<key>.*)\.DATA/';
        if (preg_match_all($regex, $content, $keys)) {
            return $keys['key'];
        }

        return [];
    }
}
