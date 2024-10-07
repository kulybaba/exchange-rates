<?php

namespace App\Service\BankService;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MonobankService extends BaseBankService
{
    public function __construct(
        #[Autowire(service: 'http_client')]
        private HttpClientInterface $client,

        #[Autowire(env: 'MONOBANK_API_URL')]
        private string $apiUrl,

        #[Autowire(env: 'json:CURRENCY_CODES')]
        private array $currencyCodes,
    ) {
        parent::__construct($this->client, $this->apiUrl, $this->currencyCodes);
    }

    protected function filterContent(array $content): array
    {
        return array_filter($content, function ($currencyRate) {
            return in_array($currencyRate['currencyCodeA'], array_values($this->currencyCodes))
                && $currencyRate['currencyCodeB'] == $this->currencyCodes[self::CURRENCY_UAH];
        });
    }

    protected function prepareContent(array $content): array
    {
        $result = [];
        $currencyCodeToName = array_flip($this->currencyCodes);
        foreach ($content as $key => $currencyRate) {
            $result[$key] = [
                'name' => $currencyCodeToName[$currencyRate['currencyCodeA']],
                'buy' => (float) number_format(round($currencyRate['rateBuy'], 2), 2, '.', ''),
                'sell' => (float) number_format(round($currencyRate['rateSell'], 2), 2, '.', ''),
            ];
        }

        asort($result);

        return $result;
    }
}
