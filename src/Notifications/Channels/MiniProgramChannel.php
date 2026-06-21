<?php

/**
 * Copyright (C) 2020 Tencent Cloud.
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *   http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Discuz\Notifications\Channels;

use App\Models\NotificationTpl;
use Discuz\Contracts\Setting\SettingsRepository;
use Discuz\Wechat\EasyWechatTrait;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Notifications\Notification;

/**
 * 小程序通知 - 频道
 * Class WechatChannel
 *
 * @package Discuz\Notifications\Channels
 */
class MiniProgramChannel
{
    use EasyWechatTrait;

    protected $settings;

    /**
     * WechatChannel constructor.
     *
     * @param SettingsRepository $settings
     */
    public function __construct(SettingsRepository $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Send the given notification.
     *
     * @param $notifiable
     * @param Notification $notification
     * @return false
     * @throws GuzzleException
     */
    public function send($notifiable, Notification $notification)
    {
        if (! empty($notifiable->wechat) && ! empty($notifiable->wechat->min_openid)) {

            // wechat post json
            $build = $notification->toMiniProgram($notifiable);

            /**
             * get Wechat Template
             *
             * @var NotificationTpl $notificationData
             */
            $notificationData = $notification->getTplModel('miniProgram');
            $templateID = $notificationData->template_id;

            $appID = $this->settings->get('miniprogram_app_id', 'wx_miniprogram');
            $secret = $this->settings->get('miniprogram_app_secret', 'wx_miniprogram');

            if (blank($templateID) || blank($appID) || blank($secret)) {
                NotificationTpl::writeError($notificationData, 0, trans('setting.template_app_id_secret_not_found'));
                return false;
            }

            // to user
            $toUser = $notifiable->wechat->min_openid;

            $app = $this->miniProgram();

            // build
            $sendBuild = [
                'template_id' => $templateID,                   // 所需下发的订阅模板id
                'touser'      => $toUser,                       // 接收者（用户）的 openid
                'page'        => $notificationData->page_path,  // 点击模板卡片后的跳转页面
                'data'        => $build['content'],             // 模板内容，格式形如 { "key1": { "value": any }, "key2": { "value": any } }
            ];

            try {
                $response = $app->subscribe_message->send($sendBuild);
            } catch (Exception $e) {
                NotificationTpl::writeError($notificationData, $e->getCode(), $e->getMessage(), $sendBuild);
                return false;
            }

            // catch error
            if (isset($response['errcode']) && $response['errcode'] != 0) {
                $errMsg = $response['errmsg'] ?? '';
                NotificationTpl::writeError($notificationData, $response['errcode'], $errMsg, $sendBuild);
            } else {
                // reset error status
                if ($notificationData->is_error) {
                    $notificationData->is_error = 0;
                    $notificationData->error_msg = null;
                    $notificationData->save();
                }
            }
        }
    }

}
