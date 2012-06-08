## App_Module_Abstract

[App_Module_Abstract] is a very simple wrapper for [Zend Framework](https://github.com/zendframework/)'s DB class.
It allows you to get records from database, create, update or even delete them. Also, it allows you to join related tables, filter results or apply sorting, all with simple extend

## Basics

I hope this will help you understand how it all works:

```php
<?php
class App_Module_Example extends App_Module_Abstract
{
  protected $_table = null;

  public function __construct()
  {
    $this->_table = new Table_Example();
  }

  public function getExamples()
  {
    return $this->getRecords();
  }
}
```

As you can see, only thing you required to do - initialize table (or tables). After that, you can just call any desired function.
Please note, that you can't override main methods of the class (like getRecords(), getRowset(), etc.), but you can call then from other functions. I think it's much easier to create new function in your class, than use debug_backtrace() to found who override function you need.

## Methods

Methods description and parameters will be described later.

```php
* getRecords()
* getRowset()
* getTotalRecordsWithPagination()
* getTotalRecords()
* getPagination()
* createRow($data, $var_table_name = self::DEFAULT_VAR_TABLE_NAME) - create row filled with $data in $var_table_name
* updateRecord($id, $data, $var_table_name = self::DEFAULT_VAR_TABLE_NAME) - update record with id = $id with $data in $var_table_name
* updateRow($row, $data) - update row $row with $data
* getRecord()
* getRow()
* getDefaultRow
* deleteRecord()
* deleteRow($row) - delete row $row from database
* doesRecordExists($row_or_val, $exclude_row_or_val = null, $key = self::DEFAULT_KEY, $var_table_name = self::DEFAULT_VAR_TABLE_NAME) - checks if record ($row_or_val with key $key) or row ($row_or_val) exists in $var_table_name, excluding $exclude_row_or_val
* prefilterVarTableName($var_table_name = self::DEFAULT_VAR_TABLE_NAME) - prefilter $var_table_name, check if parameter got '_' in beginning, etc.
* getTable($var_table_name = self::DEFAULT_VAR_TABLE_NAME) - return table instance, stored in $var_table_name
* getTableName($var_table_name = self::DEFAULT_VAR_TABLE_NAME) - get table name from table, stored in $var_table_name
* prepareSelect($where = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME) - create new Zend_Db_Select object from table, stored in $var_table_name and applies to object condition $where
* applyWhere($select, $where = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME) - applies condition $where for table, stored in $var_table_name to instance of Zend_Db_Select $select
* prepareFilter()
* applyFilterRules()
* - filterId()
* - filterMultiple()
* - filterBoolean()
* - filterRange()
* - filterBetween()
* - filterDate()
* - filterString()
* - filterStringLike()
* - filterUrl()
* applyOrderAndLimit()
* joinRelatedTables()
* prefilterId()
* prefilterWhere()
* prefilterArray()
* prefilterRow()
* prefilterSelect()
* _replacePlaceholders()
* getConfig()
* getExceptionCode()
* prepareDate()
* loadData()
```

## Examples

**Creating new row**

```php
<?php
class App_Module_Example extends App_Module_Abstract
{
  protected $_table = null;

  public function __construct()
  {
    $this->_table = new Table_Example();
  }

  public function createExample($data)
  {
    return $this->createRow($data);
  }
}
```

**Join related tables with rowset**

```php
<?php
class App_Module_Example extends App_Module_Abstract
{
  protected $_table = null;

  public function __construct()
  {
    // create new table
    $this->_table = new Table_Example();
  }

  public function getExamplesFull($var_table_name)
  {
    // order by, limit, page, where, filter, is_full, var_table_name
    return $this->getRecords(null, null, null, array(), array(), true, $var_table_name);
  }

  // joinRelatedTables will be called automatically through App_Module_Abstract::getRecords() method
  public function joinRelatedTables($select, $select_fields = true, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    // prefilter table and select, in case we get incorrect instances of empty objects
    parent::joinRelatedTables($select, $select_fields, $var_table_name);
    $var_table_name = $this->prefilterVarTableName($var_table_name);

    // if we going to join few tables, Zend will throw some exceptions, we don't want that
    $select->setIntegrityCheck(false);

    // variable initialization
    $test_fields = array();

    if($select_fields)
    {
      $test_fields = array(
        'test__id'   => 'test.id',
        'test__data' => 'test.data',
      );
    }

    // creating new instance of related table
    $tbl_test = new Table_Tests();
    // condition to join tables
    $join_cond = 'test.id = '. self::PLACEHOLDER_TABLENAME .'.test_id';

    // replace placeholders, so instead of "self::PLACEHOLDER_TABLENAME .'.test_id'" we will get "'example.test_id'" or something similar
    $join_cond = $this->_replacePlaceholders($join_cond, $var_table_name);

    // join itself
    $select->joinLeft(
      array('test' => $tbl_test->getName()),
      $join_cond,
      $test_fields
    );

    return $select;
  }
}
```

## Issues

Have a bug? Please, create an issue on GitHub!

https://github.com/staticall/App_Module_Abstract/issues

## Versioning

Version will be in following format

`<major>.<minor>.<hotfix>`

`Major` is a big update (like structure changing, etc.);

`Minor` is a small update, which, usually, fix few bugs, adds new functions or expand current;

`Hotfix` is a tiny update, which fixes some critical error.

## Authors

**staticall**

+ https://github.com/staticall
+ https://twitter.com/staticall

## License

Copyright 2012 staticall

Licensed under the Apache License, Version 2.0: http://www.apache.org/licenses/LICENSE-2.0