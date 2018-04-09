<?php
namespace Maphper\Lib\Sql;

interface WhereConditional {
    public function matches($key, $value, $mode);
    public function getSql($key, $value, $mode);
}
