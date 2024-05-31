<?php

namespace App\Controller;

use App\Service\DataService;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController  {

    private DataService $dataService;

    public function __construct(
        DataService $dataService
    ) {
        $this->dataService = $dataService;
    }

    #[Route('/', name: 'index')]
    public function simon(): Response  {

        $data = array();
        foreach(Constants::CODE_SYSTEMS as $type) {
            $data[$type]['years'] = $this->dataService->getYears($type);
        }

        return $this->render('index.html.twig',
            ['data' => $data]
        );
    }
}
