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
    #[Route('/{type}-umsteiger-suche', name: 'umsteiger_suche')]
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
                $history = $this->render_history_recursive(
                    $this->dbRepo->readUmsteigerHistory($type, $first_year, $code)
                );
                $render_results[] = $this->render_search_result(
                    ['code' => $code, 'name' => $result['name'], 'history' => $history]
                );
            }
        }

        return $this->render('umsteiger_search.html.twig', ['type' => $type, 'results' => $render_results, 'search' => $search]);
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
        return $this->renderView('umsteiger_search_history.html.twig', ['data'=> $data]);
    }

    #[Route('/umsteiger-suche-api', name: 'umsteiger_search_api')]
    public function umsteiger_search_api(Request $request): Response {

        $type = $request->query->get('t') ?? '';
        $year = $request->query->get('y') ?? '';
        $code = $request->query->get('s') ?? '';
        if($type==='' || $year==='' || $code==='') {
            $content = '<div></div>';
        } else {
            $results = $this->dbRepo->readUmsteigerHistory($type, $year, $code);
            if(empty($results)) {
                $content = '<div>Keine Ergebnisse</div>';
            } else {
                $history = $this->render_history_recursive($results);
                $name = $results['umsteiger'][0]['new_name'];
                $content = $this->render_search_result(
                    ['code' => $code, 'name' => $name, 'history' => $history]
                );
            }
        }

        return $this->render('modal.html.twig', ['content'=> $content]);
    }
}