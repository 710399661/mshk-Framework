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

namespace Discuz\Http\Middleware;

use App\Common\ResponseCode;
use App\Models\User;
use App\Models\UserSignInFields;
use Discuz\Auth\Exception\PermissionDeniedException;
use Discuz\Common\Utils;
use Discuz\Contracts\Setting\SettingsRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckUserStatus implements MiddlewareInterface
{
    //用户状态路由白名单：审核中、扩展字段
    private $noAuditAction = [
        'users/pc/wechat/h5.genqrcode', // h5二维码生成
        'users/pc/wechat/miniprogram.genqrcode', // 小程序二维码生成
        'users/pc/wechat/h5.login', // h5登录轮询
        'users/pc/wechat/miniprogram.login', //小程序登录轮询
        'users/mobilebrowser/wechat/miniprogram.genscheme', //小程序Scheme拉起
        'users/username.login', // 用户名登录
        'users/username.register', // 用户名注册
//        'users/username.login.isdisplay', // 用户名入口是否展示
        'users/username.check', // 用户名检测
        'users/sms.send', // 手机号发送
        'users/sms.verify', // 手机号验证用户
        'users/sms.login', // 手机号登录
        'users/sms.bind', // 手机号绑定
        'users/wechat/h5.oauth', // h5授权
        'users/wechat/h5.login', // h5登录
        'users/wechat/h5.bind', // h5绑定
        'users/wechat/miniprogram.login', // 小程序登录
        'users/wechat/miniprogram.bind', // 小程序绑定
        'users/wechat/transition/username.autobind', // 过渡开关打开微信绑定自动创建账号
        'users/wechat/transition/sms.bind', // 过渡流程绑定手机号
        'users/nickname.set', // 登录页昵称设置

        'user', // 用户信息
        'forum', // 首页配置接口
//        'follow',
        'thread.list', // 帖子列表
        'users.list',
        'order.create', // 订单创建
        'trade/pay/order',
        'order.detail',
        'wallet/cash',
        'wallet/log',
        'wallet/user', // 用户钱包
        'categories', //分类接口
        'thread.stick', // 置顶
        'tom.permissions', //权限
        'thread.recommends', // 帖子
        'trade/notify/wechat',
        'threads/notify/video',
        'offiaccount/jssdk',
//        'attachment.download',
        'user/signinfields.list', // 查询扩展字段
        'user/signinfields.create', // 提交扩展字段
        'attachments', //上传图片、附件
        'unreadnotification', // 消息
        'posts.list', // 帖子
        'backAdmin/login',
        'emoji',
        'view.count',
        'plugin/list',
        'thread.typelist'
    ];

    /**
     * {@inheritdoc}
     *
     * @throws PermissionDeniedException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $api = Utils::getApiName();
        if ($api === 'forum' || $api === 'user') {
            return $handler->handle($request);
        }

        $actor = $request->getAttribute('actor');
        if ($actor->isGuest() || $actor->isAdmin()) {
            return $handler->handle($request);
        }
        // 被禁用的用户
        if ($actor->status == User::STATUS_BAN) {
            $rejectReason = !empty($actor->reject_reason) ? $actor->reject_reason : '';
            Utils::outPut(ResponseCode::USER_BAN, '', ['banReason' => $rejectReason]);
        }
        // 审核中的用户
        if ($actor->status == User::STATUS_MOD) {
            if (!in_array($api, $this->noAuditAction) && !(strpos($api, 'users') === 0)) {
                Utils::outPut(ResponseCode::JUMP_TO_AUDIT);
            }
        }
        // 审核拒绝
        if ($actor->status == User::STATUS_REFUSE) {
            Utils::outPut(ResponseCode::VALIDATE_REJECT,
                          ResponseCode::$codeMap[ResponseCode::VALIDATE_REJECT],
                          User::getUserReject($actor->id)
            );
        }
        // 审核忽略
        if ($actor->status == User::STATUS_IGNORE) {
            Utils::outPut(ResponseCode::VALIDATE_IGNORE);
        }
        // 待填写扩展审核字段的用户
        if ($actor->status == User::STATUS_NEED_FIELDS || $this->isJumpSignFields($actor)) {
            if (!in_array($api, $this->noAuditAction) && !(strpos($api, 'users') === 0)) {
                Utils::outPut(ResponseCode::JUMP_TO_SIGIN_FIELDS);
            }
        }
        return $handler->handle($request);
    }

    private function isJumpSignFields($actor){
        $userId = !empty($actor->id) ? (int)$actor->id : 0;
        $settings = app(SettingsRepository::class);
        $openExtFields = $settings->get('open_ext_fields');
        $userSignInFields = UserSignInFields::query()->where('user_id', $userId)->exists();
        if ($actor->status == USER::STATUS_NORMAL && !empty($openExtFields) && !$userSignInFields && !$actor->isAdmin()) {
            return true;
        }
        return false;
    }
}
