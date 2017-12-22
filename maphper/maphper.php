<?php
namespace Maphper;
class Maphper implements \Countable, \ArrayAccess, \IteratorAggregate {
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
	private $iterator = 0;

	public function __construct(DataSource $dataSource, array $settings = [], array $relations = []) {
		$this->dataSource = $dataSource;
		$this->settings = array_replace($this->settings, $settings);
		$this->relations = $relations;
		if (!isset($this->settings['writeClosure'])) {
			$this->settings['writeClosure'] = function ($field, $value) {	$this->$field = $value;	};
		} elseif (! $this->settings['writeClosure'] instanceof \Closure) {
			throw new \InvalidArgumentException('The mapper writeClosure must be an anonymous function');
		}
	}

	public function addRelation($name, Relation $relation) {
		$this->relations[$name] = $relation;
	}

	public function count($group = null) {
		$this->wrapFilter();
		return $this->dataSource->findAggregate('count', $group == null ? $this->dataSource->getPrimaryKey() : $group, $group, $this->settings['filter']);
	}

	//Allow filter(['user' => $user]) where $user is an object instead of
	//filter(['userId' => $user->id])
	private function wrapFilter() {
		foreach ($this->settings['filter'] as $name => $value) {
			if (isset($this->relations[$name])) {
				$filter = $this->relations[$name]->getFilter($value);
				$this->settings['filter'] = array_merge($this->settings['filter'], $filter);
				unset($this->settings['filter'][$name]);
			}
		}
	}

	private function getResults() {
		foreach ($this->settings['filter'] as $name => &$filter) {
			if (isset($this->relations[$name])) {
				$this->relations[$name]->overwrite($filter, $filter[$name]);
			}
		}
		$results = $this->dataSource->findByField($this->settings['filter'],
			['order' => $this->settings['sort'], 'limit' => $this->settings['limit'], 'offset' => $this->settings['offset'] ]);

		$siblings = new \ArrayObject();

		$results = array_map([$this, 'createNew'], $results);
		array_walk($results, [$this, 'wrap'], $siblings);

		return $results;
	}

	public function item($n) {
		$array = $this->getResults();
		return isset($array[$n]) ? $array[$n] : null;
	}

	public function getIterator() {
		return new Iterator($this->getResults(), $this->dataSource->getPrimaryKey());
	}

	private function processFilters($value) {
		//When saving to a mapper with filters, write the filters back into the object being stored
		foreach ($this->settings['filter'] as $key => $filterValue) {
			if (empty($value->$key) && !is_array($filterValue)) $value->$key = $filterValue;
		}
		return $value;
	}

	public function offsetSet($offset, $valueObj) {
		if ($valueObj instanceof \Maphper\Relation) throw new \Exception();

		//Extract private properties from the object
		$propertyReader = new \Maphper\Lib\VisibilityOverride();
		$value = $propertyReader->getProperties($valueObj);

		$value = $this->processFilters($value);
		$pk = $this->dataSource->getPrimaryKey();
		if ($offset !== null) $value->{$pk[0]} = $offset;
		$valueCopy = clone $value;
		$value = $this->wrap($value);
		$this->dataSource->save($value);
		$value = $this->wrap((object) array_merge((array)$value, (array)$valueCopy));
        $this->createNew($value, $valueObj);
	}

	public function offsetExists($offset) {
		if (count($this->dataSource->getPrimaryKey()) > 1) return new MultiPk($this, $offset, $this->dataSource->getPrimaryKey());
        if (!empty($this->settings['filter'])) {
            $data = $this->dataSource->findByField(array_merge($this->settings['filter'], [$this->dataSource->getPrimaryKey()[0] => $offset]));
            return isset($data[0]);
        }
		return (bool) $this->dataSource->findById($offset);
	}

	public function offsetUnset($id) {
		$this->dataSource->deleteById($id);
	}

	public function offsetGet($offset) {
		if (count($this->dataSource->getPrimaryKey()) > 1) return new MultiPk($this, $offset, $this->dataSource->getPrimaryKey());
        if (!empty($this->settings['filter'])) {
            $data = $this->dataSource->findByField(array_merge($this->settings['filter'], [$this->dataSource->getPrimaryKey()[0] => $offset]));
            return $this->wrap($this->createNew(isset($data[0]) ? $data[0] : null));
        }
		return $this->wrap($this->createNew($this->dataSource->findById($offset)));
	}

	private function createNew($data = [], $obj = null) {
		if (!$obj) $obj = (is_callable($this->settings['resultClass'])) ? call_user_func($this->settings['resultClass']) : new $this->settings['resultClass'];
		if (!($obj instanceof \stdclass)) $write = $this->settings['writeClosure']->bindTo($obj, $obj);
		if ($data != null) {
			foreach ($data as $key => $value) {
				if ($obj instanceof \stdclass) $obj->$key = $this->processDates($value);
				else $write($key, $this->processDates($value));
			}
		}
		return $obj;
	}

	private function processDates($obj) {
		$injector = new Lib\DateInjector;
		return $injector->replaceDates($obj);
	}

	private function wrap($object, $key = null, $siblings = null) {
		//see if any relations need overwriting
		foreach ($this->relations as $name => $relation) {
			if (isset($object->$name) && !($object->$name instanceof \Maphper\Relation) ) {
				//After overwriting the relation, does the parent object ($object) need overwriting as well?
				if ($relation->overwrite($object, $object->$name)) $this[] = $object;
			}

			$object->$name = $relation->getData($object, $siblings);
			//var_dump($siblings);
		}
		return $object;
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
		$this->dataSource->deleteByField($this->settings['filter'], ['order' => $this->settings['sort'], 'limit' => $this->settings['limit'], 'offset' => $this->settings['offset']]);
	}
}
