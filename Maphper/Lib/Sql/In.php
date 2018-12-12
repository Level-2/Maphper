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
        $value = array_values($value); // fix numeric index being different than $i
        for ($i = 0; $i < $count; $i++) {
            $args[$key . $i] = $value[$i];
            $inSql[] = ':' . $key . $i;
        }

        $notText = '';
        if (\Maphper\Maphper::FIND_NOT & $mode) {
            $notText = ' NOT';
        }

        if (count($inSql) == 0) return ['args' => [], 'sql' => ''];
        else $sql = [$key . $notText . ' IN ( ' .  implode(', ', $inSql) . ')'];

        return ['args' => $args, 'sql' => $sql];
    }
}
