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
        return "case when role = 'IT Manager' then 0 when role = 'IT Staff' then 1 when role = 'IT Trainee' then 2 else 3 end";
    }
}
