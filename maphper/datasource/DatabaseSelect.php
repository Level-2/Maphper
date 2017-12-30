<?php
namespace Maphper\DataSource;

class DatabaseSelect {
    private $resultCache = [];
    private $selectBuilder;
    private $whereBuilder;
    private $adapter;
    private $databaseModify;
    private $defaultSort;
    private $table;

    public function __construct(DatabaseAdapter $adapter, DatabaseModify $databaseModify,  $defaultSort, $table) {
        $this->adapter = $adapter;
        $this->databaseModify = $databaseModify;
        $this->selectBuilder = new \Maphper\Lib\SelectBuilder();
        $this->whereBuilder = new \Maphper\Lib\Sql\WhereBuilder();
        $this->defaultSort = $defaultSort;
        $this->table = $table;
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

    private function selectQuery(\Maphper\Lib\Query $query) {
        return $this->adapter->query($query)->fetchAll(\PDO::FETCH_OBJ);
    }

    public function clearResultCache() {
        $this->resultCache = [];
    }
}
