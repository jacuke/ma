<?php

namespace App\Service;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Throwable;

class ConceptMapService {

    private Serializer $serializer;
    private UmsteigerService $umsteigerService;

    public function __construct(
        UmsteigerService $umsteigerService
    ) {
        $this->serializer = new Serializer([], [new XmlEncoder()]);
        $this->umsteigerService = $umsteigerService;
    }

    public function test():string {

        // todo
        //$umsteiger = $this->umsteigerService->test1();
        $umsteiger = $this->umsteigerService->mergeAllAutoUmsteiger();
        $group = $this->convert_umsteiger_to_conceptmap_elements($umsteiger);

        $xml_context = [
            'xml_format_output' => true,
            'xml_root_node_name' => 'ConceptMap',
        ];

        $data = array();
        /** @noinspection HttpUrlsUsage */
        $data['@xmlns'] = 'http://hl7.org/fhir';
        $data['id'] = ['@value' => 'icdtest1']; // todo
        $data['url'] = ['@value' => 'urn:uuid:' . $this->generate_uuid()];
        $data['group'] = $group;

        return $this->serializer->serialize($data, 'xml', $xml_context);
    }

    private function convert_umsteiger_to_conceptmap_elements (array $umsteiger) : array {

        $ret = array();
        $ret['source'] = ['@value' => 'icd']; // todo
        $ret['target'] = ['@value' => '2024']; // todo
        $elements = array();
        foreach ($umsteiger as $old => $new) {
            $elements[] = ['code' => ['@value' => $old],
                'target' => ['code' => ['@value' => $new]]];
        }
        $ret['element'] = $elements;
        return $ret;
    }

    private function generate_uuid():string {

        try {
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
        } catch (Throwable) {
            // todo
            return '';
        }
    }
}