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

namespace Discuz\Api\ExceptionHandler;

use App\Common\ResponseCode;
use Discuz\Auth\Exception\LoginFailedException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tobscure\JsonApi\Exception\Handler\ExceptionHandlerInterface;
use Tobscure\JsonApi\Exception\Handler\ResponseBag;
use Discuz\Common\Utils;
class LoginFailedExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function manages(Exception $e)
    {
        return $e instanceof LoginFailedException;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Exception $e)
    {
        $path = Request::capture()->getPathInfo();
        $detail = Str::replaceFirst(':values', $e->getMessage(), app('translator')->get('login.residue_degree'));
        if (strstr($path, 'v2')||strstr($path, 'v3')) {
            if (empty($e->getMessage())) {
                Utils::outPut(ResponseCode::LOGIN_FAILED, app('translator')->get('login.login_info_error'));
            }
            Utils::outPut(ResponseCode::LOGIN_FAILED, $detail);
            return null;
        }

        $status = $e->getCode();
        $error = [
            'status' => (string) $status,
            'code' => 'login_failed',
        ];

        if (is_numeric($e->getMessage())) {
            $error['detail'] = [$detail];
        }

        return new ResponseBag($status, [$error]);
    }
}
