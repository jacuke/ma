<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace App\Command;

use App\Repository\ConfigRepository;
use App\Repository\PatientsRepository;
use App\Service\ConceptMapService;
use App\Service\DataService;
use App\Service\UmsteigerService;
use App\Util\Constants;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use App\Repository\DatabaseRepository;

#[AsCommand(name: 'test')]
class TestCommand extends Command {

    private DatabaseRepository $dbRepo;
    private ConfigRepository $configRepo;
    private PatientsRepository $patientsRepo;
    private DataService $dataService;
    private UmsteigerService $umsteigerService;
    private ConceptMapService $conceptMapService;
    private string $projectDir;
    
    public function __construct(
        DatabaseRepository $generalRepo,
        ConfigRepository   $configRepo,
        PatientsRepository $patientsRepo,
        DataService        $dataService,
        UmsteigerService   $umsteigerService,
        ConceptMapService  $conceptMapService,
        string             $projectDir
    ) {
        parent::__construct();

        $this->dbRepo = $generalRepo;
        $this->configRepo = $configRepo;
        $this->patientsRepo = $patientsRepo;
        $this->dataService = $dataService;
        $this->umsteigerService = $umsteigerService;
        $this->conceptMapService = $conceptMapService;
        $this->projectDir = $projectDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {

        //$config_entry = Constants::config_name_umsteiger_info('icd10gm', '2024');
        //$this->dbRepo->writeConfig($config_entry, 'OK');


        //$s = $this->dbRepo->searchUmsteiger('icd10gm', '2014', 'G83.88');
        //var_dump($s);


        //var_dump($this->dbRepo->readData('icd10gm', Constants::TABLE_CODES, '2024', '', 'A04.7'));

        $has_umsteiger_info = Constants::CONFIG_STATUS_OK === $this->configRepo->readConfigStatus(
            Constants::config_name_umsteiger_info(Constants::ICD10GM, '2022'), true
        );
        var_dump($has_umsteiger_info);


        return Command::SUCCESS;



        $history = $this->dbRepo->readUmsteigerHistory('icd10gm', '2021', 'K57.02');
        var_dump($history);
        return Command::SUCCESS;

        $this->umsteigerService->mergeAllAutoUmsteiger();
        return Command::SUCCESS;

        $type = 'icd10gm';
        $years = $this->dataService->getUmsteigerYears($type);
        foreach ($years as $year) {
            $data = $this->dbRepo->readData($type, Constants::TABLE_UMSTEIGER, $year);
            var_dump($data);
        }
        return Command::SUCCESS;

        $data = $this->dbRepo->readUmsteigerHistory('icd10gm', '2024', 'U69.04');
        var_dump($data);

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

        $xml = $this->conceptMapService->test();
        //$output->writeln($xml);
        file_put_contents($this->projectDir . '/files/test.xml', $xml);

        return Command::SUCCESS;

        //var_dump($this->umsteigerService->test1());

//            var_dump($this->dataService->getIcdYears());
//            var_dump($this->umsteigerService->mergeAllAutoUmsteiger());
        //$this->umsteigerService->mergeAllAutoUmsteiger();
        //$this->umsteigerService->test();

        return Command::SUCCESS;
    }
}
