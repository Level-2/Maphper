<?php 
namespace Maphper\Relation;
class Many implements \Maphper\Relation {
	private $mapper;
	private $parentField;
	private $localField;
	
	public function __construct(\Maphper\Maphper $mapper, $parentField, $localField, array $critiera = []) {
		if ($critiera) $mapper->filter($critiera);
		$this->mapper = $mapper;
		$this->parentField = $parentField;
		$this->localField = $localField;		
	}
	
	
	public function getData($parentObject) {
		if (!isset($parentObject->{$this->parentField})) $mapper = $this->mapper;
		else $mapper = $this->mapper->filter([$this->localField => $parentObject->{$this->parentField}]);
		
		return $mapper;
	}

	
	public function overwrite($key, &$mapper) {
		if (!isset($key->{$this->parentField})) return false;
		foreach ($mapper as $k => $val) {
			if (!empty($val->{$this->localField}) && $val->{$this->localField} == $key->{$this->parentField}) continue;
			$val->{$this->localField} = $key->{$this->parentField};
			$this->mapper[] = $val;
		}
	}


}