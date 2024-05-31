<?php

namespace App\Service;

use App\Repository\DatabaseRepository;

class UmsteigerService {

    private DatabaseRepository $dbRepo;
    private DataService $dataService;

    public function __construct(
        DatabaseRepository $dbRepo,
        DataService        $dataService
    ) {
        $this->dbRepo = $dbRepo;
        $this->dataService = $dataService;
    }

    public function test():void {

        $umsteiger_2024 = $this->generateAutoUmsteiger($this->dbRepo->getIcdUmsteiger('2024', '2023'));
        $umsteiger_2023 = $this->generateAutoUmsteiger($this->dbRepo->getIcdUmsteiger('2023', '2022'));

        var_dump($this->mergeAutoUmsteiger($umsteiger_2024, $umsteiger_2023));
    }

    public function test1():array {

        return $this->generateAutoUmsteiger($this->dbRepo->getIcdUmsteiger('2024', '2023'));
    }

    public function test2():void {

        // check for codes appearing multiple times
        $years = $this->dataService->getIcdUmsteigerYears();
        $data = array();
        foreach ($years as $year) {
            $umsteiger = $this->dbRepo->getIcdUmsteiger($year, $this->dataService->getIcdPreviousYear($year));
            $data[$year] = $this->generateAutoUmsteiger($umsteiger);
        }
        foreach ($years as $year1) {
            foreach ($years as $year2) {
                if($year1 == $year2) continue;
                foreach($data[$year1] as $old1 => $new1) {
                    foreach($data[$year2] as $old2 => $new2) {
                        if($old1 == $new2 || $old2 == $new1) {
                            var_dump("hit");
                        }
                    }
                }
            }
        }
    }

    // merging like this [2021->[2022->[2023->2024]]]
    public function mergeAllAutoUmsteiger ():array {

        $years = $this->dataService->getIcdUmsteigerYears();
        $umsteiger_years = array();
        foreach ($years as $year) {
            $umsteiger = $this->dbRepo->getIcdUmsteiger($year, $this->dataService->getIcdPreviousYear($year));
            $umsteiger_years[$year] = $this->generateAutoUmsteiger($umsteiger);
        }

        $ret = [];
        foreach ($umsteiger_years as $umsteiger_year) {
            $ret = $this->mergeAutoUmsteiger($ret, $umsteiger_year);
        }

        return $ret;
    }

    // if we find something like this:
    // 2024: B->C
    // 2023: A->B
    // we have to add A->C instead of A->B
    public function mergeAutoUmsteiger (array $current_umsteiger, array $previous_umsteiger):array {

        $ret = $current_umsteiger;
        foreach ($previous_umsteiger as $old_prev => $new_prev) {
            $match = '';
            foreach ($current_umsteiger as $old_curr => $new_curr) {
                if(strcmp($new_prev, $old_curr) == 0) {
                    $match = $new_curr;
                    var_dump("match"); // todo: debugging
                    break;
                }
            }
            if(isset($ret[$old_prev])) { // todo: debugging
                var_dump("code $old_prev already exists");
            }
            if($match!=='') {
                $ret[$old_prev] = $match;
            } else {
                $ret[$old_prev] = $new_prev;
            }
        }

        return $ret;
    }

    // Fall 1: alter Code -> mehrere neue Codes, inkl. alter Code | wird ignoriert
    // Fall 2: alter Code -> neuer Code                           | wird übernommen
    // Fall 3: alte Codes -> neue Codes, plus einer mit extra 0   | alter Code -> neuer Code mit extra 0
    // Fall 4: alte Codes -> neue Codes                           | der ähnlichste Code wird gewählt + Regel 3
    // Fall 5: UNDEF -> neuen Code                                | wird ignoriert
    // Fall 6: alter Code -> UNDEF                                | wird ignoriert

    public function generateAutoUmsteiger (array $umsteiger): array {

        $grouped = array();
        foreach($umsteiger as $code) {
            // remove unnecessary umsteiger
            if($code['old']==='UNDEF' || $code['new']==='UNDEF') {
                continue;
            }
            // group umsteiger by old code
            $exists = $grouped[$code['old']] ?? [];
            if(count($exists)==0) {
                $grouped[$code['old']] = [$code['new']];
            } else {
                $exists[] = $code['new'];
                $grouped[$code['old']] = $exists;
            }
        }

        // determine the most general new code
        $result = array();
        foreach ($grouped as $old => $group_new) {
            // sorted new codes by number of characters matching old code
            $sorted = array();
            foreach ($group_new as $new) {
                $mc = $this->count_character_matches($old, $new);
                $exists = $sorted[$mc] ?? [];
                if(count($exists)==0) {
                    $sorted[$mc] = [$new];
                } else {
                    $exists[] = $new;
                    $sorted[$mc] = $exists;
                }
            }
            // pick the group/code with the highest number of characters matching the old code
            ksort($sorted, SORT_NUMERIC);
            $best_group = end($sorted);
            // pick the first code by alphabetic sorting
            sort($best_group, SORT_STRING);
            $result[$old] = $best_group[0];
        }

        // remove redundant code changes (new = old)
        foreach ($result as $old => $new) {
            if(strcmp($new, $old)==0) {
                unset($result[$old]);
            }
        }

        return $result;
    }

    private function count_character_matches (string $first, string $second):int {
        $count = 0;
        for($i = 0; $i < strlen($first) && $i <strlen($second); $i++) {
            if($first[$i] === $second[$i]) {
                $count++;
            }
        }
        return $count;
    }
}