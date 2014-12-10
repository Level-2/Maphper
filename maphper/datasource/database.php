<?php 
namespace Maphper\DataSource;
class Database implements \Maphper\DataSource {
	private $db;
	private $table;
	private $cache = [];
	private $primaryKey;
	private $resultClass = 'stdClass';
	private $fields = '*';
	private $defaultSort;
	private $queryCache = [];
	private $resultCache = [];
	private $errors = [];
	private $alterDb = false;
	
	public function __construct(\PDO $pdo, $table, $primaryKey = 'id', array $options = []) {
		//Action at a distance... but this is needed to easily detect errors.
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->db = $pdo;
		$this->table = $this->tick($table);
		if (!is_array($primaryKey)) $primaryKey = [$primaryKey];
		$this->primaryKey = $primaryKey;
		
		if (isset($options['resultClass'])) $this->resultClass = $options['resultClass'];

		if (isset($options['fields'])) $this->fields = implode(',', array_map([$this, 'tick'], $options['fields']));

		$this->defaultSort = (isset($options['defaultSort'])) ? $options['defaultSort'] : implode(', ', $this->primaryKey);

		if (isset($options['editmode'])) $this->alterDb = $options['editmode'];		
	}

	public function getPrimaryKey() {
		return $this->primaryKey;
	}
	
	private function tick($str) {
		return '`' . str_replace('.', '`.`', trim($str, '`')) . '`';
	}
	
	public function createNew() {
		return (is_callable($this->resultClass)) ? call_user_func($this->resultClass) : new $this->resultClass;
	}
	
	public function deleteById($id) {
		$this->query('DELETE FROM `' . $this->table . '` WHERE ' . $this->primaryKey[0] . ' = :id', ['id' => $id]);
		unset($this->cache[$id]);
	}
	
	private function query($query, $args, $overrideClass = null) {
		$cacheId = md5(serialize(func_get_args()));
		$queryId = md5($query);
		
		if (isset($this->resultCache[$cacheId])) return $this->resultCache[$cacheId];
		
		if (isset($this->queryCache[$queryId])) $stmt = $this->queryCache[$queryId];
		else {
			try {
				$stmt = $this->db->prepare($query, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
			}
			catch (\PDOException $e) {
				return null;		
			}
			$this->queryCache[$queryId] = $stmt;
		}

		try {
			foreach ($args as &$arg) if ($arg instanceof \DateTime) $arg = $arg->format('Y-m-d H:i:s');
			if (count($args) > 0) $res = $stmt->execute($args);
			else $res = $stmt->execute();
			
			//Don't return results if it was an insert query
			if (strpos($query, 'INSERT') === 0) return $res;
			
			//To allow object constructor args to be delegated via a callback, this slightly inneficent loading needs to happen
			//Cannot use PDO::FETCH_CLASS as the constructor arguments may need to be provided by a factory
			//Load the properties into a stdClass, construct the correct object type via factory call then set the properties on the new object
			$result = $stmt->fetchAll(\PDO::FETCH_CLASS, 'stdClass');
			if ($this->resultClass == 'stdClass' || $overrideClass == 'stdClass') {
				$this->resultCache[$cacheId] = $this->processDates($result);
				return $result;
			}
			$newResult = [];
			
			//This allows writing to private properties in result classes
			$writeClosure = function($field, $value) {
				$this->$field = $value;
			};			
			
			foreach ($result as $obj) {
				$new = $this->createNew();
				$write = $writeClosure->bindTo($new, $new);
				foreach ($obj as $key => $value) $write($key, $this->processDates($value));
				$this->cache[$obj->{$this->primaryKey[0]}] = $obj;
				$newResult[] = $new;
			}
			$this->resultCache[$cacheId] = $newResult;
			return $newResult;
		}
		catch (\PDOException $e) {
			$this->errors = $e->getMessage();
			return null;
		}		
	}

	private function processDates($obj) {
		if (is_array($obj) || is_object($obj)) foreach ($obj as &$o) $o = $this->processDates($o);		
		if (is_string($obj) && is_numeric($obj[0]) && strlen($obj) <= 20) {
			try {
				$date = new \DateTime($obj);
				if ($date->format('Y-m-d H:i:s') == substr($obj, 0, 20)) $obj = $date;
			}
			catch (\Exception $e) {}
		}
		return $obj;
	}
	
	public function getErrors() {
		return $this->errors;
	}

	public function findById($id) {
		if (!isset($this->cache[$id])) {
			$result = $this->query('SELECT ' . $this->fields . ' FROM ' . $this->table . ' WHERE ' . $this->getPrimaryKey()[0] . ' = :id', [':id' => $id]);
			if (isset($result[0])) {
				$obj = $result[0];
				$this->cache[$id] = $obj;
			}
			else return null;
		}
		return $this->cache[$id];
	}

	private function buildFindQuery($fields, $mode){
		$args = [];
		$sql = [];	
		
		foreach ($fields as $key => $value) {

			if (is_numeric($key) && is_array($value)) {
				$result = $this->buildFindQuery($value, $key);
				foreach ($result['args'] as $arg_key => $arg) $args[$arg_key] = $arg;
				foreach ($result['sql'] as $arg) $sql[] = $arg;
				continue;
			}
			else if (\Maphper\Maphper::FIND_BETWEEN & $mode) {
				$sql[] = $this->tick($key) . '>= :' . $key . 'from';
				$sql[] = $this->tick($key) . ' <= :' . $key . 'to';
			
				$args[$key . 'from'] = $value[0];
				$args[$key . 'to'] = $value[1];
				continue;
			}
			else if (!is_numeric($key) && is_array($value)) {
				$inSql = [];
				for ($i = 0; $i < count($value); $i++) {
					$args[$key . $i] = $value[$i];
					$inSql[] = ':' . $key . $i;
				}
				if (count($inSql) == 0) return [];
				else $sql[] = $this->tick($key) . ' IN ( ' .  implode(', ', $inSql) . ')';
				continue;
			}			
			else if (\Maphper\Maphper::FIND_EXACT & $mode) $operator = '=';
			else if (\Maphper\Maphper::FIND_LIKE & $mode) {
				$operator = 'LIKE';
				$value = '%' . $value . '%';
			}
			else if (\Maphper\Maphper::FIND_STARTS & $mode) {
				$operator = 'LIKE';
				$value = $value . '%';
			}
			else if (\Maphper\Maphper::FIND_NOCASE & $mode) {
				$operator = 'LIKE';
			}
			else if (\Maphper\Maphper::FIND_BIT & $mode) $operator = '&';
			else if (\Maphper\Maphper::FIND_GREATER & $mode) $operator = '>';
			else if (\Maphper\Maphper::FIND_LESS & $mode) $operator = '<';
			else if (\Maphper\Maphper::FIND_NOT & $mode) $operator = '!=';
			
			$args[$key] = $value;
			$sql[] = $this->tick($key) . ' ' . $operator . ' :' . $key;
		}
		
		if (\Maphper\Maphper::FIND_OR & $mode) $query = implode(' OR  ', $sql);
		else $query = implode(' AND ', $sql);
		return ['args' => $args, 'sql' => [$query]];
	}

	public function findAggregate($function, $field, $group = null, array $criteria = [], array $options = [], $mode = null) {
		if ($mode == null) $mode = \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND;		
		
		//Cannot count/sum/max multiple fields, pick the first one. This should only come into play when trying to count() a mapper with multiple primary keys
		if (is_array($field)) $field = $field[0];
		
		$query = $this->buildFindQuery($criteria, $mode);
		$args = $query['args'];
		$sql = $query['sql'];
		
		if ($group == true) $groupBy = ' GROUP BY ' . $field;
		else $groupBy = '';
		$result = $this->query('SELECT ' . $function . '(' . $field . ') as val, ' . $field . '   FROM ' . $this->table . ($sql[0] != null ? ' WHERE ' : '') . implode(' AND ', $sql) . ' ' . $groupBy, $args, 'stdClass');

		if (isset($result[0]) && $group == null) return $result[0]->val;
		else if ($group != null) {
			$ret = [];
			foreach ($result as $res) $ret[$res->$field] = $res->val;
			return $ret;
		}
		else return 0;	
	}
	
	public function findByField(array $fields, $options = [], $mode = null ) {		
		if ($mode == null) $mode = \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND;

		$query = $this->buildFindQuery($fields, $mode);
		$args = $query['args'];
		$sql = $query['sql'];

		if ($sql[0] == '')  $where = '';
		else if (\Maphper\Maphper::FIND_OR & $mode) $where = ' WHERE ' . implode(' OR ', $sql);
		else $where = ' WHERE ' . implode(' AND ', $sql);
	
		
		$limit = (isset($options['limit'])) ? ' LIMIT ' . $options['limit'] : '';
		$offset = (isset($options['offset'])) ? ' OFFSET ' . $options['offset'] : '';
		if ($offset != '' && $limit == '') $limit = ' LIMIT 1000 ';
		$order = (!isset($options['order'])) ? $this->defaultSort : $order = $options['order'];
		return $this->query('SELECT ' . $this->fields . ' FROM ' . $this->table . $where . ' ORDER BY ' . $order . ' ' . $limit . ' ' . $offset, $args);
	}	

	
	public function deleteByField(array $fields, array $options = [], $mode = null) {
		if ($mode == null) $mode = \Maphper\Maphper::FIND_EXACT | \Maphper\Maphper::FIND_AND;
	
		$query = $this->buildFindQuery($fields, $mode);
		$args = $query['args'];
		$sql = $query['sql'];
			
	
		if ($sql[0] == '')  $where = '';
		else if (\Maphper\Maphper::FIND_OR & $mode) $where = ' WHERE ' . implode(' OR ', $sql);
		else $where = ' WHERE ' . implode(' AND ', $sql);
			
	
		if (isset($options['limit']) != null) $limit = ' LIMIT ' . $options['limit'];
		else $limit = '';
	
		$this->query('DELETE FROM ' . $this->table . $where . ' ' . $limit, $args);
		
		//Clear the cache
		$this->cache = [];
		$this->resultCache = [];
	}
	
	public function save($data) {
		$pk = $this->primaryKey;	

		$new = false;
		foreach ($pk as $k) {
			if (!isset($data->$k) || $data->$k == '') $data->$k = null;
		}
		
		
		//Extract private properties from the object
		$readClosure = function() {
			$data = new \stdClass;
			foreach ($this as $k => $v)	$data->$k = $v;
			return $data;
		};
		$read = $readClosure->bindTo($data, $data);
		$writeData = $read();			
		
		//print_r($data);
		//$writeData = $data;
		
		$query = $this->_buildSaveQuery($writeData);
		$query1 = $this->_buildSaveQuery($writeData, '1');
	
		$result = $this->query('INSERT INTO ' . $this->table . ' SET ' . implode(',', $query['sql']) . ' ON DUPLICATE KEY UPDATE ' . implode(',', $query1['sql']), array_merge($query['args'], $query1['args']));
			
		//Only fetch warnings if altering the database structure to reduce the number of trips to the DB in production
		if ($this->alterDb) {
			$warnings = $this->db->query('SHOW WARNINGS');
			$pk = $this->primaryKey[0];
			if ($result) $writeData->pk = $data->$pk = $result; 
			if (count($warnings->fetchAll()) > 0) $this->alterDatabase($writeData);			
		}
		//TODO: This will error if the primary key is a private field	
		if ($new && count($this->primaryKey) == 1) $data->{$this->primaryKey} = $this->db->lastInsertId();
		//Something has changed, clear any cached results as they may now be incorrect
		$this->resultCache = [];
		$this->cache[$this->primaryKey[0]] = $data;
	}
	
	private function getType($val) {
		if ($val instanceof \DateTime) return 'DATETIME';
		else if (is_int($val)) return  'INT(11)';
		else if (is_double($val)) return 'DECIMAL(9,' . strlen($val) - strrpos($val, '.') - 1 . ')';
		else if (is_string($val) && strlen($val) < 256) return 'VARCHAR(255)';
		else if (is_string($val) && strlen($val) > 256) return 'LONGBLOG';
		else return 'VARCHAR(255)';
	}
	
	private function alterDatabase($data) {
		if ($this->alterDb == true) {
			
			if (is_array($this->primaryKey)) {
				$parts = [];
				foreach ($this->primaryKey as $key) {
					$pk = $data->$key;
					if ($pk == null) $parts[] = $key . ' INT(11) NOT NULL AUTO_INCREMENT'; 
					else $parts[] = $key . ' ' . $this->getType($pk) . ' NOT NULL';					
				}
				
				$pkField = implode(', ', $parts) . ', PRIMARY KEY(' . implode(', ', $this->primaryKey) . ')';
			}
				
			$this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->table . ' (' . $pkField . ')');
			
			foreach ($data as $key => $value) {
				if (is_array($value) || (is_object($value) && !($value instanceof \DateTime))) continue;
				if (in_array($key, $this->primaryKey)) continue;

				$type = $this->getType($value);
			
				try {
					$this->db->query('ALTER TABLE ' . $this->table . ' ADD ' . $this->tick($key) . ' ' . $type);
				}
				catch (\PDOException $e) {
					$this->db->query('ALTER TABLE ' . $this->table . ' MODIFY ' . $this->tick($key) . ' ' . $type);
				}
			}
			$this->save($data);
		}
	}

	private function _buildSaveQuery($data, $affix = '') {
		$sql = [];
		$args = [];
		foreach ($data as $field => $value) {
			//For dates with times set, search on time, if the time is not set, search on date only.
			//E.g. searching for all records posted on '2015-11-14' should return all records that day, not just the ones posted at 00:00:00 on that day
			if ($value instanceof \DateTime) {
				if ($value->format('H:i:s')  == '00:00:00') $value = $value->format('Y-m-d');
				else $value = $value->format('Y-m-d H:i:s');
			}
			if (is_object($value)) continue;
			if ($value === null) $tmp[] = '`' . $field . '` = NULL';
			else {
				$sql[] = $this->tick($field) . ' = :' . $field . $affix;
				$args[$field . $affix] = $value;
			}
		}
		return ['sql' => $sql, 'args' => $args];
	}
	
}