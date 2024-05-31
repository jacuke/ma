<?php

namespace App\Repository;

use App\Util\Constants;
use App\Util\SqlInsertEncoder;
use Doctrine\DBAL\Exception;
use Symfony\Component\Serializer\Serializer;

class PatientsRepository extends DatabaseRepository {

    private const SEARCH = 1;
    private const COUNT = 2;
    private const TABLE = 'PATIENTS';
    public const PAGE_SIZE = 50;

    public function addPatientsTable(): bool {

        $sql = sprintf("
            CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `year` INT NOT NULL,
                `codes` VARCHAR(64),
                `names` VARCHAR(1295),
                `umsteiger` VARCHAR(64),
                CONSTRAINT patients_index PRIMARY KEY (`id`)
            );
        ", self::TABLE);

        return $this->execute_wrapper($sql);
    }

    public function addPatients (array $data): bool {

        $insert_serializer = new Serializer([], [new SqlInsertEncoder()]);

        $clean_values = [
                'names' => function($in) {return Constants::sql_clean_name($in);}
            ];

        // insert in chunks of 50
        foreach (array_chunk($data, 50) as $chunk) {

            if (count($chunk) > 0) {

                $insert = $insert_serializer->encode($chunk, 'sql', [
                    SqlInsertEncoder::TABLE_NAME => self::TABLE,
                    SqlInsertEncoder::CLEAN_VALUES_INDEX => $clean_values
                ]);

                try {
                    $this->connection->executeQuery($insert);
                } catch (Exception $e) {
                    var_dump($e->getMessage()); // todo
                    return false;
                }
            }
        }

        return true;
    }

    public function build_query (int $type, string $search_code = '', string $search_name = '', int $page = 1) : string {

        $query = '';
        // select
        $query .= match($type) {
            self::SEARCH => 'SELECT  `id`, `year`, `codes`, `names`, `umsteiger`',
            self::COUNT => 'SELECT COUNT(*)'
        };
        // from
        $query .= sprintf(' FROM `%s`', self::TABLE);
        // where
        if($search_code !== '' && $search_name !== '') {
            $query .= " WHERE `codes` LIKE '%$search_code%' AND `names` LIKE '%$search_name%'";
        } elseif($search_code !== '') {
            $query .= " WHERE `codes` LIKE '%$search_code%'";
        } elseif($search_name !== '') {
            $query .= " WHERE `names` LIKE '%$search_name%'";
        }
        // order by
        $query .= ' ORDER BY `year` DESC, `id` DESC';
        // limit/offset
        if($type===self::SEARCH) {
            $query .= sprintf(' LIMIT %d OFFSET %d',
                self::PAGE_SIZE, self::PAGE_SIZE * ($page - 1)
            );
        }

        return $query;
    }

    public function readPatients (string $search_code, string $search_name, int $page = 1): array {

        $sql = $this->build_query(self::SEARCH, $search_code, $search_name, $page);

        try {
            return $this->connection->executeQuery($sql)->fetchAllAssociative();
        } catch (Exception $e) {
            var_dump($e->getMessage()); // todo
            return [];
        }
    }

    public function countPatients (string $search_code='', string $search_name=''):int {

        $sql = $this->build_query(self::COUNT, $search_code, $search_name);

        try {
            return $this->connection->executeQuery($sql)->fetchOne();
        } catch (Exception $e) {
            $this->logger->error(__METHOD__ . ' ' . $e->getMessage());
            return 0;
        }
    }
}