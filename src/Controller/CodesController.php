<?php

namespace App\Controller;

use App\Repository\ConfigRepository;
use App\Repository\DatabaseRepository;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CodesController extends AbstractController  {

    private DatabaseRepository $dbRepo;
    private ConfigRepository $configRepo;

    public function __construct(
        DatabaseRepository $dbRepo,
        ConfigRepository $configRepo
    ) {
        $this->dbRepo = $dbRepo;
        $this->configRepo = $configRepo;
    }

    /** @noinspection PhpUnused */
    #[Route('/{type}-codes-{year}', name: 'codes')]
    public function codes(string $type, string $year, Request $request): Response  {

        $search = $request->query->get('s') ?? '';
        $data = $this->dbRepo->readTerminalCodes($type, $year, $search);
        $has_umsteiger_info = Constants::CONFIG_STATUS_OK === $this->configRepo->readConfigStatus(
            Constants::config_name_umsteiger_info($type, $year), true
        );

        return $this->render('codes.html.twig',
            ['type' => $type, 'year' => $year, 'data' => $data, 'umsteigerInfo' => $has_umsteiger_info]);
    }
}
