<?php
namespace Maphper\Relation;
class One implements \Maphper\Relation {
	private $mapper;
	private $parentField;
	private $localField;
	private $parentObject;
	private $criteria = [];
	private $data;
	
	public function __construct(\Maphper\Maphper $mapper, $parentField, $localField, array $criteria = []) {
		$this->mapper = $mapper;
		$this->parentField = $parentField;
		$this->localField = $localField;
		$this->criteria = $criteria;
	}
	
	public function getData($parentObject) {
		//Don't actually fetch the related data, return an instance of $this that will lazy load data when __get is called
		$clone = clone $this;
		$clone->parentObject = $parentObject;
		return $clone;		
	}	
	
	private function lazyLoad() {
		if (!isset($this->data)) {

			if ($this->parentObject == null) throw new \Exception('Error, no object set');			

			if ($this->criteria) $this->data = $this->mapper->filter($this->criteria)->filter([$this->localField => $this->parentObject->{$this->parentField}])->item(0);
			else $this->data = $this->mapper->filter([$this->localField => $this->parentObject->{$this->parentField}])->item(0);
		}
		return $this->data;
	}
	public function __call($func, array $args = []) {
		if ($this->lazyLoad() == null) return '';
		return call_user_func_array([$this->lazyLoad(), $func], $args);
	}
	
	public function __get($name) {
		if ($this->lazyLoad()) return $this->lazyLoad()->$name;
	}
	
	
	public function overwrite($parentObject, $data) {
		$this->mapper[] = $data;
		$parentObject->{$this->parentField} = $data->{$this->localField};
	}
}