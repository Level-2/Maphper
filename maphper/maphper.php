<?php 
namespace Maphper;
class Maphper implements \Countable, \ArrayAccess, \Iterator {
	const FIND_EXACT 		= 	0x1;
	const FIND_LIKE 		= 	0x2;
	const FIND_STARTS 		= 	0x4;
	const FIND_ENDS 		= 	0x8;
	const FIND_BIT 			= 	0x10;
	const FIND_GREATER 		= 	0x20;
	const FIND_LESS 		=	0x40;
	const FIND_EXPRESSION 	= 	0x80;
	const FIND_AND 			= 	0x100;
	const FIND_OR 			= 	0x200;
	const FIND_NOT 			= 	0x400;
	const FIND_BETWEEN		= 	0x800;
	const FIND_NOCASE		= 	0x1000;
	
	private $dataSource;
	private $relations = [];	
	private $settings = ['filter' => [], 'sort' => null, 'limit' => null, 'offset' => null, 'resultClass' => '\\stdClass'];	
	private $array = [];
	private $iterator = 0;

	public function __construct(DataSource $dataSource, array $settings = [], array $relations = []) {
		$this->dataSource = $dataSource;
		$this->settings = array_replace($this->settings, $settings);
		$this->relations = $relations;		
	}
	
	public function addRelation($name, Relation $relation) {
		$this->relations[$name] = $relation;
	}

	public function count($group = null) {
		return $this->dataSource->findAggregate('count', $group == null ? $this->dataSource->getPrimaryKey() : $group, $group, $this->settings['filter']);
	}

	public function current() {
		return $this->wrap($this->createNew($this->array[$this->iterator]));
	}
		
	public function key() {
		$pk = $this->dataSource->getPrimaryKey();
		$pk = end($pk);
		return $this->array[$this->iterator]->$pk;
	}
	
	public function next() {
		++$this->iterator;
	}
	
	public function valid() {
		return isset($this->array[$this->iterator]);
	}	

	public function rewind() {
		$this->iterator = 0;
		if (empty($this->array)) $this->array = $this->dataSource->findByField($this->settings['filter'], ['order' => $this->settings['sort'], 'limit' => $this->settings['limit'], 'offset' => $this->settings['offset'] ]);
	}
	
	public function item($n) {
		$this->rewind();
		return isset($this->array[$n]) ? $this->wrap($this->array[$n]) : null;
	}
	
	private function processFilters($value) {
		//When saving to a mapper with filters, write the filters back into the object being stored
		foreach ($this->settings['filter'] as $key => $filterValue) {			
			if (empty($value->$key) && !is_array($filterValue)) $value->$key = $filterValue;
		}
		return $value;
	}

	public function offsetSet($offset, $value) {	
		$value = $this->processFilters($value);
		$pk = $this->dataSource->getPrimaryKey();
		if ($offset !== null) $value->{$pk[0]} = $offset;
		$this->dataSource->save($value);
		$value = $this->wrap($value);
	}
	
	public function offsetExists($offset) {
		return (bool) $this->dataSource->findById($offset);
	}
	
	public function offsetUnset($id) {
		$this->dataSource->deleteById($id);
	}

	public function offsetGet($offset) {
		if (count($this->dataSource->getPrimaryKey()) > 1) return new MultiPk($this, $offset, $this->dataSource->getPrimaryKey());			
		return $this->wrap($this->createNew($this->dataSource->findById($offset)));
	}

	private function createNew($data = []) {
		$obj = (is_callable($this->settings['resultClass'])) ? call_user_func($this->settings['resultClass']) : new $this->settings['resultClass'];
		$writeClosure = function($field, $value) {	$this->$field = $value;	};			
		$write = $writeClosure->bindTo($obj, $obj);
		if ($data != null) {
			foreach ($data as $key => $value) $write($key, $this->dataSource->processDates($value));			
		}
		return $obj;		
	}
	
	private function wrap($object) {
		//see if any relations need overwriting
		foreach ($this->relations as $name => $relation) {
			if (isset($object->$name)) {					
				//After overwriting the relation, does the parent object ($object) need overwriting as well?
				if ($relation->overwrite($object, $object->$name)) $this[] = $object;
			}
			$object->$name = $relation->getData($object); 
		}			
		return $object;		
	}

	public function getErrors() {
		return $this->dataSource->getErrors();
	}
	
	public function __call($method, $args) {
		if (array_key_exists($method, $this->settings)) {
			$maphper = new Maphper($this->dataSource, $this->settings, $this->relations);
			if (is_array($maphper->settings[$method])) $maphper->settings[$method] = $args[0] + $maphper->settings[$method];
			else $maphper->settings[$method] = $args[0];
			return $maphper;
		}
		else throw new \Exception('Method Maphper::' . $method . ' does not exist');
	}
	
	public function findAggregate($function, $field, $group = null) {
		return $this->dataSource->findAggregate($function, $field, $group, $this->settings['filter']);
	}
	
	public function delete() {
		$this->array = [];
		$this->dataSource->deleteByField($this->settings['filter'], ['order' => $this->settings['sort'], 'limit' => $this->settings['limit'], 'offset' => $this->settings['offset']]);
	}
}