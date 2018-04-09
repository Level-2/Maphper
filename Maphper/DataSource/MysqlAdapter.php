<?php
namespace Maphper\DataSource;
class MysqlAdapter implements DatabaseAdapter {
	private $pdo;
	private $stmtCache;
    private $generalEditor;

	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
		//Set to strict mode to detect 'out of range' errors, action at a distance but it needs to be set for all INSERT queries
		$this->pdo->query('SET sql_mode = STRICT_ALL_TABLES');
        $this->stmtCache = new StmtCache($pdo);
        $this->generalEditor = new GeneralEditDatabase($this->pdo, ['short_string_max_len' => 191]);
	}

	public function quote($str) {
		return $this->generalEditor->quote($str);
	}

	public function query(\Maphper\Lib\Query $query) {
		$stmt = $this->stmtCache->getCachedStmt($query->getSql());
		$args = $query->getArgs();
        $stmt->execute($args);

		return $stmt;
	}

    private function alterColumns($table, array $primaryKey, $data) {
        foreach ($data as $key => $value) {
			if ($this->generalEditor->isNotSavableType($value, $key, $primaryKey)) continue;

			$type = $this->generalEditor->getType($value);
			$this->tryAlteringColumn($table, $key, $type);
		}
    }

    private function tryAlteringColumn($table, $key, $type) {
        try {
            if (!$this->pdo->query('ALTER TABLE ' . $table . ' ADD ' . $this->quote($key) . ' ' . $type)) throw new \Exception('Could not alter table');
        }
        catch (\Exception $e) {
            $this->pdo->query('ALTER TABLE ' . $table . ' MODIFY ' . $this->quote($key) . ' ' . $type);
        }
    }

	public function alterDatabase($table, array $primaryKey, $data) {
		$this->generalEditor->createTable($table, $primaryKey, $data);
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
