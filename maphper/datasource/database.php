<?php 
namespace Maphper\DataSource;
class Database implements \Maphper\DataSource {
	const EDIT_STRUCTURE = 1;
	const EDIT_INDEX = 2;
	const EDIT_OPTIMISE = 4;
	
	private $table;
	private $cache = [];
	private $primaryKey;
	private $fields = '*';
	private $defaultSort;
	private $resultCache = [];	
	private $errors = [];
	private $alterDb = false;	
	private $adapter;
	private $processCache;
	private $queryBuilder;
	
	public function __construct($db, $table, $primaryKey = 'id', array $options = []) {
		if ($db instanceof \PDO) $this->adapter = $this->getAdapter($db);
		else $this->adapter = $db;
		
		$this->queryBuilder = isset($options['queryBuilder']) ? $options['queryBuilder'] : new \Maphper\DataSource\Database\QueryBuilder;
		$this->table = $table;
		$this->primaryKey = is_array($primaryKey) ? $primaryKey : [$primaryKey];

		if (isset($options['fields'])) $this->fields = implode(',', array_map([$this->adapter, 'quote'], $options['fields']));
		$this->defaultSort = (isset($options['defaultSort'])) ? $options['defaultSort'] : implode(', ', $this->primaryKey);
		if (isset($options['editmode'])) $this->alterDb = $options['editmode'] == true ? self::EDIT_STRUCTURE | self::EDIT_INDEX | self::EDIT_OPTIMISE : $options['editmode'];
		if (self::EDIT_OPTIMISE & $this->alterDb && rand(0,500) == 1) $this->adapter->optimiseColumns($table);
	}

	private function getAdapter(\PDO $pdo) {
		$adapter = '\\Maphper\\DataSource\\' . $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) . 'Adapter';
		return new $adapter($pdo);
	}
	
	public function getPrimaryKey() {
		return $this->primaryKey;
	}	
	
	public function deleteById($id) {
		$this->adapter->query($this->queryBuilder->delete($this->table, [$this->primaryKey[0] . ' = :id'], [':id' => $id], 1));
		unset($this->cache[$id]);
	}
		
	public function processDates($obj, $reset = true) {
		//prevent infinite recursion
		if ($reset) $this->processCache = new \SplObjectStorage();
		if (is_object($obj) && $this->processCache->contains($obj)) return $obj;
		else if (is_object($obj)) $this->processCache->attach($obj, true);

		if (is_array($obj) || (is_object($obj) && (!$obj instanceof \Iterator))) foreach ($obj as &$o) $o = $this->processDates($o, false);
		if (is_string($obj) && is_numeric($obj[0]) && strlen($obj) <= 20) {
			try {
				$date = new \DateTime($obj);
				if ($date->format('Y-m-d H:i:s') == substr($obj, 0, 20)) $obj = $date;
			}
			catch (\Exception $e) {	//Doesn't need to do anything as the try/catch is working out whether $obj is a date
			}
		}
		return $obj;
	}
	
	public function getErrors() {
		return $this->errors;
	}	
		
	public function findById($id) {
		if (!isset($this->cache[$id])) {
			try {
				$result = $this->adapter->query($this->queryBuilder->select($this->table, [$this->getPrimaryKey()[0] . ' = :id'], [':id' => $id], null, 1));
			}
			catch (\Exception $e) {
				$this->errors[] = $e;
			}
				
			if (isset($result[0])) 	$this->cache[$id] = $result[0];
			else return null;
		}
		return $this->cache[$id];
	}
	
	public function findAggregate($function, $field, $group = null, array $criteria = [], array $options = []) {
		//Cannot count/sum/max multiple fields, pick the first one. This should only come into play when trying to count() a mapper with multiple primary keys
		if (is_array($field)) $field = $field[0];		
		$query = $this->queryBuilder->selectBuilder($criteria, \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND);

		try {
			$this->addIndex(array_keys($query['args']));
			if ($group) $this->addIndex(explode(',', $group));
			$result = $this->adapter->query($this->queryBuilder->aggregate($this->table, $function, $field, $query['sql'], $query['args'], $group));

			if (isset($result[0]) && $group == null) return $result[0]->val;
			else if ($group != null) {
				$ret = [];
				foreach ($result as $res) $ret[$res->$field] = $res->val;
				return $ret;
			}
			else return 0;
		}
		catch (\Exception $e) {
			$this->errors[] = $e;
			return $group ? [] : 0;
		}
	}
	
	private function addIndex($args) {
		if (self::EDIT_INDEX & $this->alterDb) $this->adapter->addIndex($this->table, $args);
	}

	public function findByField(array $fields, $options = []) {
		$cacheId = md5(serialize(func_get_args()));	
		if (!isset($this->resultCache[$cacheId])) {
			$query = $this->queryBuilder->selectBuilder($fields, \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND);
			$limit = (isset($options['limit'])) ? $options['limit'] : null;
			$offset = (isset($options['offset'])) ? $options['offset'] : '';	
			$order = (!isset($options['order'])) ? $this->defaultSort : $order = $options['order'];
			if ($query['sql'][0] == '') $query['sql'] = [];

			try {
				$this->resultCache[$cacheId] = $this->adapter->query($this->queryBuilder->select($this->table, $query['sql'], $query['args'], $order, $limit, $offset));
				$this->addIndex(array_keys($query['args']));
				$this->addIndex(explode(',', $order));
			}
			catch (\Exception $e) {
				$this->errors[] = $e;
				$this->resultCache[$cacheId] = [];
			}
		}
		return $this->resultCache[$cacheId];
	}
	
	public function deleteByField(array $fields, array $options = [], $mode = null) {
		if ($mode == null) $mode = \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND;
		if (isset($options['limit']) != null) $limit = ' LIMIT ' . $options['limit'];
		else $limit = '';

		$query = $this->queryBuilder->selectBuilder($fields, $mode);
		$this->adapter->query($this->queryBuilder->delete($this->table, $query['sql'], $query['args'], $limit));
		$this->addIndex(array_keys($query['args']));

		//Clear the cache
		$this->cache = [];
		$this->resultCache = [];
	}
	
	public function save($data, $tryagain = true) {
		$new = false;
		foreach ($this->primaryKey as $k) {
			if (!isset($data->$k) || $data->$k == '') {
				$data->$k = null;
				$new = true;
			}
		}		
		//Extract private properties from the object
		$readClosure = function() {
			$data = new \stdClass;
			foreach ($this as $k => $v)	{
				if (is_scalar($v) || is_null($v) || (is_object($v) && $v instanceof \DateTime))	$data->$k = $v;
			}
			return $data;
		};
		$read = $readClosure->bindTo($data, $data);
		$writeData = $read();	

		try {
			$result = $this->insert($this->table, $this->primaryKey, $writeData);
			//If there was an error but PDO is silent, trigger the catch block anyway
			if ($result->errorCode() > 0) throw new \Exception('Could not insert into ' . $this->table);
		}
		catch (\Exception $e) {
			if ($tryagain && self::EDIT_STRUCTURE & $this->alterDb) {
				$this->adapter->alterDatabase($this->table, $this->primaryKey, $writeData);
				$this->save($data, false);
			}
			else throw $e;
		}
		//TODO: This will error if the primary key is a private field
		if ($new && count($this->primaryKey) == 1) $data->{$this->primaryKey[0]} = $this->adapter->lastInsertId();
		//Something has changed, clear any cached results as they may now be incorrect
		$this->resultCache = [];
		$this->cache[$this->primaryKey[0]] = $data;
	}

	private function insert($table, array $primaryKey, $data) {
		$error = false;
		try {
			$result = $this->adapter->query($this->queryBuilder->insert($table, $data));	
		}
		catch (\Exception $e) {
			$error = true;
		}
				
 		if ($error === true || $result->errorCode() > 0) $result = $this->adapter->query($this->queryBuilder->update($table, $primaryKey, $data));
		return $result;
	}
}
