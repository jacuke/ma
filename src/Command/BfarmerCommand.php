<?php

namespace App\Command;

use App\Service\DataService;
use App\Service\PatientsService;
use App\Service\SetupService;
use App\Service\UmsteigerService;
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
    private const UMSTEIGER_INFO = 'umsteiger';

    private DataService $dataService;
    private SetupService $setupService;
    private PatientsService $patientsService;
    private UmsteigerService $umsteigerService;
    
    public function __construct(
        DataService $dataService,
        SetupService $setupService,
        PatientsService $patientsService,
        UmsteigerService $umsteigerService
    ) {
        parent::__construct();

        $this->dataService = $dataService;
        $this->setupService = $setupService;
        $this->patientsService = $patientsService;
        $this->umsteigerService = $umsteigerService;
    }

    protected function configure() : void {
    
        $this->addOption(self::SETUP, 's', InputOption::VALUE_NONE, 'Setup');
        $this->addOption(self::PATIENTS, 'p', InputOption::VALUE_OPTIONAL, 'Add patients', 0);
        $this->addOption(self::UMSTEIGER_INFO, 'u', InputOption::VALUE_NONE, 'Generate Umsteiger Info');
        $this->addOption(Constants::ICD10GM, 'i', InputOption::VALUE_NONE, 'Only ICD10GM');
        $this->addOption(Constants::OPS, 'o', InputOption::VALUE_NONE, 'Only OPS');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {

        if($input->getOption(Constants::ICD10GM)) {
            $code_systems = [Constants::ICD10GM];
        } elseif($input->getOption(Constants::OPS)) {
            $code_systems = [Constants::OPS];
        } else {
            $code_systems = Constants::CODE_SYSTEMS;
        }

        // todo: only allow one command (that does stuff)

        // option: umsteiger info
        if($input->getOption(self::UMSTEIGER_INFO)) {
            foreach ($code_systems as $type) {
                $years = $this->dataService->getYears($type);
                foreach ($years as $year) {
                    $this->umsteigerService->saveUmsteigerInfo($type, $year);
                }
            }
        }

        // option: patients
        $num_patients = $input->getOption(self::PATIENTS) ?? 100;
        $num_patients = intval($num_patients);
        if($num_patients!=0) {
            $this->patientsService->addPatients($num_patients);
        }

        // option: setup
        if($input->getOption(self::SETUP)) {

            $this->setupService->init();
            foreach ($code_systems as $type) {
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
