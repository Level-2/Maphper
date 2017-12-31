<?php
namespace Maphper\DataSource;

class SqliteAdapter implements DatabaseAdapter {
	private $pdo;
	private $stmtCache;

	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
        $this->stmtCache = new StmtCache($pdo);
	}

	public function quote($str) {
		return '`' . str_replace('.', '`.`', trim($str, '`')) . '`';
	}

	public function query(\Maphper\Lib\Query $query) {
        $stmt = $this->stmtCache->getCachedStmt($query->getSql());
		$args = $query->getArgs();

        //Handle SQLite when PDO_ERRMODE is set to SILENT
        if ($stmt === false) throw new \Exception('Invalid query');

        $stmt->execute($args);
        if ($stmt->errorCode() !== '00000' && $stmt->errorInfo()[2] == 'database schema has changed') {
			$this->stmtCache->deleteQueryFromCache($query->getSql());
			return $this->query($query);
        }

        return $stmt;
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
		$this->stmtCache->clearCache();

        // Create temp table to create a new structure
		$affix = '_'.substr(md5($table), 0, 6);
        $tempTable = $table . $affix;
		$this->createTable($tempTable, $primaryKey, $data);
        $this->alterColumns($tempTable, $primaryKey, $data);
		$this->copyTableData($table, $tempTable);

		$this->pdo->query('DROP TABLE IF EXISTS ' . $table );
		$this->pdo->query('ALTER TABLE ' . $tempTable . ' RENAME TO '. $table );

	}

    private function copyTableData($tableFrom, $tableTo) {
        try {
			if ($this->tableExists($tableFrom)) {
				$columns = implode(', ', $this->getColumns($tableFrom));

				$this->pdo->query('INSERT INTO ' . $this->quote($tableTo) . '(' . $columns . ') SELECT ' . $columns . ' FROM ' . $this->quote($tableFrom));
			}
		}
		catch (\PDOException $e) {
			// No data to copy
			echo $e->getMessage();
		}
    }

	private function createTable($table, array $primaryKey, $data) {
		$parts = [];
		foreach ($primaryKey as $key) {
			$pk = $data->$key;
			if ($pk == null) $parts[] = $key . ' INTEGER'; 
			else $parts[] = $key . ' ' . $this->getType($pk) . ' NOT NULL';					
		}
		
		$pkField = implode(', ', $parts) . ', PRIMARY KEY(' . implode(', ', $primaryKey) . ')';

		$this->pdo->query('CREATE TABLE ' . $table . ' (' . $pkField . ')');
	}

    private function alterColumns($table, array $primaryKey, $data) {
        foreach ($data as $key => $value) {
			if ($this->isNotSavableType($value, $key, $primaryKey)) continue;

			$type = $this->getType($value);
		
			$this->pdo->query('ALTER TABLE ' . $table . ' ADD ' . $this->quote($key) . ' ' . $type);
		}
    }

    private function isNotSavableType($value, $key, $primaryKey) {
        return is_array($value) || (is_object($value) && !($value instanceof \DateTime)) ||
                in_array($key, $primaryKey);
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