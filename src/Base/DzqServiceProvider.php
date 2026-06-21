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

namespace Discuz\Base;

use Discuz\Common\Utils;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

abstract class DzqServiceProvider extends ServiceProvider
{
    abstract public function boot();

    public function outPut($code, $msg = '', $data = []){
        Utils::outPut($code, $msg, $data, Str::uuid(), date('Y-m-d H:i:s'));
    }
}