<?php

namespace ByJG\Authenticate\Enum;

enum User: string
{
    // User field name constants
    case Userid = 'userid';
    case Name = 'name';
    case Email = 'email';
    case Username = 'username';
    case Password = 'password';
    case Created_at = 'created_at';
    case uUpdated_at = 'updated_at';
    case Deleted_at = 'deleted_at';
    case Role = 'role';
}
