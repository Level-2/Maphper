<?php
namespace Maphper\DataSource;
class SqliteAdapter implements DatabaseAdapter {
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
		$result = $this->query('SELECT ' . $function . '(' . $field . ') as val, ' . $field . '	  FROM ' . $table . ($where[0] != null ? ' WHERE ' : '') . implode(' AND ', $where) . ' ' . $groupBy, $args);

		if (isset($result[0]) && $group == null) return $result[0]->val;
		else if ($group != null) {
			$ret = [];
			foreach ($result as $res) $ret[$res->$field] = $res->val;
			return $ret;
		}
		else return 0;
	}
	
	private function buildSaveQuery($data, $prependField = false) {
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
			if ($prependField){
				$sql[] = $this->quote($field) . ' = :' . $field;
			} else {
				$sql[] = ':' . $field;
			}
			$args[$field] = $value;			
		}
		return ['sql' => $sql, 'args' => $args];
	}
	
	public function insert($table, array $primaryKey, $data) {
		$query = $this->buildSaveQuery($data);
		$result = $this->query('INSERT INTO ' . $this->quote($table) . ' ('.implode(', ', array_keys($query['args'])).') VALUES ( ' . implode(', ', $query['sql']). ' )', $query['args']);

		if ($result->errorCode() > 0) {
			$query = $this->buildSaveQuery($data, true);
			$where = [];
			foreach($primaryKey as $field) $where[] = $this->quote($field) . ' = :' . $field;
			$result = $this->query('UPDATE ' . $this->quote($table) . ' SET ' . implode(', ', $query['sql']). ' WHERE '. implode(' AND ', $where), $query['args']);
		}

		return $result;
	}
		
	private function query($query, $args = []) {
		$queryId = md5($query);
		if (isset($this->queryCache[$queryId])) $stmt = $this->queryCache[$queryId];
		else {
			$stmt = $this->pdo->prepare($query, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
			if ($stmt) $this->queryCache[$queryId] = $stmt;
		}
		
		foreach ($args as &$arg) if ($arg instanceof \DateTime) {
			if ($arg->format('H:i:s')  == '00:00:00') $arg = $arg->format('Y-m-d');
			else $arg = $arg->format('Y-m-d H:i:s');
		}
				
		if ($stmt !== false) {
			try {
				if (count($args) > 0) $res = $stmt->execute($args);
				else $res = $stmt->execute();
				if (substr($query, 0, 6) === 'SELECT') return $stmt->fetchAll(\PDO::FETCH_OBJ);
				else return $stmt;
			}
			catch (\Exception $e) {
				return $stmt;
			}
		}
	}
	
	public function lastInsertId() {
		return $this->pdo->lastInsertId();
	}
	
	private function getType($val) {
		if ($val instanceof \DateTime) return 'DATETIME';
		else if (is_int($val)) return  'INTEGER';
		else if (is_double($val)) return 'DECIMAL(9,' . strlen($val) - strrpos($val, '.') - 1 . ')';
		else if (is_string($val) && strlen($val) < 256) return 'VARCHAR(255)';
		else if (is_string($val) && strlen($val) > 256) return 'LONGBLOG';
		else return 'VARCHAR(255)';		
	}

	protected function tableExists($name) {
		$result = $this->pdo->query('SELECT name FROM sqlite_master WHERE type="table" and name="'. $name.'"');
		return count($result->fetchAll()) == 1;
	}

	private function getColumns($table) {
		$result = $this->pdo->query('PRAGMA table_info(' . $table . ');')->fetchAll(\PDO::FETCH_OBJ);
		$return = [];
		foreach ($result as $row) {
			$return[] = $row->name;
		}
		return $return;
	}

	//Alter the database so that it can store $data
	public function alterDatabase($table, array $primaryKey, $data) {
		$affix = '_'.substr(md5($table), 0, 6);
		$this->createTable($table . $affix, $primaryKey, $data);
		$fields = [];
		foreach ($data as $key => $value) { $fields[] = $key; }
		try {
			if ($this->tableExists($table)) {
				$columns = implode(', ', $this->getColumns($table));			

				$this->pdo->query('INSERT INTO ' . $this->quote($table . $affix) . '(' . $columns . ') SELECT ' . $columns . ' FROM ' . $this->quote($table));
				$this->pdo->query('DROP TABLE ' . $table );
			}
		}
		catch (\PDOException $e) {
			// No data to copy
			echo $e->getMessage();
		}
		$this->pdo->query('ALTER TABLE ' . $table . $affix. ' RENAME TO '. $table );
	}

	public function createTable($table, array $primaryKey, $data) {
		$parts = [];
		foreach ($primaryKey as $key) {
			$pk = $data->$key;
			if ($pk == null) $parts[] = $key . ' INTEGER'; 
			else $parts[] = $key . ' ' . $this->getType($pk) . ' NOT NULL';					
		}
		
		$pkField = implode(', ', $parts) . ', PRIMARY KEY(' . implode(', ', $primaryKey) . ')';
				
		$this->pdo->query('DROP TABLE IF EXISTS ' . $table );
		$this->pdo->query('CREATE TABLE ' . $table . ' (' . $pkField . ')');
					
		foreach ($data as $key => $value) {
			if (is_array($value) || (is_object($value) && !($value instanceof \DateTime))) continue;
			if (in_array($key, $primaryKey)) continue;

			$type = $this->getType($value);
		
			$this->pdo->query('ALTER TABLE ' . $table . ' ADD ' . $this->quote($key) . ' ' . $type);
		}
	}
	
	public function addIndex($table, array $fields) {
		//TODO
	}
	
	public function optimiseColumns($table) {
		//TODO
	}
}