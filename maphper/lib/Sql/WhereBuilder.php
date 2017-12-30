<?php
namespace Maphper\Lib\Sql;

class WhereBuilder {
    //Needs to be broken up into better methods
	public function createSql($fields, $mode = \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND){
		$args = [];
		$sql = [];

		foreach ($fields as $key => $value) {
            if ($value instanceof \DateTime) {
    			if ($value->format('H:i:s')  == '00:00:00') $value = $value->format('Y-m-d');
    			else $value = $value->format('Y-m-d H:i:s');
    		}

            if (is_object($value)) continue;
			if (is_numeric($key) && is_array($value)) {
				$result = $this->createSql($value, $key);
				foreach ($result['args'] as $arg_key => $arg) $args[$arg_key] = $arg;
				foreach ($result['sql'] as $arg) $sql[] = $arg;
			}
			else if (\Maphper\Maphper::FIND_BETWEEN & $mode) {
				$sql[] = $key . '>= :' . $key . 'from';
				$sql[] = $key . ' <= :' . $key . 'to';

				$args[$key . 'from'] = $value[0];
				$args[$key . 'to'] = $value[1];
			}
			else if (!is_numeric($key) && is_array($value)) {
				$inSql = [];
				$count = count($value);
				for ($i = 0; $i < $count; $i++) {
					$args[$key . $i] = $value[$i];
					$inSql[] = ':' . $key . $i;
				}
				if (count($inSql) == 0) return [];
				else $sql[] = $key . ' IN ( ' .  implode(', ', $inSql) . ')';
			}
			else if ($value === NULL) {
				$nullSql = $key . ' IS ';
				if (\Maphper\Maphper::FIND_NOT & $mode) $nullSql .= 'NOT ';
				$sql[] = $nullSql . 'NULL';
			}
			else {

				if (\Maphper\Maphper::FIND_LIKE & $mode) {
					$operator = 'LIKE';
					$value = '%' . $value . '%';
				}
				else if (\Maphper\Maphper::FIND_STARTS & $mode) {
					$operator = 'LIKE';
					$value = $value . '%';
				}
				else $operator = $this->getOperator($mode);

				$args[$key] = $value;
				$sql[] = $key . ' ' . $operator . ' :' . $key;
			}
		}

		if (\Maphper\Maphper::FIND_OR & $mode) $query = implode(' OR  ', $sql);
		else $query = implode(' AND ', $sql);
		if (!empty($query)) $query = '(' . $query . ')';
		return ['args' => $args, 'sql' => $query];
	}

    private function getOperator($mode) {
        $operator = "";

        if (\Maphper\Maphper::FIND_NOCASE & $mode) $operator = 'LIKE';
        else if (\Maphper\Maphper::FIND_BIT & $mode) $operator = '&';
        else if (\Maphper\Maphper::FIND_GREATER & $mode) $operator = '>';
        else if (\Maphper\Maphper::FIND_LESS & $mode) $operator = '<';
        else if (\Maphper\Maphper::FIND_NOT & $mode) $operator = '!=';

        if (\Maphper\Maphper::FIND_EXACT & $mode) $operator .= '=';

        return $operator;
    }
}
