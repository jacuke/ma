<?php

namespace App\Service;

use App\Util\Constants;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClientService implements LoggerAwareInterface {

    private HttpClientInterface $client;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $client) {
        $this->client = $client;
    }

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

    public function downloadFile (string $url, string $file): int {

        $status = Constants::STATUS_OK;

        $handle = fopen($file , 'wb');
        if($handle===false) {
            $status = Constants::STATUS_ERROR;
        } else {

            try {
                $response = $this->client->request('GET', $url);
            } catch (ExceptionInterface $e) {
                $response = null;
                $status = Constants::STATUS_ERROR;
                $this->logger->error("Download failed for $url: " . $e->getMessage());
            }
            if($response) {
                foreach ($this->client->stream($response) as $chunk) {
                    try {
                        fwrite($handle, $chunk->getContent());
                    } catch (ExceptionInterface $e) {
                        $this->logger->error("Can't save to file: " . $e->getMessage());
                        $status = Constants::STATUS_ERROR;
                        break;
                    }
                }
            }
            fclose($handle);
        }

        return $status;
    }

    public function downloadUrlAsText (string $url): string {

        try {
            $response = $this->client->request('GET', $url);
        } catch (ExceptionInterface) {
            return '';
        }

        try {
            return $response->getContent();
        } catch (ExceptionInterface) {
            return '';
        }
    }
}