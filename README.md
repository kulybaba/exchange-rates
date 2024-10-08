### Setup:

```shell
git clone git@github.com:kulybaba/exchange-rates.git
```

```shell
docker compose up -d
```

```shell
docker compose exec app sh -c 'composer install'
```

### Start worker to async process and send emails:

```shell
docker compose exec app sh -c 'php bin/console messenger:consume async -vv'
```

### Run console command:

Set variable `MAILER_TO` (email for notifications) in `.env` or run console command with `MAILER_TO` variable:

```shell
docker compose exec app sh -c 'MAILER_TO=test@mail.com php bin/console app:check-rates 0.25'
```

With the currency name argument:

```shell
docker compose exec app sh -c 'MAILER_TO=test@mail.com php bin/console app:check-rates 0.25 USD'
```

> The first run of the console command will just save data about exchange rates, all next runs of the console command will track changes in exchange rates.

### To testing, change the Redis data and run the console command again:

```shell
docker exec -it redis redis-cli set privatbank '[{"name":"EUR","buy":30.00,"sell":30.00},{"name":"USD","buy":30.00,"sell":30.00}]'
```

```shell
docker exec -it redis redis-cli set monobank '[{"name":"EUR","buy":35.00,"sell":35.00},{"name":"USD","buy":35.00,"sell":35.00}]'
```
