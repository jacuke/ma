<?php

namespace App\Controller;

use App\Repository\BfarmRepository;
use App\Service\DataService;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UmsteigerController extends AbstractController {

    public function __construct(
        private readonly BfarmRepository $bfarmRepository,
        private readonly DataService     $dataService
    ) {}

    /** @noinspection PhpUnused */
    #[Route('/{type}-umsteiger', name: 'umsteiger')]
    public function umsteiger(string $type): Response {

        $years = $this->dataService->getUmsteigerYears($type);
        $data = array();
        foreach ($years as $year) {
            $prev = $this->dataService->getNextOlderYear($type, $year);
            $umsteiger = $this->bfarmRepository->readData($type, Constants::TABLE_UMSTEIGER_JOIN, $year, $prev);
            $code_count = $this->bfarmRepository->countCodes($type, $year);
            if($code_count===0) {
                $rate = '0';
            } else {
                $rate = number_format(round(count($umsteiger) / $code_count * 100, 2), 2, ',', '');
            }
            $data[$year] = [
                'prev' => $prev,
                'codes' => $umsteiger,
                'rate' => $rate
            ];
        }

        return $this->render('umsteiger.html.twig',
            ['type' => $type, 'data' => $data, 'fhir_versions' => Constants::FHIR_VERSIONS]);
    }

    /** @noinspection PhpUnused */
    #[Route('/umsteiger-icons', name: 'umsteiger_icons')]
    public function umsteiger_icons (Request $request): Response {

        $year = $request->query->get('y') ?? '';
        $prev = $request->query->get('p') ?? '';

        return $this->render('umsteiger_icons.html.twig',
            ['year' => $year, 'prev' => $prev]);
    }
}