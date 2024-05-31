<?php

namespace App\Util;

final class Constants {

    public const ICD10GM = 'icd10gm';
    public const OPS = 'ops';
    public const CODE_SYSTEMS = [self::ICD10GM, self::OPS];

    public const UNDEF = 'UNDEF';

    public const ALL = 'all';

    public const FHIR_VERSION_4 = 'R4';
    public const FHIR_VERSION_5 = 'R5';
    public const FHIR_VERSIONS = [4 => self::FHIR_VERSION_4, 5 => self::FHIR_VERSION_5];

    // - - -

    public const CONFIG_STATUS_OK = 'OK';
    public const CONFIG_STATUS_NOT_FOUND = 'N/A';
    public const CONFIG_STATUS_ERROR = 'ERROR';
    public const CONFIG_STATUS_QUERY_ERROR = 'SQL_ERROR';

    public const CONFIG_ENTRY_PREFIX_UMST_INFO = 'UMST_INFO_';
    public const TABLE_CONFIG = 'BFARMER';
    public const TABLE_CODES = 1;
    public const TABLE_UMSTEIGER = 2;
    public const TABLE_UMSTEIGER_JOIN = 3;
    public const TABLE_UMSTEIGER_JOIN_REV = 4;

    public const DIRECTORY_FILES = '/files/';

    public const SQL_CODE = 'code';
    public const SQL_NAME = 'name';
    public const SQL_UMST = 'umst';
    public const SQL_OLD = 'old';
    public const SQL_NEW = 'new';
    public const SQL_AUTO = 'auto';
    public const SQL_AUTO_R = 'auto_r';

    public const STATUS_OK = 0;
    public const STATUS_INVALID = 1;
    public const STATUS_EXISTS_OK = 2;
    public const STATUS_ZIP_FAILED = 3;
    public const STATUS_DOWNLOAD_FAILED = 4;
    public const STATUS_ERROR = -1;

    public const JSON = 'json';
    public const XML = 'xml';

    public const XML_YEAR = 'year';
    public const XML_URL = 'url';
    public const XML_PREV = 'prev';
    public const XML_ZIP = 'zip';
    public const XML_CODES = 'codes';
    public const XML_UMSTEIGER = 'umsteiger';
    public const XML_DIR = 'dir';
    public const XML_ENCODING = 'encoding';
    public const XML_OPTIONS = 'options';
    public const XML_PUNKT_STRICH = 'punkt-strich';
    public const XML_KREUZ_STERN = 'kreuz-stern';
    public const XML_6COL = 'col6-umsteiger';
    public const XML_OPS_OLD_6COL = 'ops-old-6col-umsteiger';
    public const XML_OPS_5COL = 'ops-5col-umsteiger';
    public const XML_OPS_4COL = 'ops-4col-umsteiger';
    public const XML_OPS_3COL = 'ops-3col-umsteiger';
    public const XML_OPS_KOMBI = 'ops-kombi-code';
    public const XML_UNDEF_NONE = 'undef-none';
    public const XML_NONTERM_UMST = 'non-terminal-umsteiger';
    public const XML_OPTIONS_ARRAY = [
        self::XML_PUNKT_STRICH,
        self::XML_KREUZ_STERN,
        self::XML_6COL,
        self::XML_OPS_OLD_6COL,
        self::XML_OPS_5COL,
        self::XML_OPS_4COL,
        self::XML_OPS_3COL,
        self::XML_UNDEF_NONE,
        self::XML_NONTERM_UMST
    ];
    public const XML_VORAB = 'vorab';

    public static function file_name (string $type): string {
        return match ($type) {
            self::ICD10GM, self::OPS => ($type . '.xml'),
            default => ''
        };
    }

    public static function table_name (string $type, string $year): string {

        return strtoupper($type) . '_' . $year;
    }

    public static function table_name_umsteiger (string $type, string $year, string $prev): string {

        return 'UMSTEIGER_' . strtoupper($type) . '_' . $year . '_' . $prev;
    }

    public static function config_name_umsteiger_info (string $type, string $year): string {

        return self::CONFIG_ENTRY_PREFIX_UMST_INFO . strtoupper($type) . '_' . $year;
    }

    public static function display_name (string $type): string {

        return match ($type) {
            Constants::ICD10GM => 'ICD-10-GM',
            Constants::OPS => 'OPS',
            default => 'n/a'
        };
    }

    public static function sql_clean_name (string $input): string {

        return mb_ereg_replace("'", "''", $input);
    }

    public static function year_str_to_int (string $year): int {

        $ret = mb_ereg_replace("[^0-9]", "", $year);
        return intval($ret);
    }

    public static function year_int_to_str (int $year): string {

        return match ($year) {
            13 => '1.3',
            20 => '2.0',
            default => strval($year),
        };
    }
}