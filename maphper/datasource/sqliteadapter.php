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
		
	public function query(\Maphper\DataSource\Database\Query $query) {
		$queryId = md5($query->getSql());
		if (isset($this->queryCache[$queryId])) $stmt = $this->queryCache[$queryId];
		else {
			$stmt = $this->pdo->prepare($query->getSql(), [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
			if ($stmt) $this->queryCache[$queryId] = $stmt;
		}
		
		$args = $query->getArgs();
		foreach ($args as &$arg) if ($arg instanceof \DateTime) {
			if ($arg->format('H:i:s')  == '00:00:00') $arg = $arg->format('Y-m-d');
			else $arg = $arg->format('Y-m-d H:i:s');
		}
				
		if ($stmt !== false) {
			try {
				if (count($args) > 0) $res = $stmt->execute($args);
				else $res = $stmt->execute();
				if (substr($query->getSql(), 0, 6) === 'SELECT') return $stmt->fetchAll(\PDO::FETCH_OBJ);
				else return $stmt;
			}
			catch (\Exception $e) {
				//SQLite causes an error if when the DB schema changes, rebuild $stmt and try again.
				if ($e->getMessage() == 'SQLSTATE[HY000]: General error: 17 database schema has changed') {
					unset($this->queryCache[$queryId]);
					return $this->query($query);	
				}
				else return $stmt;				
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

	private function tableExists($name) {
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
		//Unset query cache, otherwise it causes:
		// SQLSTATE[HY000]: General error: 17 database schema has changed
		$this->queryCache = [];

		$affix = '_'.substr(md5($table), 0, 6);
		$this->createTable($table . $affix, $primaryKey, $data);
		$fields = [];
		foreach ($data as $key => $value) { $fields[] = $key; }
		try {
			if ($this->tableExists($table)) {
				$columns = implode(', ', $this->getColumns($table));			

				$this->pdo->query('INSERT INTO ' . $this->quote($table . $affix) . '(' . $columns . ') SELECT ' . $columns . ' FROM ' . $this->quote($table));
				$this->pdo->query('DROP TABLE IF EXISTS ' . $table );
			}
		}
		catch (\PDOException $e) {
			// No data to copy
			echo $e->getMessage();
		}

		$this->pdo->query('DROP TABLE IF EXISTS ' . $table );
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
		if (empty($fields)) return false;
		
		//SQLite doesn't support ASC/DESC indexes, remove the keywords
		foreach ($fields as &$field) $field = str_ireplace([' desc', ' asc'], '', $field);
		sort($fields);
		$fields = array_map('strtolower', $fields);
		$fields = array_map('trim', $fields);
		$keyName = implode('_', $fields);
	
		
		try {
			$this->pdo->query('CREATE INDEX IF NOT EXISTS  ' . $keyName . ' ON ' . $table . ' (' . implode(', ', $fields) . ')');
		}
		catch (\Exception $e) {
			
		}
	}
	
	public function optimiseColumns($table) {
		//TODO
	}
}