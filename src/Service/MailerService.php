<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class MailerService
{
    public function __construct(
        private MailerInterface $mailer,

        private BodyRendererInterface $bodyRenderer,

        #[Autowire(env: 'MAILER_FROM')]
        private string $from,

        #[Autowire(env: 'MAILER_TO')]
        private string $to,
    ) {
    }

    public function sendCurrencyRatesMail(array $currencyRates): void
    {
        $email = (new TemplatedEmail())
            ->from($this->from)
            ->to(new Address($this->to))
            ->subject('New currency rates')
            ->htmlTemplate('emails/currency_rates.html.twig')
            ->context($currencyRates);

        $this->bodyRenderer->render($email);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
