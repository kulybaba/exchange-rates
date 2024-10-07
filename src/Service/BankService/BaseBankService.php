<?php

namespace App\Service\BankService;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class BaseBankService
{
    const CURRENCY_UAH = 'UAH';

    public function __construct(
        private HttpClientInterface $client,
        private string $apiUrl,
        private array $currencyCodes,
    ) {
    }

    public function getExchangeRates(): array
    {
        $response = $this->client->request('GET', $this->apiUrl);
        if ($response->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS) {
            throw new \Exception('Too many requests');
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \Exception('Error');
        }

        $filteredContent = $this->filterContent($response->toArray());

        return $this->prepareContent($filteredContent);
    }

    protected function filterContent(array $content): array
    {
        return array_filter($content, function ($currencyRate) {
            return in_array($currencyRate['ccy'], array_keys($this->currencyCodes))
                && $currencyRate['base_ccy'] === self::CURRENCY_UAH;
        });
    }

    protected function prepareContent(array $content): array
    {
        $result = [];
        foreach ($content as $key => $currencyRate) {
            $result[$key] = [
                'name' => $currencyRate['ccy'],
                'buy' => (float) number_format(round($currencyRate['buy'], 2), 2, '.', ''),
                'sell' => (float) number_format(round($currencyRate['sale'], 2), 2, '.', ''),
            ];
        }

        asort($result);

        return $result;
    }
}
