<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Payment\Providers\CashOnDeliveryProvider;
use App\Domain\Payment\Providers\ManualBankTransferProvider;
use App\Domain\Payment\Providers\MockOnlineProvider;
use App\Domain\Payment\Providers\PaymentProvider;
use Illuminate\Container\Container;
use RuntimeException;

/**
 * Looks up a PaymentProvider implementation by name (matching
 * payment_methods.provider). Resolves through the IoC container so providers
 * can have constructor dependencies (HTTP clients, signing keys from .env).
 *
 * To add a real gateway in a sub-phase: implement PaymentProvider, register
 * the class here, add a payment_methods row with the matching provider value.
 */
class PaymentProviderRegistry
{
    /** @var array<string, class-string<PaymentProvider>> */
    protected array $providers = [
        'cod'             => CashOnDeliveryProvider::class,
        'manual_transfer' => ManualBankTransferProvider::class,
        'online_mock'     => MockOnlineProvider::class,
    ];

    public function resolve(string $name): PaymentProvider
    {
        if (! isset($this->providers[$name])) {
            throw new RuntimeException("Unknown payment provider: {$name}. Available: " . implode(', ', array_keys($this->providers)));
        }
        return Container::getInstance()->make($this->providers[$name]);
    }

    /** @return array<string, class-string<PaymentProvider>> */
    public function all(): array
    {
        return $this->providers;
    }

    public function register(string $name, string $class): void
    {
        if (! is_subclass_of($class, PaymentProvider::class)) {
            throw new RuntimeException("{$class} must implement PaymentProvider.");
        }
        $this->providers[$name] = $class;
    }
}
