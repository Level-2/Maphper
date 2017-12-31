<?php
namespace Maphper\Lib\Sql;
use Maphper\Maphper;

class Like implements WhereConditional {
    public function matches($key, $value, $mode) {
        return (Maphper::FIND_LIKE | Maphper::FIND_STARTS |
                Maphper::FIND_ENDS | Maphper::FIND_NOCASE) & $mode;
    }

    public function getSql($key, $value, $mode) {
        return [
            'sql' => [$key . ' LIKE :' . $key],
            'args' => [$key => $this->getValue($value, $mode)]
        ];
    }

    private function getValue($value, $mode) {
        if ((Maphper::FIND_LIKE | Maphper::FIND_ENDS) & $mode) $value = '%' . $value;
        if ((Maphper::FIND_LIKE | Maphper::FIND_STARTS) & $mode) $value .= '%';
        return $value;
    }
}
