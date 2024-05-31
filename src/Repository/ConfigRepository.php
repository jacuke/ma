<?php

namespace App\Repository;

use App\Util\Constants;
use Doctrine\DBAL\Exception;

class ConfigRepository extends DatabaseRepository {

    public function addConfigTable(): bool {

        $sql = sprintf("
            CREATE TABLE IF NOT EXISTS `%s` (
                `key` VARCHAR(255),
                `status` VARCHAR(255),
                CONSTRAINT key_index PRIMARY KEY (`key`)
            );
        ", Constants::TABLE_CONFIG);

        return $this->execute_wrapper($sql);
    }

    public function writeConfig(string $key, string $status = '') : bool {

        $sql = sprintf("
            INSERT IGNORE INTO `%s` (`key`, `status`)
            VALUES('%s', '%s')
            ON DUPLICATE KEY UPDATE `status` = '%s';
        ", Constants::TABLE_CONFIG, $key, $status, $status);

        return $this->execute_wrapper($sql);
    }

    public function readConfigStatus(string $key) : string {

        $sql = sprintf("
            SELECT `status` FROM `%s`
            WHERE `key` = '%s';
        ", Constants::TABLE_CONFIG, $key);

        try {
            $status = $this->connection->executeQuery($sql)->fetchOne();
            if($status===false) {
                return Constants::CONFIG_STATUS_NOT_FOUND;
            } else {
                return $status;
            }
        } catch (Exception $e) {
            var_dump($e->getMessage());
            return Constants::CONFIG_STATUS_QUERY_ERROR;
        }
    }
}