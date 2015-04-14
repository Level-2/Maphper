<?php 
namespace Maphper;
class Maphper implements \Countable, \ArrayAccess, \Iterator {
	const FIND_EXACT 	= 	0x1;
	const FIND_LIKE 	= 	0x2;
	const FIND_STARTS 	= 	0x4;
	const FIND_ENDS 	= 	0x8;
	const FIND_BIT 		= 	0x10;
	const FIND_GREATER 	= 	0x20;
	const FIND_LESS 	=	0x40;
	const FIND_EXPRESSION = 0x80;
	const FIND_AND 		= 0x100;
	const FIND_OR 		= 0x200;
	const FIND_NOT 		= 0x400;
	const FIND_BETWEEN	= 0x800;
	const FIND_NOCASE	= 0x1000;
	
	private $dataSource;
	private $relations = [];	
	private $settings = ['filter' => [], 'sort' => null, 'limit' => null, 'offset' => null, 'resultClass' => '\\stdClass'];	
	private $array = [];
	private $iterator = 0;

	public function __construct(DataSource $dataSource, array $settings = null, array $relations = []) {
		$this->dataSource = $dataSource;
		if ($settings) $this->settings = array_replace($this->settings, $settings);
		if ($relations) $this->relations = $relations;		
	}
	
	public function addRelation($name, Relation $relation) {
		$this->relations[$name] = $relation;
	}

	public function getRelations() {
		return $this->relations;
	}
	public function count($group = null) {
		return $this->dataSource->findAggregate('count', $group == null ? $this->dataSource->getPrimaryKey() : $group, $group, $this->settings['filter']);
	}

	public function current() {
		return $this->wrap($this->array[$this->iterator]);
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
	
	public function offsetSet($offset, $value) {
		foreach ($this->relations as $name => $relation) {
			//If a relation has been overridden, run the overwrite	
			if (isset($value->$name) &&	!($value->$name instanceof Relation\One)) $relation->overwrite($value, $value->$name);			
		}

		foreach ($this->settings['filter'] as $key => $filterValue) {
			//When saving to a mapper with filters, write the filters back into the object being stored
			if (empty($value->$key) && !is_array($filterValue)) $value->$key = $filterValue;
		}
		
		$value = $this->wrap($value, true);
		
		$pk = $this->dataSource->getPrimaryKey();
		if ($offset !== null) $value->{$pk[0]} = $offset;
		$this->dataSource->save($value);		
	}
	
	public function offsetExists($offset) {
		return (bool) $this->dataSource->findById($offset);
	}
	
	public function offsetUnset($id) {
		$this->dataSource->deleteById($id);
	}

	public function offsetGet($offset) {
		if (isset($offset)) {
			if (count($this->dataSource->getPrimaryKey()) > 1) return new MultiPk($this, $offset, $this->dataSource->getPrimaryKey());			
			return $this->wrap($this->dataSource->findById($offset));
		}
		else {
			$obj = $this->createNew();
			foreach ($this->dataSource->getPrimaryKey() as $k) $obj->$k = null;
			return $this->wrap($obj);
		}
	}

	public function createNew() {
		return (is_callable($this->settings['resultClass'])) ? call_user_func($this->settings['resultClass']) : new $this->settings['resultClass'];
	}
	
	private function wrap($object, $updateExisting = false) {		
		if (is_array($object)) {
			foreach ($object as &$o) $this->wrap($o);
			return $object;
		}
		else if (!is_object($object)) return $object;
		else {
			if (isset($object->__maphperRelationsAttached)) return $object;			
			$writeClosure = function($field, $value) {	$this->$field = $value;	};
			
			$new = $updateExisting ? $object : $this->createNew();
			$write = $writeClosure->bindTo($new, $new);
			foreach ($object as $key => $value) $write($key, $this->dataSource->processDates($value));			
			foreach ($this->relations as $name => $relation) $new->$name = $relation->getData($new); 

			$new->__maphperRelationsAttached = $this;
			return $new;
		}
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
	
	public function findAggregate($function, $field, $group = null, array $criteria = []) {
		return $this->dataSource->findAggregate($function, $field, $group, $this->settings['filter']);
	}
	
	public function delete() {
		$this->array = [];
		$this->dataSource->deleteByField($this->settings['filter'], ['order' => $this->settings['sort'], 'limit' => $this->settings['limit'], 'offset' => $this->settings['offset']]);
	}
}