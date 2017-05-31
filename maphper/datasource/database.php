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
	private $crudBuilder;

	public function __construct($db, $table, $primaryKey = 'id', array $options = []) {
		if ($db instanceof \PDO) $this->adapter = $this->getAdapter($db);
		else $this->adapter = $db;

		$this->table = $table;
		$this->primaryKey = is_array($primaryKey) ? $primaryKey : [$primaryKey];

		$this->crudBuilder = new \Maphper\Lib\CrudBuilder();
		$this->selectBuilder = new \Maphper\Lib\SelectBuilder();

		if (isset($options['fields'])) $this->fields = implode(',', array_map([$this->adapter, 'quote'], $options['fields']));
		$this->defaultSort = (isset($options['defaultSort'])) ? $options['defaultSort'] : implode(', ', $this->primaryKey);
		if (isset($options['editmode'])) $this->alterDb = $options['editmode'] == true ? self::EDIT_STRUCTURE | self::EDIT_INDEX | self::EDIT_OPTIMISE : $options['editmode'];
		if (self::EDIT_OPTIMISE & $this->alterDb && rand(0,500) == 1) $this->adapter->optimiseColumns($table);
	}

	private function getAdapter(\PDO $pdo) {
        $adapter = '\\Maphper\\DataSource\\' . ucfirst($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)) . 'Adapter';
		return new $adapter($pdo);
	}

	public function getPrimaryKey() {
		return $this->primaryKey;
	}

	public function deleteById($id) {
		$this->adapter->query($this->crudBuilder->delete($this->table, [$this->primaryKey[0] . ' = :id'], [':id' => $id], 1));
		unset($this->cache[$id]);
	}

	public function getErrors() {
		return $this->errors;
	}

	public function findById($id) {
		if (!isset($this->cache[$id])) {
			try {
				$result = $this->adapter->query($this->selectBuilder->select($this->table, [$this->getPrimaryKey()[0] . ' = :id'], [':id' => $id], ['limit' => 1]));
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
		$query = $this->selectBuilder->createSql($criteria, \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND);

		try {
			$this->addIndex(array_keys($query['args']));
			$this->addIndex(explode(',', $group));
			$result = $this->adapter->query($this->selectBuilder->aggregate($this->table, $function, $field, $query['sql'], $query['args'], $group));

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
			$query = $this->selectBuilder->createSql($fields, \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND);

			if (!isset($options['order'])) $options['order'] = $this->defaultSort;

			$query['sql'] = array_filter($query['sql']);

			try {
				$this->resultCache[$cacheId] = $this->adapter->query($this->selectBuilder->select($this->table, $query['sql'], $query['args'], $options));
				$this->addIndex(array_keys($query['args']));
				$this->addIndex(explode(',', $options['order']));
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

		$query = $this->selectBuilder->createSql($fields, $mode);
        $query['sql'] = array_filter($query['sql']);
		$this->adapter->query($this->crudBuilder->delete($this->table, $query['sql'], $query['args'], $limit));
		$this->addIndex(array_keys($query['args']));

		//Clear the cache
		$this->cache = [];
		$this->resultCache = [];
	}

	public function save($data, $tryagain = true) {
		$new = false;
		foreach ($this->primaryKey as $k) {
			if (empty($data->$k)) {
				$data->$k = null;
				$new = true;
			}
		}

		try {
			$result = $this->insert($this->table, $this->primaryKey, $data);
			//If there was an error but PDO is silent, trigger the catch block anyway
			if ($result->errorCode() !== "00000") throw new \Exception('Could not insert into ' . $this->table);
		}
		catch (\Exception $e) {
			if ($tryagain && self::EDIT_STRUCTURE & $this->alterDb) {
				$this->adapter->alterDatabase($this->table, $this->primaryKey, $data);
				$this->save($data, false);
			}
			else throw $e;
		}
		//TODO: This will error if the primary key is a private field
		if ($new && count($this->primaryKey) == 1) $data->{$this->primaryKey[0]} = $this->adapter->lastInsertId();
		//Something has changed, clear any cached results as they may now be incorrect
		$this->resultCache = [];
		$pkValue = $data->{$this->primaryKey[0]};
		if (isset($this->cache[$pkValue])) $this->cache[$pkValue] = (object) array_merge((array)$this->cache[$pkValue], (array)$data);
		else $this->cache[$pkValue] = $data;
	}

	private function insert($table, array $primaryKey, $data) {
		$error = 0;
		try {
			$result = $this->adapter->query($this->crudBuilder->insert($table, $data));
		}
		catch (\Exception $e) {
			$error = 1;
		}

 		if ($error || $result->errorCode() !== "00000") $result = $this->adapter->query($this->crudBuilder->update($table, $primaryKey, $data));
		return $result;
	}
}
