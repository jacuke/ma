<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Command;

use App\Repository\ConfigRepository;
use App\Repository\PatientsRepository;
use App\Service\ClientService;
use App\Service\ConceptMapService;
use App\Service\DataService;
use App\Service\SetupService;
use App\Service\UmsteigerService;
use App\Util\Constants;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use App\Repository\BfarmRepository;

#[AsCommand(name: 'test')]
class TestCommand extends Command implements LoggerAwareInterface {

    private const COMPARE_UMSTEIGER_SEARCHES = 'compare';

    private BfarmRepository $bfarmRepository;
    private ConfigRepository $configRepository;
    private PatientsRepository $patientsRepository;
    private DataService $dataService;
    private UmsteigerService $umsteigerService;
    private ConceptMapService $conceptMapService;
    private SetupService $setupService;
    private ClientService $clientService;
    private string $projectDir;

    private LoggerInterface $logger;

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

    public function __construct(
        BfarmRepository    $bfarmRepository,
        ConfigRepository   $configRepository,
        PatientsRepository $patientsRepository,
        DataService        $dataService,
        UmsteigerService   $umsteigerService,
        ConceptMapService  $conceptMapService,
        SetupService       $setupService,
        ClientService      $clientService,
        string             $projectDir
    ) {
        parent::__construct();

        $this->bfarmRepository = $bfarmRepository;
        $this->configRepository = $configRepository;
        $this->patientsRepository = $patientsRepository;
        $this->dataService = $dataService;
        $this->umsteigerService = $umsteigerService;
        $this->conceptMapService = $conceptMapService;
        $this->setupService = $setupService;
        $this->clientService = $clientService;
        $this->projectDir = $projectDir;
    }

    protected function configure() : void {

        $this->addOption(self::COMPARE_UMSTEIGER_SEARCHES, 'c', InputOption::VALUE_NONE, 'Compare umsteiger searches');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {

        if($input->getOption(self::COMPARE_UMSTEIGER_SEARCHES)) {

            // old
//            $horizontal = $this->hasUmsteigerHorizonzal('icd10gm', '2024');
//            $vertical = $this->hasUmsteigerVertical_test('icd10gm', '2024');
//            foreach($horizontal as $k => $h) {
//                $v = $vertical[$k];
//                if($h != $v) {
//                    $h_out = $h ? 'Y' : 'N';
//                    $v_out = $v ? 'Y' : 'N';
//                    $output->writeln("mismatch! $k - h: $h_out | v: $v_out");
//                }
//            }



        // new, only for 2024
//            $vertical = $this->hasUmsteigerVertical_test('icd10gm', '2024');
//            $codes = $this->dbRepo->readTerminalCodes('icd10gm', '2024');
//            foreach ($codes as $entry) {
//                $code = $entry['code'];
//                $u = $entry['umst'];
//                $u2 = $vertical[$code];
//                if($u != $u2) {
//                    $h_out = $u ? 'Y' : 'N';
//                    $v_out = $u2 ? 'Y' : 'N';
//                    $output->writeln("mismatch! $code - h: $h_out | v: $v_out");
//                } else {
//                    //$output->writeln("match: $code");
//                }
//            }

            $data = $this->umsteigerService->searchAllUmsteigerVertical('icd10gm');
            $oldestYear = $this->dataService->getOldestYear('icd10gm');
            $newestYear = $this->dataService->getNewestYear('icd10gm');

            foreach ($this->dataService->getYears('icd10gm') as $year) {
                $codes = $this->bfarmRepository->readCodes('icd10gm', $year);
                $output->writeln($year);
                foreach ($codes as $entry) {
                    $code = $entry['code'];
                    $u = $entry['umst'];

                    $oldest = isset($data[$oldestYear][$year][$code]);
                    $newest = isset($data[$newestYear][$year][$code]);

                    $u2 = $oldest || $newest;

                    if($u != $u2) {
                        $h_out = $u ? 'Y' : 'N';
                        $v_out = $u2 ? 'Y' : 'N';
                        $output->writeln("mismatch! $code - h: $h_out | v: $v_out");
                    }
                }
            }
            return Command::SUCCESS;
        }





//        $data = $this->umsteigerService->mergeAll2('icd10gm');
//        $oldestYear = $this->dataService->getOldestYear('icd10gm');
//        $newestYear = $this->dataService->getNewestYear('icd10gm');
//
//        var_dump($data[$oldestYear]['2025']['A00.0']);
//        var_dump($data[$newestYear]['2025']['A00.0']);

        return Command::SUCCESS;

//        $xml = $this->conceptMapService->test();
//        //$output->writeln($xml);
//        file_put_contents($this->projectDir . '/files/test.xml', $xml);
//        return Command::SUCCESS;


        //$config_entry = Constants::config_name_umsteiger_info('icd10gm', '2024');
        //$this->dbRepo->writeConfig($config_entry, 'OK');


        //$s = $this->dbRepo->searchUmsteiger('icd10gm', '2014', 'G83.88');
        //var_dump($s);


        //var_dump($this->dbRepo->readData('icd10gm', Constants::TABLE_CODES, '2024', '', 'A04.7'));

//        $has_umsteiger_info = Constants::CONFIG_STATUS_OK === $this->configRepo->readConfigStatus(
//            Constants::config_name_umsteiger_info(Constants::ICD10GM, '2022'), true
//        );
//        var_dump($has_umsteiger_info);



//        $data =  $this->umsteigerService->determineUmsteiger2('icd10gm', true);
//        var_dump($data['G83.8']);
//        return Command::SUCCESS;

        $data = $this->umsteigerService->searchUmsteigerVertical('icd10gm', '2020');
        //var_dump($data['2019']['T88.7']);
        var_dump($data['1.3']['A41.5']);
        return Command::SUCCESS;



//        $xml = $this->conceptMapService->test();
//        file_put_contents($this->projectDir . '/files/test2.xml', $xml);
//        return Command::SUCCESS;

        // this
        $data =  $this->umsteigerService->searchUmsteigerVertical('icd10gm', '2024');
        //var_dump($data);
        var_dump($data['2023']['Q35.6']);
        return Command::SUCCESS;



        $this->umsteigerService->mergeAllAutoUmsteiger();
        return Command::SUCCESS;

        $type = 'icd10gm';
        $years = $this->dataService->getUmsteigerYears($type);
        foreach ($years as $year) {
            $data = $this->bfarmRepository->readData($type, Constants::TABLE_UMSTEIGER, $year);
            var_dump($data);
        }
        return Command::SUCCESS;

//        $umst = $this->dbRepo->getIcdUmsteiger('2020', '2019');
//        $auto = $this->umsteigerService->generateAutoUmsteiger($umst);
//
//        $data = $umst;
//        foreach($data as &$entry) {
//            $has_auto = $auto[$entry['old']] ?? '';
//            if($has_auto!=='') {
//                $entry['auto'] = $has_auto;
//            }
//        }
//        var_dump($data);







//        var_dump($umst);
//        var_dump($auto);


        //$output->writeln(Constants::file_name(Constants::ICD10GM));

        //var_dump($this->umsteigerService->test1());

//            var_dump($this->dataService->getIcdYears());
//            var_dump($this->umsteigerService->mergeAllAutoUmsteiger());
        //$this->umsteigerService->mergeAllAutoUmsteiger();
        //$this->umsteigerService->test();

        return Command::SUCCESS;
    }

//    protected function hasUmsteigerHorizonzal($type, $year): array {
//
//        $result = [];
//        $codes = $this->bfarmRepository->readCodes($type, $year);
//        $i=0;
//        foreach ($codes as $entry) {
//            $code = $entry['code'];
//            $umsteiger_search = $this->bfarmRepository->searchUmsteiger($type, $year, $code);
//            $has_umsteiger = (bool)(count($umsteiger_search['fwd']) + count($umsteiger_search['rev']));
//            $result[$code] = $has_umsteiger;
//            $i++;
//            if($i>100) {
//                //break;
//            }
//        }
//        return $result;
//    }
//
//
////    protected function hasUmsteigerVertical($type, $year): array {
////
////        $result = [];
////        $umsteiger_search = $this->umsteigerService->mergeAll($type, $year);
////        $codes = $this->dbRepo->readTerminalCodes($type, $year);
////        foreach ($codes as $entry) {
////            $code = $entry['code'];
////            if(isset($umsteiger_search['fwd'][$code]) || isset($umsteiger_search['rev'][$code]) ) {
////                $result[$code] = true;
////            } else {
////                $result[$code] = false;
////            }
////        }
////        return $result;
////    }
//
//    protected function hasUmsteigerVertical_test($type, $year): array {
//
//        $result = [];
//        $umsteiger_search = $this->umsteigerService->determineUmsteigerAll($type, '1.3');
//        $codes = $this->bfarmRepository->readCodes($type, $year);
//        foreach ($codes as $entry) {
//            $code = $entry['code'];
//            if(isset($umsteiger_search['2024'][$code])) {
//                $result[$code] = true;
//            } else {
//                $result[$code] = false;
//            }
//        }
//        return $result;
//    }


//    // todo: return type
//    public function saveUmsteigerInfo_slow (string $type, string $year): bool {
//
//        if(!$this->bfarmRepository->tableExists(Constants::TABLE_CONFIG)) {
//            return false; // todo
//        }
//
//        $config_entry = Constants::config_name_umsteiger_info($type, $year);
//
//        $status = $this->configRepository->readConfigStatus($config_entry);
//        if($status!==Constants::CONFIG_STATUS_OK) {
//            $codes = $this->bfarmRepository->readCodes($type, $year);
//            foreach ($codes as $entry) {
//                $code = $entry['code'];
//                $umsteiger_search = $this->bfarmRepository->searchUmsteiger($type, $year, $code);
//                $has_umsteiger = (bool)(count($umsteiger_search['fwd']) + count($umsteiger_search['rev']));
//                if($has_umsteiger) {
//                    $success = $this->bfarmRepository->updateUmsteigerInfo($type, $year, $code, true);
//                    if(!$success) {
//                        return false;
//                    }
//                }
//            }
//            $this->configRepository->writeConfig($config_entry, Constants::CONFIG_STATUS_OK);
//
//        } else {
//            // todo
//            var_dump($type . $year . ' already exists');
//        }
//
//        return true;
//    }

}
