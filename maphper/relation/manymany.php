<?php 
namespace Maphper\Relation;
class ManyMany implements \Iterator, \ArrayAccess, \Countable, \Maphper\Relation {
	private $results;
	private $localField;
	private $parentField;
	private $iterator = 0;
	private $otherInfo;
	private $relatedMapper;
	private $intermediateMapper;
	private $autoTraverse = false;
	private $object;
	private $intermediateName;
	
	public function __construct(\Maphper\Maphper $intermediateMapper, \Maphper\Maphper $relatedMapper, $localField, $parentField, $intermediateName = null) {
		$this->intermediateMapper = $intermediateMapper;
		$this->relatedMapper = $relatedMapper;
		$this->localField = $localField;
		$this->parentField = $parentField;
		$this->autoTraverse = $intermediateName ? false : true;
		$this->intermediateName = $intermediateName ?: 'rel_' . $parentField;
		$this->intermediateMapper->addRelation($this->intermediateName, new One($this->relatedMapper, $parentField, $localField));
		
	}
	
	public function getData($parentObject) {
		$this->object = $parentObject;
		return clone $this;	
	}
	
	public function overwrite($parentObject, &$data) {
		$this->object = $parentObject;
		foreach ($data as $d) $this[] = $d;
	}
	
	//bit hacky, breaking encapsulation, but simplest way to work out the info for the other side of the many:many relationship.
	private function getOtherFieldNameInfo() {
		if ($this->otherInfo == null) {
			$propertyReader = function($name) {
				return $this->$name;
			};
			
			foreach ($this->intermediateMapper->getRelations() as $relation) {		
				$propertyReader = $propertyReader->bindTo($relation, $relation);
				if ($propertyReader('parentField') != $this->parentField) {
					$relation = $relation->getData($this->object);					
					$this->otherInfo = [$propertyReader('localField'),  $propertyReader('parentField'), $propertyReader('mapper')];
				}
			}
		}
		return $this->otherInfo;
	}
	
	public function count() {
		list ($relatedField, $valueField, $mapper) = $this->getOtherFieldNameInfo();
		return count($this->intermediateMapper->filter([$valueField => $this->object->$relatedField]));
	}
	
	public function current() {
		$result = $this->results[$this->iterator];
		if ($this->autoTraverse) return $result->{$this->intermediateName};
		else return $result;
	}
	
	public function key() {
		return $this->iterator;
	}
	
	public function next() {
		$this->iterator++;
	}
	
	public function item($i) {
		if (empty($this->results)) $this->rewind();
		if (!isset($this->results[$i])) throw new Exception('Item does not exist');
		else {
			if ($this->autoTraverse) return $this->results[$i]->{$this->intermediateName};
			else return $this->results[$i];
		}
	}
	
	public function valid() {
		return isset($this->results[$this->iterator]);
	}
	
	public function rewind() {
		$this->iterator = 0;
		list ($relatedField, $valueField, $relatedMapper) = $this->getOtherFieldNameInfo();
		$this->results = iterator_to_array($this->intermediateMapper->filter([$valueField => $this->object->$relatedField]), false);
	}
	
	public function offsetExists($name) {
		list ($relatedField, $valueField) = $this->getOtherFieldNameInfo();
		$items = $this->relation->mapper->filter([$relatedField => $this->object->$valueField, $this->relation->parentField => $id]);
		return isset($items[0]);
	}
	
	public function offsetGet($id) {
		list ($relatedField, $valueField) = $this->getOtherFieldNameInfo();
		$items = $this->relation->mapper->filter([$relatedField => $this->object->$valueField, $this->relation->parentField => $id]);
		return $items[0]->{$this->name};
	}
	
	public function offsetSet($name, $value) {	
		list($relatedField, $valueField, $mapper) = $this->getOtherFieldNameInfo();
		if ($this->autoTraverse) {
			$record = new \stdClass;		
			$record->{$this->parentField} =  $value->{$this->localField};
			$record->$valueField = $this->object->{$relatedField};
			$this->intermediateMapper[] = $record;
		}
		else {
			$record = $value;
			$record->{$this->parentField} = $value->{$this->intermediateName}->{$this->localField};
			$record->$valueField = $this->object->{$relatedField};			
			$this->intermediateMapper[] = $record;
		}		
	}
	
	public function offsetUnset($id) {
		//$this->relation->mapper->filter([$relatedField => $this->object->$valueField, $this->relation->parentField => $id])->delete();
	}
}