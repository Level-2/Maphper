<?php 
namespace Maphper\Loader\Xml\DataSource;
class Database implements \Maphper\Loader\Xml\DataSource {
	public function load(\SimpleXmlElement $xml) {
		$db = new \Dice\Rule;
		$db->instanceOf = 'Maphper\\DataSource\\Database';
		$db->constructParams[] = (string) $xml->table;
		return $db;
	}
}