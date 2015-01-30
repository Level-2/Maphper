<?php 
namespace Maphper\Relation;
class Many implements \Maphper\Relation {
	private $mapper;
	private $parentField;
	private $localField;
	private $criteria = [];
	
	public function __construct(\Maphper\Maphper $mapper, $parentField, $localField, array $critiera = []) {
		$this->mapper = $mapper;
		$this->parentField = $parentField;
		$this->localField = $localField;
		$this->criteria = $critiera;
	}
	
	public function getData($parentObject) {
		return $this->mapper->filter([$this->localField => $parentObject->{$this->parentField}]);
	}
	
	public function overwrite($key, $value) {
		//TODO
	}
}