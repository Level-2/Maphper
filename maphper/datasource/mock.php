<?php
namespace Maphper\DataSource;
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
        $filteredArray = array_filter($array, function ($data) use ($fields) {
            foreach ($fields as $key => $val) {
                if (!isset($data->$key)) return false;
                else {
                    if (is_array($val)) {
                        if (!in_array($data->$key, $val)) return false;
                    }
                    else if ($data->$key !== $val) return false;
                }
            }
            return true;
        });
        // Need to reset indexes
        $filteredArray = array_values($filteredArray);
        if (isset($options['order'])) {
            list($columns, $order) = explode(' ', $options['order']);
            $sortFunction = function($a, $b) use ($order, $columns) {
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
            usort($filteredArray, $sortFunction);
        }
        if (isset($options['offset'])) $filteredArray = array_slice($filteredArray, $options['offset']);
        if (isset($options['limit'])) $filteredArray = array_slice($filteredArray, 0, $options['limit']);
        return $filteredArray;
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
}
