<?php

namespace ByJG\Authenticate\Enum;

enum UserField: string
{
    // User field name constants
    case Userid = 'userid';
    case Name = 'name';
    case Email = 'email';
    case Username = 'username';
    case Password = 'password';
    case CreatedAt = 'created_at';
    case UpdatedAt = 'updated_at';
    case DeletedAt = 'deleted_at';
    case Role = 'role';
}
