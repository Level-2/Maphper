<?php
namespace Maphper\DataSource;
class Mock implements \Maphper\DataSource {
    private $data;
    private $id;

    public function __construct(\ArrayObject $data, $id) {
        $this->data = $data;
        $this->id = $id;
    }

    public function getPrimaryKey() {
        return $this->id;
    }

    public function findById($id) {
        return isset($this->data[$id]) ?: [];
    }

    public function processDates($obj) {
		$injector = new DateInjector;
		return $injector->replaceDates($obj);
	}

    public function findByField(array $fields, $options = []) {
        $array = iterator_to_array($this->data->getIterator());
        $filteredArray = array_filter($array, function ($data) use ($fields) {
            foreach ($fields as $key => $val) {
                if (!isset($data[$key])) return false;
                else {
                    if (is_array($val)) {
                        if (!in_array($data[$key], $val)) return false;
                    }
                    else if ($data[$key] !== $val) return false;
                }
            }
            return true;
        });
        // Need to reset indexes
        return array_values($filteredArray);
    }

	public function findAggregate($function, $field, $group = null, array $criteria = [], array $options = []) {
        $array = iterator_to_array($this->data);
        return $function($this->findByField($criteria));
    }

	public function deleteById($id) {
        unset($this->data[$id]);
    }

	public function deleteByField(array $fields) {
        foreach ($this->findByField($fields) as $key => $val) unset($this->data[$key]);
    }

	public function save($data) {
        if (isset($data->{$this->id})) {
            $id = $data->{$this->id};
        }
        else $id = null;

        $this->data[$id] = $data;
    }

	public function getErrors() {
        return [];
    }
}
