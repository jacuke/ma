<?php /** @noinspection PhpUnused */

namespace App\Controller;

use App\Repository\BfarmRepository;
use App\Service\DataService;
use App\Service\UmsteigerService;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UmsteigerSearchController extends AbstractController {

    private BfarmRepository $bfarmRepository;
    private DataService $dataService;
    private UmsteigerService $umsteigerService;

    public function __construct(
        BfarmRepository  $bfarmRepository,
        DataService      $dataService,
        UmsteigerService $umsteigerService
    ) {
        $this->bfarmRepository = $bfarmRepository;
        $this->dataService = $dataService;
        $this->umsteigerService = $umsteigerService;
    }

    /** @noinspection PhpUnused */
    #[Route('/{type}-umsteiger-suche', name: 'umsteiger_suche')]
    public function umsteiger_suche(string $type, Request $request): Response {

        $render_results = array();
        $searchCode = $request->query->get('s') ?? '';
        $searchYear = $request->query->get('y') ?? '';
        $years = $this->dataService->getYears($type);
        if($searchYear==='') {
            $searchYear = $this->dataService->getNewestYear($type);
        }
        if(strlen($searchCode)>1) {
            $results = $this->bfarmRepository->readCodes($type, $searchYear, $searchCode);
            foreach ($results as $result) {

                $data = array();
                $code = $result['code'];
                if($code===Constants::UNDEF) {
                    continue;
                }
                $searchUmsteiger = $this->umsteigerService->searchUmsteigerHorizontal($type, $searchYear, $code);
                foreach ($searchUmsteiger as $key => $direction) {
                    if(!empty($direction)) {
                        $data[$key] = $this->render_recursive($type, $direction, $key==='fwd');
                    }
                }

                $render_results[] = $this->renderView('umsteiger_search_result.html.twig',
                    ['type' => $type, 'code' => $code, 'name' => $result['name'], 'data' => $data, 'searchYear' => $searchYear]
                );
            }
        }

        return $this->render('umsteiger_search.html.twig',
            ['type' => $type, 'results' => $render_results, 'searchCode' => $searchCode,
                'years' => $years, 'searchYear' => $searchYear]);
    }

    private function render_recursive (string $type, array $data, bool $chronological) :string {

        if(empty($data)) {
            return '';
        }

        foreach ($data['umsteiger'] as &$v) {
            if(isset($v['recursion'])) {
                $v['subsection'] = $this->render_recursive ($type, $v['recursion'], $chronological);
            }
        }
        return $this->renderView('umsteiger_search_recursion.html.twig',
            ['type' => $type, 'data'=> $data, 'chronological' => $chronological]);
    }

    #[Route('/umsteiger-suche-api', name: 'umsteiger_search_api')]
    public function umsteiger_search_api(Request $request): Response {

        $type = $request->query->get('t') ?? '';
        $year = $request->query->get('y') ?? '';
        $code = $request->query->get('s') ?? '';
        if($type==='' || $year==='' || $code==='') {
            $content = '<div></div>';
        } else {
            $name = '';
            $data = array();
            $searchUmsteiger = $this->umsteigerService->searchUmsteigerHorizontal($type, $year, $code);
            foreach ($searchUmsteiger as $key => $direction) {
                if(!empty($direction)) {
                    $name = $direction['umsteiger'][0][$key==='fwd' ? 'old_name' : 'new_name'] ?? '';
                    $data[$key] = $this->render_recursive($type, $direction, $key==='fwd');
                }
            }
            if(empty($data)) {
                $content = '<div>Keine Ergebnisse</div>';
            } else {
                $content = $this->renderView('umsteiger_search_result.html.twig',
                    ['type' => $type, 'code' => $code, 'name' => $name, 'data' => $data, 'searchYear' => $year]
                );
            }
        }

        return $this->render('modal.html.twig', ['content'=> $content]);
    }
}