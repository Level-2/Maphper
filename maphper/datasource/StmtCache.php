<?php
namespace Maphper\DataSource;
class StmtCache {
    private $pdo;
    private $queryCache = [];

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getCachedStmt($sql) {
		$queryId = $this->getQueryId($sql);
		if (isset($this->queryCache[$queryId])) $stmt = $this->queryCache[$queryId];
		else {
			$stmt = $this->pdo->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
			if ($stmt) $this->queryCache[$queryId] = $stmt;
		}
		return $stmt;
	}

    private function getQueryId($sql) {
        return md5($sql);
    }

    public function deleteQueryFromCache($sql) {
        unset($this->queryCache[$this->getQueryId($sql)]);
    }
}
