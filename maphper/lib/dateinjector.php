<?php
namespace Maphper\Lib;
//Replaces dates in an object graph with \DateTime instances
class DateInjector {
	private $processCache;

	public function replaceDates($obj, $reset = true) {
		//prevent infinite recursion, only process each object once
		if ($reset) $this->processCache = new \SplObjectStorage();
		if (is_object($obj) && $this->processCache->contains($obj)) return $obj;
		else if (is_object($obj)) $this->processCache->attach($obj, true);

		if (is_array($obj) || (is_object($obj) && ($obj instanceof \Iterator))) foreach ($obj as &$o) $o = $this->replaceDates($o, false);
		if (is_string($obj) && isset($obj[0]) && is_numeric($obj[0]) && strlen($obj) <= 20) {
			try {
				$date = new \DateTime($obj);
				if ($date->format('Y-m-d H:i:s') == substr($obj, 0, 20)) $obj = $date;
			}
			catch (\Exception $e) {	//Doesn't need to do anything as the try/catch is working out whether $obj is a date
			}
		}
		return $obj;
	}
}
