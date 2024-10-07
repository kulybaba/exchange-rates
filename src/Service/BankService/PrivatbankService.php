<?php

namespace App\Service\BankService;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PrivatbankService extends BaseBankService
{
    public function __construct(
        #[Autowire(service: 'http_client')]
        private HttpClientInterface $client,

        #[Autowire(env: 'PRIVATBANK_API_URL')]
        private string $apiUrl,

        #[Autowire(env: 'json:CURRENCY_CODES')]
        private array $currencyCodes,
    ) {
        parent::__construct($this->client, $this->apiUrl, $this->currencyCodes);
    }
}
