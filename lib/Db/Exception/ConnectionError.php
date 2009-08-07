<?php
/*
 * Created by Martin Wernståhl on 2009-08-07.
 * Copyright (c) 2009 Martin Wernståhl.
 * All rights reserved.
 */

/**
 * Exception for the event that a database connection error occurs.
 */
class Db_Exception_ConnectionError extends Db_Exception
{
	/**
	 * The error message.
	 * 
	 * @var string
	 */
	public $error_message;
	
	function __construct($error_message)
	{
		parent::__construct('Connection Error: "'.$error_message.'".');
		
		$this->error_message = $error_message;
	}
}


/* End of file ConnectionError.php */
/* Location: ./lib/Db/Exception */