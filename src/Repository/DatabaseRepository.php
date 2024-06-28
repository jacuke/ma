<?php

namespace App\Repository;

use App\Service\DataService;
use App\Util\Constants;
use App\Util\SqlInsertEncoder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Serializer\Serializer;

class DatabaseRepository {

    private Connection $connection;
    private DataService $dataService;

    public function __construct(
        Connection  $connection,
        DataService $dataService
    ) {
        $this->connection = $connection;
        $this->dataService = $dataService;
    }

    // todo: return type
    private function execute_wrapper(string $sql) : bool {

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

    public function tableExists(string $table): bool {

        $sql = sprintf("
            SELECT *
            FROM information_schema.tables
            WHERE table_schema = 'bfarmer'
            AND table_name = '%s'
            LIMIT 1;
        ", $table);

        try {
            if($this->connection->executeQuery($sql)->fetchOne()===false) {
                return false;
            } else {
                return true;
            }
        } catch (Exception $e) {
            var_dump($e->getMessage());
            return false;
        }
    }

    public function addConfigTable(): bool {

        $sql = sprintf("
            CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `key` VARCHAR(255),
                `status` VARCHAR(255),
                CONSTRAINT config_index PRIMARY KEY (`id`),
                CONSTRAINT type_key UNIQUE (`key`)
            );
        ", Constants::TABLE_CONFIG);

        return $this->execute_wrapper($sql);
    }

    public function writeConfig(string $key, string $status = '') : bool {

        $sql = sprintf("
            INSERT IGNORE INTO `%s` (`key`, `status`)
            VALUES('%s', '%s');
        ", Constants::TABLE_CONFIG, $key, $status);

        return $this->execute_wrapper($sql);
    }

    public function updateConfigStatus(string $key, $status) : bool {

        $sql = sprintf("
            UPDATE `%s`
            SET `status` = '%s'
            WHERE `key` = '%s';
        ", Constants::TABLE_CONFIG, $status, $key);

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

    private function table_structure (string $type, int $table_type, string $year, string $table):string {

        $code_length = match($type) {
            Constants::ICD10GM => 7,
            Constants::OPS => 8,
            default => 255
        };

        return match ($table_type) {
            Constants::TABLE_CODES => sprintf("
                CREATE TABLE `%s` (
                    `%s` VARCHAR(%d),
                    `%s` VARCHAR(255),
                    CONSTRAINT `key_%s_%s` PRIMARY KEY (`code`)
                )",
                $table,
                Constants::SQL_CODE, $code_length,
                Constants::SQL_NAME,
                $type,
                $year
            ),
            Constants::TABLE_UMSTEIGER => sprintf("
                CREATE TABLE `%s` (
                    `%s` VARCHAR(%d),
                    `%s` VARCHAR(%d),
                    `%s` VARCHAR(1),
                    `%s` VARCHAR(1),
                    CONSTRAINT `key_%s_%s_%s_umsteiger` PRIMARY KEY (`old`, `new`)
                )",
                $table,
                Constants::SQL_OLD, $code_length,
                Constants::SQL_NEW, $code_length,
                Constants::SQL_AUTO,
                Constants::SQL_AUTO_R,
                $type,
                $year,
                $this->dataService->getPreviousYear($type, $year)
            ),
        };
    }

    // todo: return type
    public function addTable (string $type, string $year, int $table_type, array $data): bool {

        $table = match($table_type) {
            Constants::TABLE_CODES =>
                Constants::table_name($type, $year),
            Constants::TABLE_UMSTEIGER =>
                Constants::table_name_umsteiger($type, $year, $this->dataService->getPreviousYear($type, $year)),
        };

        if($this->tableExists($table)) {
            return true;
        }

        $sql = $this->table_structure($type, $table_type, $year, $table);
        if($sql===Constants::CONFIG_STATUS_ERROR) {
            return false;
        }
        try {
            $this->connection->executeQuery($sql);
        } catch (Exception $e) {
            var_dump($e->getMessage());
            return false;
        }

        $insert_serializer = new Serializer([], [new SqlInsertEncoder()]);

        $clean_values = match ($table_type) {
            Constants::TABLE_CODES => [
                1 => function($in) {return Constants::sql_clean_name($in);}
            ],
            Constants::TABLE_UMSTEIGER => []
        };

        // insert in chunks of 50
        foreach (array_chunk($data, 50) as $chunk) {

            if (count($chunk) > 0) {

                $insert = $insert_serializer->encode($chunk, 'sql', [
                    SqlInsertEncoder::TABLE_NAME => $table,
                    SqlInsertEncoder::CLEAN_VALUES_INDEX => $clean_values
                ]);

                try {
                    $this->connection->executeQuery($insert);
                } catch (Exception $e) {
                    var_dump($e->getMessage());
                    return false;
                }
            }
        }

        return true;
    }

    private function select (string $type, int $table_type, string $year, string $prev, string $search=''):string {

        $table = match($table_type) {
            Constants::TABLE_CODES =>
                Constants::table_name($type, $year),
            Constants::TABLE_UMSTEIGER, Constants::TABLE_UMSTEIGER_JOIN =>
                Constants::table_name_umsteiger($type, $year, $prev),
        };

        $select = match($table_type) {
            Constants::TABLE_CODES => sprintf(
                "
                SELECT `%s`, `%s`
                FROM `%s`
                ",
                Constants::SQL_CODE, Constants::SQL_NAME,
                $table
            ),
            Constants::TABLE_UMSTEIGER => sprintf(
                "
                SELECT `%s`, `%s`, `%s`, `%s`
                FROM `%s`
                WHERE (`%s` != `%s`
                OR `%s` != 'A'
                OR `%s` != 'A')
                ",
                Constants::SQL_OLD, Constants::SQL_NEW, Constants::SQL_AUTO, Constants::SQL_AUTO_R,
                $table,
                Constants::SQL_OLD, Constants::SQL_NEW,
                Constants::SQL_AUTO,
                Constants::SQL_AUTO_R
            ),
            Constants::TABLE_UMSTEIGER_JOIN => sprintf(
                "
                SELECT  u.`%s`, u.`%s`,
                        u.`%s`, u.`%s`,                       
                        o.`%s` old_name, n.`%s` new_name
                FROM `%s` u
                JOIN `%s` o ON u.`%s` = o.`%s`
                JOIN `%s` n ON u.`%s` = n.`%s`
                WHERE (`%s` != `%s`
                OR `%s` != 'A'
                OR `%s` != 'A')
                ",
            Constants::SQL_OLD, Constants::SQL_NEW,
                Constants::SQL_AUTO, Constants::SQL_AUTO_R,
                Constants::SQL_NAME, Constants::SQL_NAME,
                $table,
                Constants::table_name($type, $prev), Constants::SQL_OLD, Constants::SQL_CODE,
                Constants::table_name($type, $year), Constants::SQL_NEW, Constants::SQL_CODE,
                Constants::SQL_OLD, Constants::SQL_NEW,
                Constants::SQL_AUTO,
                Constants::SQL_AUTO_R
            ),
        };

        if($search!==''){
            $where = match($table_type) {
                Constants::TABLE_CODES => sprintf(
                    "
                WHERE `%s` like '%%%s%%'
                ",
                    Constants::SQL_CODE, $search
                ),
                Constants::TABLE_UMSTEIGER, Constants::TABLE_UMSTEIGER_JOIN => sprintf(
                    "
                AND `%s` like '%%%s%%'
                ",
                    Constants::SQL_NEW, $search
                ),
            };
            $select .= ' ' . $where;
        }

        return $select;
    }

    public function readData(string $type, int $table_type, string $year, string $prev='', string $search='') :array {

        if($prev==='' &&
            ($table_type===Constants::TABLE_UMSTEIGER || $table_type===Constants::TABLE_UMSTEIGER_JOIN)
        ) {
            $prev = $this->dataService->getPreviousYear($type, $year);
        }

        $sql = $this->select($type, $table_type, $year, $prev, $search);

        try {
            return $this->connection->executeQuery($sql)->fetchAllAssociative();
        } catch (Exception) {
            // todo: log error
            return [];
        }
    }

    public function readTerminalCodes(string $type, string $year, string $search = '') :array {

        $data = $this->readData($type, Constants::TABLE_CODES, $year, '', $search);
        foreach($data as $k => $v) {
            $current = $v['code'];
            $next = next($data)['code'] ?? '';
            if(str_contains($next, $current) &&
                (strlen($next) > strlen($current))) {
                unset($data[$k]);
            }
        }
        return array_values($data);
    }

    public function readUmsteigerHistory(string $type, string $begin_year, string $search = '') :array {

        $ret = array();
        $year = $begin_year;
        $prev = $this->dataService->getPreviousYear($type, $year);
        while($prev!=='') {
            $umsteiger_in = $this->readData($type, Constants::TABLE_UMSTEIGER_JOIN, $year, $prev, $search);
            if(count($umsteiger_in) > 0) {
                $umsteiger_out = array();
                foreach($umsteiger_in as $find) {
                    if($find['old']!==Constants::UNDEF) {
                        $history = $this->readUmsteigerHistory($type, $prev, $find['old']);
                        if(count($history)>0) {
                            $find['history'] = $history;
                        }
                        $umsteiger_out[] = $find;
                    } else {
                        array_unshift($umsteiger_out, $find);
                    }
                }
                $ret['umsteiger'] = $umsteiger_out;
                $ret['year'] = $year;
            }
            $year = $prev;
            $prev = $this->dataService->getPreviousYear($type, $year);
        }

        return $ret;
    }

    public function countCodes (string $type, string $year):int {

        $table = Constants::table_name($type, $year);

        $sql = "SELECT COUNT(*) FROM `$table`";

        try {
            return $this->connection->executeQuery($sql)->fetchOne();
        } catch (Exception) {
            // todo: log error
            return 0;
        }
    }
}