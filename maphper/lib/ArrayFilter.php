<?php
namespace Maphper\Lib;
use Maphper\Maphper;

class ArrayFilter {
    private $array;

    public function __construct(array $array) {
        $this->array = $array;
    }

    public function filter($fields) {
        $filteredArray = array_filter($this->array, $this->getSearchFieldFunction($fields, \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND));
        // Need to reset indexes
        $filteredArray = array_values($filteredArray);
        return $filteredArray;
    }

    private function getSearchFieldFunction($fields, $mode) {
        return function ($data) use ($fields, $mode) {
            foreach ($fields as $key => $val) {
                $currentFieldResult = $this->getIfFieldMatches($key, $val, $data, $mode);

                if (Maphper::FIND_OR & $mode && $currentFieldResult === true) return true;
                else if (!(Maphper::FIND_OR & $mode) && $currentFieldResult === false) return false;
            }
            return !(Maphper::FIND_OR & $mode);
        };
    }

    private function getIfFieldMatches($key, $val, $data, $mode) {
        if (is_numeric($key) && is_array($val)) {
            return $this->getSearchFieldFunction($val, $key)($data);
        }
        else if (!isset($data->$key)) return false;
        else if (!(Maphper::FIND_BETWEEN & $mode) && !is_numeric($key) && is_array($val))
            return in_array($data->$key, $val);
        else
            return $this->processFilter($mode, $val, $data->$key);
    }

    private function processFilter($mode, $expected, $actual) {
        if (Maphper::FIND_NOT & $mode) return $expected != $actual;
        else if ((Maphper::FIND_GREATER | Maphper::FIND_EXACT) === $mode) return $expected <= $actual;
        else if ((Maphper::FIND_LESS | Maphper::FIND_EXACT) === $mode) return $expected >= $actual;
        else if (Maphper::FIND_GREATER & $mode) return $expected < $actual;
        else if (Maphper::FIND_LESS & $mode) return $expected > $actual;
        else if (Maphper::FIND_BETWEEN & $mode) return $expected[0] <= $actual && $actual <= $expected[1];
        else if (Maphper::FIND_NOCASE & $mode) return strtolower($expected) == strtolower($actual);
        return $expected == $actual;
    }
}
