<?php
namespace Maphper\Relation;
class ManyMany implements \IteratorAggregate, \ArrayAccess, \Countable, \Maphper\Relation {
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
		$this->results = $data;
		$this->object = $parentObject;
		foreach ($data as $dt) $this[] = $dt;
	}

	//bit hacky, breaking encapsulation, but simplest way to work out the info for the other side of the many:many relationship.
	private function getOtherFieldNameInfo() {
		if ($this->otherInfo == null) {
			$propertyReader = function($name) {return $this->$name;	};

			$reader = $propertyReader->bindTo($this->intermediateMapper, $this->intermediateMapper);

			foreach ($reader('relations') as $relation) {
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
		return count($this->getResults());
	}

	private function getResults() {
		list ($relatedField, $valueField, $relatedMapper) = $this->getOtherFieldNameInfo();

		$x = $this->intermediateMapper->filter([$valueField => $this->object->$relatedField]);
		return $x;
	}

	public function getIterator() {
		$results = $this->getResults()->getIterator();
		return new ManyManyIterator($results, $this->autoTraverse ? $this->intermediateName : null);
	}

	public function item($i) {
		return iterator_to_array($this->getIterator(), false)[$i];
	}

	public function offsetExists($name) {
		$items = $this->getResults()->filter([$this->parentField => $name]);

		return $items->getIterator()->valid();
	}

	public function offsetGet($name) {
		$items = $this->getResults()->filter([$this->parentField => $name]);

		return $items->getIterator()->current()->{$this->intermediateName};
	}

	public function offsetSet($name, $value) {
		list($relatedField, $valueField, $mapper) = $this->getOtherFieldNameInfo();
		if ($this->autoTraverse) $this->offsetSetAutotraverse($value, $relatedField, $valueField);
		else if ($this->doUpdateInterMapper($value, $relatedField, $valueField)) {
            $record = $value;
			$record->{$this->parentField} = $value->{$this->intermediateName}->{$this->localField};
			$record->$valueField = $this->object->{$relatedField};
			$this->intermediateMapper[] = $record;
		}
	}

    private function doUpdateInterMapper($record, $relatedField, $valueField) {
        return !(isset($record->{$this->parentField}) && isset($record->{$this->intermediateName}) &&
                $record->{$this->parentField} == $record->{$this->intermediateName}->{$this->localField} &&
                $record->$valueField == $this->object->{$relatedField});
    }

    private function offsetSetAutotraverse($value, $relatedField, $valueField) {
        $record = new \stdClass;
        $record->{$this->parentField} =  $value->{$this->localField};
        $record->$valueField = $this->object->{$relatedField};
        $this->intermediateMapper[] = $record;
    }

	public function offsetUnset($id) {
		//$this->relation->mapper->filter([$relatedField => $this->object->$valueField, $this->relation->parentField => $id])->delete();
	}
}
