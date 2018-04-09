<?php
namespace Maphper\Lib\Sql;

class In implements WhereConditional {
    public function matches($key, $value, $mode) {
        return !is_numeric($key) && is_array($value);
    }

    public function getSql($key, $value, $mode) {
        $args = [];
        $inSql = [];
        $count = count($value);
        for ($i = 0; $i < $count; $i++) {
            $args[$key . $i] = $value[$i];
            $inSql[] = ':' . $key . $i;
        }
        if (count($inSql) == 0) return [];
        else $sql = [$key . ' IN ( ' .  implode(', ', $inSql) . ')'];

        return ['args' => $args, 'sql' => $sql];
    }
}
