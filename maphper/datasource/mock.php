<?php
namespace Maphper\DataSource;
class Mock implements \Maphper\DataSource {
    private $data;
    private $id;

    public function __construct(\ArrayObject $data, $id) {
        $this->data = $data;
        $this->id = $id;
    }

    private function addPkToData($data, $id) {
        $data->{$this->id} = $id;
        return $data;
    }

    public function getPrimaryKey() {
        return $this->id;
    }

	public function findById($id) {
        return $this->addPkToData($this->data[$id], $id);
    }

	public function findByField(array $fields, $options = []) {
        $array = iterator_to_array($this->data);
        return array_filter($array, function ($val, $key) use ($fields) {
            if (!isset($fields[$key])) return true;
            else {
                if (is_array($fields[$key])) {
                    return in_array($val, $fields[$key]);
                }
                else return $fields[$key] === $val;
            }
        }, ARRAY_FILTER_USE_BOTH);
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
            unset($data->{$this->id});
        }
        else $id = null;

        $this->data[$id] = $data;
    }

	public function getErrors() {
        return [];
    }
}
