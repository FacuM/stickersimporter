<?php

namespace StickersImporter\Core;

use StickersImporter\Exceptions\AttachmentDownloadError;
use StickersImporter\Exceptions\ClientError;

use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

use Monolog\Logger;

use Throwable;

class ServerResponseHandler extends SystemCommand
{

    private int     $userId;
    private Message $message;

    private ?Logger $log = null;

    private string  $stickerSetId;

    private array $createdFiles = [];

    /**
     * addCreatedFile
     *
     * @param  string $absolutePath The absolute path to the file
     * @return string The same path provided as an input
     */
    private function addCreatedFile(string $absolutePath): string {
        $this->createdFiles[] = $absolutePath;

        return $absolutePath;
    }

    /**
     * downloadFile
     * 
     * @throws AttachmentDownloadError
     *
     * @param  string $fileId Telegram servers' file ID
     * @return string The local path
     */
    private function downloadFile(string $fileId): string {
        $file = Request::getFile([ 'file_id' => $fileId ]);

        if (!$file->isOk() || !Request::downloadFile($file->getResult())) {
            throw new AttachmentDownloadError('Failed to download attachment, please try again later.');
        }

        $absolutePath = $this->telegram->getDownloadPath() . '/' . $file->getResult()->getFilePath();

        $this->log->debug( __METHOD__ . ": {$this->userId}: file with ID {$fileId} saved temporarily as {$absolutePath}." );

        return $this->addCreatedFile(absolutePath: $absolutePath);
    }
    
    /**
     * uploadStickerPlaceholder
     * 
     * @throws ClientError
     *
     * @param  string $absolutePath The absolute path to the image uploaded by the user
     * @return string The sticker file ID generated
     */
    private function uploadStickerPlaceholder(string $absolutePath): string {
        $uploadStickerFileResponse = Request::uploadStickerFile([
            'user_id'        => $this->userId,
            'sticker'        => $this->convertToSticker($absolutePath),
            'sticker_format' => 'static'
        ]);

        if (!$uploadStickerFileResponse->isOk()) {
            throw new ClientError('Something went wrong.');
        }

        return $uploadStickerFileResponse->getResult()->getProperty('file_id');
    }

    private function stickerSetExists(): bool {
        return Request::getStickerSet([ 'name' => $this->stickerSetId ])->isOk();
    }

    private function buildStickerEntity(string $stickerFileId): array {
        return [
            'sticker'    => $stickerFileId,
            'format'     => 'static',
            'emoji_list' => ['💻']
        ];
    }
    
    /**
     * createNewStickerSet
     * 
     * @throws ClientError
     *
     * @param  string $stickerFileId
     * @return void
     */
    private function createNewStickerSet(string $stickerFileId): void {
        $createNewSticketSetResponse = Request::createNewStickerSet([
            'user_id'   => $this->userId,
            'name'      => $this->stickerSetId,
            'title'     => $this->stickerSetId,
            'stickers'  => [ $this->buildStickerEntity(stickerFileId: $stickerFileId) ]
        ]);

        if (!$createNewSticketSetResponse->isOk()) {
            throw new ClientError('Couldn\'t create sticker pack, please try again later.');
        }
    }
    
    /**
     * addStickerToSet
     * 
     * @throws ClientError
     *
     * @param  string $stickerFileId
     * @return void
     */
    private function addStickerToSet(string $stickerFileId): void {
        $addStickerToSetResponse = Request::addStickerToSet([
            'user_id'   => $this->userId,
            'name'      => $this->stickerSetId,
            'sticker'   => $this->buildStickerEntity(stickerFileId: $stickerFileId)
        ]);

        if (!$addStickerToSetResponse->isOk()) {
            throw new ClientError('Couldn\'t add sticker to pack, please try again later.');
        }
    }

    private function uploadStickerToSet(string $fileId): void {
        if ($this->stickerSetExists()) {
            $this->addStickerToSet(stickerFileId: $fileId);
        } else {
            $this->createNewStickerSet(stickerFileId: $fileId);
        }
    }

    private function handleSuccess(): ServerResponse {
        return $this->replyToChat("There you go! Check https://t.me/addstickers/{$this->stickerSetId}");
    }

    /**
     * convertToSticker
     *
     * @param  string $filePath The local file path
     * @return string The new path pointing to the converted result
     */
    private function convertToSticker(string $filePath): string {
        // create image manager with desired driver
        $manager = new ImageManager( new Driver() );

        // open an image file
        $image = $manager->read($filePath);

        // resize image instance
        $image->contain(width: 512, height: 512);

        $newPath = "{$filePath}.webp";

        // save encoded image
        $image->toWebp()->save(filepath: $newPath);

        return $this->addCreatedFile(absolutePath: $newPath);
    }

    private function trySetClientBusyStatus(string $action): void {
        $sendChatActionResponse = Request::sendChatAction([
            'chat_id'   => $this->userId,
            'action'    => $action
        ]);

        if (!$sendChatActionResponse->isOk()) {
            $this->log->warning( __METHOD__ . ": {$this->userId}: couldn't set chat action: " . json_encode($sendChatActionResponse) );
        }
    }

    /**
     * Constructor
     *
     * @param Telegram    $telegram
     * @param Update|null $update
     * @param Logger      $log
     */
    public function __construct(Telegram $telegram, Logger $log, ?Update $update = null)
    {
        $this->telegram = $telegram;

        if ($update !== null) {
            $this->setUpdate($update);
        }

        $this->config = $telegram->getCommandConfig($this->name);

        $this->log = $log;

        $this->message  = $this->getMessage();

        $from = $this->message->getFrom();

        $this->userId       = $from->getId();
        $this->stickerSetId = $from->getUsername() . '_by_' . $this->telegram->getBotUsername();
    }

    public function __destruct() {
        foreach ($this->createdFiles as $createdFile) {
            $this->log->debug( __METHOD__ . ": {$this->userId}: removing temporary file at {$createdFile}..." );

            if (unlink($createdFile)) {
                $this->log->info( __METHOD__ . ": {$this->userId}: removed temporary file at {$createdFile}!" );
            } else {
                $this->log->warning( __METHOD__ . ": {$this->userId}: couldn't remove temporary file at {$createdFile}!" );
            }
        }
    }
    
    /**
     * handle
     *
     * @throws ClientError
     * 
     * @return ServerResponse
     */
    public function handle(): ServerResponse {
        $messageType = $this->message->getType();

        if (!in_array($messageType, ['document', 'photo'], true)) {
            throw new ClientError("Please upload one or more images to convert them into stickers, a message with type \"{$messageType}\" wasn't expected.");
        }

        $document = $this->message->{'get' . ucfirst($messageType)}();

        // For photos, get the best quality!
        ($messageType === 'photo') && $document = end($document);

        $attachmentMimeType = $document->getMimeType();

        if (
            $messageType !== 'photo'
            &&
            !str_starts_with($attachmentMimeType, 'image/')
        ) {
            throw new ClientError("Please upload one or more images to convert them into stickers, a message containing a file with MIME \"{$attachmentMimeType}\" wasn't expected.");
        }

        $this->trySetClientBusyStatus(action: 'choose_sticker');

        $originalFilePath = $this->downloadFile(fileId: $document->getFileId());
        $stickerFileId    = $this->uploadStickerPlaceholder(absolutePath: $originalFilePath);

        $this->uploadStickerToSet(fileId: $stickerFileId);

        return $this->handleSuccess();
    }

    /**
     * Main command execution
     *
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute(): ServerResponse {
        try {
            return $this->handle();
        } catch (ClientError $clientError) {
            return $this->replyToChat(text: $clientError->getMessage());

            $this->log->error("{$this->userId} request failed with client error \"{$clientError->getMessage()}\".");
        } catch (Throwable $throwable) {
            return $this->replyToChat(text: 'Something went wrong, please try again later.');

            $this->log->critical(
                "{$this->userId} request failed with internal error \"{$throwable->getMessage()}\" in {$throwable->getFile()}:{$throwable->getLine()}" . PHP_EOL .
                $throwable->getTraceAsString() .
                '========== END OF TRACE ==========' . PHP_EOL .
                'message: ' . json_encode($this->message)
            );
        }
    }
}