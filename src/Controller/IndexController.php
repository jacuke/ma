<?php

namespace App\Controller;

use App\Service\DataService;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController  {

    public function __construct(private readonly DataService $dataService) {}

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
