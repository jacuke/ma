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
    private const KEEP = 'keep';
    private const PATIENTS = 'patients';
    private const UMSTEIGER_INFO = 'umsteiger';
    
    public function __construct(
        private readonly DataService $dataService,
        private readonly SetupService $setupService,
        private readonly PatientsService $patientsService,
        private readonly UmsteigerService $umsteigerService
    ) {
        parent::__construct();
    }

    protected function configure() : void {
    
        $this->addOption(self::SETUP, 's', InputOption::VALUE_NONE, 'Setup');
        $this->addOption(self::KEEP,  'k', InputOption::VALUE_NONE, 'For setup: keep files after download');
        $this->addOption(self::PATIENTS, 'p', InputOption::VALUE_OPTIONAL, 'Add patients', 0);
        $this->addOption(self::UMSTEIGER_INFO, 'u', InputOption::VALUE_NONE, 'Generate umsteiger info');
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

        $keep_files = (bool) $input->getOption(self::KEEP);

        // option: patients
        $num_patients = $input->getOption(self::PATIENTS) ?? 100;
        $num_patients = intval($num_patients);
        if($num_patients!=0) {
            $this->patientsService->addPatients($num_patients);
            return Command::SUCCESS;
        }

        // option: setup
        if($input->getOption(self::SETUP)) {

            $this->setupService->init();
            foreach ($code_systems as $type) {
                $xml = $this->dataService->readXmlData($type);
                foreach($xml as $entry) {
                    $status = $this->setupService->setupEntry($type, $entry, $keep_files);
                    $out = match($status) {
                        Constants::STATUS_INVALID =>
                            'Invalid entry: ' . Constants::XML_YEAR . ' missing in ' . Constants::file_name($type),
                        Constants::STATUS_EXISTS_OK =>
                            'Already exists: ' . $type . ' ' . $entry[Constants::XML_YEAR],
                        Constants::STATUS_ZIP_FAILED =>
                            'Failed to unzip nested file: ' . $type . ' ' . $entry[Constants::XML_YEAR],
                        Constants::STATUS_DOWNLOAD_FAILED =>
                            'Failed to download file: ' . $type . ' ' . $entry[Constants::XML_YEAR],
                        default =>
                            '',
                    };
                    if($out != '') {
                        $output->writeln($out);
                    }
                    if($status === Constants::STATUS_DOWNLOAD_FAILED) {
                        break;
                    }
                }
            }
        }

        // option: umsteiger info
        if($input->getOption(self::UMSTEIGER_INFO)) {
            $this->setupService->init();
            foreach ($code_systems as $type) {
                $this->umsteigerService->saveUmsteigerInfo($type);
            }
        }
        
        return Command::SUCCESS;
    }
}
