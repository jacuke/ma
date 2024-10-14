<?php

namespace App\Controller;

use App\Service\ConceptMapService;
use App\Service\DataService;
use App\Service\UmsteigerService;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class ConceptMapController extends AbstractController  {

    private bool $ob_flush;

    public function __construct(
        private readonly DataService       $dataService,
        private readonly ConceptMapService $conceptMapService,
        private readonly UmsteigerService  $umsteigerService
    ) {
        $ob_status = ob_get_status();
        $this->ob_flush = isset($ob_status['flags']) && ($ob_status['flags'] & PHP_OUTPUT_HANDLER_FLUSHABLE);
    }

    /** @noinspection PhpUnused */
    #[Route('/{type}-conceptmap', name: 'conceptmap', methods: ['GET'])]
    public function concept_map(string $type): Response  {

        $data = [
            'years' => $this->dataService->getYears($type),
            'newestYear' => $this->dataService->getNewestYear($type),
            'type' => $type,
            'fhir_versions' => Constants::FHIR_VERSIONS,
        ];
        return $this->render('conceptmap.html.twig', $data);
    }

    /** @noinspection PhpUnused */
    #[Route('/{type}-conceptmap', name: 'conceptmap_file', methods: ['POST'])]
    public function concept_map_file(string $type, Request $request): Response  {

        $target_year = $request->request->getString('year');
        $fhir_version = $request->request->getString('fhir');
        $umst_only = $request->request->getBoolean('umst-only');
        $single_year = $request->request->getBoolean('single');
        $chronological = $request->request->getBoolean('chronological');
        $prev = $request->request->getString('prev');
        $file_type = $request->request->getString('file');
        $file_name = 'conceptmap_' . strtolower($fhir_version) . '_' . $type;
        $all = $target_year === Constants::ALL;

        if($single_year) {
            $file_name .= '_s';
            if($chronological) {
                $file_name .= $prev . '-' . $target_year;
            } else {
                $file_name .= $target_year . '-' . $prev;
            }
            $all = true;
            $umst_only = true;
        }
        if(!$all) {
            $file_name .= '_' . $target_year;
        }
        if(!$umst_only) {
            $file_name .= '_all_codes';
        }
        $file_name = str_replace('.', '', $file_name);
        $file_name .= '.' . $file_type;

        $content_type = match($file_type) {
            'json' => 'application/json',
            'xml' => 'text/xml',
        };

        $response = new StreamedResponse();
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Content-Type', $content_type);
        $response->headers->set('Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file_name)
        );
        $response->sendHeaders();

        $response->setCallback(
            function () use (
                $type,
                $target_year,
                $fhir_version,
                $umst_only,
                $single_year,
                $chronological,
                $prev,
                $file_type,
                $all
            ) {
                $out = fopen('php://output', 'wb');

                if($single_year) {
                    if($chronological) {
                        $from = $prev;
                        $to = $target_year;
                    } else {
                        $from = $target_year;
                        $to = $prev;
                    }
                } else {
                    // transitive
                    $from = $this->dataService->getNewestYear($type);
                    $to = $this->dataService->getOldestYear($type);
                }

                $id = $type . '_from:' . $from . '_to:' . $to;
                if(!$all) {
                    $id .= '_target:' . $target_year;
                }
                fwrite($out, $this->conceptMapService->head($id, $file_type));
                $this->flush();

                if($single_year) {
                    $internal_function = function (array $data) use
                        ($out, $type, $from, $to, $fhir_version, $file_type) {

                        $text = $this->conceptMapService->group($type, $data, $from, $to, $fhir_version, $file_type);
                        fwrite($out, $text);
                        fwrite($out,"\n");
                    };
                    $this->umsteigerService->determineTwoUmsteigerVertical($type, $chronological, $target_year, $prev, $internal_function);
                } else {
                    // transitive
                    $first_group = true;
                    $internal_function = function (array $data, string $year, string $target_year) use
                        ($out, $type, $fhir_version, $file_type, $umst_only, &$first_group) {

                        if($first_group) {
                            $first_group = false;
                            if($file_type==='json') {
                                fwrite($out,"\n");
                            }
                        } else {
                            if($file_type==='json') {
                                fwrite($out,",");
                            }
                        }
                        $text = $this->conceptMapService->group($type, $data, $year, $target_year, $fhir_version, $file_type, $umst_only);
                        fwrite($out, $text);
                        fwrite($out,"\n");
                        $this->flush();
                    };
                    if(!$all) {
                        $this->umsteigerService->searchUmsteigerVertical($type, $target_year, $internal_function);
                    } else {
                        $this->umsteigerService->searchAllUmsteigerVertical($type, $internal_function);
                    }
                }

                fwrite($out, $this->conceptMapService->tail($file_type));
                fclose($out);
            }
        );

        return $response;
    }

    private function flush():void {

        if($this->ob_flush) {
            ob_flush();
        }
        flush();
    }

    protected function test($function): void {

        for($i=0; $i<50; $i++) {
            $function("$i\n");
        }
    }
}
