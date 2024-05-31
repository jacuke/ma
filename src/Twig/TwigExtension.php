<?php

namespace App\Twig;

use App\Util\Constants;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension {

    public function getFunctions(): array {
        return [
            new TwigFunction('code_title', [$this, 'displayCodeTitle']),
        ];
    }

    public function displayCodeTitle(string $type): string {

        return Constants::display_name($type);
    }
}