<?php

namespace App\Enums;

enum Role: string
{
    case Manager = 'IT Manager';
    case Staff = 'IT Staff';
    case Trainee = 'IT Trainee';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function orderSql(): string
    {
        return "case role when 'IT Manager' then 0 when 'IT Staff' then 1 when 'IT Trainee' then 2 else 3 end";
    }
}
