<?php

namespace App\Util;

use Symfony\Component\Serializer\Encoder\EncoderInterface;

class SqlInsertEncoder implements EncoderInterface {

    public const TABLE_NAME = 'table';
    public const CLEAN_VALUES_INDEX = 'clean';

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
        $insert = "INSERT INTO `$table` VALUES ";

        if (empty($data)) {
            return '';
        } else {
            if(!array_is_list($data)) {
                $data = [$data];
            }
        }

        foreach($data as $line) {
            $values = count($line);
            if($values==0) {
                continue;
            }
            $line_str = '(';
            for($i=0; $i<$values; $i++) {
                $v = $line[$i];
                if(in_array($i,array_keys($clean))) {
                    $v = $clean[$i]($v);
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