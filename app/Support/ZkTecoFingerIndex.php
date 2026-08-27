<?php

namespace App\Support;

final class ZkTecoFingerIndex
{
    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return [
            0 => '0 — Left pinky',
            1 => '1 — Left ring',
            2 => '2 — Left middle',
            3 => '3 — Left index',
            4 => '4 — Left thumb',
            5 => '5 — Right thumb',
            6 => '6 — Right index',
            7 => '7 — Right middle',
            8 => '8 — Right ring',
            9 => '9 — Right pinky',
        ];
    }

    /**
     * @return list<int>
     */
    public static function keys(): array
    {
        return array_keys(self::options());
    }
}
