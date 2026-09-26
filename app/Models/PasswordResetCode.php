<?php

namespace App\Models;

class PasswordResetCode extends SupabaseModel
{
    protected static $table = 'password_reset_codes';
}