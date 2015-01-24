<?php
namespace Maphper;
class Relation {
	const MANY = 2;
	const ONE = 1;
	const MANY_MANY = 3;
	
	public $mapper;
	public $field;
	public $parentField;
	public $relationType;
	public $criteria;

	public function __construct($relationType, $mapper, $field, $parentField = 'id', $criteria = null) {
		$this->mapper = $mapper;
		$this->field = $field;
		$this->parentField = $parentField;
		$this->relationType = $relationType;
		$this->criteria = $criteria;
	}
}