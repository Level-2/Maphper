<?php
namespace Maphper\DataSource;
use Maphper\Maphper;
class Mock implements \Maphper\DataSource {
    private $data;
    private $id;

    public function __construct(\ArrayObject $data, $id) {
        $this->data = $data;
        $this->id = is_array($id) ? $id : [$id];
    }

    public function getPrimaryKey() {
        return $this->id;
    }

    public function findById($id) {
        return isset($this->data[$id]) ? (array)$this->data[$id] : [];
    }

    public function findByField(array $fields, $options = []) {
        $array = iterator_to_array($this->data->getIterator());
        $filteredArray = array_filter($array, $this->getSearchFieldFunction($fields, \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND));
        // Need to reset indexes
        $filteredArray = array_values($filteredArray);
        if (isset($options['order'])) {
            list($columns, $order) = explode(' ', $options['order']);
            usort($filteredArray, $this->getOrderFunction($order, $columns));
        }
        if (isset($options['offset'])) $filteredArray = array_slice($filteredArray, $options['offset']);
        if (isset($options['limit'])) $filteredArray = array_slice($filteredArray, 0, $options['limit']);
        return $filteredArray;
    }

    private function getSearchFieldFunction($fields, $mode) {
        return function ($data) use ($fields, $mode) {
            foreach ($fields as $key => $val) {
                $currentFieldResult = $this->getIfFieldMatches($key, $val, $data, $mode);

                if (Maphper::FIND_OR & $mode && $currentFieldResult === true) return true;
                else if (!(Maphper::FIND_OR & $mode) && $currentFieldResult === false) return false;
            }
            return !(Maphper::FIND_OR & $mode);
        };
    }

    private function getIfFieldMatches($key, $val, $data, $mode) {
        if (is_numeric($key) && is_array($val)) {
            return $this->getSearchFieldFunction($val, $key)($data);
        }
        else if (!isset($data->$key)) return false;
        else if (!(Maphper::FIND_BETWEEN & $mode) && !is_numeric($key) && is_array($val))
            return in_array($data->$key, $val);
        else
            return $this->processFilter($mode, $val, $data->$key);
    }

  	public function findAggregate($function, $field, $group = null, array $criteria = [], array $options = []) {
        $array = iterator_to_array($this->data);
        return $function($this->findByField($criteria));
    }

  	public function deleteById($id) {
        unset($this->data[$id]);
    }

  	public function deleteByField(array $fields) {
        foreach ($this->findByField($fields) as $val) unset($this->data[$val->{$this->id[0]}]);
    }

    public function save($data) {
        if (isset($data->{$this->id[0]})) {
            $id = $data->{$this->id[0]};
        }
        else {
            $id = count($this->data);
            $data->{$this->id[0]} = $id;
        }

        $this->data[$id] = (object)array_merge($this->findById($id), (array)$data);
    }

    public function getErrors() {
        return [];
    }

    private function getOrderFunction($order, $columns) {
        return function($a, $b) use ($order, $columns) {
          foreach (explode(',', $columns) as $column) {
            $aColumn = $a->$column;
            $bColumn = $b->$column;
            if ($aColumn === $bColumn) {
              $sortVal = 0;
              continue;
            }
            else $sortVal = ($aColumn < $bColumn) ? -1 : 1;
            break;
          }
          if ($order === 'desc') return -$sortVal;
          else return $sortVal;
        };
    }

    private function processFilter($mode, $expected, $actual) {
        if (Maphper::FIND_NOT & $mode) return $expected != $actual;
        else if (Maphper::FIND_GREATER & $mode) return $expected < $actual;
        else if (Maphper::FIND_LESS & $mode) return $expected > $actual;
        else if (Maphper::FIND_BETWEEN & $mode) return $expected[0] <= $actual && $actual <= $expected[1];
        else if (Maphper::FIND_NOCASE & $mode) return strtolower($expected) == strtolower($actual);
        return $expected == $actual;
    }
}
