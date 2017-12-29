<?php

class Blog {
	
	
}

class BlogPrivate {
	private $title;
    private $id;

	public function getTitle() {
		return $this->title;
	}
	
	public function setTitle($title) {
		$this->title = $title;
	}

    public function getID() {
        return $this->id;
    }
}
