<?php

namespace App\Support;

use InvalidArgumentException;
use ZkTeco\Enums\Privilege;

final class ZkTecoUserPrivilege
{
    /**
     * @return array<string, string> privilege key => UI label
     */
    public static function options(): array
    {
        return [
            'User' => 'User (normal)',
            'Enroller' => 'Enroller',
            'Manager' => 'Manager',
            'Admin' => 'Admin',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::options());
    }

    public static function toEnum(string $key): Privilege
    {
        return match ($key) {
            'User' => Privilege::User,
            'Enroller' => Privilege::Enroller,
            'Manager' => Privilege::Manager,
            'Admin' => Privilege::Admin,
            default => throw new InvalidArgumentException('Invalid device user privilege: '.$key),
        };
    }

    public static function fromEnum(Privilege $privilege): string
    {
        return $privilege->name;
    }

    public static function label(?string $key): string
    {
        if ($key === null || $key === '') {
            return 'User (normal)';
        }

        return self::options()[$key] ?? $key;
    }
}
