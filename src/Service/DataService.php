<?php

namespace App\Service;

use App\Util\Constants;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class DataService {

    private Serializer $serializer;
    private string $projectDir;
    private array $data;

    private const YEARS = 'y';
    private const LINKED_YEARS = 'l';
    private const UMSTEIGER_YEARS = 'u';
    private const LAST_YEAR = 'z';

    public function __construct(
        string $projectDir
    ) {
        $this->projectDir = $projectDir;

        $this->serializer = new Serializer([], [new XmlEncoder()]);

        foreach (Constants::CODE_SYSTEMS as $code) {

            $xml_data = $this->readXmlData($code);

            // last year
            if(isset(end($xml_data)[Constants::XML_PREV][Constants::XML_YEAR])) {
                $last_year = end($xml_data)[Constants::XML_PREV][Constants::XML_YEAR];
            } else {
                $last_year = array_pop($xml_data)[Constants::XML_YEAR];
            }
            $this->data[$code][self::LAST_YEAR] = $last_year;

            // linked years
            $linked_years = array();
            reset($xml_data);
            foreach($xml_data as $xml) {
                $next_year = next($xml_data)[Constants::XML_YEAR] ?? '';
                if($next_year==='') {
                    $next_year = $last_year;
                }
                $linked_years[$xml[Constants::XML_YEAR]] = (string) $next_year;
            }
            $this->data[$code][self::LINKED_YEARS] = $linked_years;

            // umsteiger years
            $umsteiger_years = array();
            foreach(array_keys($linked_years) as $year) {
                $umsteiger_years[] = (string) $year;
            }
            $this->data[$code][self::UMSTEIGER_YEARS] = $umsteiger_years;

            // years
            $years = $umsteiger_years;
            $years[] = (string) end($linked_years);
            $this->data[$code][self::YEARS] = $years;
        }
    }

    public function readXmlData (string $type): array {

        $icd_xml = file_get_contents($this->projectDir . '/files/' . Constants::file_name($type));
        return $this->serializer->decode($icd_xml, 'xml')[$type] ?? [];
    }

    public function getYears(string $type): array {

        return $this->data[$type][self::YEARS];
    }

    public function getUmsteigerYears(string $type): array {

        return $this->data[$type][self::UMSTEIGER_YEARS];
    }

    public function getPreviousYear(string $type, string $year): string {

        return $this->data[$type][self::LINKED_YEARS][$year] ?? '';
    }

    public function getLastYear(string $type): string {

        return $this->data[$type][self::LAST_YEAR] ?? '';
    }
}