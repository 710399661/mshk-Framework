<?php

namespace Discuz\Base;


use App\Repositories\UserRepository;

abstract class DzqAdminController extends DzqController
{

    protected function checkRequestPermissions(UserRepository $userRepo)
    {
        return $this->user->isAdmin();
    }
}
