## App_Module
1.14.1

**App_Module** is a very simple wrapper for [Zend Framework](https://github.com/zendframework/)'s DB class.
It allows you to get records from database, create, update or even delete them. Also, it allows you to join related tables, filter results or apply sorting, all with simple extend

## Basics

I hope this will help you understand how it all works:

```php
<?php
class App_Module_Example extends App_Module
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

## Hooks

In this version, Hooks added to create method. This allows you to simply override them, instead of creating new functions

```php
<?php
class App_Module_Example extends App_Module
{
  protected $_table = null;

  const STATUS_TEST    = 'test';
  const STATUS_EXAMPLE = 'example';

  public function __construct()
  {
    $this->_table = new Table_Example();
  }

  public function hookPreCreate($data, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(isset($data['id'])) unset($data['id']);
    if(!$data['status']) $data['status'] = self::STATUS_TEST;

    return $data;
  }

  public function hookPostCreate($row, $data, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(!$row) throw new App_Module_Example_Exception('Row not created, please, recheck data array passed and table');

    return $row;
  }
}
```

## Examples

**Creating new row**

```php
<?php
class App_Module_Example extends App_Module
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
class App_Module_Example extends App_Module
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

  // joinRelatedTables will be called automatically through App_Module::getRecords() method
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

https://github.com/staticall/App_Module/issues

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