<?php
namespace Maphper\Lib\Sql;

class NullConditional implements WhereConditional {
    public function matches($key, $value, $mode) {
        return $value === NULL;
    }

    public function getSql($key, $value, $mode) {
        $nullSql = $key . ' IS ';
        if (\Maphper\Maphper::FIND_NOT & $mode) $nullSql .= 'NOT ';
        $sql = [$nullSql . 'NULL'];

        return ['args' => [], 'sql' => $sql];
    }
}
