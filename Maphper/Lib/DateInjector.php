<?php
namespace Maphper\Lib;
//Replaces dates in an object graph with \DateTime instances
class DateInjector {
	private $processCache;
	private $dateFormats = [
		['Y-m-d H:i:s', 20],
		['Y-m-d', 10],
		['Y-n-d', 9]
	];

	public function replaceDates($obj, $reset = true) {
		//prevent infinite recursion, only process each object once
		if ($this->checkCache($obj, $reset)) return $obj;

		if ($this->isIterable($obj)) foreach ($obj as &$o) $o = $this->replaceDates($o, false);
		if ($this->isPossiblyDateString($obj)) $obj = $this->tryToGetDateObjFromString($obj);
		return $obj;
	}

    private function tryToGetDateObjFromString($obj) {
        try {
            $date = new \DateTime($obj);
			if ($this->dateMatchesFormats($date, $obj)) $obj = $date;
        }
        catch (\Exception $e) {	//Doesn't need to do anything as the try/catch is working out whether $obj is a date
        }
        return $obj;
    }

	private function dateMatchesFormats($date, $str) {
		foreach ($this->dateFormats as list($format, $len)) {
			if ($date->format($format) == substr($str, 0, $len)) return true;
		}
		return false;
	}

    private function isIterable($obj) {
        return is_array($obj) || (is_object($obj) && ($obj instanceof \Iterator));
    }

    private function isPossiblyDateString($obj) {
        return is_string($obj) && isset($obj[0]) && is_numeric($obj[0]) && strlen($obj) <= 20;
    }

	private function checkCache($obj, $reset) {
		if ($reset) $this->processCache = new \SplObjectStorage();
        if (!is_object($obj)) return false;

		if ($this->processCache->contains($obj)) return $obj;
		else $this->processCache->attach($obj, true);

		return false;
	}
}
