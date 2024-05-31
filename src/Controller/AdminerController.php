<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AdminerController extends AbstractController {

    private string $projectDir;

    public function __construct(string $projectDir) {

        $this->projectDir = $projectDir;
    }

    #[Route('/adminer', name: 'adminer_test')]
    public function adminer(): Response {

        return new Response(include_once $this->projectDir . '/public/adminer.php');
    }
}