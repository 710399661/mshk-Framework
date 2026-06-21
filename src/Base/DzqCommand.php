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

use Discuz\Console\AbstractCommand;

abstract class DzqCommand extends AbstractCommand
{
    /**
     * 命名名称
     * @var string
     */
    protected $signature;

    /**
     * 命令说明
     * @var string
     */
    protected $description;

    /**
     * @return mixed
     * -------------------------------------
     * @desc    定时脚本入口方法
     * @author  coralchu 2020/9/27
     */
    abstract protected function main();

    public function handle()
    {
        $this->startCommand();
        $this->main();
        $this->endCommand();
    }

    private function startCommand()
    {
        echo PHP_EOL;
        echo sprintf('%s RUNNING ...', static::class) . PHP_EOL;
        echo PHP_EOL;
        echo '************************** START ' . date('Y-m-d H:i:s') . ' START ****************************' . PHP_EOL;
        echo PHP_EOL;
    }

    private function endCommand()
    {
        echo PHP_EOL;
        echo PHP_EOL;
        echo '**************************  END   ' . date('Y-m-d H:i:s') . ' END  ****************************' . PHP_EOL;
        echo PHP_EOL;
    }
}
