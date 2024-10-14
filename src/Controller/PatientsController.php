<?php

namespace App\Controller;

use App\Repository\PatientsRepository;
use App\Util\Constants;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PatientsController extends AbstractController  {

    public function __construct(private readonly PatientsRepository $patientsRepository) {}

    /** @noinspection PhpUnused */
    #[Route('/icd10gm-patients', name: 'patients')]
    public function patients(Request $request): Response  {

        $init = false;
        if(!$request->query->has('c') && !$request->query->has('t')) {
            $init = true;
        }

        $data = array();
        $search_code = $request->query->get('c') ?? '';
        $search_name = $request->query->get('t') ?? '';
        $page = $request->query->get('p') ?? 1;
        $total_count = $this->patientsRepository->countPatients();
        $search_count = 0;
        $max_page = 0;

        if(!$init) {
            $search_count = $this->patientsRepository->countPatients($search_code, $search_name);
            $max_page = floor($search_count/PatientsRepository::PAGE_SIZE);
            if($search_count % PatientsRepository::PAGE_SIZE !==0) {
                $max_page++;
            }
            $patients = $this->patientsRepository->readPatients($search_code, $search_name, $page);
            $num_digits = floor(log10($total_count) + 1);
            $id_format = '%0' . "$num_digits" . 'd';

            foreach ($patients as $patient) {
                $entry = array();
                $entry['id'] = sprintf($id_format, $patient['id']);
                $entry['year'] = Constants::year_int_to_str($patient['year']);
                $codes = json_decode($patient['codes'], true);
                $names = json_decode($patient['names'], true);
                $umsteiger = json_decode($patient['umsteiger'], true);
                $entry['count'] = count($codes);
                $list = array();
                foreach ($codes as $i => $code) {
                    $list[] = [
                        'code' => $code,
                        'name' => $names[$i],
                        'umsteiger' => $umsteiger[$i],
                    ];
                }
                $entry['list'] = $list;
                $data[] = $entry;
            }
        }

        return $this->render('patients.html.twig',
            ['init' => $init, 'data' => $data, 'total_count' => $total_count,
                'search_count' => $search_count, 'search_code' => $search_code, 'search_name' => $search_name,
                'page' => $page, 'max_page' => $max_page]);
    }
}
