<?php

namespace App\Util;

use Symfony\Component\Serializer\Encoder\EncoderInterface;

class SqlInsertEncoder implements EncoderInterface {

    public const TABLE_NAME = 'table';
    public const CLEAN_VALUES_INDEX = 'clean';
    public const INSERT_IGNORE = 'ignore';

    public function supportsEncoding(string $format) : bool {

        if(0===strcasecmp($format, 'sql')) {
            return true;
        } else {
            return false;
        }
    }

    public function encode($data, string $format, array $context = []) : string {

        $table = $context[self::TABLE_NAME] ?? '';
        $clean = $context[self::CLEAN_VALUES_INDEX] ?? [];
        $insert_ignore =  $context[self::INSERT_IGNORE] ?? false;

        $insert = 'INSERT ' . ($insert_ignore ? 'IGNORE ' : '') . "INTO `$table` VALUES ";

        if (empty($data)) {
            return '';
        } else {
            if(!array_is_list($data)) {
                $data = [$data];
            }
        }

        foreach($data as $line) {
            if(count($line)==0) {
                continue;
            }
            $line_str = '(';
            foreach($line as $k => &$v) {
                if(in_array($k,array_keys($clean))) {
                    $v = $clean[$k]($v);
                }
                $line_str .= "'" . $v . "',";
            }
            $line_str[strlen($line_str)-1] = ')';
            $line_str .= ',';
            $insert .= $line_str;
        }
        $insert[strlen($insert)-1] = ';';

        return $insert;
    }
}