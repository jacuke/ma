<?php

namespace App\Controller;

use App\Repository\ConfigRepository;
use App\Repository\BfarmRepository;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CodesController extends AbstractController  {

    private BfarmRepository $bfarmRepository;
    private ConfigRepository $configRepository;

    public function __construct(
        BfarmRepository  $bfarmRepository,
        ConfigRepository $configRepository
    ) {
        $this->bfarmRepository = $bfarmRepository;
        $this->configRepository = $configRepository;
    }

    /** @noinspection PhpUnused */
    #[Route('/{type}-codes-{year}', name: 'codes')]
    public function codes(string $type, string $year, Request $request): Response  {

        $search = $request->query->get('s') ?? '';
        $data = $this->bfarmRepository->readCodes($type, $year, $search);
        $has_umsteiger_info = Constants::CONFIG_STATUS_OK === $this->configRepository->readConfigStatus(
            Constants::config_name_umsteiger_info($type, $year)
        );

        return $this->render('codes.html.twig',
            ['type' => $type, 'year' => $year, 'data' => $data, 'umsteigerInfo' => $has_umsteiger_info]);
    }
}
