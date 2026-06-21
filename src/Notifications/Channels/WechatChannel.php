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
use Discuz\Notifications\Traits\NotificationTimingTrait;
use Discuz\Wechat\EasyWechatTrait;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use EasyWeChat\Kernel\Exceptions\InvalidConfigException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;

/**
 * 微信通知 - 频道
 * Class WechatChannel
 *
 * @package Discuz\Notifications\Channels
 */
class WechatChannel
{
    use EasyWechatTrait;

    use NotificationTimingTrait;

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
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws GuzzleException
     */
    public function send($notifiable, Notification $notification)
    {
        if (! empty($notifiable->wechat) && ! empty($notifiable->wechat->mp_openid)) {
            $tplId = collect($notification)->get('tplId');
            $wechatNoticeId = $tplId['wechat'];

            $sendResponse = $this->sendNotification($notifiable->id, $wechatNoticeId);

            // wechat post json
            $build = $notification->toWechat($notifiable, $sendResponse['noticeTimingId']);

            if ($sendResponse['result'] == false) {
                return false;
            }

            // 替换掉内容中的换行符
            $content = str_replace(PHP_EOL, '', Arr::get($build, 'content'));
            $build['content'] = json_decode($content, true);

            /**
             * get Wechat Template
             *
             * @var NotificationTpl $notificationData
             */
            $notificationData = $notification->getTplModel('wechat');
            $templateID = $notificationData->template_id;

            $appID = $this->settings->get('offiaccount_app_id', 'wx_offiaccount');
            $secret = $this->settings->get('offiaccount_app_secret', 'wx_offiaccount');

            if (blank($templateID) || blank($appID) || blank($secret)) {
                NotificationTpl::writeError($notificationData, 0, trans('setting.template_app_id_secret_not_found'));
                return false;
            }

            // to user
            $toUser = $notifiable->wechat->mp_openid;

            // redirect
            $url = Arr::pull($build, 'content.redirect_url');

            $app = $this->offiaccount();

            // build
            $sendBuild = [
                'touser'      => $toUser,
                'template_id' => $templateID,
                'url'         => $notificationData->redirect_type == NotificationTpl::REDIRECT_TYPE_TO_NO ? '' : $url,
                'data'        => $build['content']['data'],
            ];

            // 判断是否开启跳转小程序
            if ($notificationData->redirect_type == NotificationTpl::REDIRECT_TYPE_TO_MINIPROGRAM) {
                $sendBuild = array_merge($sendBuild, [
                    'miniprogram' => [
                        'appid'    => $this->settings->get('miniprogram_app_id', 'wx_miniprogram'),
                        'pagepath' => $notificationData->page_path,
                    ],
                ]);
            }

            // send
            $response = $app->template_message->send($sendBuild);

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
