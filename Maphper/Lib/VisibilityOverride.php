<?php
namespace Maphper\Lib;
//Allows reading/writing of properties on objects ignoring their visibility
class VisibilityOverride {
	private $readClosure;
	private $writeClosure;

	public function __construct($object) {
		if ($object instanceof \stdclass) {
			$this->readClosure = function() use ($object) { return $object;	};
			$this->writeClosure = function ($field, $value) use ($object) { $object->$field = $value; };
		}
		else {
            $visOverride = $this;
			$this->readClosure = function() use ($visOverride) {
                return (object) array_filter(get_object_vars($this), [$visOverride, 'isReturnableDataType']);
			};
			$this->readClosure = $this->readClosure->bindTo($object, $object);

			$this->writeClosure = function ($field, $value) { $this->$field = $value; };
			$this->writeClosure = $this->writeClosure->bindTo($object, $object);
		}

	}

    public function isReturnableDataType($v) {
        return is_scalar($v) || is_null($v) || (is_object($v) && $v instanceof \DateTimeInterface);
    }

	public function getProperties() {
		return ($this->readClosure)();
	}

	public function write($data) {
		if ($data != null) {
			foreach ($data as $key => $value) {
				($this->writeClosure)($key,  $this->processDates($value));
			}
		}
	}

	private function processDates($obj) {
		$injector = new DateInjector;
		return $injector->replaceDates($obj);
	}
}
