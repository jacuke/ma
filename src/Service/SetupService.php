<?php

namespace App\Service;

use App\Repository\ConfigRepository;
use App\Repository\BfarmRepository;
use App\Util\Constants;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use ZipArchive;

class SetupService {

    private string $projectDir;
    private BfarmRepository $bfarmRepository;
    private ConfigRepository $configRepository;
    private DataService $dataService;
    private ClientService $clientService;
    private Serializer $serializer;
    private Filesystem $filesystem;

    public function __construct(
        BfarmRepository  $bfarmRepository,
        ConfigRepository $configRepository,
        DataService      $dataService,
        ClientService    $clientService,
        string           $projectDir
    ) {
        $this->bfarmRepository = $bfarmRepository;
        $this->configRepository = $configRepository;
        $this->dataService = $dataService;
        $this->clientService = $clientService;
        $this->projectDir = $projectDir;

        $this->serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder(), new XmlEncoder()]);
        $this->filesystem = new Filesystem();
    }

    public function init():void {
        $this->configRepository->addConfigTable();
    }

    private function determine_options (array $entry): array {

        $options = [];
        $encoding = $entry[Constants::XML_ENCODING] ?? '';
        if($encoding!=='') {
            $options[Constants::XML_ENCODING] = $encoding;
        }
        foreach(Constants::XML_OPTIONS_ARRAY as $option) {
            if(isset($entry[Constants::XML_OPTIONS][$option])) {
                $options[$option] = true;
            }
        }
        return $options;
    }

    public function setupEntry (string $type, array $entry, bool $keep_files) : int {

        $year = $entry[Constants::XML_YEAR] ?? '';
        if($year==='') {
            return Constants::STATUS_INVALID;
        }

        // check config status
        $status = $this->configRepository->readConfigStatus(Constants::table_name($type, $year));
        if($status===Constants::CONFIG_STATUS_OK) {
            return Constants::STATUS_EXISTS_OK;
        }

        // read other data from xml file
        $url = $entry[Constants::XML_URL] ?? '';
        $prev = $entry[Constants::XML_PREV] ?? [];
        $nested_zip = $entry[Constants::XML_ZIP] ?? '';
        $codes = $entry[Constants::XML_CODES] ?? '';
        $umsteiger = $entry[Constants::XML_UMSTEIGER] ?? '';
        $dir = $entry[Constants::XML_DIR] ?? '';
        if($dir!=='') {
            $dir .= '/';
        }
        $options = $this->determine_options($entry);
        $prev_year = $this->dataService->getNextOlderYear($type, $year);

        // download/lookup file
        $path = $this->projectDir . Constants::DIRECTORY_FILES . $type . Constants::year_str_to_int($year) . '.zip';
        if(!$this->filesystem->exists($path)) {
            var_dump('downloading ' . $type . ' ' . $year);
            $status = $this->clientService->downloadFile($url, $path);
            if($status!==Constants::STATUS_OK) {
                $this->filesystem->remove($path);
                return Constants::STATUS_DOWNLOAD_FAILED;
            }
            var_dump('download finished');
            $downloaded = true;
        } else {
            $downloaded = false;
        }

        $tmp_dir = '';
        if($nested_zip!=='') {
            $tmp_dir = $this->create_tmp_zip($path, $nested_zip);
            if($tmp_dir==='') {
                return Constants::STATUS_ZIP_FAILED;
            }
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
            $prev_options = $this->determine_options($prev);
            $prev_codes = $prev[Constants::XML_CODES] ?? '';
            if($prev_codes==='') {
                $prev_codes = sprintf('%sKlassifikationsdateien/%s%ssyst.txt', $dir, $type, $prev_year);
            }
            $this->setup_tables($type, $prev_year, $prev_options, $path, $prev_codes);
        }

        if($tmp_dir!=='') {
            $this->filesystem->remove($tmp_dir);
        }

        if($downloaded && !$keep_files) {
            $this->filesystem->remove($path);
        }

        return $ret;
    }

    private function create_tmp_zip(string $outer_zip, string $inner_zip):string {

        $tmp_path = sys_get_temp_dir() . '/bfarmer' . time();
        $zip = new ZipArchive();
        if(!$zip->open($outer_zip)) {
            return '';
        }else {
            if(!$zip->extractTo($tmp_path, $inner_zip)) {
                $this->filesystem->remove($tmp_path);
                $tmp_path = '';
            }
            $zip->close();
            return $tmp_path;
        }
    }

    private function setup_tables (string $type, string $year, array $options, string $path, string $codes, string $umsteiger = '') : int {

        $table = Constants::table_name($type, $year);
        $status = $this->configRepository->readConfigStatus($table);
        if($status!==Constants::CONFIG_STATUS_NOT_FOUND) {
            $table_umsteiger =
                Constants::table_name_umsteiger($type, $year, $this->dataService->getNextOlderYear($type, $year));
            $this->bfarmRepository->dropTable($table);
            $this->bfarmRepository->dropTable($table_umsteiger);
        }
        $this->configRepository->writeConfig($table);

        $this->add_table_with_data($type, $year, $options, $path, $codes, Constants::TABLE_CODES);

        if($umsteiger!=='') {
            $this->add_table_with_data($type, $year, $options, $path, $umsteiger, Constants::TABLE_UMSTEIGER);
        }

        // set status OK if no errors
        // todo: check errors
        $this->configRepository->writeConfig($table, Constants::CONFIG_STATUS_OK);

        return Constants::STATUS_OK;
    }

    // todo: return type
    private function add_table_with_data (string $type, string $year, array $options, string $path, string $file, int $table_type): void {

        $fp = fopen('zip://' . $path . '#' . $file, 'r');
        if($fp) {
            $contents = stream_get_contents($fp);
            $this->process_input($table_type, $options, $contents);
            $data = $this->serializer->decode($contents, 'csv', ['csv_delimiter' => ';', 'no_headers' => true]);
            $this->process_data($type, $table_type, $options, $data);
            $this->bfarmRepository->addTable($type, $year, $table_type, $data);
            fclose($fp);
        } //else {
            // todo: error handling
        //}
    }

    private function process_input (int $table_type, array $options, string& $input): void {

        $encoding = $options[Constants::XML_ENCODING] ?? '';
        if($encoding!=='' && $table_type===Constants::TABLE_CODES) {
            $input =  mb_convert_encoding($input, "UTF-8", $encoding);
        }
    }

    private function process_data (string $type, int $table_type, array $options, array & $data):void {

        $functions = [];

        // remove the first line for OPS with code KOMBI
        if( $type===Constants::OPS &&
            $table_type===Constants::TABLE_CODES &&
            isset($options[Constants::XML_OPS_KOMBI])
        ) {
            $functions[] = function(&$data) { $this->remove_first_row($data); };
        }

        // convert ICD10GM umsteiger to 4 columns by removing the 5th and 6th column
        if( $type===Constants::ICD10GM &&
            $table_type===Constants::TABLE_UMSTEIGER &&
            isset($options[Constants::XML_6COL])
        ) {
            $functions[] = function(&$data) { $this->remove_columns($data, [4,5]); };
        }

        // remove kreuz-stern characters from ICD10GM codes
        if( $type===Constants::ICD10GM &&
            isset($options[Constants::XML_KREUZ_STERN])
        ) {
            if($table_type===Constants::TABLE_CODES) {
                $functions[] = function(&$data) { $this->remove_kreuz_stern_codes($data); };
            }
            elseif($table_type===Constants::TABLE_UMSTEIGER) {
                $functions[] = function(&$data) { $this->remove_kreuz_stern_umsteiger($data); };
            }
        }

        // remove punkt-strich notation from ICD10GM
        if( $type===Constants::ICD10GM &&
            isset($options[Constants::XML_PUNKT_STRICH])
        ) {
            if($table_type===Constants::TABLE_CODES) {
                $functions[] = function(&$data) { $this->remove_punkt_strich_codes($data); };
            }
            elseif($table_type===Constants::TABLE_UMSTEIGER) {
                $functions[] = function(&$data) { $this->remove_punkt_strich_umsteiger($data); };
            }
        }

        // convert OPS umsteiger to 4 columns ...
        if( $type===Constants::OPS &&
            $table_type===Constants::TABLE_UMSTEIGER &&
            !isset($options[Constants::XML_OPS_4COL])
        ) {
            if(isset($options[Constants::XML_6COL])) {
                // ... by removing the 5th and 6th column
                $functions[] = function (&$data) { $this->remove_columns($data, [4, 5]); };
            } elseif(isset($options[Constants::XML_OPS_OLD_6COL])) {
                // ... by removing 3rd and 4th column
                $functions[] = function (&$data) { $this->remove_columns($data, [2, 3]); };
            } elseif (isset($options[Constants::XML_OPS_5COL])) {
                // ... by removing the 3rd column
                $functions[] = function (&$data) { $this->remove_columns($data, [2]); };
            } elseif (isset($options[Constants::XML_OPS_3COL])) {
                // ... by converting from 3 to 4 columns
                $functions[] = function (&$data) { $this->convert_3_to_4_columns($data); };
            } else {
                // ... by removing 2nd and 4th column
                $functions[] = function(&$data) { $this->remove_columns($data, [1,3]); };
            }
        }

        // replace "None" with "UNDEF" for OPS
        if( $type===Constants::OPS &&
            isset($options[Constants::XML_UNDEF_NONE])
        ) {
            if($table_type===Constants::TABLE_CODES) {
                $functions[] = function(&$data) { $this->replace_none_undef_codes($data); };
            }
            elseif($table_type===Constants::TABLE_UMSTEIGER) {
                $functions[] = function(&$data) { $this->replace_undef_none_umsteiger($data); };
            }
        }

        // remove non-terminal umsteiger
        if($table_type===Constants::TABLE_UMSTEIGER &&
            isset($options[Constants::XML_NONTERM_UMST])
        ) {
            $functions[] = function(&$data) { $this->remove_non_terminal_umsteiger($data); };
        }

        // remove non-umsteiger (new code = old code and 2x A)
        if($table_type===Constants::TABLE_UMSTEIGER
        ) {
            $functions[] = function(&$data) { $this->remove_non_umsteiger($data); };
        }

        // remove non-terminal codes
        if($table_type===Constants::TABLE_CODES
        ) {
            $functions[] = function(&$data) { $this->remove_non_terminal_codes($data); };
        }

        // add an additional column to all codes tables
        if($table_type===Constants::TABLE_CODES
        ) {
            $functions[] = function(&$data) { $this->add_additional_column($data); };
        }

        // run all functions
        foreach ($functions as $function) {
            $function($data);
        }
    }

    private function remove_first_row(array& $data): void {
        unset($data[0]);
        $data = array_values($data);
    }

    private function remove_kreuz_stern_codes(array& $data): void {
        $this->replace_strings($data, [0], ['+', '!', '*']);
    }

    private function remove_kreuz_stern_umsteiger(array& $data): void {
        $this->replace_strings($data, [0,1], ['+', '!', '*']);
    }

    private function remove_punkt_strich_codes(array& $data): void {
        $this->replace_strings($data, [0], ['.-', '-']);
    }

    private function remove_punkt_strich_umsteiger(array& $data): void {
        $this->replace_strings($data, [0,1], ['.-', '-']);
    }

    private function replace_none_undef_codes(array& $data): void {
        $this->replace_strings($data, [0], ['None'], Constants::UNDEF);
    }

    private function replace_undef_none_umsteiger(array & $data): void {
        $this->replace_strings($data, [0,1], ['None'], Constants::UNDEF);
    }

    private function replace_strings(array & $data, array $indices, array $strings, string $replacement = ''): void {

        foreach($data as &$entry) {
            foreach($indices as $index) {
                $entry[$index] = str_ireplace($strings, $replacement, $entry[$index]);
            }
        }
    }

    private function convert_3_to_4_columns (array& $data): void {
        foreach($data as $k => $v) {
            $old = $v[0];
            $new = $v[2];
            $auto = $v[1];
            if($auto==='') {
                $data[] = [$old, $new, '', ''];
            } else {
                if($old!==$new) {
                    $data[] = [$old, $new, 'A', 'A'];
                }
            }
            unset($data[$k]);
        }
        $data = array_values($data);
    }

    private function remove_columns (array& $data, array $indices): void {

        rsort($indices);
        foreach ($indices as $index) {
            array_walk($data, function (&$entry) use ($index) {
                array_splice($entry, $index, 1);
            });
        }
    }

    private function remove_non_terminal_umsteiger(array & $data):void {

        $index = end($data)[0];
        $prev = prev($data)[0] ?? '';
        while($prev!=='') {

            if(str_contains($index, $prev) &&
                strlen($index) > strlen($prev)
            ) {
                unset($data[key($data)]);
            } else {
                $index = $prev;
            }
            $prev = prev($data)[0] ?? '';
        }

        $data = array_values($data);
    }

    private function remove_non_umsteiger (array& $data): void {

        foreach($data as $k => $v) {
            if($v[0]===$v[1] && $v[2]==='A' && $v[3]==='A') {
                unset($data[$k]);
            }
        }
        $data = array_values($data);
    }

    private function remove_non_terminal_codes (array& $data): void {

        foreach($data as $k => $v) {
            $current = $v[0];
            $next = next($data)[0] ?? '';
            if(str_contains($next, $current) &&
                (strlen($next) > strlen($current))) {
                unset($data[$k]);
            }
        }
        $data = array_values($data);
    }

    private function add_additional_column (array& $data): void {

        foreach($data as &$entry) {
            $entry[] = '0';
        }
    }
}