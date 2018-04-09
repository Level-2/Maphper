<?php

namespace Maphper;

class Iterator implements \Iterator {
	private $array;
	private $pk;
	private $iterator = 0;

	public function __construct(array $array, $pk) {
		$this->array = $array;
		$this->pk = $pk;
	}

	public function current() {
		return $this->array[$this->iterator];
	}

	public function key() {
		if (count($this->pk) == 1) {
			$pk = end($this->pk);
			return $this->array[$this->iterator]->$pk;
		}
		else {
			$current = $this->current();
			return array_map(function($pkName) use ($current) {
				return $current->$pkName;
			}, $this->pk);
		}
	}

	public function next() {
		++$this->iterator;
	}

	public function valid() {
		return isset($this->array[$this->iterator]);
	}

	public function rewind() {
		$this->iterator = 0;
	}
}
