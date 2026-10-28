<?php

namespace App\Enums;

/**
 * Values stored in users.otorisasi (kept compatible with the legacy system).
 */
enum Role: int
{
    case Auditor = 1;
    case Auditee = 2;
    case Admin = 3;

    public static function fromName(string $name): self
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->name, $name) === 0) {
                return $case;
            }
        }

        throw new \ValueError("Unknown role [{$name}].");
    }
}
