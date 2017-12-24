<?php
namespace Maphper\DataSource;
class DatabaseOptions {
	private $db;
	private $options;

	public function __construct($db, $options) {
		$this->db = $db;
		$this->options = $options;
	}

	public function getAdapter() {
		if (!($this->db instanceof \PDO)) return $this->db;

		$adapter = '\\Maphper\\DataSource\\' . ucfirst($this->db->getAttribute(\PDO::ATTR_DRIVER_NAME)) . 'Adapter';

		return new $adapter($this->db);
	}

	public function getEditMode() {
		if (!isset($this->options['editmode'])) return false;

		return $this->options['editmode'] === true ? Database::EDIT_STRUCTURE | Database::EDIT_INDEX | Database::EDIT_OPTIMISE : $this->options['editmode'];
	}

	public function read($option) {
		return $this->options[$option] ?? false;
	}
}