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

namespace Discuz\Notifications\Traits;

use App\Models\NotificationTiming;
use App\Models\NotificationTpl;
use App\Notifications\Messages\Database\GroupMessage;
use App\Notifications\Messages\Database\PostMessage;
use App\Notifications\Messages\Database\RegisterMessage;
use App\Notifications\Messages\Database\StatusMessage;
use Carbon\Carbon;
use Discuz\Base\DzqLog;

trait NotificationTimingTrait
{
    public $systemMethod = [
        'wechat.registered.passed' => RegisterMessage::class,
        'wechat.registered.approved' => StatusMessage::class,
        'wechat.registered.unapproved' => StatusMessage::class,
        'wechat.post.approved' => PostMessage::class,
        'wechat.post.unapproved' => PostMessage::class,
        'wechat.post.deleted' => PostMessage::class,
        'wechat.post.essence' => PostMessage::class,
        'wechat.post.sticky' => PostMessage::class,
        'wechat.post.update' => PostMessage::class,
        'wechat.user.disable' => StatusMessage::class,
        'wechat.user.normal' => StatusMessage::class,
        'wechat.user.group' => GroupMessage::class,
    ];

    public $nonSystemMethod = [
        'wechat.post.replied' => 'Replied',
        'wechat.post.liked' => 'Liked',
        'wechat.post.paid' => 'Rewarded',
        'wechat.post.reminded' => 'Related',
        'wechat.withdraw.noticed' => 'Withdrawal',
        'wechat.withdraw.withdraw' => 'Withdrawal',
        'wechat.divide.income' => 'Rewarded',
        'wechat.question.asked' => 'Questioned',
        'wechat.question.answered' => 'Questioned',
        'wechat.question.expired' => 'Rewarded',
        'wechat.red_packet.gotten' => 'ReceiveRedPacket',
        'wechat.question.rewarded' => 'ThreadRewarded',
        'wechat.question.rewarded.expired' => 'ThreadRewardedExpired',
    ];

    public function sendNotification($receiveUserId, $wechatNoticeId, $isCount = true): array
    {
        $response = [
            'result' => true,
            'noticeTimingId' => 0
        ];
        $pushType = NotificationTpl::getPushType($wechatNoticeId);
        if ($pushType === false) {
            DzqLog::error('notice_id_not_exist', ['receiveUserId' => $receiveUserId, 'wechatNoticeId' => $wechatNoticeId]);
            $response['result'] = false;
            return $response;
        }

        if ($pushType == NotificationTpl::PUSH_TYPE_DELAY) {
            $lastNotification = NotificationTiming::getLastNotification($wechatNoticeId, $receiveUserId);
            $lastNotificationTime = strtotime($lastNotification['expired_at']);
            $delayTime = NotificationTpl::getDelayTime($wechatNoticeId);
            $nowTime = strtotime(Carbon::now());

            if (abs($nowTime - $lastNotificationTime) > 1) {
                $currentNotification = NotificationTiming::getCurrentNotification($wechatNoticeId, $receiveUserId);
                $notNotification = $lastNotificationTime + $delayTime > $nowTime; // 不进行通知
                if (!empty($currentNotification)) {
                    $response['noticeTimingId'] = $currentNotification['id'];
                    // ToDo: 累计通知次数
                    NotificationTiming::addNotificationNumber($currentNotification['id'], $isCount);
                    if ($notNotification) {
                        $response['result'] = false;
                        return $response;
                    } else {
                        // ToDo: 发送即时通知,将过期时间置为当前时间
                        $updateNum = NotificationTiming::setExpireAt($currentNotification['id']);
                        if ($updateNum == 0) {
                            DzqLog::error('set_notice_expire_at_error', ['notificationId' => $lastNotification['id'], 'updateNum' => $updateNum]);
                        }
                    }
                } else {
                    if ($notNotification) {
                        $expiredAt = null;
                    } else {
                        $expiredAt = Carbon::now();
                    }
                    $notificationTiming = NotificationTiming::createNotificationTiming($wechatNoticeId, $receiveUserId, $expiredAt);
                    $response['noticeTimingId'] = $notificationTiming['id'];
                    $response['result'] = ($expiredAt == null ? false : true);
                    return $response;
                }
            } else {
                // ToDo: 初始化发送即时通知
            }
        }

        $response['result'] = true;
        return $response;
    }
}
