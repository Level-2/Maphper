<?php
namespace Maphper\Lib;
class SelectBuilder {
	public function select($table, $criteria, $args, $options = []) {
		$where = $criteria ? ' WHERE ' . $criteria : '';
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
		return new Query('SELECT ' . $function . '(' . $field . ') as val, ' . $field . '   FROM ' . $table . ($where != null ? ' WHERE ' . $where : '') . ' ' . $groupBy, $args);
	}
}
