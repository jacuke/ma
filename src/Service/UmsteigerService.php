<?php

namespace App\Service;

use App\Repository\ConfigRepository;
use App\Repository\BfarmRepository;
use App\Util\Constants;

class UmsteigerService {

    private BfarmRepository $bfarmRepository;
    private ConfigRepository $configRepository;
    private DataService $dataService;

    public function __construct(
        BfarmRepository  $bfarmRepository,
        ConfigRepository $configRepository,
        DataService      $dataService
    ) {
        $this->bfarmRepository = $bfarmRepository;
        $this->configRepository = $configRepository;
        $this->dataService = $dataService;
    }

    public function searchUmsteigerHorizontal(string $type, string $year, string $search) :array {

        return [
            'fwd' => $this->searchUmsteigerHorizontal_recursion($type, $year, $search, true),
            'rev' => $this->searchUmsteigerHorizontal_recursion($type, $year, $search, false)
        ];
    }

    private function searchUmsteigerHorizontal_recursion(string $type, string $year, string $search, bool $chronological) :array {

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
        if($prev==='') {
            return $ret;
        }

        $umsteiger_in = $this->bfarmRepository->readData($type, $table_type, $year, $prev, $search);
        if(empty($umsteiger_in)) {
            return $this->searchUmsteigerHorizontal_recursion($type, $prev, $search, $chronological);
        }

        $umsteiger_out = array();
        foreach($umsteiger_in as $find) {
            $search_code = $find[$which];
            if($search_code!==Constants::UNDEF) {
                $history = $this->searchUmsteigerHorizontal_recursion($type, $prev, $search_code, $chronological);
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
    }

    public function searchAllUmsteigerVertical (string $type, $function = null): array {

        $data = [];
        foreach ($this->dataService->getYears($type) as $year) {
            $search = $this->searchUmsteigerVertical($type, $year, $function);
            if(!empty($search)) {
                $data[$year] = $search;
            }
        }
        return $data;
    }

    public function determineTwoUmsteigerVertical (string $type, bool $chronological, string $year, string $prev, callable $function):void {

        $data = [];
        $data = $this->merge_umsteiger($type, $chronological, $year, $prev, $data);
        $function($data);
    }

    public function searchUmsteigerVertical (string $type, string $target_year, $function = null):array {

        $data = [];
        $data += $this->searchUmsteigerVertical_subroutine($type, $target_year, false, $function);
        $data += $this->searchUmsteigerVertical_subroutine($type, $target_year, true, $function);

        return $data;
    }

    private function searchUmsteigerVertical_subroutine (string $type, string $target_year, bool $chronological, $function = null):array {

        $data = [];
        $merge = [];

        $year = $target_year;
        while (1) {

            $other = $year;

            if($chronological) {
                $year = $this->dataService->getNextOlderYear($type, $other);
            } else {
                $year = $this->dataService->getNextNewerYear($type, $other);
            }

            if($year==='') {
                break;
            }

            if($chronological) {
                $merge = $this->merge_umsteiger($type, $chronological, $other, $year, $merge);
            } else {
                $merge = $this->merge_umsteiger($type, $chronological, $year, $other, $merge);
            }

            if($function) {
                $function($merge, $year, $target_year);
            } else {
                $data[$year] = $merge;
            }
        }

        return $data;
    }

    private function merge_umsteiger (string $type, bool $chronological, string $year, string $prev, array $merge_into):array {

        if($chronological) {
            $current = 'old';
            $other = 'new';
        } else {
            $other = 'old';
            $current = 'new';
        }

        $umsteiger = array();
        $data = $this->bfarmRepository->readData($type, Constants::TABLE_UMSTEIGER, $year, $prev);
        foreach ($data as $umst) {

            $current_code = $umst[$current];
            $other_code = $umst[$other];

            if($current_code === 'UNDEF' || $other_code === 'UNDEF') {
                $undef = true;
            } else {
                $undef = false;
            }

            if(!$undef && isset($merge_into[$other_code])) {
                $umsteiger[$current_code] = array_merge($merge_into[$other_code], $umsteiger[$current_code] ?? []);
            } else {
                $umsteiger[$current_code][] = $other_code;
            }
        }

        return $umsteiger + $merge_into;
    }

    // todo: return type
    public function saveUmsteigerInfo(string $type): bool {

        $data = $this->searchAllUmsteigerVertical($type);
        $oldestYear = $this->dataService->getOldestYear($type);
        $newestYear = $this->dataService->getNewestYear($type);

        foreach ($this->dataService->getYears($type) as $year) {

            $config_entry = Constants::config_name_umsteiger_info($type, $year);
            $codes = $this->bfarmRepository->readCodes($type, $year);
            foreach ($codes as $entry) {
                $code = $entry['code'];
                $oldest = isset($data[$oldestYear][$year][$code]);
                $newest = isset($data[$newestYear][$year][$code]);
                $has_umsteiger = $oldest || $newest;
                $success = $this->bfarmRepository->updateUmsteigerInfo($type, $year, $code, $has_umsteiger);
                if (!$success) {
                    return false;
                }
            }
            $this->configRepository->writeConfig($config_entry, Constants::CONFIG_STATUS_OK);

            var_dump($type . $year . ' done'); // todo: proper output
        }

        return true;
    }
}