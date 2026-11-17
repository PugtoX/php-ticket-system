<?php
declare(strict_types=1);

namespace Tests;

use App\TicketStatus;
use PHPUnit\Framework\TestCase;

final class TicketStatusTest extends TestCase
{
    public function testValidStates(): void
    {
        self::assertTrue(TicketStatus::isValid('open'));
        self::assertFalse(TicketStatus::isValid('deleted'));
        self::assertFalse(TicketStatus::isValid(''));
    }

    public function testRejectsSameStateTransition(): void
    {
        self::assertFalse(TicketStatus::canTransition('open', 'open'));
        self::assertFalse(TicketStatus::canTransition('closed', 'closed'));
    }

    public function testOpenCanMoveToInProgressOrClosed(): void
    {
        self::assertTrue(TicketStatus::canTransition('open', 'in_progress'));
        self::assertTrue(TicketStatus::canTransition('open', 'closed'));
        self::assertFalse(TicketStatus::canTransition('open', 'archived'));
    }

    public function testClosedCanOnlyReopen(): void
    {
        self::assertTrue(TicketStatus::canTransition('closed', 'open'));
        self::assertFalse(TicketStatus::canTransition('closed', 'in_progress'));
    }

    public function testNextStatesMatchesTransitionTable(): void
    {
        self::assertSame(['in_progress', 'closed'], TicketStatus::nextStates('open'));
        self::assertSame(['open'], TicketStatus::nextStates('closed'));
        self::assertSame([], TicketStatus::nextStates('nonsense'));
    }
}
