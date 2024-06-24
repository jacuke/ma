<?php

namespace App\Util;

final class Constants {

    public const ICD10GM = 'icd10gm';
    public const OPS = 'ops';
    public const CODE_SYSTEMS = [self::ICD10GM, self::OPS];

    // - - -

    public const CONFIG_STATUS_OK = 'OK';
    public const CONFIG_STATUS_ERROR = 'ERROR';
    public const TABLE_CONFIG = 'BFARMER';
    public const TABLE_CODES = 1;
    public const TABLE_UMSTEIGER = 2;
    public const TABLE_UMSTEIGER_JOIN = 3;

    public const DIRECTORY_FILES = '/files/';

    public const SQL_CODE = 'code';
    public const SQL_NAME = 'name';
    public const SQL_OLD = 'old';
    public const SQL_NEW = 'new';
    public const SQL_AUTO = 'auto';
    public const SQL_AUTO_R = 'auto_r';

    public const STATUS_OK = 0;
    public const STATUS_INVALID = 1;
    public const STATUS_EXISTS_OK = 2;

    public const XML_YEAR = 'year';
    public const XML_PREV = 'prev';
    public const XML_ZIP = 'zip';
    public const XML_CODES = 'codes';
    public const XML_UMSTEIGER = 'umsteiger';
    public const XML_DIR = 'dir';

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

    public static function display_name (string $type): string {

        return match ($type) {
            Constants::ICD10GM => 'ICD-10-GM',
            Constants::OPS => 'OPS',
            default => 'n/a'
        };
    }
}