<?php
namespace Maphper\DataSource;
class Database implements \Maphper\DataSource {
	const EDIT_STRUCTURE = 1;
	const EDIT_INDEX = 2;
	const EDIT_OPTIMISE = 4;

	private $table;
    private $options;
	private $cache = [];
	private $primaryKey;
	private $fields = '*';
	private $defaultSort;
	private $adapter;
	private $crudBuilder;
    private $selectBuilder;
    private $whereBuilder;
    private $databaseModify;
    private $databaseSelect;

	public function __construct($db, $table, $primaryKey = 'id', array $options = []) {
		$this->options = new DatabaseOptions($db, $options);
		$this->adapter = $this->options->getAdapter();

		$this->table = $table;
		$this->primaryKey = is_array($primaryKey) ? $primaryKey : [$primaryKey];

		$this->crudBuilder = new \Maphper\Lib\CrudBuilder();
		$this->selectBuilder = new \Maphper\Lib\SelectBuilder();
        $this->whereBuilder = new \Maphper\Lib\Sql\WhereBuilder();

		$this->fields = implode(',', array_map([$this->adapter, 'quote'], (array) $this->options->read('fields')));

		$defaultSort = $this->options->read('defaultSort') !== false ? $this->options->read('defaultSort')  : implode(', ', $this->primaryKey);
        $this->databaseModify = new DatabaseModify($this->adapter, $this->options->getEditMode(), $this->table);
        $this->databaseSelect = new DatabaseSelect($this->adapter, $this->databaseModify, $defaultSort, $this->table);

		$this->databaseModify->optimizeColumns();
	}

	public function getPrimaryKey() {
		return $this->primaryKey;
	}

	public function deleteById($id) {
		$this->adapter->query($this->crudBuilder->delete($this->table, [$this->primaryKey[0] . ' = :id'], [':id' => $id], 1));
		unset($this->cache[$id]);
	}

	public function findById($id) {
		if (!isset($this->cache[$id])) {
			try {
				$result = $this->selectQuery($this->selectBuilder->select($this->table, $this->getPrimaryKey()[0] . ' = :id', [':id' => $id], ['limit' => 1]));
			}
			catch (\Exception $e) {
			}

			if (isset($result[0])) 	$this->cache[$id] = $result[0];
			else return null;
		}
		return $this->cache[$id];
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

	public function findByField(array $fields, $options = []) {
        return $this->databaseSelect->findByField($fields, $options);
	}

	public function deleteByField(array $fields, array $options = []) {
		$query = $this->whereBuilder->createSql($fields);
		$this->adapter->query($this->crudBuilder->delete($this->table, $query['sql'], $query['args'], $options['limit'], null, $options['order']));
		$this->databaseModify->addIndex(array_keys($query['args']));

		//Clear the cache
		$this->cache = [];
		$this->databaseSelect->clearResultCache();
	}

    private function getIfNew($data) {
        $new = false;
        foreach ($this->primaryKey as $k) {
            if (empty($data->$k)) {
                $data->$k = null;
                $new = true;
            }
        }
        return $new;
    }

	public function save($data, $tryagain = true) {
        $new = $this->getIfNew($data);

		try {
            $result = $this->insert($this->table, $this->primaryKey, $data);

			//If there was an error but PDO is silent, trigger the catch block anyway
			if ($result->errorCode() !== '00000') throw new \Exception('Could not insert into ' . $this->table);
		}
		catch (\Exception $e) {
			if (!$this->getTryAgain($tryagain)) throw $e;

			$this->adapter->alterDatabase($this->table, $this->primaryKey, $data);
			$this->save($data, false);
		}

		$this->updatePK($data, $new);
		//Something has changed, clear any cached results as they may now be incorrect
		$this->databaseSelect->clearResultCache();
		$this->updateCache($data);
	}

    private function getTryAgain($tryagain) {
        return $tryagain && self::EDIT_STRUCTURE & $this->alterDb;
    }

    private function updatePK($data, $new) {
        if ($new && count($this->primaryKey) == 1) $data->{$this->primaryKey[0]} = $this->adapter->lastInsertId();
    }

    private function checkIfUpdateWorked($data) {
        $updateWhere = $this->whereBuilder->createSql($data);
        $matched = $this->findByField($updateWhere['args']);
        if (count($matched) == 0) throw new \InvalidArgumentException('Record inserted into table ' . $this->table . ' fails table constraints');
    }

    private function updateCache($data) {
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

 		if ($error || $result->errorCode() !== '00000') {
            $result = $this->tryUpdate($table, $primaryKey, $data);
        }

		return $result;
	}

    private function tryUpdate($table, array $primaryKey, $data) {
        $result = $this->adapter->query($this->crudBuilder->update($table, $primaryKey, $data));
        if ($result->rowCount() === 0) $this->checkIfUpdateWorked($data);

        return $result;
    }

    private function selectQuery(\Maphper\Lib\Query $query) {
        return $this->adapter->query($query)->fetchAll(\PDO::FETCH_OBJ);
    }
}
