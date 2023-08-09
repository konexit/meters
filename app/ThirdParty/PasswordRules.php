<?php

namespace App\ThirdParty;

use App\Models\User;

class PasswordRules
{
    private $user;

    public function __construct()
    {
        $this->user = new User();
    }


    public function checkPass(string $password): bool
    {
        $login = session('mLogin') == null ? $_POST['log'] : session('mLogin');
        return $this->user->checkUser($login, $password);
    }
}
