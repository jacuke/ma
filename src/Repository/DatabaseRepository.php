<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class DatabaseRepository implements LoggerAwareInterface {

    protected LoggerInterface $logger;

    public function __construct(protected readonly Connection $connection) {}

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

    // todo: return type
    protected function execute_wrapper(string $sql) : bool {

        try {
            $this->connection->executeQuery($sql);
            return true;
        } catch (Exception $e) {
            var_dump($e->getMessage());
            return false;
        }
    }

    public function dropTable(string $table) : bool {

        $sql = sprintf("
            DROP TABLE IF EXISTS `%s`;
        ", $table);

        return $this->execute_wrapper($sql);
    }
}