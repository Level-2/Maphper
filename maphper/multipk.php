<?php 
namespace Maphper;
class MultiPk implements \ArrayAccess {
	private $parent;
	private $primaryKey;
	private $lookup;
	
	public function __construct(Maphper $mapper, $lookup, array $primaryKey, MultiPk $parent = null) {
		$this->parent = $parent;
		$this->primaryKey = $primaryKey;
		$this->lookup = $lookup;
		$depth = $this->getDepth();
		$this->mapper = $mapper->filter([$primaryKey[$depth] => $lookup]);
	}
		
	private function getDepth() {
		$depth = 0;
		$obj = $this;
		while ($obj->parent != null) {
			$depth++;
			$obj = $obj->parent;
		}		
		return $depth;
	}
	
	public function offsetGet($key) {			
		$depth = $this->getDepth()+1;	
		if (count($this->primaryKey)-1 == $depth) return $this->mapper->filter([$this->primaryKey[$depth] => $key])->item(0);
		else return new MultiPk($this->mapper, $key, $this->primaryKey, $this); 
	}
	
	public function offsetSet($key, $value) {		
		$keys = $this->primaryKey;
		$obj = $this;
		$key1 = array_pop($keys);
		$value->$key1 = $key;
		
		while ($key = array_pop($keys)) {
			$value->$key = $obj->lookup;
			$obj = $obj->parent;
		}		
		$this->mapper[] = $value;
	}
	
	public function offsetUnset($key) {
		$keys = $this->primaryKey;
		$this->mapper->filter([ array_pop($keys) => $key])->delete();
	}
	
	public function offsetExists($key) {
		$keys = $this->primaryKey;
		$mapper = $this->mapper->filter([array_pop($keys) => $key]);
		return count($mapper) > 0;
	}
}