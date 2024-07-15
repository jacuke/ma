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
    private const REV_LINKED_YEARS = 'r';
    private const UMSTEIGER_YEARS = 'u';
    private const NEWEST_YEAR = 'n';
    private const OLDEST_YEAR = 'o';

    public function __construct(
        string $projectDir
    ) {
        $this->projectDir = $projectDir;

        $this->serializer = new Serializer([], [new XmlEncoder()]);

        foreach (Constants::CODE_SYSTEMS as $code) {

            $xml_data = $this->readXmlData($code);

            // oldest year
            if(isset(end($xml_data)[Constants::XML_PREV][Constants::XML_YEAR])) {
                $oldest_year = end($xml_data)[Constants::XML_PREV][Constants::XML_YEAR];
            } else {
                $oldest_year = array_pop($xml_data)[Constants::XML_YEAR];
            }
            $this->data[$code][self::OLDEST_YEAR] = $oldest_year;

            // newest year
            $this->data[$code][self::NEWEST_YEAR] = reset($xml_data)[Constants::XML_YEAR];

            // linked years & reverse linked years
            $linked_years = array();
            $rev_linked_years = array();
            foreach($xml_data as $xml) {
                $next_year = next($xml_data)[Constants::XML_YEAR] ?? '';
                if($next_year==='') {
                    $next_year = $oldest_year;
                }
                $linked_years[$xml[Constants::XML_YEAR]] = (string) $next_year;
                $rev_linked_years[$next_year] = (string) $xml[Constants::XML_YEAR];
            }
            $this->data[$code][self::LINKED_YEARS] = $linked_years;
            $this->data[$code][self::REV_LINKED_YEARS] = $rev_linked_years;

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

        return $this->data[$type][self::YEARS] ?? [];
    }

    public function getUmsteigerYears(string $type): array {

        return $this->data[$type][self::UMSTEIGER_YEARS] ?? [];
    }

    public function getNextOlderYear(string $type, string $year): string {

        return $this->data[$type][self::LINKED_YEARS][$year] ?? '';
    }

    public function getNextNewerYear(string $type, string $year): string {

        return $this->data[$type][self::REV_LINKED_YEARS][$year] ?? '';
    }

    public function getOldestYear(string $type): string {

        return $this->data[$type][self::OLDEST_YEAR] ?? '';
    }

    public function getNewestYear(string $type): string {

        return $this->data[$type][self::NEWEST_YEAR] ?? '';
    }
}