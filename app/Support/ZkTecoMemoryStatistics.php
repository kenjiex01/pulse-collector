<?php

namespace App\Support;

/**
 * Parses CMD_GET_FREE_SIZES payloads (pyzk 80-byte layout and newer 92-byte terminals).
 */
final class ZkTecoMemoryStatistics
{
    /**
     * @return array{
     *     users_used: int,
     *     users_capacity: ?int,
     *     users_free: ?int,
     *     fingers_used: int,
     *     fingers_capacity: ?int,
     *     fingers_free: ?int,
     *     attendance_used: int,
     *     attendance_capacity: ?int,
     *     attendance_free: ?int,
     *     faces_used: ?int,
     *     faces_capacity: ?int,
     * }
     */
    public static function parse(string $payload): array
    {
        $empty = [
            'users_used' => 0,
            'users_capacity' => null,
            'users_free' => null,
            'fingers_used' => 0,
            'fingers_capacity' => null,
            'fingers_free' => null,
            'attendance_used' => 0,
            'attendance_capacity' => null,
            'attendance_free' => null,
            'faces_used' => null,
            'faces_capacity' => null,
        ];

        if ($payload === '') {
            return $empty;
        }

        if (strlen($payload) >= 92) {
            $stats = self::parseNinetyTwoByteLayout($payload);
            if ($stats !== null) {
                return $stats;
            }
        }

        if (strlen($payload) >= 80) {
            return self::parseLegacyEightyByteLayout($payload);
        }

        return $empty;
    }

    /**
     * @return array{
     *     users_used: int,
     *     users_capacity: ?int,
     *     users_free: ?int,
     *     fingers_used: int,
     *     fingers_capacity: ?int,
     *     fingers_free: ?int,
     *     attendance_used: int,
     *     attendance_capacity: ?int,
     *     attendance_free: ?int,
     *     faces_used: ?int,
     *     faces_capacity: ?int,
     * }|null
     */
    private static function parseNinetyTwoByteLayout(string $payload): ?array
    {
        /** @var list<int> $fields */
        $fields = array_values(unpack('V23', substr($payload, 0, 92)));

        $attendanceCapacity = $fields[16] ?? 0;
        if ($attendanceCapacity <= 0) {
            return null;
        }

        return [
            'users_used' => max(0, $fields[4] ?? 0),
            'users_capacity' => self::positiveOrNull($fields[15] ?? 0),
            'users_free' => self::positiveOrNull($fields[18] ?? 0),
            'fingers_used' => max(0, $fields[6] ?? 0),
            'fingers_capacity' => self::positiveOrNull($fields[14] ?? 0),
            'fingers_free' => self::positiveOrNull($fields[17] ?? 0),
            'attendance_used' => max(0, $fields[8] ?? 0),
            'attendance_capacity' => self::positiveOrNull($attendanceCapacity),
            'attendance_free' => self::positiveOrNull($fields[19] ?? 0),
            'faces_used' => self::positiveOrNull($fields[20] ?? 0),
            'faces_capacity' => self::positiveOrNull($fields[22] ?? 0),
        ];
    }

    /**
     * @return array{
     *     users_used: int,
     *     users_capacity: ?int,
     *     users_free: ?int,
     *     fingers_used: int,
     *     fingers_capacity: ?int,
     *     fingers_free: ?int,
     *     attendance_used: int,
     *     attendance_capacity: ?int,
     *     attendance_free: ?int,
     *     faces_used: ?int,
     *     faces_capacity: ?int,
     * }
     */
    private static function parseLegacyEightyByteLayout(string $payload): array
    {
        /** @var list<int> $fields */
        $fields = array_values(unpack('V20', substr($payload, 0, 80)));

        $stats = [
            'users_used' => max(0, $fields[4] ?? 0),
            'users_capacity' => self::positiveOrNull($fields[15] ?? 0),
            'users_free' => self::positiveOrNull($fields[18] ?? 0),
            'fingers_used' => max(0, $fields[6] ?? 0),
            'fingers_capacity' => self::positiveOrNull($fields[14] ?? 0),
            'fingers_free' => self::positiveOrNull($fields[17] ?? 0),
            'attendance_used' => max(0, $fields[8] ?? 0),
            'attendance_capacity' => self::positiveOrNull($fields[16] ?? 0),
            'attendance_free' => self::positiveOrNull($fields[19] ?? 0),
            'faces_used' => null,
            'faces_capacity' => null,
        ];

        if (strlen($payload) >= 92) {
            /** @var list<int> $faceFields */
            $faceFields = array_values(unpack('V3', substr($payload, 80, 12)));
            $stats['faces_used'] = self::positiveOrNull($faceFields[0] ?? 0);
            $stats['faces_capacity'] = self::positiveOrNull($faceFields[2] ?? 0);
        }

        return $stats;
    }

    private static function positiveOrNull(int $value): ?int
    {
        return $value > 0 ? $value : null;
    }
}
