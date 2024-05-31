<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SimonController extends AbstractController
{
    #[Route('/simon', name: 'simon')]
    public function simon($projectDir): Response
    {
        return $this->render('simon.html.twig', ['simon_data' => $projectDir]);
    }
}
