<?php
/*
 * Created by Martin Wernståhl on 2009-08-07.
 * Copyright (c) 2009 Martin Wernståhl.
 * All rights reserved.
 */

/**
 * A class describing a column mapped to a class.
 */
class Rdm_Descriptor_Column
{
	/**
	 * Parent descriptor.
	 * 
	 * @var Rdm_Descriptor
	 */
	protected $parent_descriptor;
	
	/**
	 * The name of the column this object describes.
	 * 
	 * @var string
	 */
	protected $column;
	
	/**
	 * The property name in the class the described column maps to.
	 * 
	 * @var string
	 */
	protected $property;
	
	/**
	 * The data type of the described column.
	 * 
	 * @var string  Rdm_type name
	 */
	protected $data_type;
	
	/**
	 * The (maximum) length of the described column.
	 * 
	 * @var int
	 */
	protected $data_length;
	
	/**
	 * The data type object.
	 * 
	 * @var Rdm_Descriptor_Type_Generic
	 */
	protected $data_type_object; 
	
	/**
	 * Tells if this column can be inserted.
	 * 
	 * @var bool
	 */
	protected $insertable = true;
	
	/**
	 * Tells if this column can be updated.
	 * 
	 * @var bool
	 */
	protected $updatable = true;
	
	/**
	 * If to fetch this column from the database after insert has been performed
	 * to update eg. an ON INSERT data.
	 * 
	 * @var boolean
	 */
	protected $load_after_insert = false;
	
	// ------------------------------------------------------------------------

	/**
	 * Creates a new Column object.
	 * 
	 * @param  string  The column name
	 * @param  string|Rdm_Descriptor_TypeInterface The data type
	 * @param  int|false  The data type length
	 * @param  string  The property name of this column, defaults to $name
	 * @return Rdm_Column
	 */
	public function __construct($name,
	                            $type = Rdm_Descriptor::GENERIC,
	                            $type_length = false,
	                            $property = false)
	{
		// TODO: Filter input
		$this->column = $name;
		$this->data_type = $type;
		$this->data_type_length = $type_length;
		$this->property = $property ? $property : $name;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Internal: Sets the descriptor owning this column descriptor.
	 * 
	 * @param  Rdm_Descriptor
	 * @return void
	 */
	public function setParentDescriptor(Rdm_Descriptor $desc)
	{
		$this->parent_descriptor = $desc;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the column name this object describes.
	 * 
	 * @throws Rdm_Descriptor_MissingValueException
	 * @return string
	 */
	public function getColumn()
	{
		if(empty($this->column))
		{
			// TODO: Add the name of the parent descriptor?
			throw new Rdm_Descriptor_MissingValueException('column', '');
		}
		
		return $this->column;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the column this object describes.
	 * 
	 * @param  string
	 * @return self
	 */
	public function setColumn($col)
	{
		$this->column = $col;
		
		return $this;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the property name of the column this object describes.
	 * 
	 * @return string
	 */
	public function getProperty()
	{
		return empty($this->property) ? $this->getColumn() : $this->property;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the property this descriptor describes.
	 * 
	 * @param  string
	 * @return self
	 */
	public function setProperty($prop)
	{
		$this->property = $prop;
		
		return $this;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the database column type of the described column.
	 * 
	 * If not set, this method uses the value of $this->data_type_default.
	 * 
	 * @return string
	 */
	public function getDataType()
	{
		return empty($this->data_type) ? Rdm_Descriptor::GENERIC : $this->data_type;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the database column type of the column this object describes.
	 * 
	 * @param  string
	 * @return self
	 */
	public function setDataType($type)
	{
		$this->data_type = $type;
		$this->data_type_object = null;
		
		return $this;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the data type object which handles the data type conversions and
	 * how filters works.
	 * 
	 * @return Rdm_Descriptor_Type_Generic
	 */
	public function getDataTypeObject()
	{
		if($this->data_type_object)
		{
			return $this->data_type_object;
		}
		else
		{
			return $this->data_type_object = $this->parent_descriptor->getDataTypeObject($this);
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the data length for the column this object describes.
	 * 
	 * If not set, this method uses the value of $this->data_length_default.
	 * 
	 * @return int
	 */
	public function getDataLength()
	{
		return empty($this->data_length) ? null : $this->data_length;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the data length for the column this object describes.
	 * 
	 * @param  int
	 * @return self
	 */
	public function setDataLength($length)
	{
		$this->data_length = $length;
		
		return $this;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns true if this column can be used in an INSERT query.
	 * 
	 * @return bool
	 */
	public function isInsertable()
	{
		return $this->insertable;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets if this column can be used in an INSERT query.
	 * 
	 * @param  bool
	 * @return self
	 */
	public function setInsertable($value)
	{
		$this->insertable = $value;
		
		return $this;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns true if this column can be used in an UPDATE query.
	 * 
	 * @return bool
	 */
	public function isUpdatable()
	{
		return $this->updatable;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets if this column can be used in an UPDATE query.
	 * 
	 * @param  bool
	 * @return self
	 */
	public function setUpdatable($value)
	{
		$this->updatable = $value;
		
		return $this;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets if this column should be fetched from the database after an insert
	 * has been run.
	 * 
	 * By default, setting this switch to true will change the status of this
	 * column to disallow inserts.
	 * To avoid this, call setInsertable(true) after the call to setLoadAfterInsert().
	 * 
	 * @param  boolean|int  Boolean yes or no, or an integer constant from Rdm_Descriptor
	 *                      Boolean yes gives Rdm_Descriptor::PLAIN_COLUMN.
	 * @return self
	 */
	public function setLoadAfterInsert($value = true)
	{
		if($value === true)
		{
			$value = Rdm_Descriptor::PLAIN_COLUMN;
		}
		
		// Set insertable to false, to prevent inserting it because we're going to fetch it after insert
		if($value === Rdm_Descriptor::PLAIN_COLUMN)
		{
			$this->insertable = false;
		}
		
		$this->load_after_insert = $value;
		
		return $this;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the value of the load after insert setting.
	 * 
	 * @return boolean
	 */
	public function getLoadAfterInsert()
	{
		return $this->load_after_insert;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the name of the column as it is named in PHP (NOT IN THE FINAL SQL), including the table alias.
	 * 
	 * @param  string
	 * @return string
	 */
	public function getLocalColumn($table_alias)
	{
		return $table_alias.'.'.$this->getProperty();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the source column name (ie. as it looks in the final SQL), including the table alias.
	 * 
	 * @param  string
	 * @return string
	 */
	public function getSourceColumn($table_alias)
	{
		return $table_alias.'.'.$this->getColumn();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns a fragment which selects the column and aliases it properly.
	 * 
	 * @param  string			Passed through Rdm_Adapter::protectIdentifiers()
	 * @param  string			Passed through Rdm_Adapter::protectIdentifiers()
	 * @param  Rdm_Adapter
	 * @return string
	 */
	public function getSelectCode($table, $alias, Rdm_Adapter $db)
	{
		return $db->protectIdentifiers($this->getSourceColumn($table)).' AS '.$db->protectIdentifiers($alias.'_'.$this->getProperty());
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns a piece of code which fetches the data from a result row and inserts it
	 * into an instance of the described object.
	 * 
	 * Usually the data always exists on the result object.
	 * 
	 * Example of generated code:
	 * <code>
	 * // params: $object_var = '$obj', $data_var = '$row', $data_prefix_var = '$alias'
	 * $obj->id = (Int) $row->{$alias.'__id'};
	 * </code>
	 * 
	 * @param  string	The name of the variable holding an instance of the described object.
	 * @param  string	The name of the variable holding an instance of StdClass, containing the row data.
	 * @param  string	The name of the variable holding a prefix for the column name
	 * @return string
	 */
	public function getFromDataToObjectCode($object_var, $data_var, $data_prefix_var)
	{
		return $this->getAssignToObjectCode($object_var, $this->getDataTypeObject()->getCastToPhpCode($this->getFromDataCode($data_var, $data_prefix_var))).';';
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns a short piece of a statement which references the column value in the $data_var.
	 * 
	 * Example of generated code:
	 * <code>
	 * // params: $data_var = '$row', $data_prefix_var = '$alias'
	 * $row->{$alias.'__id'}
	 * </code>
	 * 
	 * @param  string	The name of the variable holding an instance of StdClass, containing the row data.
	 * @param  string	The name of the variable holding a prefix for the column name
	 * @return string
	 */
	public function getFromDataCode($data_var, $data_prefix_var)
	{
		return $data_var.'->{'.$data_prefix_var.'.\'_'.$this->getProperty().'\'}';
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns a piece of code which fetches the data from an instance of the described
	 * object and inserts it into an array, with the column name as key.
	 * 
	 * Only assign data if the value exists on the described object, otherwise
	 * do not assign anything to the $dest_var.
	 * 
	 * Example of generated code:
	 * <code>
	 * // params: $object_var = '$obj', $dest_var = '$data'
	 * isset($obj->id) && $data['PK_id'] = (Int) $obj->id;
	 * </code>
	 * 
	 * Note: Only assign the columns which are allowed to be updated!
	 * 
	 * @param  string	The name of the variable holding an instance of the described object.
	 * @param  string	The name of the variable holding an associative array to assign the data to.
	 * @return string
	 */
	public function getFromObjectToDataCode($object_var, $dest_var)
	{
		return $dest_var.'[\''.$this->getColumn().'\'] = '.$this->getDataTypeObject()->getCastFromPhpCode($this->getFetchFromObjectCode($object_var)).';';
	}
	
	// ------------------------------------------------------------------------

	/**
	 * 
	 * 
	 * @return 
	 */
	public function getFromObjectToSetSQLValueCode($object_var)
	{
		return $this->getDataTypeObject()->getCastFromPhpCode($this->getFetchFromObjectCode($object_var));
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns a short piece of a statement which references the property value in $object_var.
	 * 
	 * Example of generated code:
	 * <code>
	 * // params: $object_var = '$obj'
	 * $obj->foo
	 * </code>
	 * 
	 * @param  string
	 * @return string
	 */
	public function getFetchFromObjectCode($object_var)
	{
		return $object_var.'->'.$this->getProperty();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns a piece of a statement which will assign data to the object.
	 * 
	 * Example of generated code:
	 * <code>
	 * // params $object_var = '$obj', $data_code = '$foo['test']'
	 * $obj->property = $foo['test'];
	 * </code>
	 * 
	 * @param  string	The object variable
	 * @param  string	The partial statement which returns the data (no ending semicolon)
	 * @return 
	 */
	public function getAssignToObjectCode($object_var, $data_code)
	{
		return $object_var.'->'.$this->getProperty().' = '.$data_code.';';
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the code which does assignments to columns and/or performs validation before insert.
	 * 
	 * @param  string
	 * @return string
	 */
	public function getPreInsertCode($data_var, $object_var)
	{
		// TODO: Add an option to generate columns with a callable (eg. dates)
		return '';
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the code which does assignments to columns after insert.
	 * 
	 * @param  string
	 * @return string
	 */
	public function getPostInsertCode($object_var)
	{
		// TODO: Add the generated option
		return '';
	}
}

/* End of file Column.php */
/* Location: ./lib/Rdm/Descriptor */