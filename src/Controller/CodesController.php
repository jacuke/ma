<?php

namespace App\Controller;

use App\Repository\DatabaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CodesController extends AbstractController  {

    private DatabaseRepository $dbRepo;

    public function __construct(
        DatabaseRepository $dbRepo
    ) {
        $this->dbRepo = $dbRepo;
    }

    /** @noinspection PhpUnused */
    #[Route('/{type}_codes/{year}', name: 'codes')]
    public function codes(string $type, string $year, Request $request): Response  {

        $search = $request->request->get('search') ?? '';
        $data = $this->dbRepo->readTerminalCodes($type, $year, $search);

        return $this->render('codes.html.twig',
            ['type' => $type, 'year' => $year, 'data' => $data]);
    }
}
