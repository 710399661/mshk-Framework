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

namespace Discuz\Api;

use App\Common\ResponseCode;
use Discuz\Base\DzqBase;
use Discuz\Base\DzqLog;
use Discuz\Http\DiscuzResponseFactory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Throwable;
use Tobscure\JsonApi\Document;
use Tobscure\JsonApi\ErrorHandler as JsonApiErrorHandler;

class ErrorHandler extends DzqBase
{
    protected $errorHandler;

    protected $logger;

    public function __construct(JsonApiErrorHandler $errorHandler, LoggerInterface $logger)
    {
        $this->errorHandler = $errorHandler;
        $this->logger = $logger;
    }

    public function handler(Throwable $e)
    {
        if (!$e instanceof Exception) {
            $debug = app()->config('debug');
            if($debug){
                $e = new Exception($e->getMessage().'\n'.$e->getTraceAsString(), $e->getCode(), $e);
            }else{
                $e = new Exception();
            }
        }

        $info = sprintf('%s: %s in %s:%s', get_class($e), $e->getMessage() . '\n' . $e->getTraceAsString(), $e->getFile(), $e->getLine());
        $this->logger->info('errorhandler：'.$info);
        $response = $this->errorHandler->handle($e);
        $errors = $response->getErrors();
        $path = Request::capture()->getPathInfo();
        if (strstr($path, 'v2')||strstr($path, 'v3')) {
            $error = Arr::first($errors);
            if (isset($error['status'])) {
                switch ($error['status']) {
                    case 500:
                        $this->outPut(ResponseCode::INTERNAL_ERROR,$e->getMessage());
                        break;
                    case 401:
                        DzqLog::info('error_handle_401_no_permission', [
                            'errorMessage' => $e->getMessage()
                        ]);
                        $this->outPut(ResponseCode::UNAUTHORIZED,$e->getMessage());
                        break;
                    default:
                        $validate_error = $e->validator->errors()->first();
                        $error_message = !empty($validate_error) ? $validate_error : $e->getMessage();
                        $this->outPut(ResponseCode::UNKNOWN_ERROR,$error_message);
                        break;
                }
            }
        }

        if (stristr(json_encode($errors, 256), 'SQLSTATE')) {
            $this->logger->info('database-error:' . json_encode($errors, 256));
            $errors = ['database error'];
        }
        $document = new Document;
        $document->setErrors($errors);
        return DiscuzResponseFactory::JsonApiResponse($document, $response->getStatus());
    }
}
