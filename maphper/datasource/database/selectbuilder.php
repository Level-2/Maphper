<?php
namespace Maphper\Datasource\Database;
class SelectBuilder {
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
			else {
				$operator = '=';
		
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

				
				$args[$key] = $value;
				$sql[] = $key . ' ' . $operator . ' :' . $key;
			}
		}
		
		if (\Maphper\Maphper::FIND_OR & $mode) $query = implode(' OR  ', $sql);
		else $query = implode(' AND ', $sql);
		return ['args' => $args, 'sql' => [$query]];
	}
}