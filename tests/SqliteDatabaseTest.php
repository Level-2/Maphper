<?php 
/*
 * Integration tests. Tests Maphper working with a Database DataSource
 * This does the same tests as MySQL, the only difference is it uses a SQLite backend 
 */
require_once __DIR__ . '/MySqlDatabaseTest.php';
class SqliteDatabaseTest extends MySqlDatabaseTest {

	
	public function __construct() {
		parent::__construct(false);
		
		//prevent any Date errors
		date_default_timezone_set('Europe/London');
	}
	

	protected function setUp() {
		parent::setUp ();
		$this->pdo = new PDO('sqlite:./test.db');
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
	}

	protected function tearDown() {
		$this->pdo = null;
	}


	//@Override
	protected function tableExists($name) {
		$result = $this->pdo->query('SELECT * FROM sqlite_master WHERE type="table" and name="'. $name.'"');
		return count($result->fetchAll()) == 1;
	}
	
	//@Override
	protected function dropTable($name) {
		$this->pdo->query('DROP TABLE IF EXISTS ' . $name);
	}

	protected function getColumnType($table, $column) {
		$result = $this->pdo->query('PRAGMA table_info(' . $table . ');')->fetchAll(\PDO::FETCH_OBJ);
		foreach ($result as $row) {
			if (strtolower($row->name) == $column) return $row->type;
		}
	}

	//@Override
	protected function hasIndex($table, $column) {
		//$result = $this->pdo->query('indices ' . $table)->fetchAll(\PDO::FETCH_OBJ);
		$result = $this->pdo->query('PRAGMA index_list(' . $table . ')')->fetchAll();
		foreach ($result as $index) {
			if (strtolower($index['name']) == strtolower($column)) return true;
		}
		return false;

	}

}
