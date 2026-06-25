<?php

namespace App\Exceptions\Reservation;

use App\Exceptions\DomainException;

final class SlotUnavailableException extends DomainException
{
    public function __construct(string $message = 'Ce créneau n\'est plus disponible, choisissez-en un autre.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'SLOT_UNAVAILABLE';
    }
}
