<?php

namespace Maphper\Relation;

class ManyManyIterator implements \Iterator {
  private $iterator;
  private $intermediateName;

  public function __construct(\Maphper\Iterator $iterator, $intermediateName = null) {
    $this->iterator = $iterator;
    $this->intermediateName = $intermediateName;
  }

  public function current() {
    if ($this->intermediateName) return $this->iterator->current()->{$this->intermediateName};
		return $this->iterator->current();
	}

	public function key() {
    return $this->iterator->key();
	}

	public function next() {
		$this->iterator->next();
	}

	public function valid() {
		return $this->iterator->valid();
	}

	public function rewind() {
		$this->iterator->rewind();
	}
}
