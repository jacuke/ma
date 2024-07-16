<?php

namespace App\Repository;

use App\Service\DataService;
use App\Util\Constants;
use App\Util\SqlInsertEncoder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Serializer\Serializer;

class DatabaseRepository {

    protected Connection $connection;
    private DataService $dataService;

    public function __construct(
        Connection  $connection,
        DataService $dataService
    ) {
        $this->connection = $connection;
        $this->dataService = $dataService;
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
            Constants::TABLE_UMSTEIGER, Constants::TABLE_UMSTEIGER_JOIN, Constants::TABLE_UMSTEIGER_JOIN_REV =>
                Constants::table_name_umsteiger($type, $year, $prev),
        };

        $select = match($table_type) {
            Constants::TABLE_CODES => sprintf(
                "
                SELECT `%s`, `%s`, `%s`
                FROM `%s`
                %%s",
                Constants::SQL_CODE, Constants::SQL_NAME, Constants::SQL_UMST,
                $table
            ),
            Constants::TABLE_UMSTEIGER => sprintf(
                "
                SELECT `%s`, `%s`, `%s`, `%s`
                FROM `%s`
                WHERE (`%s` != `%s`
                OR `%s` != 'A'
                OR `%s` != 'A')
                %%s
                ORDER BY `%s`
                ",
                Constants::SQL_OLD, Constants::SQL_NEW, Constants::SQL_AUTO, Constants::SQL_AUTO_R,
                $table,
                Constants::SQL_OLD, Constants::SQL_NEW,
                Constants::SQL_AUTO,
                Constants::SQL_AUTO_R,
                Constants::SQL_NEW
            ),
            Constants::TABLE_UMSTEIGER_JOIN, Constants::TABLE_UMSTEIGER_JOIN_REV => sprintf(
                "
                SELECT  u.`%s`, u.`%s`,
                        u.`%s`, u.`%s`,                       
                        o.`%s` old_name, n.`%s` new_name
                FROM `%s` u
                JOIN `%s` o ON u.`%s` = o.`%s`
                JOIN `%s` n ON u.`%s` = n.`%s`
                WHERE (u.`%s` != u.`%s`
                OR u.`%s` != 'A'
                OR u.`%s` != 'A')
                %%s
                ORDER BY u.`%s`
                ",
            Constants::SQL_OLD, Constants::SQL_NEW,
                Constants::SQL_AUTO, Constants::SQL_AUTO_R,
                Constants::SQL_NAME, Constants::SQL_NAME,
                $table,
                Constants::table_name($type, $prev), Constants::SQL_OLD, Constants::SQL_CODE,
                Constants::table_name($type, $year), Constants::SQL_NEW, Constants::SQL_CODE,
                Constants::SQL_OLD, Constants::SQL_NEW,
                Constants::SQL_AUTO,
                Constants::SQL_AUTO_R,
                Constants::SQL_NEW
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
                AND `%s` = '%s'
                ",
                    Constants::SQL_NEW, $search
                ),
                Constants::TABLE_UMSTEIGER_JOIN_REV => sprintf(
                    "
                AND `%s` = '%s'
                ",
                    Constants::SQL_OLD, $search
                ),
            };
            $select = sprintf($select, ' ' . $where);
        } else {
            $select = sprintf($select, '');
        }

        return $select;
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

        $sql = $this->select($type, $table_type, $year, $prev, $search);

        try {
            return $this->connection->executeQuery($sql)->fetchAllAssociative();
        } catch (Exception) {
            // todo: log error
            return [];
        }
    }

    public function updateUmsteigerInfo(string $type, string $year, string $code, bool $umsteiger_info) : bool {

        $table = Constants::table_name($type, $year);

        $sql = sprintf("
            UPDATE `%s`
            SET `%s` = %d
            WHERE `%s` = '%s';
        ", $table,
        Constants::SQL_UMST, $umsteiger_info,
        Constants::SQL_CODE, $code
        );

        return $this->execute_wrapper($sql);
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

    public function searchUmsteiger(string $type, string $year, string $search) :array {

        return [
            'fwd' => $this->searchUmsteigerRecursive($type, $year, $search, true),
            'rev' => $this->searchUmsteigerRecursive($type, $year, $search, false)
        ];
    }

    public function searchUmsteigerRecursive(string $type, string $year, string $search, bool $chronological) :array {

        $ret = array();
        if($year==='') {
            return $ret;
        }
        if($chronological) {
            $prev = $this->dataService->getNextNewerYear($type, $year);
            $table_type = Constants::TABLE_UMSTEIGER_JOIN_REV;
            $which = 'new';
        } else {
            $prev = $this->dataService->getNextOlderYear($type, $year);
            $table_type = Constants::TABLE_UMSTEIGER_JOIN;
            $which = 'old';
        }
        if($prev!=='') {
            $umsteiger_in = $this->readData($type, $table_type, $year, $prev, $search);
            if(count($umsteiger_in) > 0) {
                $umsteiger_out = array();
                foreach($umsteiger_in as $find) {
                    $search_code = $find[$which];
                    if($search_code!==Constants::UNDEF) {
                        $history = $this->searchUmsteigerRecursive($type, $prev, $search_code, $chronological);
                        if(count($history)>0) {
                            $find['recursion'] = $history;
                        }
                        $umsteiger_out[] = $find;
                    } else {
                        array_unshift($umsteiger_out, $find);
                    }
                }
                $ret['umsteiger'] = $umsteiger_out;
                $ret['year'] = $year;
                $ret['prev'] = $prev;
                return $ret;
            } else {
                return $this->searchUmsteigerRecursive($type, $prev, $search, $chronological);
            }
        }
        return $ret;
    }

    public function readUmsteigerHistory(string $type, string $begin_year, string $search = '') :array {

        return $this->searchUmsteigerRecursive($type, $begin_year, $search, false);
    }

    public function readUmsteigerHistoryRev(string $type, string $begin_year, string $search = '') :array {

        return $this->searchUmsteigerRecursive($type, $begin_year, $search, true);
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