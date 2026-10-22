<?php
declare(strict_types=1);
namespace App\Billing;

use DomainException;

/** A payment that cannot be applied to an invoice; $reason is a payment_import_rows status code. */
final class PaymentRejectedException extends DomainException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
