<?php

namespace ByJG\Authenticate\Model;

class UserToken
{
    public ?UserModel $user = null;
    public string $token = '';
    public array $data = [];

    public function __construct(UserModel $user, string $token, array $data)
    {
        $this->user = $user;
        $this->token = $token;
        $this->data = $data;
    }
}