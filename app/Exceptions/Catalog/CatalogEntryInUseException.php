<?php

namespace App\Exceptions\Catalog;

use App\Exceptions\DomainException;

final class CatalogEntryInUseException extends DomainException
{
    private function __construct(private readonly string $entryCode, string $message)
    {
        parent::__construct($message);
    }

    public static function cuisineType(): self
    {
        return new self('CUISINE_TYPE_IN_USE', 'This cuisine type cannot be deleted while restaurants are using it.');
    }

    public static function allergen(): self
    {
        return new self('ALLERGEN_IN_USE', 'This allergen cannot be deleted while menu items are using it.');
    }

    public function statusCode(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return $this->entryCode;
    }
}
