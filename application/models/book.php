<?php

class Book extends MY_Model {

	public $has_many = array('author', 'review');

	public function __construct()
	{
		parent::__construct();
	}

	public function reverse()
	{
		return strrev($this->title);
	}
}