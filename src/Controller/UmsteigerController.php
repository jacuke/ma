<?php

namespace App\Controller;

use App\Repository\DatabaseRepository;
use App\Service\DataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UmsteigerController extends AbstractController {

    private DatabaseRepository $dbRepo;
    private DataService $dataService;

    public function __construct(
        DatabaseRepository $dbRepo,
        DataService        $dataService
    ) {
        $this->dbRepo = $dbRepo;
        $this->dataService = $dataService;
    }

    /** @noinspection PhpUnused */
    #[Route('/{type}_umsteiger', name: 'umsteiger')]
    public function umsteiger(string $type): Response {

        $years = $this->dataService->getUmsteigerYears($type);
        $data = array();
        foreach ($years as $year) {
            $prev = $this->dataService->getPreviousYear($type, $year);
            $umsteiger = $this->dbRepo->getUmsteigerWithNames(
                $type, $year, $prev
            );
            $data[$year] = [
                'prev' => $prev,
                'codes' => $umsteiger,
                'rate' => number_format(
                    round(count($umsteiger) / $this->dbRepo->countCodes($type, $year) * 100, 2),
                    2, ',', ''
                )
            ];
        }

        return $this->render('umsteiger.html.twig',
            ['type' => $type, 'data' => $data]);
    }
}