<?php

namespace App\Service;

use App\Repository\ConfigRepository;
use App\Repository\BfarmRepository;
use App\Repository\PatientsRepository;
use App\Util\Constants;

class PatientsService {

    private array $yearBins;
    private const YEAR_BINS = 3;
    private const NUM_CODES_BINS = [1, 2, 2, 2, 3, 3, 3, 3, 3, 4, 4, 5];

    public function __construct(
        private readonly PatientsRepository $patientsRepository,
        private readonly ConfigRepository   $configRepository,
        private readonly BfarmRepository    $bfarmRepository,
        private readonly DataService        $dataService,
        private readonly UmsteigerService   $umsteigerService,
    ) {
        $this->patientsRepository->addPatientsTable();
        $this->yearBins = array();
    }

    public function addPatients(int $num) :void {

        $random_years = array();
        for($i = 0; $i < $num; $i++) {

            $random_year = $this->draw_random_year();
            if(!isset($random_years[$random_year])) {
                $random_years[$random_year] = 1;
            } else {
                $random_years[$random_year]++;
            }
        }

        foreach ($random_years as $k => $v) {
            $patients = $this->create_random_patients($k, $v);
            $this->patientsRepository->addPatients($patients);
        }
    }

    private function create_random_patients(string $year, int $num):array {

        $patients = array();
        $has_umsteiger_info = Constants::CONFIG_STATUS_OK === $this->configRepository->readConfigStatus(
            Constants::config_name_umsteiger_info(Constants::ICD10GM, $year)
        );
        $year_int = Constants::year_str_to_int($year);
        $data = $this->bfarmRepository->readCodes(Constants::ICD10GM, $year);
        for($i = 0; $i < $num; $i++) {

            $num_codes = $this->draw_random_num_codes();
            if($num_codes <= 1) {
                $rand_keys = [array_rand($data)];
            } else {
                $rand_keys = array_rand($data, $num_codes);
            }
            $patient = array();
            $codes = array();
            $names = array();
            $umsteiger = array();
            foreach($rand_keys as $key) {
                $code = $data[$key]['code'];
                $codes[] = $code;
                $names[] = $data[$key]['name'];
                if($has_umsteiger_info) {
                    $umsteiger[] = (bool) $data[$key]['umst'];
                } else {
                    $umsteiger_search = $this->umsteigerService->searchUmsteigerHorizontal(Constants::ICD10GM, $year, $code);
                    $umsteiger[] = (bool)(count($umsteiger_search['fwd']) + count($umsteiger_search['rev']));
                }
            }
            $patient['id'] = '0';
            $patient['year'] = $year_int;
            $patient['codes'] = json_encode($codes);
            $patient['names'] = json_encode($names, JSON_UNESCAPED_UNICODE );
            $patient['umsteiger'] = json_encode($umsteiger);
            $patients[] = $patient;
        }

        return $patients;
    }

    private function draw_random_year () : string {

        if(count($this->yearBins) === 0) {
            $this->yearBins = $this->create_year_bins();
        }

        $random_bin = rand(0, self::YEAR_BINS -1);
        $random_year = rand(1, $this->yearBins[$random_bin]);
        return $this->dataService->getYears(Constants::ICD10GM)[$random_year-1];
    }

    private function create_year_bins() : array {

        $years = $this->dataService->getYears(Constants::ICD10GM);
        $years_num = count($years);
        $div_rounded = floor($years_num / self::YEAR_BINS);
        $div_rest = $years_num % self::YEAR_BINS;
        $year_bins = array();
        for ($i = 1; $i <= self::YEAR_BINS; $i++) {
            $year_bins[] = ($i * $div_rounded) + $div_rest;
        }
        return $year_bins;
    }

    private function draw_random_num_codes() : int {

        $random_bin = rand(0, count(self::NUM_CODES_BINS) -1);
        return self::NUM_CODES_BINS[$random_bin];
    }
}