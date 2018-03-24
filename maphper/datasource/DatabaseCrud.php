<?php
namespace Maphper\DataSource;

class DatabaseCrud {
    private $crudBuilder;
    private $whereBuilder;
    private $adapter;
    private $databaseModify;
    private $databaseSelect;
    private $table;
    private $primaryKey;

    public function __construct(DatabaseAdapter $adapter, DatabaseModify $databaseModify, DatabaseSelect $databaseSelect, $table, $primaryKey) {
        $this->adapter = $adapter;
        $this->databaseModify = $databaseModify;
        $this->databaseSelect = $databaseSelect;
        $this->crudBuilder = new \Maphper\Lib\CrudBuilder();
        $this->whereBuilder = new \Maphper\Lib\Sql\WhereBuilder();
        $this->table = $table;
        $this->primaryKey = $primaryKey;
    }

    public function deleteById($id) {
        $this->adapter->query($this->crudBuilder->delete($this->table, $this->primaryKey[0] . ' = :id', [':id' => $id], 1));
        $this->databaseSelect->deleteIDFromCache($id);
	}

    public function deleteByField(array $fields, array $options = []) {
		$query = $this->whereBuilder->createSql($fields);
		$this->adapter->query($this->crudBuilder->delete($this->table, $query['sql'], $query['args'], $options['limit'], null, $options['order']));
		$this->databaseModify->addIndex(array_keys($query['args']));

		//Clear the cache
		$this->databaseSelect->clearIDCache();
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
			if (!$this->databaseModify->getTryInsertAgain($tryagain)) throw $e;

			$this->adapter->alterDatabase($this->table, $this->primaryKey, $data);
			$this->save($data, false);
		}

		$this->updatePK($data, $new);
		//Something has changed, clear any cached results as they may now be incorrect
		$this->databaseSelect->clearResultCache();
		$this->databaseSelect->updateCache($data, $data->{$this->primaryKey[0]});
	}

    private function updatePK($data, $new) {
        if ($new && count($this->primaryKey) == 1) $data->{$this->primaryKey[0]} = $this->adapter->lastInsertId();
    }

    private function checkIfUpdateWorked($data) {
        $updateWhere = $this->whereBuilder->createSql($data);
        $matched = $this->databaseSelect->findByField($updateWhere['args']);
        if (count($matched) == 0) throw new \InvalidArgumentException('Record inserted into table ' . $this->table . ' fails table constraints');
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
}
