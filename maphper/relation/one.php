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
			$results = $this->relation->mapper->filter([$this->relation->parentField => $this->parentField]);
			if (count($results) > 0) $this->data = $results->item(0);
			else $this->data = null;
		}
		return $this->data;
	}

	public function __get($name) {
		if ($this->getData()) return $this->getData()->$name;
	}

	public function __call($func, array $args = []) {
		if ($this->getData() == null) return '';
		return call_user_func_array([$this->getData(), $func], $args);
	}
	
	public function __toString() {
		if (method_exists($this->getData(), '__toString')) return $this->data->__toString();
		else return spl_object_hash($this->data);
	}
}
