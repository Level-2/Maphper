<?php 
namespace Maphper\Relation;
class ManyMany implements \Iterator, \ArrayAccess, \Countable {
	private $object;
	private $relation;
	private $results;
	private $name;
	private $iterator = 0;
	private $otherInfo;
	
	public function __construct($relation, $name, $object) {
		$this->relation = $relation;
		$this->object = $object;
		$this->name = $name;
	}
	
	//bit hacky but simplest way to work out the info for the other side of the many:many relationship.
	private function getOtherFieldNameInfo() {
		if ($this->otherInfo == null) {
			foreach ($this->relation->mapper->getRelations() as $relation) {
				if ($relation->field != $this->relation->parentField) {
					$this->otherInfo = [$relation->field,  $relation->parentField, $relation->mapper];
				}
			}
		}
		return $this->otherInfo;
	}
	
	public function count() {
		list ($relatedField, $valueField) = $this->getOtherFieldNameInfo();
		return count($this->relation->mapper->filter([$relatedField => $this->object->$valueField]));
	}
	
	public function current() {
		return $this->results[$this->iterator]->{$this->name};
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
		else return $this->results[$i]->{$this->name};
	}
	
	public function valid() {
		return isset($this->results[$this->iterator]);
	}
	
	public function rewind() {
		$this->iterator = 0;
		list ($relatedField, $valueField, $relatedMapper) = $this->getOtherFieldNameInfo();
		$this->results = iterator_to_array($this->relation->mapper->filter([$relatedField => $this->object->$valueField]), false);
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
		list($relatedField, $valueField) = $this->getOtherFieldNameInfo();
		$record = new \stdClass;
		$record->{$this->relation->parentField} =  $value->$valueField;
		$record->$relatedField = $this->object->{$this->relation->field};
		
		$this->relation->mapper[] = $record;		
	}
	
	public function offsetUnset($name) {
		$this->relation->mapper->filter([$relatedField => $this->object->$valueField, $this->relation->parentField => $id])->delete();
	}
}