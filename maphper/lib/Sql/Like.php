<?php
namespace Maphper\Lib\Sql;
use Maphper\Maphper;

class Like implements WhereConditional {
    public function matches($key, $value, $mode) {
        return Maphper::FIND_LIKE & $mode || Maphper::FIND_STARTS & $mode ||
                Maphper::FIND_ENDS & $mode || Maphper::FIND_NOCASE & $mode;
    }

    public function getSql($key, $value, $mode) {
        if (Maphper::FIND_LIKE & $mode || Maphper::FIND_STARTS & $mode) $value = '%' . $value;
        if (Maphper::FIND_LIKE & $mode || Maphper::FIND_ENDS & $mode) $value .= '%';

        return [
            'sql' => [$key . ' LIKE :' . $key],
            'args' => [$key => $value]
        ];
    }
}
