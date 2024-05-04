#!/usr/bin/env php

<?php

use StickersImporter\Core\ServerResponseHandler;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey     = $_ENV['BOT_API_KEY']  ?? '';
$username   = $_ENV['BOT_USERNAME'] ?? '';

if (!trim($apiKey) || !trim($username)) {
    echo 'Missing API key or username.';

    exit(1);
}

try {

    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram(
        api_key:        $apiKey,
        bot_username:   $username
    );

    $telegram->useGetUpdatesWithoutDatabase();
    $telegram->setDownloadPath('/tmp');

    $stream = new StreamHandler('/var/log/stickersimporter/system.log', Level::fromName($_ENV['LOG_LEVEL']));
    $stream->setFormatter(new LineFormatter(format: "[%datetime%] %channel%.%level_name%: %message%\n"));

    $log = new Logger('system');
    $log->pushHandler($stream);

    while (true) {
        echo date('Y-m-d H:i:s') . ' - PING!' . PHP_EOL;

        // Handle telegram getUpdates request
        $response = $telegram->handleGetUpdates();

        if (!$response->isOk()) {
            echo $response->printError();

            return;
        }

        foreach ($response->getResult() as $entity) {
            $handler = new ServerResponseHandler(
                telegram:   $telegram,
                update:     $entity,
                log:        $log
            );

            $handler->execute();

            unset($handler);
        }

        echo date('Y-m-d H:i:s') . ' - PONG! ' . count($response->getResult()) . ' updates processed.' . PHP_EOL;

        sleep(1);
    }

} catch (Throwable $throwable) {
    echo "{$throwable->getMessage()} in {$throwable->getFile()}:{$throwable->getLine()}" . PHP_EOL .
         $throwable->getTraceAsString();
}