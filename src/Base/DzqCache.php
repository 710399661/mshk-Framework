<?php
/**
 * Copyright (C) 2021 Tencent Cloud.
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


use App\Common\CacheKey;

class DzqCache
{
    const CACHE_SWICH = true;

    const CACHE_TTL = true;

    public static function set($key, $value, $ttl = 0)
    {
        if (self::CACHE_TTL) {
            return app('cache')->put($key, $value, 10 * 60);
        }
        return app('cache')->put($key, $value);
    }

    public static function get($key)
    {
        return app('cache')->get($key);
    }

    public static function delKey($key)
    {
        return app('cache')->forget($key);
    }

    public static function clear()
    {
        return app('cache')->flush();
    }

    public static function delHashKey($key, $hashKey)
    {
        if (isset(CacheKey::$fileStore[$key])) {
            $count = CacheKey::$fileStore[$key];
            $fileId = intval($hashKey) % $count;
            $key = $key . $fileId;
        }
        $data = self::get($key);
        if ($data && array_key_exists($hashKey, $data)) {
            unset($data[$hashKey]);
            return self::set($key, $data);
        }
        return true;
    }

    /**
     * @desc 获取一个key下的数据
     * @param $key
     * @param $hashKey
     * @param callable|null $callBack
     * @param bool $autoCache
     * @return bool|mixed
     */
    public static function hGet($key, $hashKey, callable $callBack = null, $autoCache = true)
    {
        $data = self::getAppCache($key, $hasCache, $cacheData);
        $ret = false;
        if (!is_null($hashKey) && $data && self::CACHE_SWICH) {
            if (array_key_exists($hashKey, $data)) {
                $ret = $data[$hashKey];
            }
        }
        if ($ret === false && !empty($callBack)) {
            $ret = $callBack($hashKey);
            if ($autoCache && !is_null($hashKey)) {
                $data[$hashKey] = $ret;
                self::set($key, $data);
                $hasCache && self::setAppCache($key, $data, $cacheData);
            }
        }
        return $ret;
    }

    /**
     * @desc 查询数据是否存在
     * @param $key
     * @param $hashKey
     * @param callable|null $callBack
     * @param bool $autoCache
     * @return bool
     */
    public static function exists($key, $hashKey, callable $callBack = null, $autoCache = true)
    {
        $data = self::getAppCache($key, $hasCache, $cacheData);
        if ($data && self::CACHE_SWICH) {
            if (array_key_exists($hashKey, $data)) {
                if (empty($data[$hashKey])) {
                    return false;
                } else {
                    return true;
                }
            }
        }
        if (!empty($callBack)) {
            $ret = $callBack();
            !$ret && $ret = null;
            $data[$hashKey] = $ret;
            $hasCache && self::setAppCache($key, $data, $cacheData);
            $autoCache && self::set($key, $data);
            return !empty($ret);
        } else {
            return false;
        }
    }

    /**
     * @desc 获取指定hashKey数组的对应数据
     * @param $key
     * @param $hashKeys
     * @param callable|null $callBack
     * @param null $index 数据转换的字段
     * @param bool $mutiColumn 每个字段下的数据是否保留多个
     * @param bool $autoCache
     * @return bool|array
     */
    public static function hMGet($key, $hashKeys, callable $callBack = null, $index = null, $mutiColumn = false, $autoCache = true)
    {
        if (isset(CacheKey::$fileStore[$key])) {
            $ret = self::getFragmentFileStore($key, $hashKeys);
        } else {
            $data = self::get($key);
            $ret = [];
            if(!empty($hashKeys)){
                if ($data && self::CACHE_SWICH) {
                    foreach ($hashKeys as $hashKey) {
                        if (array_key_exists($hashKey, $data)) {
                            if (!empty($data[$hashKey])) {
                                $ret[$hashKey] = $data[$hashKey];
                            }
                        } else {
                            $ret = false;
                            break;
                        }
                    }
                }else{
                    $ret = false;
                }
            }
        }
        if ($ret === false && !empty($callBack)) {
            $ret = $callBack($hashKeys);
            list($resultWithNull, $resultNotNull) = self::hMSetResult($ret, $index, $mutiColumn, $hashKeys, null);
            if ($autoCache) {
                if (isset(CacheKey::$fileStore[$key])) {
                    self::putFragmentFileStore($key, $resultWithNull);
                } else {
                    $data = self::get($key);
                    foreach ($resultWithNull as $k => $v) {
                        $data[$k] = $v;
                    }
                    self::set($key, $data);
                }
            }
            return $resultNotNull;
        }
        return $ret;
    }

    /**
     * @desc 帖子列表二级缓存和预加载
     * @param $key
     * @param $hashKey1
     * @param $hashKey2
     * @param callable|null $callBack
     * @param bool $preload
     * @return bool|mixed
     */
    public static function hM2Get($key, $hashKey1, $hashKey2, callable $callBack = null, $preload = false)
    {
        $data = self::get($key);
        $ret = false;
        if ($data && self::CACHE_SWICH) {
            if (array_key_exists($hashKey1, $data) && array_key_exists($hashKey2, $data[$hashKey1])) {
                $ret = $data[$hashKey1][$hashKey2];
            }
        }
        if (($ret === false || !$data) && !empty($callBack)) {
            $ret = $callBack();
            !$data && $data = [];
            if ($preload) {
                $data[$hashKey1] = $ret;
                $ret = $data[$hashKey1][$hashKey2];
            } else {
                $data[$hashKey1][$hashKey2] = $ret;
            }
            self::set($key, $data);
        }
        return $ret;
    }

    private static function hMSetResult($values, $indexField = null, $mutiColumn = false, $indexes = [], $defaultValue = [])
    {
        $resultWithNull = [];
        if ($indexField) {
            if ($mutiColumn) {
                foreach ($values as $item) {
                    $resultWithNull[$item[$indexField]][] = $item;
                }
            } else {
                $resultWithNull = array_column($values, null, $indexField);
            }
        } else {
            $resultWithNull = $values;
        }
        $resultNotNull = $resultWithNull;
        foreach ($indexes as $index) {
            if (!isset($resultWithNull[$index])) {
                $resultWithNull[$index] = $defaultValue;
            }
        }
        return [$resultWithNull, $resultNotNull];
    }

    /**
     * @desc 缓存分片存储
     * @param $key
     * @param $cacheData
     * @return bool
     */
    private static function putFragmentFileStore($key, $cacheData)
    {
        $count = CacheKey::$fileStore[$key];
        $resFileId = [];
        foreach ($cacheData as $k1 => $v1) {
            $fileId = $k1 % $count;
            $resFileId[$fileId][$k1] = $v1;
        }
        foreach ($resFileId as $fileId => $res) {
            $cacheKey = $key . $fileId;
            $data = self::get($cacheKey);
            foreach ($res as $k => $v) {
                $data[$k] = $v;
            }
            self::set($cacheKey, $data);
        }
    }

    private static function getFragmentFileStore($key, $hashKeys)
    {
        $count = CacheKey::$fileStore[$key];
        $resFileId = [];
        foreach ($hashKeys as $k) {
            $fileId = $k % $count;
            $resFileId[$fileId][] = $k;
        }
        $ret = false;
        foreach ($resFileId as $fileId => $keys) {
            $cacheKey = $key . $fileId;
            $data = self::get($cacheKey);
            if (!$data) {
                $ret = false;
                break;
            }
            $dataError = false;
            if ($data && self::CACHE_SWICH) {
                foreach ($keys as $hashKey) {
                    if (array_key_exists($hashKey, $data)) {
                        if (!empty($data[$hashKey])) {
                            $ret[$hashKey] = $data[$hashKey];
                        }
                    } else {
                        $dataError = true;
                        break;
                    }
                }
            }
            if ($dataError) {
                $ret = false;
                break;
            }
        }
        return $ret;
    }

    private static function getAppCache($key, &$hasCache, &$cacheData = [])
    {
        $data = null;
        $hasCache = app()->has(CacheKey::APP_CACHE);
        if ($hasCache) {
            $cacheData = app()->get(CacheKey::APP_CACHE);
            $data = $cacheData[$key] ?? null;
        }
        if (empty($data)) {
            $data = self::get($key);
        }
        return $data;
    }

    private static function setAppCache($key, $data, $cacheData)
    {
        $cacheData[$key] = $data;
        app()->instance(CacheKey::APP_CACHE, $cacheData);
    }

}
