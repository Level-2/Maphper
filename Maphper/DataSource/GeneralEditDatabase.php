<?php
namespace Maphper\DataSource;

class GeneralEditDatabase {
    private $pdo;
    private $dataTypes = [
        'datetime' => 'DATETIME',
        'int' => 'INT(11)',
        'decimal' => 'DECIMAL',
        'short_string' => 'VARCHAR',
        'long_string' => 'LONGBLOG',
        'short_string_max_len' => 255,
        'other' => 'VARCHAR(255)',

        'pk_default' => 'INT(11) NOT NULL AUTO_INCREMENT'
    ];

    public function __construct(\PDO $pdo, array $dataTypes) {
        $this->pdo = $pdo;
        $this->dataTypes = array_merge($this->dataTypes, $dataTypes);
    }

    public function quote($str) {
		return '`' . str_replace('.', '`.`', trim($str, '`')) . '`';
	}

    public function getType($val) {
		if ($val instanceof \DateTime) return $this->dataTypes['datetime'];
		else if ($result = $this->doNumberTypes($val)) return $result;
		else if ($result = $this->doStringTypes($val)) return $result;
		else return $this->dataTypes['other'];
	}

    private function doNumberTypes($val) {
        if (is_int($val)) return $this->dataTypes['int'];
		else if (is_double($val)) return $this->dataTypes['decimal'] . '(9,' . (strlen($val) - strrpos($val, '.') - 1) . ')';
        else return false;
    }

    private function doStringTypes($val) {
        if (!is_string($val)) return false;
        if (strlen($val) <= $this->dataTypes['short_string_max_len'])
            return $this->dataTypes['short_string'] . '(' . $this->dataTypes['short_string_max_len'] . ')';
		else return $this->dataTypes['long_string'];
    }

    public function isNotSavableType($value, $key, $primaryKey) {
        return is_array($value) || (is_object($value) && !($value instanceof \DateTime)) ||
                in_array($key, $primaryKey);
    }

    //Alter the database so that it can store $data
    public function createTable($table, array $primaryKey, $data) {
		$parts = [];
		foreach ($primaryKey as $key) {
			$pk = $data->$key;
			if ($pk == null) $parts[] = $key . ' ' . $this->dataTypes['pk_default'];
			else $parts[] = $key . ' ' . $this->getType($pk) . ' NOT NULL';
		}

		$pkField = implode(', ', $parts) . ', PRIMARY KEY(' . implode(', ', $primaryKey) . ')';
		$this->pdo->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (' . $pkField . ')');
	}
}
