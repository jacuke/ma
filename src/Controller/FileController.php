<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FileController extends AbstractController {

    public function __construct(private readonly string $projectDir) {}

    /** @noinspection PhpUnused */
    #[Route('/vortrag', name: 'vortrag')]
    public function vortrag(): Response {

        return new BinaryFileResponse($this->projectDir . '/tex/beamer/vortrag.pdf');
    }
}