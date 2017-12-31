<?php
namespace Maphper\DataSource;
class MysqlAdapter implements DatabaseAdapter {
	private $pdo;
	private $stmtCache;

	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
		//Set to strict mode to detect 'out of range' errors, action at a distance but it needs to be set for all INSERT queries
		$this->pdo->query('SET sql_mode = STRICT_ALL_TABLES');
        $this->stmtCache = new StmtCache($pdo);
	}

	public function quote($str) {
		return '`' . str_replace('.', '`.`', trim($str, '`')) . '`';
	}

	public function query(\Maphper\Lib\Query $query) {
		$stmt = $this->stmtCache->getCachedStmt($query->getSql());
		$args = $query->getArgs();
        $stmt->execute($args);

		return $stmt;
	}

	private function getType($val) {
		if ($val instanceof \DateTime) return 'DATETIME';
		else if (is_int($val)) return  'INT(11)';
		else if (is_double($val)) return 'DECIMAL(9,' . strlen($val) - strrpos($val, '.') - 1 . ')';
		else if (is_string($val)) return strlen($val) < 192 ? 'VARCHAR(191)' : 'LONGBLOB';
		return 'VARCHAR(191)';
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

    private function alterColumns($table, array $primaryKey, $data) {
        foreach ($data as $key => $value) {
			if ($this->isNotSavableType($value, $key, $primaryKey)) continue;

			$type = $this->getType($value);

			try {
				if (!$this->pdo->query('ALTER TABLE ' . $table . ' ADD ' . $this->quote($key) . ' ' . $type)) throw new \Exception('Could not alter table');
			}
			catch (\Exception $e) {
				$this->pdo->query('ALTER TABLE ' . $table . ' MODIFY ' . $this->quote($key) . ' ' . $type);
			}
		}
    }

    private function isNotSavableType($value, $key, $primaryKey) {
        return is_array($value) || (is_object($value) && !($value instanceof \DateTime)) ||
                in_array($key, $primaryKey);
    }

	public function alterDatabase($table, array $primaryKey, $data) {
		$this->createTable($table, $primaryKey, $data);
        $this->alterColumns($table, $primaryKey, $data);
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
		//TODO
		return;
	}
}
