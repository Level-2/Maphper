<?php
namespace Maphper\DataSource;
class MySqlAdapter implements DatabaseAdapter {
	private $pdo;
	private $queryCache = [];
	
	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;	
	}
	
	public function quote($str) {
		return '`' . str_replace('.', '`.`', trim($str, '`')) . '`';
	}
	
	public function delete($table, array $criteria, $args, $limit = null, $offset = null) {
		$limit = $limit ? ' LIMIT ' . $limit : '';
		$this->query('DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $criteria) . $limit, $args);
	}
	
	public function select($table, array $criteria, $args, $order = null, $limit = null, $offset = null) {
		$where = count($criteria) > 0 ? ' WHERE ' . implode(' AND ', $criteria) : '';
		$limit = $limit ? ' LIMIT ' . $limit : '';  
		
		if ($offset) {
			$offset = $offset ? ' OFFSET ' . $offset : '';
			if (!$limit) $limit = ' LIMIT  1000';
		}	
		
		$order = $order ? ' ORDER BY ' . $order : '';  	
		return $this->query('SELECT * FROM ' . $table . ' ' . $where . $order . $limit . $offset, $args);
	}
	
	public function aggregate($table, $function, $field, $where, $args, $group) {
		if ($group == true) $groupBy = ' GROUP BY ' . $field;
		else $groupBy = '';
		$result = $this->query('SELECT ' . $function . '(' . $field . ') as val, ' . $field . '   FROM ' . $table . ($where[0] != null ? ' WHERE ' : '') . implode(' AND ', $where) . ' ' . $groupBy, $args);

		if (isset($result[0]) && $group == null) return $result[0]->val;
		else if ($group != null) {
			$ret = [];
			foreach ($result as $res) $ret[$res->$field] = $res->val;
			return $ret;
		}
		else return 0;
	}
	
	public function getErrors() {
		return $this->query('SHOW WARNINGS');
	}
	
	private function buildSaveQuery($data, $affix = '') {
		$sql = [];
		$args = [];
		foreach ($data as $field => $value) {
			//For dates with times set, search on time, if the time is not set, search on date only.
			//E.g. searching for all records posted on '2015-11-14' should return all records that day, not just the ones posted at 00:00:00 on that day
			if ($value instanceof \DateTime) {
				if ($value->format('H:i:s')  == '00:00:00') $value = $value->format('Y-m-d');
				else $value = $value->format('Y-m-d H:i:s');
			}
			if (is_object($value)) continue;
			$sql[] = $this->quote($field) . ' = :' . $field . $affix;
			$args[$field . $affix] = $value;
		}
		return ['sql' => $sql, 'args' => $args];
	}
	
	public function insert($table, array $primaryKey, $data) {
		$query = $this->buildSaveQuery($data);
		$query1 = $this->buildSaveQuery($data, 1);		
		return $this->query('INSERT INTO ' . $table . ' SET ' . implode(',', $query['sql']) . ' ON DUPLICATE KEY UPDATE ' . implode(',', $query1['sql']), array_merge($query['args'], $query1['args']));
	}
		
	private function query($query, $args = []) {
		$queryId = md5($query);
		
		if (isset($this->queryCache[$queryId])) $stmt = $this->queryCache[$queryId];
		else {
			try {
				$stmt = $this->pdo->prepare($query, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
			}
			catch (\PDOException $e) {
				return null;
			}
			$this->queryCache[$queryId] = $stmt;
		}
		
		foreach ($args as &$arg) if ($arg instanceof \DateTime) $arg = $arg->format('Y-m-d H:i:s');
		
		try {
			if (count($args) > 0) $res = $stmt->execute($args);
			else $res = $stmt->execute();
			return $stmt->fetchAll(\PDO::FETCH_OBJ);
		}
		catch (\Exception $e) {
			return [];
		}
			
	}
	
	private function getType($val) {
		if ($val instanceof \DateTime) return 'DATETIME';
		else if (is_int($val)) return  'INT(11)';
		else if (is_double($val)) return 'DECIMAL(9,' . strlen($val) - strrpos($val, '.') - 1 . ')';
		else if (is_string($val) && strlen($val) < 256) return 'VARCHAR(255)';
		else if (is_string($val) && strlen($val) > 256) return 'LONGBLOG';
		else return 'VARCHAR(255)';		
	}
	
	//Alter the database so that it can store $data
	public function alterDatabase($table, array $primaryKey, $data) {
		$parts = [];
		foreach ($primaryKey as $key) {
			$pk = $data->$key;
			if ($pk == null) $parts[] = $key . ' INT(11) NOT NULL AUTO_INCREMENT'; 
			else $parts[] = $key . ' ' . $this->getType($pk) . ' NOT NULL';					
		}
		
		$pkField = implode(', ', $parts) . ', PRIMARY KEY(' . implode(', ', $primaryKey) . ')';
		$this->pdo->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (' . $pkField . ')');
		
		foreach ($data as $key => $value) {
			if (is_array($value) || (is_object($value) && !($value instanceof \DateTime))) continue;
			if (in_array($key, $primaryKey)) continue;

			$type = $this->getType($value);
		
			try {
				$this->pdo->query('ALTER TABLE ' . $table . ' ADD ' . $this->quote($key) . ' ' . $type);
			}
			catch (\PDOException $e) {
				$this->pdo->query('ALTER TABLE ' . $table . ' MODIFY ' . $this->quote($key) . ' ' . $type);
			}
		}
	}
}
