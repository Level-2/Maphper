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

	public function write($data) {
		if ($data != null) {
			foreach ($data as $key => $value) {
				($this->closure)($key,  $this->processDates($value));
			}
		}
	}

	private function processDates($obj) {
		$injector = new DateInjector;
		return $injector->replaceDates($obj);
	}
}