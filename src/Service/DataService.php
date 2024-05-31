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

    public function __construct(
        string $projectDir
    ) {
        $this->projectDir = $projectDir;

        $this->serializer = new Serializer([], [new XmlEncoder()]);

        foreach (Constants::CODE_SYSTEMS as $code) {

            $xml_data = $this->readXmlData($code);

            // linked years
            $linked_years = array();
            $i=0;
            for(; $i<count($xml_data)-1; $i++) {
                $linked_years[$xml_data[$i][Constants::XML_YEAR]] =
                    (string) $xml_data[$i+1][Constants::XML_YEAR];
            }
            $linked_years[$xml_data[$i][Constants::XML_YEAR]] =
                (string) $xml_data[$i][Constants::XML_PREV][Constants::XML_YEAR] ?? '0';
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

    public function readIcdData(): array {

        return $this->readXmlData(Constants::ICD10GM);
    }

    public function readOpsData(): array {

        return $this->readXmlData(Constants::OPS);
    }

    public function readXmlData (string $type): array {

        $icd_xml = file_get_contents($this->projectDir . '/files/' . Constants::file_name($type));
        return $this->serializer->decode($icd_xml, 'xml')[$type] ?? [];
    }

    public function getIcdYears(): array {

        return $this->getYears(Constants::ICD10GM);
    }

    public function getYears(string $type): array {

        return $this->data[$type][self::YEARS];
    }

    public function getIcdUmsteigerYears(): array {

        return $this->getUmsteigerYears(Constants::ICD10GM);
    }

    public function getUmsteigerYears(string $type): array {

        return $this->data[$type][self::UMSTEIGER_YEARS];
    }

    public function getIcdPreviousYear(string $year): string {

        return $this->getPreviousYear(Constants::ICD10GM, $year);
    }

    public function getPreviousYear(string $type, string $year): string {

        return $this->data[$type][self::LINKED_YEARS][$year] ?? '0';
    }
}