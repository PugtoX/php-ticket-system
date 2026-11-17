<?php
declare(strict_types=1);

namespace App;

/**
 * Turns a ticket into a reply draft.
 *
 * A separate interface so the route can be tested with a stub and so the app
 * still runs when no LLM service is configured (the route returns 503).
 */
interface Suggester
{
    /**
     * @return array{reply:?string,error:?string}
     */
    public function suggest(string $subject, string $body): array;
}
