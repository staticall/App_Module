## App_Module_Abstract

[App_Module_Abstract] is a very simple wrapper for [Zend Framework](https://github.com/zendframework/)'s DB class.
It allows you to get records from database, create, update or even delete them. Also, it allows you to join related tables, filter results or apply sorting, all with simple extend

## Basics

I hope this will help you understand how it all works:

```php
<?php
class App_Module_Example extends App_Module_Abstract
{
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

* getRecords()
* getRowset()
* getTotalRecordsWithPagination()
* getTotalRecords()
* getPagination()
* createRow()
* updateRecord()
* updateRow()
* getRecord()
* getRow()
* getDefaultRow
* deleteRecord()
* deleteRow()
* doesRecordExists()
* prefilterVarTableName()
* getTable()
* getTableName()
* prepareSelect()
* applyWhere()
* prepareFilter()
* applyFilterRules()
* * filterId()
* * filterMultiple()
* * filterBoolean()
* * filterRange()
* * filterBetween()
* * filterDate()
* * filterString()
* * filterStringLike()
* * filterUrl()
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

## Issues

Have a bug? Please create an issue on GitHub!

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

Copyright 2011 staticall

Licensed under the Apache License, Version 2.0: http://www.apache.org/licenses/LICENSE-2.0
