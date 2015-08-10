<?php
namespace Maphper\DataSource;
class MySqlAdapter implements DatabaseAdapter {
	private $pdo;
	private $queryCache = [];
	
	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
		//Set to strict mode to detect 'out of range' errors, action at a distance but it needs to be set for all INSERT queries
		$this->pdo->query('SET sql_mode = STRICT_ALL_TABLES');
	}
		
	public function quote($str) {
		return '`' . str_replace('.', '`.`', trim($str, '`')) . '`';
	}

	private function getCachedStmt($sql) {
		$queryId = md5($sql);
		if (isset($this->queryCache[$queryId])) $stmt = $this->queryCache[$queryId];
		else {
			$stmt = $this->pdo->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);			
			if ($stmt) $this->queryCache[$queryId] = $stmt;
		}
		return $stmt;
	}

	public function query(\Maphper\Lib\Query $query) {
		$stmt = $this->getCachedStmt($query->getSql());
		$args = $query->getArgs();
		foreach ($args as &$arg) if ($arg instanceof \DateTime) $arg = $arg->format('Y-m-d H:i:s');
		$res = $stmt->execute($args);
		
		if (strpos(trim($query->getSql()), 'SELECT') === 0) return $stmt->fetchAll(\PDO::FETCH_OBJ);
		else return $stmt;	
	}

	private function getType($val) {
		if ($val instanceof \DateTime) return 'DATETIME';
		else if (is_int($val)) return  'INT(11)';
		else if (is_double($val)) return 'DECIMAL(9,' . strlen($val) - strrpos($val, '.') - 1 . ')';
		else if (is_string($val)) return strlen($val) < 256 ? 'VARCHAR(255)' : 'LONGBLOB';
		return 'VARCHAR(255)';		
	}
	
	//Alter the database so that it can store $data
	private function createTable($table, array $primaryKey, $data) {
		$parts = [];
		foreach ($primaryKey as $key) {
			$pk = $data->$key;
			if ($pk == null) $parts[] = $key . ' INT(11) NOT NULL AUTO_INCREMENT'; 
			else $parts[] = $key . ' ' . $this->getType($pk) . ' NOT NULL';					
		}
		
		$pkField = implode(', ', $parts) . ', PRIMARY KEY(' . implode(', ', $primaryKey) . ')';
		$this->pdo->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (' . $pkField . ')');
	}

	public function alterDatabase($table, array $primaryKey, $data) {		
		$this->createTable($table, $primaryKey, $data);

		foreach ($data as $key => $value) {
			if (is_array($value) || (is_object($value) && !($value instanceof \DateTime))) continue;	
			if (in_array($key, $primaryKey)) continue;

			$type = $this->getType($value);
		
			try {
				if (!$this->pdo->query('ALTER TABLE ' . $table . ' ADD ' . $this->quote($key) . ' ' . $type)) throw new \Exception('Could not alter table');
			}
			catch (\Exception $e) {
				$this->pdo->query('ALTER TABLE ' . $table . ' MODIFY ' . $this->quote($key) . ' ' . $type);
			}
		}
	}
	
	public function lastInsertId() {
		return $this->pdo->lastInsertId();
	}
	
	public function addIndex($table, array $fields) {
		//Sort the fields so that the index is never created twice (col1, col2) then (col2, col1)
		sort($fields);
		$fields = array_map('strtolower', $fields);
		$fields = array_map('trim', $fields);
		$keyName = $this->quote(implode('_', $fields));
		
		$results = $this->pdo->query('SHOW INDEX FROM ' . $this->quote($table) . ' WHERE Key_Name = "' . $keyName . '"');
		if ($results && count($results->fetchAll()) == 0)  $this->pdo->query('CREATE INDEX ' . $keyName . ' ON ' . $this->quote($table) . ' (' . implode(', ', $fields) . ')');
	}
	
	public function optimiseColumns($table) {
		//Buggy, disabled for now!
		return;

		$runAgain = false;
		$columns = $this->pdo->query('SELECT * FROM '. $this->quote($table) . ' PROCEDURE ANALYSE(1,1)')->fetchAll(\PDO::FETCH_OBJ);
		foreach ($columns as $column) {
			$parts = explode('.', $column->Field_name);
			$name = $this->quote(end($parts));
			if ($column->Min_value === null && $column->Max_value === null) $this->pdo->query('ALTER TABLE ' . $this->quote($table) . ' DROP COLUMN ' . $name);
			else {
				$type = $column->Optimal_fieldtype;
				if ($column->Max_length < 11) {
					//Check for dates
					$count = $this->pdo->query('SELECT count(*) as `count` FROM ' . $this->quote($table) . ' WHERE STR_TO_DATE(' . $name . ',\'%Y-%m-%d %H:%i:s\') IS NULL OR STR_TO_DATE(' . $name . ',\'%Y-%m-%d %H:%i:s\') != ' . $name . ' LIMIT 1')->fetch(\PDO::FETCH_OBJ)->count;
					if ($count == 0) $type = 'DATETIME';					
					
					$count = $this->pdo->query('SELECT count(*) as `count` FROM ' . $this->quote($table) . ' WHERE STR_TO_DATE(' . $name . ',\'%Y-%m-%d\') IS NULL OR STR_TO_DATE(' . $name . ',\'%Y-%m-%d\') != ' . $name . ' LIMIT 1')->fetch(\PDO::FETCH_OBJ)->count;
					if ($count == 0) $type = 'DATE';					
				}
				
				//If it's text, work out if it would be better to be something else
				if (strpos($type, 'VARCHAR') !== false || strpos($type, 'CHAR') !== false || strpos($type, 'BINARY') !== false || strpos($type, 'BLOB') !== false || strpos($type, 'TEXT') !== false) {
					//See if it's an int
					$count = $this->pdo->query('SELECT count(*) FROM ' . $table . ' WHERE concat(\'\', ' . $name . ' * 1) != ABS(' . $name . ')) LIMIT 1')->fetch(\PDO::FETCH_OBJ)->count;
					if ($count == 0) {
						$type = 'INT(11)';
						$runAgain = true;
					}
					else {
						//See if it's decimal
						$count = $this->pdo->query('SELECT count(*) FROM ' . $table . ' WHERE concat(\'\', ' . $name . ' * 1) != ' . $name . ')')->fetch(\PDO::FETCH_OBJ)->count;
						if ($count == 0) {
							$type = 'DECIMAL(64,64)';
							$runAgain = true;
						}
					}
				}				
				
				$this->pdo->query('ALTER TABLE ' . $this->quote($table) . ' MODIFY '. $name . ' ' . $type);				
			}			
		}
		//Sometimes a second pass is needed, if a column has gone from varchar -> int(11) a better int type may be needed
		if ($runAgain) $this->optimiseColumns($table);
	}
}