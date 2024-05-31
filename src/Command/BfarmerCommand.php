<?php

namespace App\Command;

use App\Service\DataService;
use App\Service\SetupService;
use App\Util\Constants;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'bfarmer')]
class BfarmerCommand extends Command {

    private DataService $dataService;
    private SetupService $setupService;
    
    public function __construct(
        DataService $dataService,
        SetupService $setupService
    ) {
        parent::__construct();

        $this->dataService = $dataService;
        $this->setupService = $setupService;
    }

    protected function configure() : void {
    
        $this->addOption('setup', 's', InputOption::VALUE_NONE, 'Setup');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {

        if($input->getOption('setup')) {

            $this->setupService->init();
            foreach (Constants::CODE_SYSTEMS as $type) {
                $xml = $this->dataService->readXmlData($type);
                foreach($xml as $entry) {
                    $this->setupService->setupEntry($type, $entry);
                }
            }

            return Command::SUCCESS;
        }
        
        return Command::SUCCESS;
    }
}
