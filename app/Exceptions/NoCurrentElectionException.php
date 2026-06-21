<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when no election is currently in an operational status
 * (active, submitting, certifying). Per business rule #8: if no
 * election is in one of these statuses, election-related operations
 * must be blocked with a clear validation message rather than silently
 * falling back to a stale or arbitrary election.
 */
class NoCurrentElectionException extends Exception
{
    public function __construct(string $message = 'No election is currently active, submitting, or certifying. Election-related operations are unavailable until an election enters one of these statuses.')
    {
        parent::__construct($message);
    }
}