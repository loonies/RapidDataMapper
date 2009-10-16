<?php
/*
 * Created by Martin Wernståhl on 2009-08-09.
 * Copyright (c) 2009 Martin Wernståhl.
 * All rights reserved.
 */

/**
 * Renders the applyRelatedConditions() method of a Db_Mapper descendant.
 */
class Db_Mapper_Part_ApplyRelatedConditions extends Db_Mapper_Code_Method
{
	protected $descriptor;
	
	function __construct(Db_Descriptor $desc)
	{
		$this->name = 'applyRelatedConditions';
		$this->param_list = '$query, $relation_name, $object';
		
		$this->descriptor = $desc;
		
		$this->addContents();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Adds the default contents of this method.
	 * 
	 * @return void
	 */
	public function addContents()
	{
		$this->addPart('switch($relation_name)
{');
		
		foreach($this->descriptor->getRelations() as $rel)
		{
			$this->addPart("\tcase '".$rel->getName()."':");
			$this->addPart("\t\t".self::indentCode(self::indentCode($rel->getApplyRelatedConditionsCode('$query', '$object'))));
			$this->addPart("\t\tbreak;");
		}
		
		$this->addPart('}');
	}
}


/* End of file ApplyRelatedConditions.php */
/* Location: ./lib/Db/Mapper/Part */