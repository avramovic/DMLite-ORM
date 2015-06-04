<?php if ( !defined( 'BASEPATH' ) ) exit( 'No direct script access allowed' );

class Review extends MY_Model {

	public $has_one = array('book');

	public function __construct()
	{
		parent::__construct();
	}

	public function reverse()
	{
		return strrev($this->name);
	}
}