<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExchangeRateService
{
    public function rates(string $baseCurrency = 'USD'): array
    {
        $baseCurrency = strtoupper($baseCurrency);

        return Cache::remember(
            "exchange_rates_{$baseCurrency}",
            now()->addHours(1),
            function () use ($baseCurrency) {

                $url = sprintf(
                    '%s/%s/latest/%s',
                    config('services.exchange_rate.url'),
                    config('services.exchange_rate.key'),
                    $baseCurrency
                );

                $response = Http::timeout(10)
                    ->get($url);

                if ($response->failed()) {
                    throw new RuntimeException(
                        __('api.exchange_rate.unavailable')
                    );
                }

                $data = $response->json();

                if (($data['result'] ?? null) !== 'success') {
                    throw new RuntimeException(
                        __('api.exchange_rate.request_failed')
                    );
                }

                return $data['conversion_rates'];
            }
        );
    }

    public function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $rates = $this->rates($from);

        if (! isset($rates[$to])) {
            throw new RuntimeException(
                __('api.exchange_rate.currency_not_supported', ['currency' => $to])
            );
        }

        return $amount * $rates[$to];
    }
}
