<?php

namespace App\Service;

use App\Util\Constants;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

use App\Repository\DatabaseRepository;

class SetupService {

    private DatabaseRepository $dbRepo;
    private DataService $dataService;
    private string $projectDir;

    private Serializer $serializer;

    public function __construct(
        DatabaseRepository $databaseRepository,
        DataService        $dataService,
        string             $projectDir
    ) {
        $this->dbRepo = $databaseRepository;
        $this->dataService = $dataService;
        $this->projectDir = $projectDir;

        $this->serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder(), new XmlEncoder()]);
    }

    public function init():void {
        $this->dbRepo->addConfigTable();
    }

    public function setupEntry (string $type, array $entry) : void {

        $year = $entry[Constants::XML_YEAR] ?? '';
        if($year==='') {
            // todo: error
            return;
        }
        $prev = $entry[Constants::XML_PREV] ?? [];
        $nested_zip = $entry[Constants::XML_ZIP] ?? '';
        $codes = $entry[Constants::XML_CODES] ?? '';
        $umsteiger = $entry[Constants::XML_UMSTEIGER] ?? '';
        $file = $entry[Constants::XML_FILE] ?? '';

        $prev_year = $this->dataService->getPreviousYear($type, $year);

        if($file!=='') {
            $path = sprintf($this->projectDir . Constants::DIRECTORY_FILES . '%s', $file);
        } else {
            $path = sprintf($this->projectDir . Constants::DIRECTORY_FILES . '%s%s.zip', $type, $year);
        }

        $tmp_dir = '';
        $tmp_file = '';
        if($nested_zip!=='') {
            $tmp_dir = $this->create_tmp_zip($path, $nested_zip);
            $tmp_file = $tmp_dir . '/' . $nested_zip;
            $path = $tmp_file;
            $klassdat_pre = '';
        } elseif($file!=='') {
            $klassdat_pre = '';
        } else {
            $klassdat_pre = sprintf('%s%ssyst-ueberl/', $type, $year);
        }

        if($codes==='') {
            $codes = $klassdat_pre . sprintf('Klassifikationsdateien/%s%ssyst.txt', $type, $year);
        }
        if($umsteiger==='') {
            $umsteiger = $klassdat_pre . sprintf('Klassifikationsdateien/%s%ssyst_umsteiger_%s_%s.txt', $type, $year, $prev_year, $year);
        }

        $this->setup_tables($type, $year, $path, $codes, $umsteiger);

        if(!empty($prev)) {  // todo
            $prev_codes = sprintf('%s%ssyst-ueberl/Klassifikationsdateien/%s%ssyst.txt', $type, $year, $type, $prev_year);
            $this->setup_tables($type, $prev_year, $path, $prev_codes);
        }

        if($tmp_file!=='') {
            unlink($tmp_file);
        }
        if($tmp_dir!=='') {
            rmdir($tmp_dir);
        }
    }

    private function setup_tables (string $type, string $year, string $path, string $codes, string $umsteiger = '') : void {

        $table = Constants::table_name($type, $year);

        // read/write config entry
        $status = $this->dbRepo->readConfigStatus($table);
        if($status===Constants::STATUS_OK) {
            var_dump($year . ' already exists');
            return;
        } else {
            // todo: dump if exists without OK
        }
        $this->dbRepo->writeConfig($table);

        $this->add_table_with_data($type, $year, $path, $codes, Constants::TABLE_CODES);

        if($umsteiger!=='') {
            $this->add_table_with_data($type, $year, $path, $umsteiger, Constants::TABLE_UMSTEIGER);
        }

        // set status OK if no errors
        // todo: check errors
        $this->dbRepo->updateConfigStatus($table, Constants::STATUS_OK);
    }

    // todo: return type
    private function add_table_with_data (string $type, string $year, string $path, string $file, $table_type): void {

        $fp = fopen('zip://' . $path . '#' . $file, 'r');
        if($fp) {
            $contents = stream_get_contents($fp);
            $data = $this->serializer->decode($contents, 'csv', ['csv_delimiter' => ';', 'no_headers' => true]);

            $this->process_data($type, $table_type, $data);
            $this->dbRepo->addTable($type, $year, $table_type, $data);

            fclose($fp);
        }
    }

    private function process_data (string $type, int $table_type, array& $data):void {

        if($type===Constants::OPS && $table_type===Constants::TABLE_UMSTEIGER) {
            array_walk($data, function (&$item) {
                array_splice($item, 3, 1);
                array_splice($item, 1, 1);
            });
        }
    }

    private function ops_test (array $data) : void {

        foreach(array_chunk($data, 5) as $chunk) {

            var_dump($chunk);
            break;
        }

    }

    private function create_tmp_zip(string $outer_zip, string $inner_zip):string {

        $tmp_path = sys_get_temp_dir() . '/bfarmer' . time();
        $zip = new \ZipArchive();
        if($zip->open($outer_zip)) {
            if(!$zip->extractTo($tmp_path, $inner_zip)) {
                // todo: error handling
            }
            $zip->close();
        }
        return $tmp_path;
    }
}
