<?php
namespace Maphper\DataSource;

class DatabaseSelect {
    private $resultCache = [];
    private $idCache = [];
    private $selectBuilder;
    private $whereBuilder;
    private $adapter;
    private $databaseModify;
    private $defaultSort;
    private $table;

    public function __construct(DatabaseAdapter $adapter, DatabaseModify $databaseModify, $table, $defaultSort) {
        $this->adapter = $adapter;
        $this->databaseModify = $databaseModify;
        $this->selectBuilder = new \Maphper\Lib\SelectBuilder();
        $this->whereBuilder = new \Maphper\Lib\Sql\WhereBuilder();
        $this->defaultSort = $defaultSort;
        $this->table = $table;
    }

    public function findById($id, $pk) {
		if (!isset($this->idCache[$id])) {
			try {
				$result = $this->selectQuery($this->selectBuilder->select($this->table, $pk . ' = :id', [':id' => $id], ['limit' => 1]));
			}
			catch (\Exception $e) {
                // Don't issue an error if it cannot be found since we return null
			}

			if (isset($result[0])) 	$this->idCache[$id] = $result[0];
			else return null;
		}
		return $this->idCache[$id];
	}

    public function findByField(array $fields, $options = []) {
		$cacheId = md5(serialize(func_get_args()));
		if (!isset($this->resultCache[$cacheId])) {
			$query = $this->whereBuilder->createSql($fields);

			if (!isset($options['order'])) $options['order'] = $this->defaultSort;

			try {
				$this->resultCache[$cacheId] = $this->selectQuery($this->selectBuilder->select($this->table, $query['sql'], $query['args'], $options));
				$this->databaseModify->addIndex(array_keys($query['args']));
				$this->databaseModify->addIndex(explode(',', $options['order']));
			}
			catch (\Exception $e) {
				$this->errors[] = $e;
				$this->resultCache[$cacheId] = [];
			}
		}
		return $this->resultCache[$cacheId];
	}

    public function findAggregate($function, $field, $group = null, array $criteria = [], array $options = []) {
		//Cannot count/sum/max multiple fields, pick the first one. This should only come into play when trying to count() a mapper with multiple primary keys
		if (is_array($field)) $field = $field[0];
		$query = $this->whereBuilder->createSql($criteria);

		try {
			$this->databaseModify->addIndex(array_keys($query['args']));
			$this->databaseModify->addIndex(explode(',', $group));
			$result = $this->selectQuery($this->selectBuilder->aggregate($this->table, $function, $field, $query['sql'], $query['args'], $group));

			return $this->determineAggregateResult($result, $group, $field);
		}
		catch (\Exception $e) {
			return $group ? [] : 0;
		}
	}

    private function determineAggregateResult($result, $group, $field) {
        if ($group != null) {
            $ret = [];
            foreach ($result as $res) $ret[$res->$field] = $res->val;
            return $ret;
        }
        else if (isset($result[0])) return $result[0]->val;
        else return 0;
    }

    private function selectQuery(\Maphper\Lib\Query $query) {
        return $this->adapter->query($query)->fetchAll(\PDO::FETCH_OBJ);
    }

    public function clearResultCache() {
        $this->resultCache = [];
    }

    public function clearIDCache() {
        $this->idCache = [];
    }

    public function updateCache($data, $pkValue) {
		if (isset($this->cache[$pkValue])) $this->cache[$pkValue] = (object) array_merge((array)$this->cache[$pkValue], (array)$data);
		else $this->cache[$pkValue] = $data;
    }

    public function deleteIDFromCache($id) {
        unset($this->idCache[$id]);
    }
}
