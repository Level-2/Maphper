<?php
namespace Maphper\DataSource;

class SqliteAdapter implements DatabaseAdapter {
	private $pdo;
	private $stmtCache;
    private $generalEditor;

	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
        $this->stmtCache = new StmtCache($pdo);
        $this->generalEditor = new GeneralEditDatabase($this->pdo, [
            'int' => 'INTEGER',
            'pk_default' => 'INTEGER NOT NULL',
        ]);
	}

	public function quote($str) {
		return $this->generalEditor->quote($str);
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
		$this->generalEditor->createTable($tempTable, $primaryKey, $data);
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

    private function alterColumns($table, array $primaryKey, $data) {
        foreach ($data as $key => $value) {
			if ($this->generalEditor->isNotSavableType($value, $key, $primaryKey)) continue;

			$type = $this->generalEditor->getType($value);

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
