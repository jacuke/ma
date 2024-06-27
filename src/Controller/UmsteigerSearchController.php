<?php

namespace App\Controller;

use App\Repository\DatabaseRepository;
use App\Service\DataService;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    public function umsteiger_suche(string $type, Request $request): Response {

        $render_results = array();
        $search = $request->query->get('s') ?? '';
        if(strlen($search)>1) {
            $first_year = $this->dataService->getNewestYear($type);
            $results = $this->dbRepo->readTerminalCodes($type, $first_year, $search);
            foreach ($results as $result) {
                $code = $result['code'];
                if($code===Constants::UNDEF) {
                    continue;
                }
                $view = $this->render_history_recursive(
                    $this->dbRepo->readUmsteigerHistory($type, $first_year, $code)
                );
                $render_results[] = $this->render_search_result(
                    ['code' => $code, 'name' => $result['name'], 'view' => $view]
                );
            }
        }

        return $this->render('umsteiger_search.html.twig', ['type' => $type, 'results' => $render_results, 'search' => $search]);

        $data = $this->dbRepo->readUmsteigerHistory($type, '2024', 'U69.04');
        $view = $this->render_history_recursive($data);

        return $this->render('umsteiger_search_result.html.twig', ['view' => $view, 'data' => $data]);

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

    private function render_search_result (array $data) :string {

        return $this->renderView('umsteiger_search_result.html.twig', $data);
    }

    private function render_history_recursive (array $data) :string {

        if(empty($data)) {
            return '';
        }

        foreach ($data['umsteiger'] as &$v) {
            if(isset($v['history'])) {
                $v['subsection'] = $this->render_history_recursive ($v['history']);
            }
        }
        return $this->renderView('umsteiger_search_history.html.twig',
            ['data'=> $data]);
    }
}