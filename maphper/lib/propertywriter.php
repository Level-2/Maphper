<?php
namespace Maphper\Lib;
class PropertyWriter {
	private $closure;
	public function __construct($object) {
		if ($object instanceof \stdclass) {
			$this->closure = function ($field, $value) use ($object) { $object->$field = $value; };
		}
		else {
			$this->closure = function ($field, $value) { $this->$field = $value; };
			$this->closure = $this->closure->bindTo($object, $object);
		}
	}

	public function __set($field, $value) {
		($this->closure)($field, $value);
	}
}