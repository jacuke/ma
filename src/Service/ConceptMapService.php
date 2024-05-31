<?php

namespace App\Service;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

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
        $umsteiger = $this->umsteigerService->test1();
        $group = $this->convert_umsteiger_to_conceptmap_elements($umsteiger);

        $xml_context = [
            'xml_format_output' => true,
            'xml_root_node_name' => 'ConceptMap',
        ];

        $data = array();
        /** @noinspection HttpUrlsUsage */
        $data['@xmlns'] = 'http://hl7.org/fhir';
        $data['id'] = ['@value' => 'icdtest1']; // todo
        $data['url'] = ['@value' => 'urn:uuid:193bb9e9-f402-4ea6-95d0-47f8bdd51f67']; // todo
        //   <url value="urn:uuid:193bb9e9-f402-4ea6-95d0-47f8bdd51f68"/>
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
}