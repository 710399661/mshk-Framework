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

namespace Discuz\Qcloud;


use App\Common\Statistics;
use App\Models\Order;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Thread;
use Psr\Http\Message\ResponseInterface;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Ms\V20180408\Models\DescribeUserBaseInfoInstanceRequest;
use TencentCloud\Ms\V20180408\MsClient;

trait QcloudStatisticsTrait
{

    use QcloudTrait;

    private function postStatisData()
    {
        $setting = Setting::query()->get()->toArray();
        $setting = array_column($setting, null, 'key');
        $version = app()->version();
        $siteId = !empty($setting['site_id']) ? $setting['site_id'] : '';
        $siteSecret = !empty($setting['site_secret']) ? $setting['site_secret'] : '';
        $siteInstall = !empty($setting['site_install']) ? $setting['site_install'] : '';
        $siteManage = !empty($setting['site_manage']) ? $setting['site_manage'] : '';
        $version = app()->version();
        $t1 = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $t2 = date('Y-m-d 23:59:59', strtotime('-1 day'));
        $lastDayThreads = Thread::query()->whereBetween('created_at', [$t1, $t2])->count();
        $totalThreads = Thread::query()->count();
        $totalPosts = Post::query()->where(['is_first' => 0])->count();
        $lastDayPosts = Post::query()->where(['is_first' => 0])->whereBetween('created_at', [$t1, $t2])->count();
        $lastDayMoney = Order::query()->where(['status' => 1])->whereBetween('created_at', [$t1, $t2])->sum('amount');
        $totalMoney = Order::query()->where(['status' => 1])->sum('amount');
        $t = date('Y-m-d', strtotime('-1 day'));
        $keyPc = 'login_pc_count:' . $t;
        $keyH5 = 'login_h5_count:' . $t;
        $keyMp = 'login_mp_count:' . $t;
        $pcLogin = Statistics::get($keyPc);
        $h5Login = Statistics::get($keyH5);
        $mpLogin = Statistics::get($keyMp);
        $params = [
            'date' => date('Y-m-d', strtotime('-1 day')),
            'site_id' => $siteId['value'],
            'site_secret' => $siteSecret['value'],
            'version' => $version,
            'site_install' => $siteInstall['value'],
            'site_manage' => $siteManage['value'],
            'day_threads' => $lastDayThreads,
            'total_threads' => $totalThreads,
            'day_posts' => $lastDayPosts,
            'total_posts' => $totalPosts,
            'day_money' => $lastDayMoney,
            'total_money' => $totalMoney,
            'h5_login' => empty($h5Login) ? 0 : $h5Login,
            'pc_login' => empty($pcLogin) ? 0 : $pcLogin,
            'mp_login' => empty($mpLogin) ? 0 : $mpLogin
        ];
        Statistics::delete($keyPc);
        Statistics::delete($keyH5);
        Statistics::delete($keyMp);
        try {
            $this->statistics($params)->then(function (ResponseInterface $response) {
                echo 'report:' . $response->getBody()->getContents() . PHP_EOL;
            })->wait();
        } catch (\Exception $e) {

        }

    }


    private function uinStatis()
    {
        $setting = Setting::query()->get()->toArray();
        $setting = array_column($setting, null, 'key');
        $siteInstall = !empty($setting['site_install']) ? $setting['site_install']['value'] : '';
        $qcloudSecretId = !empty($setting['qcloud_secret_id']) ? $setting['qcloud_secret_id']['value'] : '';
        $qcloudSecretKey = !empty($setting['qcloud_secret_key']) ? $setting['qcloud_secret_key']['value'] : '';
        if (empty($qcloudSecretId) || empty($qcloudSecretKey)) return;
        try {
            $cred = new Credential($qcloudSecretId, $qcloudSecretKey);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint('ms.tencentcloudapi.com');
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new MsClient($cred, '', $clientProfile);
            $req = new DescribeUserBaseInfoInstanceRequest();
            $params = '{}';
            $req->fromJsonString($params);
            $resp = $client->DescribeUserBaseInfoInstance($req);
        } catch (\Exception $e) {
            return;
        }
        $params = [
            'uin'   =>  $resp->UserUin,
            'time' => $siteInstall,
        ];

        try {
            $this->uinStatistics($params)->then(function (ResponseInterface $response) {
            })->wait();
        } catch (\Exception $e) {

        }

    }
}
