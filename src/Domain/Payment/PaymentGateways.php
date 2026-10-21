<?php

declare(strict_types=1);

namespace Domain\Payment;

use Domain\Payment\Exception\PaymentGatewayUnavailable;

/**
 * The configured payment gateways. New charges go to the default one; a refund
 * must go back to whichever gateway took the original charge.
 */
final readonly class PaymentGateways
{
    /** @var array<string, PaymentGateway> */
    private array $byName;

    public function __construct(private PaymentGateway $default, PaymentGateway ...$others)
    {
        $byName = [];
        foreach ([$default, ...$others] as $gateway) {
            $byName[$gateway->name()] = $gateway;
        }

        $this->byName = $byName;
    }

    public function default(): PaymentGateway
    {
        return $this->default;
    }

    public function named(string $name): PaymentGateway
    {
        return $this->byName[$name] ?? throw PaymentGatewayUnavailable::because($name, 'gateway is not configured');
    }
}
