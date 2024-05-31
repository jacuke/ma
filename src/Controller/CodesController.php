<?php

namespace App\Controller;

use App\Repository\DatabaseRepository;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function codes(string $type, string $year): Response  {

        $data = $this->dbRepo->readData($type, Constants::TABLE_CODES, $year);

        return $this->render('codes.html.twig',
            ['type' => $type, 'year' => $year, 'data' => $data]);
    }
}
