<?php

namespace Discuz\Notifications\Messages;

use App\Models\NotificationTpl;
use App\Models\Post;
use Discuz\Notifications\Traits\VariableTemplateTrait;
use Discuz\Wechat\EasyWechatTrait;
use Illuminate\Support\Str;

abstract class SimpleMessage
{
    use VariableTemplateTrait;
    use EasyWechatTrait;

    /**
     * @var NotificationTpl Collection first
     */
    protected $firstData;

    protected $filterSpecialChar = true;

    protected function getContent($data)
    {
        if (isset($this->data['notify_type']) && in_array($this->data['notify_type'], ['sticky', 'essence', 'delete', 'approved'])) {
            $data['post'] = Post::changeNotifitionPostContent($data['post']);
        }

        $replaceVars = array_map(function ($var) {
            return $var;
        }, $this->contentReplaceVars($data));

        return str_replace($this->getVars(), $replaceVars, $this->firstData->content);
    }

    protected function getWechatContent($data = [])
    {
        return NotificationTpl::getWechatFormat($this->contentReplaceVars($data));
    }

    protected function getSmsContent($data = [])
    {
        return NotificationTpl::getSmsFormat($this->contentReplaceVars($data));
    }

    protected function getMiniProgramContent($data = [])
    {
        return NotificationTpl::getMiniProgramContent($this->contentReplaceVars($data), $this->getMiniProgramKeys($this->firstData));
    }

    protected function getVars()
    {
        if (!empty($this->firstData->vars)) {
            return array_keys(unserialize($this->firstData->vars));
        }
        return [];
    }

    protected function getTitle()
    {
        $replaceVars = $this->titleReplaceVars();

        return str_replace($this->getVars(), $replaceVars, $this->firstData->title);
    }

    /**
     * 修改展示的字符长度 并 过滤代码
     *
     * @param $str
     * @return string
     */
    public function strWords($str)
    {
        return Str::limit($str, 60, '...');
    }

    abstract public function setData(...$parameters);

    abstract protected function titleReplaceVars();

    abstract protected function contentReplaceVars($data);
}
