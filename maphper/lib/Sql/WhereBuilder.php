<?php
namespace Maphper\Lib\Sql;

class WhereBuilder {
    private $conditionals = [];

    public function __construct() {
        $defaultConditionals = [
            'Maphper\Lib\Sql\Between',
            'Maphper\Lib\Sql\In',
            'Maphper\Lib\Sql\NullConditional',
            'Maphper\Lib\Sql\Like',
            'Maphper\Lib\Sql\GeneralOperator'
        ];

        foreach ($defaultConditionals as $conditional) $this->addConditional(new $conditional);
    }

    public function addConditional(WhereConditional $conditional) {
        $this->conditionals[] = $conditional;
    }

	public function createSql($fields, $mode = \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND) {
		$args = [];
		$sql = [];

        foreach ($fields as $key => $value) {
            $value = $this->convertDates($value);

            if (is_object($value)) continue;
			if (is_numeric($key) && is_array($value)) {
				$result = $this->createSql($value, $key);
			}
            else {
                $result = $this->getConditional($key, $value, $mode);
            }
            $sql = array_merge($sql, (array)$result['sql']);
            $args = array_merge($args, $result['args']);
        }

		return ['args' => $args, 'sql' => $this->sqlArrayToString($sql, $mode)];
	}

    private function sqlArrayToString($sql, $mode) {
        if (\Maphper\Maphper::FIND_OR & $mode) $query = implode(' OR  ', $sql);
		else $query = implode(' AND ', $sql);
		if (!empty($query)) $query = '(' . $query . ')';
        return $query;
    }

    private function getConditional($key, $value, $mode) {
        foreach ($this->conditionals as $conditional) {
            if ($conditional->matches($key, $value, $mode))
                return $conditional->getSql($key, $value, $mode);
        }
        throw new \Exception("Invalid WHERE query");
    }

    private function convertDates($value) {
        if ($value instanceof \DateTime) {
            if ($value->format('H:i:s')  == '00:00:00') $value = $value->format('Y-m-d');
            else $value = $value->format('Y-m-d H:i:s');
        }
        return $value;
    }
}
