<?php
// C
namespace Maphper\Lib;
class Entity {
	//Creates an object of type $className and populates it with data

	private $className;
	private $parent;

	public function __construct(\Maphper\Maphper $parent, $className = null) {
		$this->className = $className;
	}
	
	public function create($data = [], $relations = [], $siblings = []) {
		$obj = (is_callable($this->className)) ? call_user_func($this->className) : new $this->className;
		$writer = new VisibilityOverride($obj);
		$writer->write($data);
		return $this->wrap($relations, $obj, $siblings);
	}

	public function wrap($relations, $object, $siblings = []) {
		//see if any relations need overwriting
		foreach ($relations as $name => $relation) {
			if (isset($object->$name) && !($object->$name instanceof \Maphper\Relation) ) {
				//After overwriting the relation, does the parent object ($object) need overwriting as well?
				if ($relation->overwrite($object, $object->$name)) $this->parent[] = $object;
			}

			$object->$name = $relation->getData($object, $siblings);
		}
		return $object;
	}
}