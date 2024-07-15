<?php

namespace App\Service;

use App\Repository\DatabaseRepository;
use App\Util\Constants;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use ZipArchive;

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

        $options = array();

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
        $encoding = $entry[Constants::XML_ENCODING] ?? '';
        if($encoding!=='') {
            $options[Constants::XML_ENCODING] = $encoding;
        }
        foreach(Constants::XML_OPTIONS_ARRAY as $option) {
            if(isset($entry[Constants::XML_OPTIONS][$option])) {
                $options[$option] = '';
            }
        }

        $prev_year = $this->dataService->getNextOlderYear($type, $year);

        // todo: download
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

        if($year===$this->dataService->getOldestYear($type)) {
            $umsteiger = '';
        }

        $ret = $this->setup_tables($type, $year, $options, $path, $codes, $umsteiger);

        if(!empty($prev)) {
            $prev_codes = $prev[Constants::XML_CODES] ?? '';
            if($prev_codes==='') {
                $prev_codes = sprintf('%sKlassifikationsdateien/%s%ssyst.txt', $dir, $type, $prev_year);
            }
            $this->setup_tables($type, $prev_year, $options, $path, $prev_codes);
        }

        if($tmp_file!=='') {
            unlink($tmp_file);
        }
        if($tmp_dir!=='') {
            rmdir($tmp_dir);
        }

        return $ret;
    }

    private function setup_tables (string $type, string $year, array $options, string $path, string $codes, string $umsteiger = '') : int {

        $table = Constants::table_name($type, $year);

        // read/write config entry
        $status = $this->dbRepo->readConfigStatus($table);
        if($status===Constants::CONFIG_STATUS_OK) {
            return Constants::STATUS_EXISTS_OK;
        } elseif($status!==Constants::CONFIG_STATUS_NOT_FOUND) {
            $table_umsteiger =
                Constants::table_name_umsteiger($type, $year, $this->dataService->getNextOlderYear($type, $year));
            $this->dbRepo->dropTable($table);
            $this->dbRepo->dropTable($table_umsteiger);
        }
        $this->dbRepo->writeConfig($table);

        $this->add_table_with_data($type, $year, $options, $path, $codes, Constants::TABLE_CODES);

        if($umsteiger!=='') {
            $this->add_table_with_data($type, $year, $options, $path, $umsteiger, Constants::TABLE_UMSTEIGER);
        }

        // set status OK if no errors
        // todo: check errors
        $this->dbRepo->updateConfigStatus($table, Constants::CONFIG_STATUS_OK);

        return Constants::STATUS_OK;
    }

    // todo: return type
    private function add_table_with_data (string $type, string $year, array $options, string $path, string $file, int $table_type): void {

        $fp = fopen('zip://' . $path . '#' . $file, 'r');
        if($fp) {
            $contents = stream_get_contents($fp);
//            foreach($options as $key => $value) {
//                if($key===Constants::XML_ENCODING) {
//                    $contents =  mb_convert_encoding($contents, "UTF-8", $value);
//                }
//            }
            $this->process_input($table_type, $options, $contents);
            $data = $this->serializer->decode($contents, 'csv', ['csv_delimiter' => ';', 'no_headers' => true]);
            $this->process_data($type, $table_type, $options, $data);
            $this->dbRepo->addTable($type, $year, $table_type, $data);
            fclose($fp);
        }
    }

    private function process_input (int $table_type, array $options, string& $input): void {

        $encoding = $options[Constants::XML_ENCODING] ?? '';
        if($encoding!=='' && $table_type===Constants::TABLE_CODES) {
            $input =  mb_convert_encoding($input, "UTF-8", $encoding);
        }
    }

    private function process_data (string $type, int $table_type, array $options, array& $data):void {

        // trim umsteiger for ICD10GM with 6 columns (versions 2.0 and 1.3)
        if($type===Constants::ICD10GM && isset($options[Constants::XML_ICD10GM_6COL]) &&
            $table_type===Constants::TABLE_UMSTEIGER
        ) {
            array_walk($data, function (&$entry) {
                array_splice($entry, 4, 2);
            });
        }

        // remove kreuz-stern characters from ICD10GM codes
        if($type===Constants::ICD10GM && isset($options[Constants::XML_KREUZ_STERN])) {
            foreach($data as $k => $v) {
                foreach(($table_type===Constants::TABLE_UMSTEIGER ? [0,1] : [0]) as $index) {
                    $data[$k][$index] = str_replace(['+', '!', '*'], '', $v[$index]);
                }
            }
        }

        // remove punkt-strich notation from ICD10GM
        if($type===Constants::ICD10GM && isset($options[Constants::XML_PUNKT_STRICH])) {
            // for codes table simply remove the .-
            if($table_type===Constants::TABLE_CODES) {
                foreach($data as $k => $v) {
                    $tmp = str_replace('.-', '', $v[0]);
                    $data[$k][0] = str_replace('-', '', $tmp);
                }
            }
            // for umsteiger remove the entry completely
            if($table_type===Constants::TABLE_UMSTEIGER) {
                foreach($data as $key => $entry) {
                    if(strrpos($entry[0], '-')!==false) {
                        unset($data[$key]);
                    }
                }
            }
        }

        // convert OPS umsteiger to 4 columns
        if($type===Constants::OPS && $table_type===Constants::TABLE_UMSTEIGER) {
            array_walk($data, function (&$entry) {
                array_splice($entry, 3, 1);
                array_splice($entry, 1, 1);
            });
        }
    }

    private function create_tmp_zip(string $outer_zip, string $inner_zip):string {

        $tmp_path = sys_get_temp_dir() . '/bfarmer' . time();
        $zip = new ZipArchive();
        if($zip->open($outer_zip)) {
            if(!$zip->extractTo($tmp_path, $inner_zip)) {
                // todo: error handling
            }
            $zip->close();
        }
        return $tmp_path;
    }
}
