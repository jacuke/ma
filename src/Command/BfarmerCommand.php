<?php

namespace App\Command;

use App\Service\DataService;
use App\Service\PatientsService;
use App\Service\SetupService;
use App\Util\Constants;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bfarmer')]
class BfarmerCommand extends Command {

    private const SETUP = 'setup';
    private const PATIENTS = 'patients';

    private DataService $dataService;
    private SetupService $setupService;
    private PatientsService $patientsService;
    
    public function __construct(
        DataService $dataService,
        SetupService $setupService,
        PatientsService $patientsService
    ) {
        parent::__construct();

        $this->dataService = $dataService;
        $this->setupService = $setupService;
        $this->patientsService = $patientsService;
    }

    protected function configure() : void {
    
        $this->addOption(self::SETUP, 's', InputOption::VALUE_NONE, 'Setup');
        $this->addOption(self::PATIENTS, 'p', InputOption::VALUE_OPTIONAL, 'Add patients', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {

        // option: patients
        $num_patients = $input->getOption(self::PATIENTS) ?? 100;
        $num_patients = intval($num_patients);
        if($num_patients!=0) {
            $this->patientsService->addPatients($num_patients);
        }

        // option: setup
        if($input->getOption(self::SETUP)) {

            $this->setupService->init();
            foreach (Constants::CODE_SYSTEMS as $type) {
                $xml = $this->dataService->readXmlData($type);
                foreach($xml as $entry) {
                    $status = $this->setupService->setupEntry($type, $entry);
                    $out = match($status) {
                        Constants::STATUS_INVALID =>
                            'Invalid entry: ' . Constants::XML_YEAR . ' missing in ' . Constants::file_name($type),
                        Constants::STATUS_EXISTS_OK =>
                            'Already exists: ' . $type . ' ' . $entry[Constants::XML_YEAR],
                        default =>
                            '',
                    };
                    if($out != '') {
                        $output->writeln($out);
                    }
                }
            }

            return Command::SUCCESS;
        }
        
        return Command::SUCCESS;
    }
}
