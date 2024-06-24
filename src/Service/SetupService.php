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

    public function setupEntry (string $type, array $entry) : int {

        $year = $entry[Constants::XML_YEAR] ?? '';
        if($year==='') {
            return Constants::STATUS_INVALID;
        }
        $prev = $entry[Constants::XML_PREV] ?? [];
        $nested_zip = $entry[Constants::XML_ZIP] ?? '';
        $codes = $entry[Constants::XML_CODES] ?? '';
        $umsteiger = $entry[Constants::XML_UMSTEIGER] ?? '';
        $dir = $entry[Constants::XML_DIR] ?? '';
        if($dir!=='') {
            $dir .= '/';
        }

        $prev_year = $this->dataService->getPreviousYear($type, $year);

        // todo
        $path = sprintf($this->projectDir . Constants::DIRECTORY_FILES . '%s%s.zip', $type, $year);

        $tmp_dir = '';
        $tmp_file = '';
        if($nested_zip!=='') {
            $tmp_dir = $this->create_tmp_zip($path, $nested_zip);
            $tmp_file = $tmp_dir . '/' . $nested_zip;
            $path = $tmp_file;
        }

        if($codes==='') {
            $codes = sprintf('%sKlassifikationsdateien/%s%ssyst.txt', $dir, $type, $year);
        }
        if($umsteiger==='') {
            $umsteiger = sprintf('%sKlassifikationsdateien/%s%ssyst_umsteiger_%s_%s.txt', $dir, $type, $year, $prev_year, $year);
        }

        if($year===$this->dataService->getLastYear($type)) {
            $umsteiger = '';
        }

        $ret = $this->setup_tables($type, $year, $path, $codes, $umsteiger);

        if(!empty($prev)) {
            $prev_codes = $prev[Constants::XML_CODES] ?? '';
            if($prev_codes==='') {
                $prev_codes = sprintf('%sKlassifikationsdateien/%s%ssyst.txt', $dir, $type, $prev_year);
            }
            $this->setup_tables($type, $prev_year, $path, $prev_codes);
        }

        if($tmp_file!=='') {
            unlink($tmp_file);
        }
        if($tmp_dir!=='') {
            rmdir($tmp_dir);
        }

        return $ret;
    }

    private function setup_tables (string $type, string $year, string $path, string $codes, string $umsteiger = '') : int {

        $table = Constants::table_name($type, $year);

        // read/write config entry
        $status = $this->dbRepo->readConfigStatus($table);
        if($status===Constants::CONFIG_STATUS_OK) {
            return Constants::STATUS_EXISTS_OK;
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
        $this->dbRepo->updateConfigStatus($table, Constants::CONFIG_STATUS_OK);

        return Constants::STATUS_OK;
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
