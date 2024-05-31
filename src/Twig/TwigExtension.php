<?php

namespace App\Twig;

use App\Service\DataService;
use App\Util\Constants;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension {

    private DataService $dataService;

    public function __construct(DataService $dataService) {
        $this->dataService = $dataService;
    }

    public function getFunctions(): array {
        return [
            new TwigFunction('code_title', [$this, 'displayCodeTitle']),
            new TwigFunction('code_external_link', [$this, 'codeExternalLink']),
        ];
    }

    public function displayCodeTitle(string $type): string {

        return Constants::display_name($type);
    }

    public function codeExternalLink(string $type, string $year, string $code): string {

        if($code==='UNDEF' ||
            ($type !== Constants::ICD10GM && $type !== Constants::OPS) ||
            in_array($year, $this->dataService->getVorabYears($type)) ||
            $year === '1.1'
        ) {
            return $code;
        }

        $year = Constants::year_str_to_int($year);

        return '<a class="code-external-link" target="_blank" onclick="externalCodeLink(' .
            "'$type','$year','$code'" . ')">' . $code . '</a>';
    }
}