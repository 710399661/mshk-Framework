<?php

/**
 * Copyright (C) 2020 Tencent Cloud.
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *   http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Discuz\Notifications\Traits;

use App\Models\NotificationTpl;
use Carbon\Carbon;
use Discuz\Http\UrlGenerator;

trait VariableTemplateTrait
{
    /**
     * @var array 模板变量数据
     */
    protected $templateData = [];

    /**
     * 设置模板变量
     * (可在该方法赋予全局默认字段)
     *
     * @param array $build
     */
    protected function setTemplateData(array $build)
    {
        $defaultData = [
            '{$notify_time}' => Carbon::now()->toDateTimeString(),
            '{$site_domain}' => app(UrlGenerator::class)->to(''),
        ];

        $this->templateData = array_merge($build, $defaultData);
    }

    /**
     * 构建模板数组
     *
     * @param array $expand 扩展数组
     * @return array
     */
    public function compiledArray($expand = [])
    {
        $build = [];

        if ($this->firstData->type == NotificationTpl::WECHAT_NOTICE) {
            // first_data
            if (! empty($this->firstData->first_data)) {
                $build['first'] = $this->matchRegular($this->firstData->first_data);
            }

            // remark_data
            if (! empty($this->firstData->remark_data)) {
                $build['remark'] = $this->matchRegular($this->firstData->remark_data);
            }

            // color
            $build['color'] = $this->firstData->color;

            // redirect_url
            if (! empty($this->firstData->redirect_url)) {
                $redirectUrl = $this->matchRegular($this->firstData->redirect_url);
            } else {
                $redirectUrl = $expand['redirect_url'] ?? '';
            }
            $build['redirect_url'] = $redirectUrl;
        }

        if (in_array($this->firstData->type, [NotificationTpl::WECHAT_NOTICE, NotificationTpl::MINI_PROGRAM_NOTICE])) {
            // page_path
            if (! empty($this->firstData->page_path)) {
                $this->firstData->page_path = $this->matchRegular($this->firstData->page_path);
            }
        }

        // keywords_data
        if (! empty($this->firstData->keywords_data)) {
            $keywords = explode(',', $this->firstData->keywords_data);
            $this->matchKeywords($keywords, $build); // &$build
        }

        return $build;
    }

    /**
     * 替换数据中心
     *
     * @param string $target 字符串
     * @param string $replace 替换值
     * @return string
     */
    protected function matchReplace(string $target, $replace = '')
    {
        $replace = $replace ?: $target;

        if (isset($this->templateData[$replace])) {
            $target = str_replace($replace, $this->templateData[$replace], $target);
        }

        return $target;
    }

    /**
     * 模板变量替换值
     *
     * @param string $target 目标字符串
     * @return string
     */
    protected function matchRegular(string $target)
    {
        if (preg_match_all('/{\$\w+}/i', $target, $match)) {
            foreach (array_shift($match) as $item) {
                $target = $this->matchReplace($target, $item);
            }
        }

        return $target;
    }

    /**
     * 顺序合并替换
     *
     * @param array $target 目标数组
     * @param $build
     */
    protected function matchKeywords(array $target, &$build)
    {
        $keywords = [];
        $i = 1;
        foreach ($target as $item) {
            $item = $this->matchRegular($item);
            $key = 'keyword' . $i;
            // Tag 按顺序合入数组
            $keywords = array_merge($keywords, [$key => $item]);
            $i++;
        }

        // 短信数据 keyword 合并在一起
        if ($this->firstData->type == NotificationTpl::SMS_NOTICE) {
            $build['keywords'] = $keywords;
        } else {
            $build = array_merge($build, $keywords);
        }
    }
}
