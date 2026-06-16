<?php


namespace mshk\Notifications\Services;


use mshk\Notifications\Messages\SimpleMessage;

interface NotificationInterface
{
    public function setNotification(SimpleMessage $notification);
}
