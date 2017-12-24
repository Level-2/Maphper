<?php
namespace Maphper\Optimiser;

class MySql {
	public function optimiseColumns($table) {
		//Buggy, disabled for now!
		return;

		$runAgain = false;
		$columns = $this->pdo->query('SELECT * FROM '. $this->quote($table) . ' PROCEDURE ANALYSE(1,1)')->fetchAll(\PDO::FETCH_OBJ);
		foreach ($columns as $column) {
			$parts = explode('.', $column->Field_name);
			$name = $this->quote(end($parts));
			if ($column->Min_value === null && $column->Max_value === null) $this->pdo->query('ALTER TABLE ' . $this->quote($table) . ' DROP COLUMN ' . $name);
			else {
				$type = $column->Optimal_fieldtype;
				if ($column->Max_length < 11) {
					//Check for dates
					$count = $this->pdo->query('SELECT count(*) as `count` FROM ' . $this->quote($table) . ' WHERE STR_TO_DATE(' . $name . ',\'%Y-%m-%d %H:%i:s\') IS NULL OR STR_TO_DATE(' . $name . ',\'%Y-%m-%d %H:%i:s\') != ' . $name . ' LIMIT 1')->fetch(\PDO::FETCH_OBJ)->count;
					if ($count == 0) $type = 'DATETIME';

					$count = $this->pdo->query('SELECT count(*) as `count` FROM ' . $this->quote($table) . ' WHERE STR_TO_DATE(' . $name . ',\'%Y-%m-%d\') IS NULL OR STR_TO_DATE(' . $name . ',\'%Y-%m-%d\') != ' . $name . ' LIMIT 1')->fetch(\PDO::FETCH_OBJ)->count;
					if ($count == 0) $type = 'DATE';
				}

				//If it's text, work out if it would be better to be something else
				if (strpos($type, 'VARCHAR') !== false || strpos($type, 'CHAR') !== false || strpos($type, 'BINARY') !== false || strpos($type, 'BLOB') !== false || strpos($type, 'TEXT') !== false) {
					//See if it's an int
					$count = $this->pdo->query('SELECT count(*) FROM ' . $table . ' WHERE concat(\'\', ' . $name . ' * 1) != ABS(' . $name . ')) LIMIT 1')->fetch(\PDO::FETCH_OBJ)->count;
					if ($count == 0) {
						$type = 'INT(11)';
						$runAgain = true;
					}
					else {
						//See if it's decimal
						$count = $this->pdo->query('SELECT count(*) FROM ' . $table . ' WHERE concat(\'\', ' . $name . ' * 1) != ' . $name . ')')->fetch(\PDO::FETCH_OBJ)->count;
						if ($count == 0) {
							$type = 'DECIMAL(64,64)';
							$runAgain = true;
						}
					}
				}

				$this->pdo->query('ALTER TABLE ' . $this->quote($table) . ' MODIFY '. $name . ' ' . $type);
			}
		}
		//Sometimes a second pass is needed, if a column has gone from varchar -> int(11) a better int type may be needed
		if ($runAgain) $this->optimiseColumns($table);
	}
}