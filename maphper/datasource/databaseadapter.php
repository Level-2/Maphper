<?php 
namespace Maphper\DataSource;
interface DatabaseAdapter {
	public function query(\Maphper\Lib\Query $query);
	public function quote($str);
	public function alterDatabase($table, array $primaryKey, $data);
	public function addIndex($table, array $fields);
	public function lastInsertId();
	public function optimiseColumns($table);
}