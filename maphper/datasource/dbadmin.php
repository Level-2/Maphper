<?php 
namespace Maphper\DataSource;
class DbAdmin {
	private $db;
	
	public function __construct(\PDO $db) {
		$this->db = $db;
	}
	
	public function createTable($name){
		$parts = explode('.', $name);
		if (count($parts) > 1)  {
			$this->db->query('USE ' . $parts[0]);
		}
		
		//$this->db->query('CREATE TABLE IF NOT EXISTS')
		
		
	} 
	
}