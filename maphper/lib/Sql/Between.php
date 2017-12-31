<?php
namespace Maphper\Lib\Sql;

class Between implements WhereConditional {
    public function matches($key, $value, $mode) {
        return is_array($value) && \Maphper\Maphper::FIND_BETWEEN & $mode;
    }

    public function getSql($key, $value, $mode) {
        return [
            'sql' => [
                $key . '>= :' . $key . 'from',
                $key . ' <= :' . $key . 'to'
            ],
            'args' => [
                $key . 'from' => $value[0],
                $key . 'to' => $value[1]
            ]
        ];
    }
}
