<?php

namespace App\Command;

use App\Service\ConceptMapService;
use App\Service\DataService;
use App\Service\UmsteigerService;
use App\Util\Constants;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

use App\Repository\DatabaseRepository;

#[AsCommand(name: 'test')]
class TestCommand extends Command {

    private DatabaseRepository $dbRepo;
    private DataService $dataService;
    private UmsteigerService $umsteigerService;
    private ConceptMapService $conceptMapService;
    private string $projectDir;
    
    public function __construct(
        DatabaseRepository $generalRepo,
        DataService        $dataService,
        UmsteigerService   $umsteigerService,
        ConceptMapService  $conceptMapService,
        string             $projectDir
    ) {
        parent::__construct();

        $this->dbRepo = $generalRepo;
        $this->dataService = $dataService;
        $this->umsteigerService = $umsteigerService;
        $this->conceptMapService = $conceptMapService;
        $this->projectDir = $projectDir;
        
        $this->serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder(), new XmlEncoder()]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {



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
