<?php
declare(strict_types=1);

namespace App;

/**
 * Ticket status machine. Pure logic, no I/O, so it is the cheapest thing to unit test.
 */
final class TicketStatus
{
    public const OPEN = 'open';
    public const IN_PROGRESS = 'in_progress';
    public const CLOSED = 'closed';

    public const STATES = [self::OPEN, self::IN_PROGRESS, self::CLOSED];

    private const ALLOWED = [
        self::OPEN => [self::IN_PROGRESS, self::CLOSED],
        self::IN_PROGRESS => [self::OPEN, self::CLOSED],
        self::CLOSED => [self::OPEN],
    ];

    public static function isValid(string $state): bool
    {
        return in_array($state, self::STATES, true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        if (!self::isValid($from) || !self::isValid($to)) {
            return false;
        }
        if ($from === $to) {
            return false;
        }
        return in_array($to, self::ALLOWED[$from], true);
    }

    /** @return string[] */
    public static function nextStates(string $from): array
    {
        return self::isValid($from) ? self::ALLOWED[$from] : [];
    }
}
