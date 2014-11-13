<?php
namespace Maphper\Relation;
class One {
	private $data;
	private $relation;
	private $parentField;

	public function __construct($relation, $parentField) {
		$this->relation = $relation;
		$this->parentField = $parentField;
	}

	private function getData() {
		if (empty($this->data)) {
			$results = $this->relation->mapper->find([$this->relation->parentField => $this->parentField]);
			$this->data = $results[0];
		}
		return $this->data;
	}

	public function __get($name) {
		return $this->getData()->$name;
	}

	public function __call($func, $args) {
		return call_user_func_array([$this->getData(), $func], $args);
	}
	
	public function __toString() {
		if (method_exists($this->getData(), '__toString')) return $this->data->__toString();
		else return spl_object_hash($this->data);
	}
}