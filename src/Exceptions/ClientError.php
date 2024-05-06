<?php

namespace StickersImporter\Exceptions;

use Longman\TelegramBot\Entities\ServerResponse;
use Monolog\Logger;

use Throwable;

class ClientError extends \Exception {

    public function __construct(
        string          $message        = '',
        int             $code           = 0,
        ?Throwable      $previous       = null,
        ?int            $userId         = null,
        ?Logger         $log            = null,
        ?ServerResponse $serverResponse = null
    ) {

        parent::__construct($message, $code, $previous);

        if ($log && $serverResponse) {
            if ($userId === null) {
                $userId = '?';
            }

            $logMessage = __METHOD__ . ": {$userId}: couldn't upload sticker file: " . json_encode($serverResponse);

            if ($serverResponse) {
                $logMessage .= PHP_EOL . "E{$serverResponse->getErrorCode()}: {$serverResponse->getDescription()}";
            }

            $log->error($logMessage);
        }

    }

}