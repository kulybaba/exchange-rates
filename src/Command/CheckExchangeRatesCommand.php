<?php

namespace App\Command;

use App\Service\BankService\MonobankService;
use App\Service\BankService\PrivatbankService;
use App\Service\MailerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use SymfonyBundles\RedisBundle\Redis\ClientInterface;

#[AsCommand(
    name: 'app:check-rates',
    aliases: ['app:check-exchange-rates'],
    description: <<<EOT
        The first run of the console command will just save data about exchange rates,
        all next runs of the console command will track changes in exchange rates.
    EOT,
)]
class CheckExchangeRatesCommand extends Command
{
    public function __construct(
        private SerializerInterface $serializer,
        private ClientInterface $redisClient,
        private MailerService $mailerService,
        private PrivatbankService $privatbankService,
        private MonobankService $monobankService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Example: app:check-rates 42.45 USD')
            ->addArgument('threshold', InputArgument::REQUIRED, 'Threshold of exchange rates (42.45)')
            ->addArgument('name', InputArgument::OPTIONAL, 'Currency name (USD, EUR)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $newPrivatbankRates = $this->privatbankService->getExchangeRates();
        $newMonobankRates = $this->monobankService->getExchangeRates();
        $newPrivatbankRatesJson = $this->serializer->serialize($newPrivatbankRates, JsonEncoder::FORMAT);
        $newMonobankRatesJson = $this->serializer->serialize($newMonobankRates, JsonEncoder::FORMAT);

        $storedPrivatbankRates = $this->redisClient->get('privatbank');
        $storedMonobankRates = $this->redisClient->get('monobank');
        if (empty($storedPrivatbankRates) && empty($storedMonobankRates)) {
            $this->redisClient->set('privatbank', $newPrivatbankRatesJson);
            $this->redisClient->set('monobank', $newMonobankRatesJson);

            $io->info('Saved new exchange rates for tracking');

            return Command::SUCCESS;
        }

        if (
            $storedPrivatbankRates === $newPrivatbankRatesJson
            && $storedMonobankRates === $newMonobankRatesJson
        ) {
            $io->info('No changes');

            return Command::SUCCESS;
        }

        $currencyName = $input->getArgument('name');
        $currencyThreshold = (float) number_format(round($input->getArgument('threshold'), 2), 2, '.', '');
        $result = [
            'privatbank' => $this->getChangedCurrencyRates(
                $newPrivatbankRates,
                json_decode($storedPrivatbankRates, true),
                $currencyThreshold,
                $currencyName
            ),
            'monobank' => $this->getChangedCurrencyRates(
                $newMonobankRates,
                json_decode($storedMonobankRates, true),
                $currencyThreshold,
                $currencyName
            ),
        ];

        if (!empty($result['privatbank']) || !empty($result['monobank'])) {
            $this->redisClient->set('privatbank', $newPrivatbankRatesJson);
            $this->redisClient->set('monobank', $newMonobankRatesJson);

            $this->mailerService->sendCurrencyRatesMail($result);
        }

        $io->success('SUCCESS');

        return Command::SUCCESS;
    }

    private function getChangedCurrencyRates(
        array $newCurrencyRates,
        array $storedCurrencyRates,
        string $currencyThreshold,
        ?string $currencyName = null
    ): array {
        $result = [];
        foreach ($newCurrencyRates as $newCurrencyRate) {
            if (!empty($currencyName) && $currencyName !== $newCurrencyRate['name']) {
                continue;
            }

            $storedCurrencyRate = $this->getCurrencyRateByName($newCurrencyRate['name'], $storedCurrencyRates);
            if ($newCurrencyRate['buy'] >= $currencyThreshold) {
                if ($newCurrencyRate['buy'] > $storedCurrencyRate['buy'] && $storedCurrencyRate['buy'] < $currencyThreshold) {
                    $result[$newCurrencyRate['name']]['buy'] = $newCurrencyRate['buy'];
                }
            } else {
                if ($newCurrencyRate['buy'] < $storedCurrencyRate['buy'] && $storedCurrencyRate['buy'] > $currencyThreshold) {
                    $result[$newCurrencyRate['name']]['buy'] = $newCurrencyRate['buy'];
                }
            }

            if ($newCurrencyRate['sell'] >= $currencyThreshold) {
                if ($newCurrencyRate['sell'] > $storedCurrencyRate['sell'] && $storedCurrencyRate['sell'] < $currencyThreshold) {
                    $result[$newCurrencyRate['name']]['sell'] = $newCurrencyRate['sell'];
                }
            } else {
                if ($newCurrencyRate['sell'] < $storedCurrencyRate['sell'] && $storedCurrencyRate['sell'] > $currencyThreshold) {
                    $result[$newCurrencyRate['name']]['sell'] = $newCurrencyRate['sell'];
                }
            }
        }

        return $result;
    }

    private function getCurrencyRateByName(string $currencyName, array $currencyRates): array
    {
        $result = [];
        foreach ($currencyRates as $currencyRate) {
            if ($currencyRate['name'] === $currencyName) {
                $result = $currencyRate;
                break;
            }
        }

        return $result;
    }
}
