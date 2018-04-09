<?php
namespace Maphper\Lib\Sql;
use Maphper\Maphper;

class GeneralOperator implements WhereConditional {
    public function matches($key, $value, $mode) {
        return (Maphper::FIND_BIT ^ Maphper::FIND_GREATER ^ Maphper::FIND_LESS ^ Maphper::FIND_NOT & $mode)
                || Maphper::FIND_EXACT & $mode;
    }

    public function getSql($key, $value, $mode) {
        return [
            'sql' => [$key . ' ' . $this->getOperator($mode) . ' :' . $key],
            'args' => [$key => $value]
        ];
    }

    private function getOperator($mode) {
        if (\Maphper\Maphper::FIND_BIT & $mode) return '&';
        else if (\Maphper\Maphper::FIND_NOT & $mode) return '!=';

        return $this->getEqualsOperators($mode);
    }

    private function getEqualsOperators($mode) {
        $operator = "";

        if (\Maphper\Maphper::FIND_GREATER & $mode) $operator = '>';
        else if (\Maphper\Maphper::FIND_LESS & $mode) $operator = '<';

        if (\Maphper\Maphper::FIND_EXACT & $mode) $operator .= '=';

        return $operator;
    }
}
