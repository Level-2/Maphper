<?php
namespace Maphper\DataSource;
class Database implements \Maphper\DataSource {
	const EDIT_STRUCTURE = 1;
	const EDIT_INDEX = 2;
	const EDIT_OPTIMISE = 4;

	private $primaryKey;
	private $fields = '*';
    private $databaseSelect;
    private $databaseCrud;

	public function __construct($db, $table, $primaryKey = 'id', array $options = []) {
		$options = new DatabaseOptions($db, $options);
		$adapter = $options->getAdapter();

		$this->primaryKey = is_array($primaryKey) ? $primaryKey : [$primaryKey];

		$this->fields = implode(',', array_map([$adapter, 'quote'], (array) $options->read('fields')));

		$defaultSort = $options->read('defaultSort') !== false ? $options->read('defaultSort')  : implode(', ', $this->primaryKey);

        $databaseModify = new DatabaseModify($adapter, $options->getEditMode(), $table);

        $this->databaseSelect = new DatabaseSelect($adapter, $databaseModify, $table, $defaultSort);
        $this->databaseCrud = new DatabaseCrud($adapter, $databaseModify, $this->databaseSelect, $table, $this->primaryKey);

		$databaseModify->optimizeColumns();
	}

	public function getPrimaryKey() {
		return $this->primaryKey;
	}

	public function deleteById($id) {
		$this->databaseCrud->deleteById($id);
	}

	public function findById($id) {
		return $this->databaseSelect->findById($id, $this->getPrimaryKey()[0]);
	}

	public function findAggregate($function, $field, $group = null, array $criteria = [], array $options = []) {
		return $this->databaseSelect->findAggregate($function, $field, $group, $criteria, $options);
	}

	public function findByField(array $fields, $options = []) {
        return $this->databaseSelect->findByField($fields, $options);
	}

	public function deleteByField(array $fields, array $options = []) {
		$this->databaseCrud->deleteByField($fields, $options);
	}

	public function save($data) {
        $this->databaseCrud->save($data, true);
	}
}
