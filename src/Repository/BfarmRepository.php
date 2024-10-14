<?php
/** @noinspection SqlType */
/** @noinspection SqlResolve */

namespace App\Repository;

use App\Service\DataService;
use App\Util\Constants;
use App\Util\SqlInsertEncoder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Serializer\Serializer;

class BfarmRepository extends DatabaseRepository {

    public function __construct(
        Connection  $connection,
        private readonly DataService $dataService
    ) {
        parent::__construct($connection);
    }

    private function table_structure (string $type, int $table_type, string $year, string $table):string {

        $code_length = match($type) {
            Constants::ICD10GM => 7,
            Constants::OPS => 8,
            default => 255
        };

        return match ($table_type) {
            Constants::TABLE_CODES => sprintf("
                CREATE TABLE IF NOT EXISTS `%s` (
                    `%s` VARCHAR(%d),
                    `%s` VARCHAR(255),
                    `%s` TINYINT(1),
                    CONSTRAINT `key_%s_%s` PRIMARY KEY (`code`)
                )",
                $table,
                Constants::SQL_CODE, $code_length,
                Constants::SQL_NAME,
                Constants::SQL_UMST,
                $type,
                $year
            ),
            Constants::TABLE_UMSTEIGER => sprintf("
                CREATE TABLE IF NOT EXISTS `%s` (
                    `%s` VARCHAR(%d),
                    `%s` VARCHAR(%d),
                    `%s` VARCHAR(1),
                    `%s` VARCHAR(1),
                    CONSTRAINT `key_%s_%s_%s_umsteiger` PRIMARY KEY (`new`, `old`)
                )",
                $table,
                Constants::SQL_OLD, $code_length,
                Constants::SQL_NEW, $code_length,
                Constants::SQL_AUTO,
                Constants::SQL_AUTO_R,
                $type,
                $year,
                $this->dataService->getNextOlderYear($type, $year)
            ),
        };
    }

    // todo: return type
    public function addTable (string $type, string $year, int $table_type, array $data): bool {

        $table = match($table_type) {
            Constants::TABLE_CODES =>
                Constants::table_name($type, $year),
            Constants::TABLE_UMSTEIGER =>
                Constants::table_name_umsteiger($type, $year, $this->dataService->getNextOlderYear($type, $year)),
        };

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
            Constants::TABLE_UMSTEIGER => [],
        };

        $insert_ignore = match ($table_type) {
            Constants::TABLE_CODES => false,
            Constants::TABLE_UMSTEIGER => true,
        };

        // insert in chunks of 50
        foreach (array_chunk($data, 50) as $chunk) {

            if (count($chunk) > 0) {

                $insert = $insert_serializer->encode($chunk, 'sql', [
                    SqlInsertEncoder::TABLE_NAME => $table,
                    SqlInsertEncoder::CLEAN_VALUES_INDEX => $clean_values,
                    SqlInsertEncoder::INSERT_IGNORE => $insert_ignore,
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
            Constants::TABLE_UMSTEIGER, Constants::TABLE_UMSTEIGER_JOIN, Constants::TABLE_UMSTEIGER_JOIN_REV =>
                Constants::table_name_umsteiger($type, $year, $prev),
        };

        $select = match($table_type) {
            Constants::TABLE_CODES => sprintf("
                SELECT `%s`, `%s`, `%s`
                FROM `%s`",
                Constants::SQL_CODE, Constants::SQL_NAME, Constants::SQL_UMST,
                $table
            ),
            Constants::TABLE_UMSTEIGER => sprintf("
                SELECT `%s`, `%s`, `%s`, `%s`
                FROM `%s`",
                Constants::SQL_OLD, Constants::SQL_NEW, Constants::SQL_AUTO, Constants::SQL_AUTO_R,
                $table
            ),
            Constants::TABLE_UMSTEIGER_JOIN, Constants::TABLE_UMSTEIGER_JOIN_REV => sprintf("
                SELECT  u.`%s`, u.`%s`,
                        u.`%s`, u.`%s`,                       
                        o.`%s` old_name, n.`%s` new_name
                FROM `%s` u
                JOIN `%s` o ON u.`%s` = o.`%s`
                JOIN `%s` n ON u.`%s` = n.`%s`",
            Constants::SQL_OLD, Constants::SQL_NEW,
                Constants::SQL_AUTO, Constants::SQL_AUTO_R,
                Constants::SQL_NAME, Constants::SQL_NAME,
                $table,
                Constants::table_name($type, $prev), Constants::SQL_OLD, Constants::SQL_CODE,
                Constants::table_name($type, $year), Constants::SQL_NEW, Constants::SQL_CODE
            ),
        };

        if($search!==''){
            $where = match($table_type) {
                Constants::TABLE_CODES =>
                    sprintf(" WHERE `%s` like '%%%s%%'", Constants::SQL_CODE, $search),
                Constants::TABLE_UMSTEIGER, Constants::TABLE_UMSTEIGER_JOIN =>
                    sprintf(" WHERE `%s` = '%s'", Constants::SQL_NEW, $search),
                Constants::TABLE_UMSTEIGER_JOIN_REV =>
                    sprintf(" WHERE `%s` = '%s'", Constants::SQL_OLD, $search),
            };
        } else {
            $where = '';
        }

        $order_by = match($table_type) {
            Constants::TABLE_CODES =>
                sprintf(" ORDER BY `%s`", Constants::SQL_CODE),
            Constants::TABLE_UMSTEIGER, Constants::TABLE_UMSTEIGER_JOIN, Constants::TABLE_UMSTEIGER_JOIN_REV =>
                sprintf(" ORDER BY `%s`, `%s`", Constants::SQL_NEW, Constants::SQL_OLD),
        };

        return $select . $where . $order_by;
    }

    public function readData(string $type, int $table_type, string $year, string $prev='', string $search='') :array {

        if($prev==='' && $table_type!==Constants::TABLE_CODES) {
            $prev = $this->dataService->getNextOlderYear($type, $year);
        }

        if($table_type==Constants::TABLE_UMSTEIGER_JOIN_REV) {
            $tmp = $prev;
            $prev = $year;
            $year = $tmp;
        }

        $sql = $this->select($type, $table_type, $year, $prev, trim($search));

        try {
            return $this->connection->executeQuery($sql)->fetchAllAssociative();
        } catch (Exception) {
            // todo: log error
            return [];
        }
    }

    public function updateUmsteigerInfo(string $type, string $year, string $code, bool $umsteiger_info) : bool {

        $sql = sprintf("
            UPDATE `%s`
            SET `%s` = %d
            WHERE `%s` = '%s'",
            Constants::table_name($type, $year),
            Constants::SQL_UMST, $umsteiger_info,
            Constants::SQL_CODE, $code
        );

        return $this->execute_wrapper($sql);
    }

    public function readCodes(string $type, string $year, string $search = '') :array {

        return $this->readData($type, Constants::TABLE_CODES, $year, '', $search);
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