<?php
/*
 * Created by Martin Wernståhl on 2010-04-03.
 * Copyright (c) 2010 Martin Wernståhl.
 * All rights reserved.
 */

/**
 * 
 */
abstract class Rdm_UnitOfWork
{
	/**
	 * Default value for the entity change policy; means that all entities
	 * will be searched for changes.
	 */
	const IMPLICIT = 1;
	
	/**
	 * Value telling us that objects has to be explicitly marked as changed
	 * to make the UnitOfWork search them for changes.
	 */
	const EXPLICIT = 2;
	
	/**
	 * Registered entities which have been fetched from the database,
	 * works as an Identity Map.
	 * 
	 * @var array(string => Object)
	 */
	public $entities = array();
	
	/**
	 * Modified entities, if objects has to be flagged as changed, this
	 * array will be filled with them using the method markChanged().
	 * 
	 * @var array(Object)
	 */
	public $modified = array();
	
	/**
	 * The value of the change policy.
	 * 
	 * @var int
	 */
	protected $change_tracking_policy = self::IMPLICIT;
	
	/**
	 * Entities which are to be inserted into the database.
	 * 
	 * @var array(Object)
	 */
	protected $new_entities = array();
	
	/**
	 * Entities which are to be deleted from the database.
	 * 
	 * @var array(Object)
	 */
	protected $deleted_entities = array();
	
	/**
	 * A list of multi delete operations to be performed.
	 * 
	 * @var array(Rdm_UnitOfWork_MultiDelete)
	 */
	protected $multi_delete = array();
	
	/**
	 * A list of multi update operations to be performed.
	 * 
	 * @var array(Rdm_UnitOfWork_MultiUpdate)
	 */
	protected $multi_update = array();
	
	/**
	 * The database adapter to use when sending calls to the database.
	 * 
	 * @var Rdm_Adapter
	 */
	protected $db;
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the database adapter which this UnitOfWork will use for database
	 * interactions.
	 * 
	 * @param  Rdm_Adapter
	 * @return void
	 */
	public function setAdapter(Rdm_Adapter $adapter)
	{
		$this->db = $adapter;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the change calculation policy of this unit of work.
	 * 
	 * @param  int  Constant from this class
	 * @return void
	 */
	public function setChangeTrackingPolicy($value)
	{
		// TODO: Validate domain of passed $value
		$this->change_tracking_policy = $value;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Adds an entity to this Unit of work, only used for objects which already
	 * exist in the database.
	 * 
	 * @param  object
	 * @param  string
	 * @return void
	 */
	public function addEntity($object, $key)
	{
		$this->entities[$key] = $object;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Registers a new entity to be inserted into the database.
	 * 
	 * @param  object
	 * @return void
	 */
	public function addNewEntity($object)
	{
		if( ! empty($object->__id))
		{
			throw Rdm_UnitOfWork_Exception::alreadyPersisted($object);
		}
		
		$oid = spl_object_hash($object);
		
		isset($this->new_entities[$oid]) OR $this->new_entities[$oid] = $object;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Registers an entity for deletion, also removes it from the entity registry
	 * of this Unit of work.
	 * 
	 * @param  object
	 * @param  string
	 * @return void
	 */
	public function addForDelete($object, $key)
	{
		if(isset($this->entities[$key]))
		{
			unset($this->entities[$key]);
			unset($this->modified[$key]);
		}
		
		if(empty($object->__id))
		{
			throw Rdm_UnitOfWork_Exception::alreadyDeleted($object);
		}
		
		$this->deleted_entities[$key] = $object;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns true if an entity with the key $key exists in this unit of work.
	 * 
	 * @param  string
	 * @return boolean
	 */
	public function hasEntity($key)
	{
		return isset($this->entities[$key]);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the entity with the key $key.
	 * 
	 * @param  string
	 * @return object
	 */
	public function getEntity($key)
	{
		return $this->entites[$key];
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Marks the supplied object as changed.
	 * 
	 * @param  Object
	 * @param  string  The unique ID of the Object
	 * @return void
	 */
	public function markEntityAsChanged($object, $key)
	{
		$this->modified[$key] = $object;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Commits the changes stored in this unit of work.
	 * 
	 * @return boolean
	 */
	public function commit()
	{
		try
		{
			$this->prepare();
			
			$this->doInserts();
			$this->doUpdates();
			$this->doDeletes();
			
			$this->cleanUp();
		}
		catch(Exception $e)
		{
			$this->reset();
			
			// Rethrow
			throw $e;
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Prepares the objects for commit.
	 * 
	 * @return void
	 */
	public function prepare()
	{
		if($this->change_tracking_policy === self::IMPLICIT)
		{
			$this->modified = $this->entities;
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Executes all INSERT queries stored in this Unit of Work.
	 * 
	 * @return void
	 */
	public function doInserts()
	{
		$this->processSingleInserts();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Executes all UPDATE queries stored in this Unit of Work.
	 * 
	 * @return 
	 */
	public function doUpdates()
	{
		$this->processSingleChanges();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Executes all DELETE queries stored in this Unit of Work.
	 * 
	 * @return void
	 */
	public function doDeletes()
	{
		$this->processSingleDeletes();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Cleans the data and updates the objects of this unit of work after
	 * doInsert(), doUpdate() and doDelete() has been run.
	 * 
	 * @return void
	 */
	public function cleanUp()
	{
		$this->moveInserted();
		$this->removeDeletedIds();
		$this->updateShadowData();
		
		$this->reset();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Resets this unit of work.
	 * 
	 * @return void
	 */
	public function reset()
	{
		// Reset this Unit of Work
		$this->new_entities =
			$this->deleted_entities =
			$this->multi_delete =
			$this->multi_update =
			$this->modified = array();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Moves the inserted rows to the $entities array with the uid as key.
	 * 
	 * @return void
	 */
	protected function moveInserted()
	{
		foreach($this->new_entities as $e)
		{
			$this->entities[implode('$', $e->__id)] = $e;
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Resets the __id array of the deleted objects.
	 * 
	 * @return void
	 */
	protected function removeDeletedIds()
	{
		foreach($this->deleted_entities as $entity)
		{
			$entity->__id = array();
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Creates insertions for objects, preferably with a single larger query if
	 * possible (ie. when not using DB generated data).
	 * 
	 * @return void
	 */
	abstract protected function processSingleInserts();
	
	// ------------------------------------------------------------------------

	/**
	 * Diffs all loaded entity objects and determines if they have been changed,
	 * if they have, their changes are committed to the database.
	 * 
	 * Use the objects in $entities.
	 * 
	 * @return void
	 */
	abstract protected function processSingleChanges();
	
	// ------------------------------------------------------------------------

	/**
	 * This method deletes all the objects existing in $deleted_entities from the
	 * database.
	 * 
	 * Delete objects stored in $deleted_entities
	 * 
	 * @return void
	 */
	abstract protected function processSingleDeletes();
	
	// ------------------------------------------------------------------------

	/**
	 * Updates the __data array in the objects.
	 * 
	 * @return void
	 */
	abstract protected function updateShadowData();
}


/* End of file UnitOfWork.php */
/* Location: ./lib/Rdm */