<?php
namespace Maphper\Lib;
//Allows reading/writing of properties on objects ignoring their visibility
class VisibilityOverride {
	private $readClosure;

	public function __construct() {
		$this->readClosure = function() {
			$data = new \stdClass;
			foreach ($this as $k => $v)	{
				if (is_scalar($v) || is_null($v) || (is_object($v) && $v instanceof \DateTime))	$data->$k = $v;
			}
			return $data;
		};
	}

	public function getProperties($obj) {
		$reflect = new \Reflectionclass($obj);
		if ($reflect->isInternal()) return $obj;
		$read = $this->readClosure->bindTo($obj, $obj);
		return $read();
	}

	public function writeProperty($obj) {

	}
}