<?php
/*
 * Created by Martin Wernståhl on 2009-11-13.
 * Copyright (c) 2009 Martin Wernståhl.
 * All rights reserved.
 */

/**
 * A class which generates code for a class.
 */
class Rdm_Util_Code_ClassBuilder extends Rdm_Util_Code_Container
{
	public $name = '';
	
	public $extends = '';
	
	public $implements = '';
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the name of the generated class.
	 * 
	 * @param  string
	 * @return void
	 */
	public function setClassName($name)
	{
		$this->name = $name;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets a class name which the generated class shall extend.
	 * 
	 * @param  string
	 * @return void
	 */
	public function setExtends($extends)
	{
		$this->extends = $extends;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets a list of interfaces which the generated class shall implement.
	 * 
	 * @param  string
	 * @return void
	 */
	public function setImplements($implements)
	{
		$this->implements = $implements;
	}
	
	// ------------------------------------------------------------------------
	
	public function getName()
	{
		return 'class_'.$this->name;
	}
	
	// ------------------------------------------------------------------------
	
	public function __toString()
	{
		$head = "class {$this->name}";
		
		if( ! empty($this->extends))
		{
			$head .= " extends {$this->extends}";
		}
		
		if( ! empty($this->implements))
		{
			$head .= " implements {$this->implements}";
		}
		
		$head .= "\n{";
		
		$contents = implode("\n\n", $this->content);
		
		return $head . self::indentCode("\n" . $contents) . "\n}";
	}
}

/* End of file ClassBuilder.php */
/* Location: ./lib/Rdm/Util/Code */