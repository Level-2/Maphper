<?php
namespace Maphper\DataSource\Database;
class Query {
	private $sql;
	private $args = [];

	public function __construct($sql, $args = null) {
		$this->sql = $sql;
		$this->args = $args;
	}

	public function getSql() {
		return $this->sql;
	}

	public function getArgs() {
		return $this->args;
	}
}