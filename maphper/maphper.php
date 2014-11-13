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
	public $settings = ['filter' => [], 'sort' => null, 'limit' => null, 'offset' => null];	
	private $array = [];
	private $iterator = 0;

	public function __construct(\Maphper\DataSource $dataSource, array $settings = null, array $relations = []) {
		$this->dataSource = $dataSource;
		if ($settings) $this->settings = $settings;
		if ($relations) $this->relations = $relations;
	}

	public function addRelation($name, \Maphper\Relation $relation) {
		$this->relations[$name] = $relation;
	}

	public function count($group = null) {
		return $this->dataSource->findAggregate('count', $group == null ? $this->dataSource->getPrimaryKey() : $group, $group, $this->settings['filter']);
	}

	public function current() {
		return $this->attachRelations($this->array[$this->iterator]);
	}
		
	public function key() {
		$pk = end($this->dataSource->getPrimaryKey());
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
		return isset($this->array[$n]) ? $this->attachRelations($this->array[$n]) : null;
	}
	
	public function offsetSet($offset, $value) {
		$pk = $this->dataSource->getPrimaryKey();
		if ($offset !== null) $value->{$pk[0]} = $offset;
		$this->dataSource->save($value);
		$this->attachRelations($value);
	}
	
	public function offsetExists($offset) {
		return (bool) $this->dataSource->findById($offset);
	}
	
	public function offsetUnset($id) {
		$this->dataSource->deleteById($id);
	}

	public function offsetGet($offset) {
		if (empty($offset)) {
			$obj = $this->dataSource->createNew();
			foreach ($this->dataSource->getPrimaryKey() as $k) $obj->$k = null;
			return $this->attachRelations($obj);
		}
		else {
			if (count($this->dataSource->getPrimaryKey()) > 1) return new MultiPk($this, $offset, $this->dataSource->getPrimaryKey());
			return $this->attachRelations($this->dataSource->findById($offset));
		}
	}
	
	private function attachRelations($object) {
		if (!is_object($object)) return $object;
		if (is_array($object)) {
			foreach ($object as &$o) $this->attachRelations($o);
			return $object;
		}
		else {				
			if (isset($object->__maphperRelationsAttached)) return $object;
			
			foreach ($this->relations as $name => $relation) {
				if (!isset($object->{$relation->field})) $object->{$relation->field} = null;
				if ($relation->relationType == \Maphper\Relation::ONE) $object->$name = new \Maphper\Relation\One($relation, $object->{$relation->field});
				else if ($relation->relationType == \Maphper\Relation::MANY) {	
					$object->$name = $relation->mapper->filter([$relation->parentField => $object->{$relation->field}]);
				}
			}				
			$object->__maphperRelationsAttached = $this;
			return $object;
		}
	}

	public function filter($filter) {
		$maphper = new Maphper($this->dataSource, $this->settings, $this->relations);
		$maphper->settings['filter'] = $filter + $this->settings['filter'];
		return $maphper;
	}
	
	public function sort($sort) {
		$maphper = new Maphper($this->dataSource, $this->settings, $this->relations);
		$maphper->settings['sort'] = $sort;
		return $maphper;		
	}
		
	public function limit($limit) {
		$maphper = new Maphper($this->dataSource, $this->settings, $this->relations);
		$maphper->settings['limit'] = $limit;
		return $maphper;
	}	
	
	public function offset($offset) {
		$maphper = new Maphper($this->dataSource, $this->settings, $this->relations);
		$maphper->settings['offset'] = $offset;
		return $maphper;
	}	
	
	public function findAggregate($function, $field, $group = null, array $criteria = []) {
		return $this->dataSource->findAggregate($function, $field, $group, $this->settings['filter']);
	}
	
	public function delete() {
		$this->array = [];
		$this->dataSource->deleteByField($this->settings['filter'], ['order' => $this->settings['sort'], 'limit' => $this->settings['limit'], 'offset' => $this->settings['offset']]);
	}
}