<?php

namespace Discuz\Base;

use Illuminate\Support\Collection;

class DzqLog
{
    const APP_DZQLOG = 'APP_DZQLOG';//容器全局变量
    const LOG_WECHAT = 'wechatLog';
    const LOG_PAY = 'payLog';
    const LOG_QCLOUND = 'qcloudLog';
    const LOG_WECHAT_OFFIACCOUNT = 'wechatOffiaccount';
    const LOG_PERFORMANCE = 'performancelog';
    const LOG_LOGIN = 'loginLog';
    const LOG_ADMIN = 'adminLog';
    const LOG_API = 'apiLog';
    const LOG_ERROR = 'errorLog';
    const LOG_INFO = 'log';

    private static function getAppLog()
    {
        $dzqLog = null;
        $hasLOG = app()->has(DzqLog::APP_DZQLOG);
        if ($hasLOG) {
            $dzqLog = app()->get(DzqLog::APP_DZQLOG);
        }
        return $dzqLog;
    }

    private static function baseData()
    {
        $appLog = [];
        if (app()->has(DzqLog::APP_DZQLOG)) {
            $appLog = app()->get(DzqLog::APP_DZQLOG);
        }
        if(empty($appLog['request'])){
            $request = app('request');
        }else{
            $request = $appLog['request'];
        }
        $uri = $request->getUri();
        $method = $request->getMethod();
        $serverParams = $request->getServerParams();
        $ip = ip($serverParams);;
        $requestId = $appLog['requestId'] ?? '';
        $userId = $appLog['userId'] ?? 0;
        $payload = $request->getParsedBody();
        $recordPayload = $payload;
        if ($payload instanceof Collection) {
            $recordPayload = $payload->toArray();
            $replaceWord = '******';
            isset($recordPayload['password'])                   && $recordPayload['password'] = $replaceWord;
            isset($recordPayload['newPassword'])                && $recordPayload['newPassword'] = $replaceWord;
            isset($recordPayload['passwordConfirmation'])       && $recordPayload['passwordConfirmation'] = $replaceWord;
            isset($recordPayload['payPassword'])                && $recordPayload['payPassword'] = $replaceWord;
            isset($recordPayload['payPasswordConfirmation'])    && $recordPayload['payPasswordConfirmation'] = $replaceWord;
            isset($recordPayload['payPasswordToken'])           && $recordPayload['payPasswordToken'] = $replaceWord;
            if ($uri->getPath() == '/api/backAdmin/settings.create') {
                foreach ($recordPayload['data'] as &$item) {
                    if (in_array($item['key'], [
                        'qcloud_secret_key','qcloud_sms_app_key','qcloud_captcha_secret_key','qcloud_vod_url_key',
                        'offiaccount_app_secret','miniprogram_app_secret','api_key'
                    ])) {
                        $item['value'] = $replaceWord;
                    }
                }
            }
        }
        $port = empty($uri->getPort())?'':':'.$uri->getPort();
        $url = $uri->getScheme().'://'.$uri->getHost().$port.$uri->getPath().'?'.$uri->getQuery();
        return [
            'IO' => '',
            'url' => urldecode($url),
            'method' => $method,
            'ip' => $ip,
            'requestId' => $requestId,
            'userId' => $userId,
            'payload' => $recordPayload,
        ];
    }

    //接口入参日志
    public static function inPut($logType = DzqLog::LOG_INFO)
    {
        $baseData = self::baseData();
        $baseData['IO'] = 'input';
        app($logType)->info(json_encode($baseData, 320));
    }

    //接口出参日志
    public static function outPut($data = [], $logType = DzqLog::LOG_INFO)
    {
        $baseData = self::baseData();
        $baseData['IO'] = 'output';
        $baseData['outPutData'] = $data;
        app($logType)->info(json_encode($baseData, 320));
    }

    //异常日志
    public static function error($tag = 'tag', $data = [], $errorMessage = '', $logType = DzqLog::LOG_ERROR)
    {
        $baseData = self::baseData();
        $baseData['IO'] = 'errorOutput';
        $baseData['messageData'] = $data;
        $baseData['errorMessage'] = $errorMessage;
        app($logType)->info($tag . '::' . json_encode($baseData, 320));
    }

    //普通日志
    public static function info($tag = 'tag', $data = [], $logType = DzqLog::LOG_INFO)
    {
        $baseData = self::baseData();
        $baseData['IO'] = 'processOutput';
        $baseData['processData'] = $data;
        app($logType)->info($tag . '::' . json_encode($baseData, 320));
    }
}
