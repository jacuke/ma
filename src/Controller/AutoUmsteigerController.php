<?php

namespace App\Controller;

use App\Repository\DatabaseRepository;
use App\Service\DataService;
use App\Service\UmsteigerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AutoUmsteigerController extends AbstractController {

    private DatabaseRepository $dbRepo;
    private DataService $dataService;
    private UmsteigerService $umsteigerService;

    public function __construct(
        DatabaseRepository $dbRepo,
        DataService        $dataService,
        UmsteigerService   $umsteigerService
    ) {
        $this->dbRepo = $dbRepo;
        $this->dataService = $dataService;
        $this->umsteigerService = $umsteigerService;
    }

    /** @noinspection PhpUnused */
    #[Route('/{type}_auto_umsteiger', name: 'auto_umsteiger')]
    public function auto_umsteiger(string $type): Response {

        $years = $this->dataService->getUmsteigerYears($type);
        $data = array();
        foreach ($years as $year) {
            $prev = $this->dataService->getPreviousYear($type, $year);
            $umsteiger = $this->dbRepo->getUmsteiger($type, $year, $prev);
            $add = array();
            $add['prev'] = $prev;
            $add['umsteiger'] = $this->umsteigerService->generateAutoUmsteiger($umsteiger);
            $data[$year] = $add;
        }

        return $this->render('auto_umsteiger.html.twig',
            ['type' => $type, 'data' => $data]);
    }
}