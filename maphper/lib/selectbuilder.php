<?php
namespace Maphper\Lib;
class SelectBuilder {

	public function select($table, array $criteria, $args, $options = []) {
		$where = count($criteria) > 0 ? ' WHERE ' . implode(' AND ', $criteria) : '';
		//$limit = $limit ? ' LIMIT ' . $limit : '';
		$limit = (isset($options['limit'])) ? ' LIMIT ' . $options['limit'] : '';

		if (isset($options['offset'])) {
			$offset = ' OFFSET ' . $options['offset'];
			if (!$limit) $limit = ' LIMIT  1000';
		}
		else $offset = '';

		$order = isset($options['order']) ? ' ORDER BY ' . $options['order'] : '';
		return new Query('SELECT * FROM ' . $table . ' ' . $where . $order . $limit . $offset, $args);
	}

	public function aggregate($table, $function, $field, $where, $args, $group) {
		if ($group == true) $groupBy = ' GROUP BY ' . $field;
		else $groupBy = '';
		return new Query('SELECT ' . $function . '(' . $field . ') as val, ' . $field . '   FROM ' . $table . ($where[0] != null ? ' WHERE ' : '') . implode(' AND ', $where) . ' ' . $groupBy, $args);
	}


	//Needs to be broken up into better methods
	public function createSql($fields, $mode){
		$args = [];
		$sql = [];

		foreach ($fields as $key => $value) {
			if (is_numeric($key) && is_array($value)) {
				$result = $this->createSql($value, $key);
				foreach ($result['args'] as $arg_key => $arg) $args[$arg_key] = $arg;
				foreach ($result['sql'] as $arg) $sql[] = $arg;
				continue;
			}
			else if (\Maphper\Maphper::FIND_BETWEEN & $mode) {
				$sql[] = $key . '>= :' . $key . 'from';
				$sql[] = $key . ' <= :' . $key . 'to';

				$args[$key . 'from'] = $value[0];
				$args[$key . 'to'] = $value[1];
				continue;
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
				continue;
			}
			else if ($value === NULL) {
				$nullSql = $key . ' IS ';
				if (\Maphper\Maphper::FIND_NOT & $mode) $nullSql .= 'NOT ';
				$sql[] = $nullSql . 'NULL';
			}
			else {
                $operator = "";

				if (\Maphper\Maphper::FIND_LIKE & $mode) {
					$operator = 'LIKE';
					$value = '%' . $value . '%';
				}
				else if (\Maphper\Maphper::FIND_STARTS & $mode) {
					$operator = 'LIKE';
					$value = $value . '%';
				}
				else if (\Maphper\Maphper::FIND_NOCASE & $mode) $operator = 'LIKE';
				else if (\Maphper\Maphper::FIND_BIT & $mode) $operator = '&';
				else if (\Maphper\Maphper::FIND_GREATER & $mode) $operator = '>';
				else if (\Maphper\Maphper::FIND_LESS & $mode) $operator = '<';
				else if (\Maphper\Maphper::FIND_NOT & $mode) $operator = '!=';

                if (\Maphper\Maphper::FIND_EXACT & $mode) $operator .= '=';

				$args[$key] = $value;
				$sql[] = $key . ' ' . $operator . ' :' . $key;
			}
		}

		if (\Maphper\Maphper::FIND_OR & $mode) $query = implode(' OR  ', $sql);
		else $query = implode(' AND ', $sql);
		if (!empty($query)) $query = '(' . $query . ')';
		return ['args' => $args, 'sql' => [$query]];
	}
}
