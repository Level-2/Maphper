<?php 
namespace Maphper\DataSource;
interface DatabaseAdapter {
	public function quote($str);
	public function delete($table, array $criteria, $args, $limit = null, $offset = null);
	public function select($table, array $criteria, $args, $order = null, $limit = null, $offset = null);
	public function aggregate($table, $function, $field, $where, $args, $group);
	public function insert($table, array $primaryKey, $data);
	public function alterDatabase($table, array $primaryKey, $data);
}