<?php

namespace App\Service;

use App\Repository\BfarmRepository;
use App\Util\Constants;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;
use const XML_PI_NODE;

class ConceptMapService {

    private const EQUIVALENCE = 0;
    private const EQUAL = 1;
    private const MULTIPLE = 2;
    private const RELATED = 3;
    private const DELETED = 4;

    private const CONCEPT_MAP = 'ConceptMap';
    private const END_CONCEPT_MAP = [
        Constants::XML => '</' . self::CONCEPT_MAP . '>',
        Constants::JSON => "]",
    ];

    private Serializer $serializer;

    public function __construct(
        private readonly BfarmRepository $bfarmRepository
    ) {
        $this->serializer = new Serializer([], [new XmlEncoder(), new JsonEncoder()]);
    }

    public function head (string $id, string $file_type):string {

        $context = match($file_type) {
            Constants::XML => [
                'xml_format_output' => true,
                'xml_root_node_name' => self::CONCEPT_MAP,
            ],
            Constants::JSON => [
                'json_encode_options' => JSON_PRETTY_PRINT,
            ],
        };

        $urn_uuid = 'urn:uuid:' . Uuid::v7();

        /** @noinspection HttpUrlsUsage */
        $data = match($file_type) {
            Constants::XML => [
                '@xmlns' => 'http://hl7.org/fhir',
                'id' => ['@value' => $id],
                'url' => ['@value' => $urn_uuid]
            ],
            Constants::JSON => [
                'resourceType' => self::CONCEPT_MAP,
                'id' => $id,
                'url' => $urn_uuid,
                'group' => []
            ],
        };

        $ret = $this->serializer->encode($data, $file_type, $context);

        $pos = strrpos($ret, self::END_CONCEPT_MAP[$file_type]);
        return substr($ret, 0, $pos);
    }

    public function tail (string $file_type):string {

        return match($file_type) {
            Constants::XML => "\n" . self::END_CONCEPT_MAP[$file_type],
            Constants::JSON => self::END_CONCEPT_MAP[$file_type] . '}',
        };
    }

    public function group (
        string $type,
        array $umsteiger,
        string $source_year,
        string $target_year,
        string $fhir_version,
        string $file_type,
        bool $umsteiger_only = true
    ):string {

        $context = match($file_type) {
            Constants::XML => [
                'xml_format_output' => true,
                'encoder_ignored_node_types' => [ XML_PI_NODE ],
                'xml_root_node_name' => 'group',
            ],
            Constants::JSON => [
                'json_encode_options' => JSON_PRETTY_PRINT,
            ],
        };

        $group = match($file_type) {
            Constants::XML => [
                'source' => ['@value' => $source_year],
                'target' => ['@value' => $target_year],
            ],
            Constants::JSON => [
                'source' => $source_year,
                'target' => $target_year,
            ],
        };

        $data = $this->bfarmRepository->readCodes($type, $source_year);
        foreach ($data as $item) {

            $code = $item['code'];

            if($code==='UNDEF') {
                continue;
            }

            if(!isset($umsteiger[$code])) {
                $type = self::EQUAL;
                if($umsteiger_only) {
                    continue;
                }
            } else {
                $target_codes = $umsteiger[$code];
                if(count($target_codes) > 1) {
                    $type = self::MULTIPLE;
                } else {
                    if($target_codes[0]==='UNDEF') {
                        $type = self::DELETED;
                    } else {
                        $type = self::RELATED;
                    }
                }
            }

            $element = match($type) {
                self::EQUAL =>    $this->create_element($type, $fhir_version, $file_type, $code, $code),
                self::MULTIPLE => $this->create_element($type, $fhir_version, $file_type, $code, $umsteiger[$code]),
                self::RELATED =>  $this->create_element($type, $fhir_version, $file_type, $code, $umsteiger[$code][0]),
                self::DELETED =>  $this->create_element($type, $fhir_version, $file_type, $code),
                default => [],
            };
            $group['element'][] = $element;
        }

        ini_set('max_execution_time', '300');
        return $this->serializer->encode($group, $file_type, $context);
    }

    private function create_element (int $equivalence, string $version, string $file_type, string $source_code, string|array $target_code = ''): array {

        $element = [];

        $element['code'] = match($file_type){
            Constants::XML => ['@value' => $source_code],
            Constants::JSON => $source_code,
        };

        if($equivalence===self::DELETED) {
            switch($version) {
                case Constants::FHIR_VERSION_4:
                    $element['target']['equivalence'] = match($file_type){
                        Constants::XML => ['@value' => 'unmatched'],
                        Constants::JSON => 'unmatched',
                    };
                    break;
                case Constants::FHIR_VERSION_5:
                    $element['noMap'] = match($file_type){
                        Constants::XML => ['@value' => 'true'],
                        Constants::JSON => 'true',
                    };
                    break;
            }
            return $element;
        }

        $equivalence_tag = $this->resolve_version(self::EQUIVALENCE, $version);
        $equivalence_value = $this->resolve_version($equivalence, $version);

        if($equivalence===self::MULTIPLE) {
            foreach($target_code as $target) {
                $sub_element = [];
                $sub_element['code'] = match($file_type){
                    Constants::XML => ['@value' => $target],
                    Constants::JSON => $target,
                };
                $sub_element[$equivalence_tag] = match($file_type){
                    Constants::XML => ['@value' => $equivalence_value],
                    Constants::JSON => $equivalence_value,
                };
                $element['target'][] = $sub_element;
            }
            return $element;
        }

        $element['target']['code'] = match($file_type) {
            Constants::XML => ['@value' => $target_code],
            Constants::JSON => $target_code,
        };
        $element['target'][$equivalence_tag] =  match($file_type) {
            Constants::XML => ['@value' => $equivalence_value],
            Constants::JSON => $equivalence_value,
        };

        return $element;
    }

    private function resolve_version (int $what, string $version): string {
        return match ($what) {
            self::EQUAL => 'equivalent',
            self::MULTIPLE => match($version) {
                Constants::FHIR_VERSION_4 => 'wider',
                Constants::FHIR_VERSION_5 => 'source-is-narrower-than-target',
            },
            self::RELATED => match($version) {
                Constants::FHIR_VERSION_4 => 'relatedto',
                Constants::FHIR_VERSION_5 => 'related-to',
            },
            self::EQUIVALENCE => match($version) {
                Constants::FHIR_VERSION_4 => 'equivalence',
                Constants::FHIR_VERSION_5 => 'relationship',
            },
            default => ''
        };
    }
}