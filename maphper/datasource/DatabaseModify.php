<?php
namespace Maphper\DataSource;

class DatabaseModify {
    private $adapter;
    private $alterDb;
    private $table;

    public function __construct(DatabaseAdapter $adapter, $alterDb, $table) {
        $this->adapter = $adapter;
        $this->alterDb = $alterDb;
        $this->table = $table;
    }

    public function addIndex($args) {
		if (Database::EDIT_INDEX & $this->alterDb) $this->adapter->addIndex($this->table, $args);
	}

    public function optimizeColumns() {
        if (Database::EDIT_OPTIMISE & $this->alterDb && rand(0,500) == 1) $this->adapter->optimiseColumns($this->table);
    }

    public function getTryInsertAgain($tryagain) {
        return $tryagain && Database::EDIT_STRUCTURE & $this->alterDb;
    }
}
