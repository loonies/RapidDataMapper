<?php
/*
 * Created by Martin Wernståhl on 2010-04-02.
 * Copyright (c) 2010 Martin Wernståhl.
 * All rights reserved.
 */

/**
 * 
 */
class Rdm_Builder_Collection extends Rdm_Util_Code_ClassBuilder
{
	public function __construct(Rdm_Descriptor $desc)
	{
		$this->setClassName($desc->getCollectionClassName());
		$this->setExtends('Rdm_Collection');
		
		$this->addPart(new Rdm_Builder_Collection_RelationConstants($desc));
		
		// Unit of work storage common to only the generated <Class>Collection
		$this->addPart('protected static $unit_of_work = null;');
		
		$this->addPart(new Rdm_Util_Code_PropertyBuilder('table_alias', $desc->getSingular()));
		
		// Static methods
		$this->addPart(new Rdm_Builder_Collection_PushChanges($desc));
		$this->addPart(new Rdm_Builder_Collection_SetUnitOfWork($desc));
		$this->addPart(new Rdm_Builder_Collection_GetUnitOfWork($desc));
		$this->addPart(new Rdm_Builder_Collection_Create($desc));
		$this->addPart(new Rdm_Builder_Collection_Persist($desc));
		$this->addPart(new Rdm_Builder_Collection_Delete($desc));
		
		$this->addPart(new Rdm_Builder_Collection_Fetch($desc));
		
		// Instance methods
		$this->addPart(new Rdm_Builder_Collection_With($desc));
		$this->addPart(new Rdm_Builder_Collection_CreateSelectPart($desc));
		$this->addPart(new Rdm_Builder_Collection_CreateSelectCountPart($desc));
		$this->addPart(new Rdm_Builder_Collection_CreateFromPart($desc));
		$this->addPart(new Rdm_Builder_Collection_HydrateObject($desc));
		$this->addPart(new Rdm_Builder_Collection_CreateFilterInstance($desc));
		$this->addPart(new Rdm_Builder_Collection_EntityToXML($desc));
		
		$this->addPart(new Rdm_Builder_Collection_Add($desc));
	}
}


/* End of file Collection.php */
/* Location: ./lib/Rdm/Builder */