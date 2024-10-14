<?php /** @noinspection PhpUnused */

namespace App\Controller;

use App\Service\ClientService;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DimdiController extends AbstractController {

    public function __construct(private readonly ClientService $clientService) {}

    #[Route('/dimdi-findcode', name: 'dimdi_findcode')]
    public function dimdi_findcode(Request $request): Response {

        $type = $request->query->get('t') ?? '';
        $code = $request->query->get('c') ?? '';
        $year = $request->query->get('y') ?? '';
        $code = strtolower($code);

        $short_code = match($type) {
            Constants::ICD10GM => substr($code, 0, 3),
            Constants::OPS => substr($code, 0, 4),
        };

        $year_link = match($type) {
            Constants::ICD10GM => match($year) {
                '13' => 'htmlsgbv',
                '20' => 'htmlsgbv20',
                default => 'htmlgm' . $year,
            },
            Constants::OPS => match($year) {
                '20' => 'opshtml',
                '21' => 'erwopshtml21',
                default => 'opshtml' . $year,
            },
        };

        $url = match($type) {
            Constants::ICD10GM =>
            "https://www.dimdi.de/static/de/klassifikationen/icd/icd-10-gm/kode-suche/$year_link/findcode.htm",
            Constants::OPS =>
            "https://www.dimdi.de/static/de/klassifikationen/ops/kode-suche/$year_link/findcode.htm"
        };

        $text = $this->clientService->downloadUrlAsText($url);
        $return = '';

        $find = "CodeArray['$short_code']";
        $find_len = strlen($find);

        $find_pos = strpos($text, $find);
        if($find_pos !== false) {
            $start_pos = strpos($text, "'", $find_pos + $find_len);
            if($start_pos !== false) {
                $start_pos++;
                $end_pos = strpos($text, "'", $start_pos);
                if($end_pos !== false) {
                    $return = substr($text, $start_pos, $end_pos - $start_pos);
                }
            }
        }

        if($return !== '') {
            $return = match($type) {
                Constants::ICD10GM =>
                    "https://www.dimdi.de/static/de/klassifikationen/icd/icd-10-gm/kode-suche/$year_link/index.htm?" .
                    "$return+$short_code",
                Constants::OPS =>
                    "https://www.dimdi.de/static/de/klassifikationen/ops/kode-suche/$year_link/" .
                    "$return#$code",
            };
        }

        return new Response($return);
    }
}