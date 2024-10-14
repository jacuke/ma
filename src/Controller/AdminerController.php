<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AdminerController extends AbstractController {

    public function __construct(private readonly string $projectDir) {}

    /** @noinspection PhpUnused */
    #[Route('/adminer', name: 'adminer_test')]
    public function adminer(): Response {

        return new Response(include_once $this->projectDir . '/public/adminer.php');
    }
}