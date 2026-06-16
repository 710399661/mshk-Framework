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

namespace mshk\Api;

use mshk\Api\Controller\AbstractSerializeController;
use mshk\Api\Events\ApiExceptionRegisterHandler;
use mshk\Api\Events\ConfigMiddleware;
use mshk\Api\ExceptionHandler\FallbackExceptionHandler;
use mshk\Api\ExceptionHandler\LoginFailedExceptionHandler;
use mshk\Api\ExceptionHandler\LoginFailuresTimesToplimitExceptionHandler;
use mshk\Api\ExceptionHandler\NotAuthenticatedExceptionHandler;
use mshk\Api\ExceptionHandler\PermissionDeniedExceptionHandler;
use mshk\Api\ExceptionHandler\RouteNotFoundExceptionHandler;
use mshk\Api\ExceptionHandler\ServiceResponseExceptionHandler;
use mshk\Api\ExceptionHandler\TencentCloudSDKExceptionHandler;
use mshk\Api\ExceptionHandler\ValidationExceptionHandler;
use mshk\Api\Listeners\AutoResisterApiExceptionRegisterHandler;
use mshk\Api\Middleware\HandlerErrors;
use mshk\Api\Middleware\InstallMiddleware;
use mshk\Foundation\Application;
use mshk\Http\Middleware\AuthenticateWithHeader;
use mshk\Http\Middleware\CheckoutSite;
use mshk\Http\Middleware\CheckUserStatus;
use mshk\Http\Middleware\DispatchRoute;
use mshk\Http\Middleware\ParseJsonBody;
use mshk\Http\Middleware\OptionsRequest;
use mshk\Http\RouteCollection;
use Illuminate\Support\ServiceProvider;
use Tobscure\JsonApi\ErrorHandler;
use Laminas\Stratigility\MiddlewarePipe;

class ApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('mshk.api.middleware', function (Application $app) {
            $pipe = new MiddlewarePipe();

            if (!$this->app->isInstall()) {
                $pipe->pipe($app->make(InstallMiddleware::class));
                return $pipe;
            }

            $pipe->pipe($app->make(HandlerErrors::class));
            $pipe->pipe($app->make(OptionsRequest::class));
            $pipe->pipe($app->make(ParseJsonBody::class));
            $pipe->pipe($app->make(AuthenticateWithHeader::class));
            $pipe->pipe($app->make(CheckoutSite::class));
            $pipe->pipe($app->make(CheckUserStatus::class));

            $app->make('events')->dispatch(new ConfigMiddleware($pipe));

            return $pipe;
        });

        $this->app->singleton(ErrorHandler::class, function (Application $app) {
            $errorHandler = new ErrorHandler;
            $errorHandler->registerHandler(new RouteNotFoundExceptionHandler());
            $errorHandler->registerHandler(new ValidationExceptionHandler());
            $errorHandler->registerHandler(new NotAuthenticatedExceptionHandler());
            $errorHandler->registerHandler(new PermissionDeniedExceptionHandler());
            $errorHandler->registerHandler(new TencentCloudSDKExceptionHandler());
            $errorHandler->registerHandler(new ServiceResponseExceptionHandler());
            $errorHandler->registerHandler(new LoginFailuresTimesToplimitExceptionHandler());
            $errorHandler->registerHandler(new LoginFailedExceptionHandler());

            $app->make('events')->dispatch(new ApiExceptionRegisterHandler($errorHandler));

            $errorHandler->registerHandler(new FallbackExceptionHandler($app->config('debug')));
            return $errorHandler;
        });

        // 保证路由中间件最后执行
        $this->app->afterResolving('mshk.api.middleware', function (MiddlewarePipe $pipe) {
            $pipe->pipe($this->app->make(DispatchRoute::class));
        });
    }

    public function boot()
    {
        $this->populateRoutes($this->app->make(RouteCollection::class));

        $this->app->make('events')->listen(ApiExceptionRegisterHandler::class, AutoResisterApiExceptionRegisterHandler::class);

        AbstractSerializeController::setContainer($this->app);
    }

    protected function populateRoutes(RouteCollection $route)
    {
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        if (empty($reqUri)) return;
        $api1 = '/api/backAdmin/';
        $api2 = '/api/';
        $api3 = '/apiv3/';
        $api4 = '/api/v3/';
        if ($this->startWith($reqUri, $api1)) {
            $route->group($api1, function (RouteCollection $route) {
                require $this->app->basePath('routes/apiadmin.php');
            });
        } else if ($this->startWith($reqUri, $api3)) {
            $route->group($api3, function (RouteCollection $route) {
                require $this->app->basePath('routes/apiv3.php');
            });
        } else if ($this->startWith($reqUri, $api4)) {
            $route->group($api4, function (RouteCollection $route) {
                require $this->app->basePath('routes/apiv3.php');
            });
        } else if ($this->startWith($reqUri, $api2)) {
            $route->group($api2, function (RouteCollection $route) {
                require $this->app->basePath('routes/api.php');
            });
        }
    }

    private function startWith($uri, $prefix)
    {
        $p = '/' . $prefix;//兼容前端错误的url拼接
        return ($uri & $prefix) == $prefix || ($uri & $p) == $p;
    }
}
