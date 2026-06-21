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

namespace Discuz\Http;

use FastRoute\DataGenerator;
use FastRoute\RouteParser;

class RouteCollection
{
    /**
     * @var array
     */
    protected $reverse = [];

    /**
     * @var DataGenerator
     */
    protected $dataGenerator;

    /**
     * @var RouteParser
     */
    protected $routeParser;

    protected $currentGroupPrefix;

    protected $times;
    protected $interval;
    protected $delay;

    public function __construct()
    {
        $this->dataGenerator = new DataGenerator\GroupCountBased;

        $this->routeParser = new RouteParser\Std;

        $this->currentGroupPrefix = '';
        $this->times = null;
        $this->interval = null;
        $this->delay = null;
    }

    public function get($path, $name, $handler, $replaceHandler = null)
    {
        return $this->addRoute('GET', $path, $name, $handler, $replaceHandler);
    }

    public function post($path, $name, $handler, $replaceHandler = null)
    {
        return $this->addRoute('POST', $path, $name, $handler, $replaceHandler);
    }

//    public function put($path, $name, $handler)
//    {
//        return $this->addRoute('PUT', $path, $name, $handler);
//    }
//
//    public function patch($path, $name, $handler)
//    {
//        return $this->addRoute('PATCH', $path, $name, $handler);
//    }
//
//    public function delete($path, $name, $handler)
//    {
//        return $this->addRoute('DELETE', $path, $name, $handler);
//    }

    public function group($prefix, callable $callback)
    {
        $previousGroupPrefix = $this->currentGroupPrefix;
        $this->currentGroupPrefix = $previousGroupPrefix . $prefix;
        $callback($this);
        $this->currentGroupPrefix = $previousGroupPrefix;
    }

    /**
     * @param callable $callback
     * @param int $times 访问次数
     * @param int $interval 时间间隔(秒)
     * @param int $delay 超过限频禁用时长(秒)
     */
    public function withFrequency(callable $callback, $times = 100, $interval = 60, $delay = 300)
    {
        $this->times = $times;
        $this->interval = $interval;
        $this->delay = $delay;
        $callback($this);
        $this->times = null;
        $this->interval = null;
        $this->delay = null;
    }

    public function addRoute($method, $path, $name, $handler, $replaceHandler = null)
    {
        $path = $this->currentGroupPrefix . $path;
        $path = str_replace('//', '/', $path);
        $routeDatas = $this->routeParser->parse($path);
        foreach ($routeDatas as $routeData) {
            $handler = [
                'method' => $method,
                'handler' => $handler,
                'replaceHandler' => $replaceHandler,
                'times' => $this->times,
                'interval' => $this->interval,
                'delay' => $this->delay
            ];
            $this->dataGenerator->addRoute($method, $routeData, $handler);
        }

        $this->reverse[$name] = $routeDatas;
        return $this;
    }

    public function getRouteData()
    {
        return $this->dataGenerator->getData();
    }

    protected function fixPathPart(&$part, $key, array $parameters)
    {
        if (is_array($part) && array_key_exists($part[0], $parameters)) {
            $part = $parameters[$part[0]];
        }
    }

    public function getPath($name, array $parameters = [])
    {
        if (isset($this->reverse[$name])) {
            $parts = $this->reverse[$name][0];
            array_walk($parts, [$this, 'fixPathPart'], $parameters);
            return '/' . ltrim(implode('', $parts), '/');
        }
        throw new \RuntimeException("Route $name not found");
    }
}
