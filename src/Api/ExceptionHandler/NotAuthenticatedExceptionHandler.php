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
use Discuz\Auth\Exception\NotAuthenticatedException;
use Discuz\Base\DzqLog;
use Exception;
use Tobscure\JsonApi\Exception\Handler\ExceptionHandlerInterface;
use Tobscure\JsonApi\Exception\Handler\ResponseBag;
use Discuz\Common\Utils;
class NotAuthenticatedExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function manages(Exception $e)
    {
        return $e instanceof NotAuthenticatedException;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Exception $e)
    {
        $status = 401;
        $error = [
            'status' => (string) $status,
            'code' => 'not_authenticated'
        ];

        DzqLog::info('not_authenticated_exception_handler', [
            'status'    => $status,
            'error'     => $error
        ]);
        Utils::outPut(ResponseCode::UNAUTHORIZED);
        return new ResponseBag($status, [$error]);
    }
}
