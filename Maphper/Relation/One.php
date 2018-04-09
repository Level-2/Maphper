<?php
namespace Maphper\Relation;
class One implements \Maphper\Relation {
	private $mapper;
	private $parentField;
	private $localField;
	private $parentObject;
	private $data;
	private $siblings = [];

	public function __construct(\Maphper\Maphper $mapper, $parentField, $localField, array $criteria = []) {
        if ($criteria) $mapper = $mapper->filter($this->criteira);
		$this->mapper = $mapper;
		$this->parentField = $parentField;
		$this->localField = $localField;
	}

	public function getData($parentObject, &$siblings = null) {
		//Don't actually fetch the related data, return an instance of $this that will lazy load data when __get is called
		$clone = clone $this;
		$clone->parentObject = $parentObject;
		$siblings[] = $clone;
		$clone->siblings = $siblings;

	//	var_dump($siblings);
		return $clone;
	}

	private function lazyLoad() {
		if (!isset($this->data)) {

			if ($this->parentObject == null) throw new \Exception('Error, no object set');

			$this->eagerLoad();

		}
		return $this->data;
	}

	private function eagerLoad() {
		$recordsToLoad = [];
		//Get a list of records by FK to eager load
		foreach ($this->siblings as $sibling) {
			$recordsToLoad[] = $sibling->parentObject->{$sibling->parentField};
		}

		$recordsToLoad = array_unique($recordsToLoad);
		//Fetch the results so they're in the cache for the corresponding maphper object
		$results = $this->mapper->filter([$this->localField => $recordsToLoad]);

        $this->loadDataIntoSiblings($results);
	}

    private function loadDataIntoSiblings($results) {
        $cache = [];
		foreach ($results as $result) {
			$cache[$result->{$this->localField}] = $result;
		}

		foreach ($this->siblings as $sibling) {
            if (isset($cache[$sibling->parentObject->{$this->parentField}])) $sibling->data = $cache[$sibling->parentObject->{$this->parentField}];
		}
		/*
		foreach ($this->siblings as $sibling) {
			if ($sibling->criteria) $sibling->data = $sibling->mapper->filter($sibling->criteria)->filter([$sibling->localField => $sibling->parentObject->{$sibling->parentField}])->item(0);
			else $sibling->data = $sibling->mapper->filter([$sibling->localField => $sibling->parentObject->{$this->parentField}])->item(0);
		}
		*/
    }

	public function __call($func, array $args = []) {
		if ($this->lazyLoad() == null) return '';
		return call_user_func_array([$this->lazyLoad(), $func], $args);
	}

	public function __get($name) {
		if ($this->lazyLoad()) return $this->lazyLoad()->$name;
        else return null;
	}

	public function __isset($name) {
		return isset($this->lazyLoad()->$name);
	}

	public function overwrite($parentObject, &$data) {
        $this->mapper[] = $data;

        if (!isset($parentObject->{$this->parentField}) || $parentObject->{$this->parentField} != $data->{$this->localField}) {
			$parentObject->{$this->parentField} = $data->{$this->localField};
			//Trigger an update of the parent object
			return true;
		}
	}

	public function getFilter($object) {
		return [$this->parentField => $object->{$this->localField}];
	}
}
