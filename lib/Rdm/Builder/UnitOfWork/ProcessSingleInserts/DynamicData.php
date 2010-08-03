<?php
/*
 * Created by Martin Wernståhl on 2010-04-05.
 * Copyright (c) 2010 Martin Wernståhl.
 * All rights reserved.
 */

/**
 * Variant of the insertion code which performs one insert per new row and then
 * fetches database generated data to populate the objects.
 */
class Rdm_Builder_UnitOfWork_ProcessSingleInserts_DynamicData extends Rdm_Util_Code_Container
{
	function __construct(Rdm_Descriptor $desc)
	{
		$db = $desc->getAdapter();
		
		$pre = array();
		$columns = array();
		$values = array();
		$loaded_column_selects = array();
		$loaded_column_assignments = array();
		$post = array();
		foreach(array_merge($desc->getPrimaryKeys(), $desc->getColumns()) as $c)
		{
			// Get validation or population code
			$code = trim($c->getPreInsertCode('$data', '$entity'));
			
			if( ! empty($code))
			{
				$pre[] = $code;
			}
			
			// If we can insert stuff, add the code
			if($c->isInsertable())
			{
				$columns[] = addcslashes($db->protectIdentifiers($c->getColumn()), "'");
				$values[] = $c->getDataType()->getSqlValueCode($c->getFetchFromObjectCode('$entity'), "'");
			}
			
			// Shall we load data from the database after we've inserted this?
			if($c->getLoadAfterInsert() === Rdm_Descriptor::PLAIN_COLUMN)
			{
				// Plain column to fetch from the database
				$loaded_column_selects[] = $c->getDataType()->getSelectCode($desc->getSingular()."'", "'");
				$loaded_column_assignments[] = $c->getFromDataToObjectCode('$event', '$udata', '$prefix');
			}
			
			// Special logic for the column
			$code = $c->getPostInsertCode('$entity');
			
			if( ! empty($code))
			{
				$post[] = $code;
			}
		}
		
		// Construct a Primary key filter for the case that we have to fetch parts of the new row after insert
		$pks = array();
		foreach($desc->getPrimaryKeys() as $k)
		{
			$pks[] = addcslashes($db->protectIdentifiers($k->getColumn()), "'").' = \'.$this->db->escape($entity->__id[\''.$k->getColumn().'\'])';
		}
		
		
		// Foreach loop for all new entities
		$str = 'foreach($this->new_entities as $entity)
{';
		
		// Add pre insert code for columns, so they can do stuff with their data
		if( ! empty($pre))
		{
			$str .= "\n\t".implode("\n\t", $pre);
		}
		
		// Add Insert query
		$str .= '
	$this->db->query(\'INSERT INTO '.addcslashes($db->protectIdentifiers($db->dbprefix.$desc->getTable()), '\'').' ('.implode(', ', $columns).') VALUES ('.implode('.\', ', $values).'.\')\');';
		
		
		// Add post insert code for columns (eg. primary keys and other more fancy stuff)
		if( ! empty($post))
		{
			$str .= "\n\n\t".implode("\n\t", $post);
		}
		
		// Load database generated columns, if we have any
		if( ! empty($loaded_column_selects))
		{
			$str .= "\n\n\t".'$this->db->query(\'SELECT '.implode(', ', $loaded_column_selects).' FROM '.addcslashes($db->protectIdentifiers($db->dbprefix.$desc->getTable()), "'").' AS '.addcslashes($db->protectIdentifiers($desc->getSingular()), "'").' WHERE '.implode('.\' AND ', $pks).');
	
	$prefix = \''.$desc->getSingular().'\';';
		}
		
		$this->addPart($str.'
}');
	}
	
	// ------------------------------------------------------------------------
	
	public function getName()
	{
		return 'dynamic';
	}
}


/* End of file DynamicData.php */
/* Location: ./lib/Rdm/Builder/UnitOfWork/ProcessSingleInserts */