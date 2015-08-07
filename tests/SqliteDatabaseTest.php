<?php 
/*
 * Integration tests. Tests Maphper working with a Database DataSource
 * This does the same tests as MySQL, the only difference is it uses a SQLite backend 
 */
require_once __DIR__ . '/MySqlDatabaseTest.php';
class SqliteDatabaseTest extends MySqlDatabaseTest {

	
	public function __construct() {
		parent::__construct();
		$this->pdo = new PDO('sqlite:./test.db');
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		//prevent any Date errors
		date_default_timezone_set('Europe/London');
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
	

}
