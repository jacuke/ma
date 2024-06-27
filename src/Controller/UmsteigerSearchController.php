<?php

namespace App\Controller;

use App\Repository\DatabaseRepository;
use App\Service\DataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UmsteigerSearchController extends AbstractController {

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
    #[Route('/{type}_umsteiger_suche', name: 'umsteiger_suche')]
    public function umsteiger_suche(string $type): Response {

        $data = $this->dbRepo->readUmsteigerHistory($type, '2024', 'U69.04');
        $view = $this->render_recursive($data);

        return $this->render('test.html.twig', ['view' => $view, 'data' => $data]);

//        $years = $this->dataService->getUmsteigerYears($type);
//        $data = array();
//        foreach ($years as $year) {
//            $prev = $this->dataService->getPreviousYear($type, $year);
//            $umsteiger = $this->dbRepo->getUmsteigerWithNames(
//                $type, $year, $prev
//            );
//            $data[$year] = [
//                'prev' => $prev,
//                'codes' => $umsteiger,
//                'rate' => number_format(
//                    round(count($umsteiger) / $this->dbRepo->countCodes($type, $year) * 100, 2),
//                    2, ',', ''
//                )
//            ];
//        }
//
//        return $this->render('umsteiger.html.twig',
//            ['type' => $type, 'data' => $data]);
    }

    private function render_recursive (array $data) :string {

        foreach ($data['umsteiger'] as &$v) {
            if(isset($v['history'])) {
                $v['subsection'] = $this->render_recursive ($v['history']);
            }
        }
        return $this->renderView('umsteiger_suche_teil.html.twig',
            ['data'=> $data]);
    }
}