<?php
/**
 * Class for work with SQL databases and performing common operations.
 *
 * @category   App
 * @package    App_Module
 * @copyright  Copyright 2012 staticall <staticall AT gmail DOT com>
 * @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License, Version 2.0
 */
class App_Module
{
  /**
   * @var <i>self::DEFAULT_INSTANCE_TABLE</i> Instance of <b>Zend_Db_Table_Abstract</b> object (or any DB Table related object)
   * @see self::DEFAULT_INSTANCE_TABLE
   * @see Zend_Db_Table_Abstract
   */
  protected $_table   = null;
  protected $_adapter = null;
  protected $_hooks   = array();

  const _EXCEPTION_CODE                   = 10050;
  const DEFAULT_INSTANCE_ROW              = 'Zend_Db_Table_Row_Abstract';
  const DEFAULT_INSTANCE_USER_ROW         = 'Row_User_Abstract';
  const DEFAULT_INSTANCE_TABLE            = 'Zend_Db_Table';
  const DEFAULT_INSTANCE_ROWSET           = 'Zend_Db_Table_Rowset';
  const DEFAULT_INSTANCE_ROWSET_PAGINATE  = 'Zend_Db_Table_Rowset';
  const DEFAULT_INSTANCE_SELECT           = 'Zend_Db_Select';
  const DEFAULT_INSTANCE_DATABASE_ADAPTER = 'Zend_Db_Adapter_Abstract';
  const DEFAULT_VAR_TABLE_NAME            = 'table';
  const DEFAULT_VAR_TABLE_NAME_SEPARATOR  = '!';
  const DEFAULT_KEY                       = 'id';
  const DEFAULT_KEY_GET                   = 'get_id';
  const DEFAULT_DATE                      = 'yyyy-MM-dd';
  const DEFAULT_TIME                      = 'HH:mm:ss';
  const DEFAULT_DATETIME                  = 'yyyy-MM-dd HH:mm:ss';
  const DEFAULT_DATE_STD                  = 'Y-m-d';
  const DEFAULT_TIME_STD                  = 'H:i:s';
  const DEFAULT_DATETIME_STD              = 'Y-m-d H:i:s';
  const PLACEHOLDER_TABLENAME             = '#TABLENAME#';

  const LOG_EMERGENCY = Zend_Log::EMERG;
  const LOG_ALERT     = Zend_Log::ALERT;
  const LOG_CRITICAL  = Zend_Log::CRIT;
  const LOG_ERROR     = Zend_Log::ERR;
  const LOG_WARNING   = Zend_Log::WARN;
  const LOG_NOTICE    = Zend_Log::NOTICE;
  const LOG_INFO      = Zend_Log::INFO;
  const LOG_DEBUG     = Zend_Log::DEBUG;

  /**
   * Get row from database and transform it into array. You may apply WHERE conditions
   *
   * @param <Mixed> $id_or_val ID or value of row, which you want to get from database
   * @param <String> $key Column name for SELECT.
   *   Will be converted to string.
   *   If $key is empty or null, value will be <i>self::DEFAULT_KEY</i>
   * @param <Array> $where Multidimensional array with WHERE conditions to apply
   * @param <Boolean> $is_full Should related tables be joined or not.
   *   Be advised, that some functions also load related data in '<b>getData()</b>' method if $is_full is true.
   *   If you choose true, then you can't use this row for 'update()', 'delete()', etc. methods unless you set row's 'setReadOnly' property to false
   * @param <String> $instanceof Instance of which object desired row should be
   * @param <String> $var_table_name Which table should be used
   * @return <Mixed> If Row exists, then return array from Row, else return <i>NULL</i>
   * @throws <App_Module_Exception_WrongData>
   * @see self::DEFAULT_KEY
   * @see getData()
   * @final
   */
  final public function getRecord($id_or_val, $key = self::DEFAULT_KEY, $where = array(), $is_full = false, $instanceof = self::DEFAULT_INSTANCE_ROW, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(!$key || !is_string($key)) $key = self::DEFAULT_KEY;
    if(!$instanceof) $instanceof = self::DEFAULT_INSTANCE_ROW;

    if(!is_null($id_or_val))
    {
      if(is_int($id_or_val))
      {
        try
        {
          $id_or_val = $this->prefilterId($id_or_val);
        }
        catch(App_Module_Exception $e)
        {
          $this->writeLog('Wrong value ("'. (int)$id_or_val .'") passed as ID');

          if(!$this->isDebug())
          {
            throw new App_Module_Exception_WrongData('Wrong value passed as ID');
          }
        }
      }
      else
      {
        $id_or_val = (string)$id_or_val;
      }
    }

    $data = array();
    $row = $this->getRow($id_or_val, $key, $where, $is_full, $var_table_name);

    try
    {
      $row = $this->prefilterRow($row, $instanceof);

      if(method_exists($row, 'getData'))
      {
        $data = $row->getData($is_full);
      }
      else
      {
        $data = $row->toArray();
      }
    }
    catch(App_Module_Exception $e)
    {
      $data = null;
    }

    return $data;
  }

  /**
   * Get row from database. You may apply WHERE conditions
   *
   * @param <Mixed> $id_or_val ID or value of row, which you want to get from database
   * @param <String> $key Column name for SELECT.
   *   Will be converted to string.
   *   If $key is empty or null, value will be <i>self::DEFAULT_KEY</i>
   * @param <Array> $where Multidimensional array with WHERE conditions to apply
   * @param <Boolean> $is_full Should related tables be joined or not.
   *   If you choose true, then you can't use this row for 'update()', 'delete()', etc. methods unless you set row's 'setReadOnly' property to false
   * @param <String> $var_table_name Which table should be used
   * @return <Mixed> <i>self::DEFAULT_INSTANCE_ROW</i> is Row exists, else return <i>NULL</i>
   * @see self::DEFAULT_KEY
   * @see self::DEFAULT_INSTANCE_ROW
   * @final
   */
  final public function getRow($id_or_val, $key = self::DEFAULT_KEY, $where = array(), $is_full = false, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(!$key || !is_string($key)) $key = self::DEFAULT_KEY;
    $key = (string)$key;

    if(!is_null($id_or_val))
    {
      if(is_int($id_or_val))
      {
        try
        {
          $id_or_val = $this->prefilterId($id_or_val);
        }
        catch(App_Module_Exception $e)
        {
          $this->writeLog('Wrong value ("'. (int)$id_or_val .'") passed as ID');

          if(!$this->isDebug())
          {
            throw new App_Module_Exception_WrongData('Wrong value passed as ID');
          }
        }
      }
      else
      {
        $id_or_val = (string)$id_or_val;
      }
    }

    $table_name = $this->getTableName($var_table_name);
    $table = $this->getTable($var_table_name);

    $where = $this->prefilterWhere($where);

    if(!$this->getActor()->isAdmin() && empty($where))
    {
      $add_is_deleted = 1;
    }

    if($id_or_val)
    {
      $where[] = array(
        'condition' => $table_name .'.'. $key .' = ?',
        'value'     => $id_or_val,
      );
    }

    if($table->isColumnExists($table::COLUMN_IS_DELETED) && $add_is_deleted)
    {
      $where[] = array(
        'condition' => $table_name .'.'. $table::COLUMN_IS_DELETED .' = ?',
        'value'     => 0,
      );
    }

    $select = $this->prepareSelect($where, $var_table_name);
    $select = $this->joinRelatedTables($select, $is_full, $var_table_name);

    $row = $table->fetchRow($select);

    if(isset($select)) unset($select);
    if(isset($table)) unset($table);

    return $row;
  }

  /**
   * Get row, marked as <i>Zend_Db_Table::COLUMN_IS_DEFAULT</i>
   *
   * @param <Array> $where Multidimensional array with WHERE conditions to apply
   * @param <Boolean> $is_full Should related tables be joined or not.
   *   Be advised, that some functions also load related data in '<b>getData()</b>' method if $is_full is true.
   *   If you choose true, then you can't use this row for 'update()', 'delete()', etc. methods unless you set row's 'setReadOnly' property to false
   * @param <Mixed> $var_table_name Which table should be used
   * @return <Mixed> <i>self::DEFAULT_INSTANCE_ROW</i> object, if row exists OR <i>NULL</i> if there is no such object
   * @throws <App_Module_Exception_NotExists>
   * @see Zend_Db_Table::COLUMN_IS_DEFAULT
   * @see getData()
   * @see self::DEFAULT_INSTANCE_ROW
   * @final
   */
  final public function getDefaultRow($where = array(), $is_full = false, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $where = $this->prefilterWhere($where);
    $is_full = ($is_full ? 1 : 0);
    $table = $this->getTable($var_table_name);

    if($table->isColumnExists($table::COLUMN_IS_DEFAULT))
    {
      if(isset($table)) unset($table);

      return $this->getRow(1, $table::COLUMN_IS_DEFAULT, $where, $is_full, $var_table_name);
    }

    throw new App_Module_Exception_NotExists('Column `'. $table::COLUMN_IS_DEFAULT .'` not exists');
  }

  /**
   * Return array with following structure:
   *   array(
   *     'records'      => array(), // Multidimensional array with records. Key will be <i>self::DEFAULT_KEY</i>, value - array with data, loaded via '<b>getData()</b>' method
   *     '__pagination' => array(), // Pagination array
   *   );
   *
   * @param <Mixed> $order_or_sorting ORDER BY value OR Sorting array like this:
   *   array(
   *     'order' => self::PLACEHOLDER_TABLENAME .'.id DESC',
   *     'limit' => 25,
   *     'page'  => 1,
   *   );
   * @param <Mixed> $limit_or_where How many records to get OR Multidimensional array with WHERE conditions to apply
   * @param <Mixed> $page_or_filter Current page OR Array with columns to filter via <b>prepareFilter()</b>
   * @param <Mixed> $where_or_is_full Multidimensional array with WHERE conditions to apply OR Add joined columns in <b>joinRelatedTables()</b> or not
   * @param <Mixed> $filter_or_var_table_name Array with columns to filter via <b>prepareFilter()</b> or Which table should be used
   * @param <Boolean> $is_full Add joined columns in <b>joinRelatedTables()</b> or not
   * @param <String> $var_table_name Which table should be used
   * @return <Array> Array with following structure:
   *   array(
   *     'records'      => array(), // Multidimensional array with records. Key will be <i>self::DEFAULT_KEY</i>, value - array with data, loaded via '<b>getData()</b>' method
   *     '__pagination' => array(), // Pagination array
   *   );
   * @see prepareFilter()
   * @see joinRelatedTables()
   * @see self::DEFAULT_KEY
   * @see Zend_Db_Table_Row_Abstract::getData()
   * @final
   */
  final public function getRecords($order_or_sorting = null, $limit_or_where = null, $page_or_filter = null, $where_or_is_full = array(), $filter_or_var_table_name = array(), $is_full = false, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $records = array();

    $rowset = $this->getRowset($order_or_sorting, $limit_or_where, $page_or_filter, $where_or_is_full, $filter_or_var_table_name, $is_full, $var_table_name);

    foreach($rowset as $row)
    {
      if(method_exists($row, 'getData'))
      {
        $data = $row->getData($is_full);
      }
      else
      {
        $data = $row->toArray();
      }

      if($row->isColumnExists(self::DEFAULT_KEY))
      {
        $_getter = self::DEFAULT_KEY_GET;
        $id = $row->$_getter();
        if(isset($_getter)) unset($_getter);
        $records[$id] = $data;
      }
      else
      {
        $records[] = $data;
      }
    }

    $result = array(
      'records'      => $records,
      '__pagination' => $rowset->pagination(),
    );

    if(isset($rowset)) unset($rowset);
    if(isset($records)) unset($records);

    return $result;
  }

  /**
   * Return <i>self::DEFAULT_INSTANCE_ROWSET</i> object filled with data from database
   *
   * @param <Mixed> $order_or_sorting ORDER BY value OR Sorting array like this:
   *   array(
   *     'order' => <i>self::PLACEHOLDER_TABLENAME</i> .'.id DESC',
   *     'limit' => 25,
   *     'page'  => 1,
   *   );
   * @param <Mixed> $limit_or_where How many records to get OR Multidimensional array with WHERE conditions to apply
   * @param <Mixed> $page_or_filter Current page OR Array with columns to filter via <b>prepareFilter()</b>
   * @param <Mixed> $where_or_is_full Multidimensional array with WHERE conditions to apply OR Add joined columns in <b>joinRelatedTables()</b> or not
   * @param <Mixed> $filter_or_var_table_name Array with columns to filter via <b>prepareFilter()</b> or Which table should be used
   * @param <Boolean> $is_full Add joined columns in <b>joinRelatedTables()</b> or not
   * @param <String> $var_table_name Which table should be used
   * @return <Array> Array with following structure:
   *   array(
   *     'records'      => array(), // Multidimensional array with records. Key will be <i>self::DEFAULT_KEY</i>, value - array with data, loaded via '<b>getData()</b>' method
   *     '__pagination' => array(), // Pagination array
   *   );
   * @see self::DEFAULT_INSTANCE_ROWSET
   * @see self::PLACEHOLDER_TABLENAME
   * @see prepareFilter()
   * @see joinRelatedTables()
   * @see self::DEFAULT_KEY
   * @see Zend_Db_Table_Row_Abstract::getData()
   * @final
   */
  final public function getRowset($order_or_sorting = null, $limit_or_where = null, $page_or_filter = null, $where_or_is_full = array(), $filter_or_var_table_name = array(), $is_full = false, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(!is_array($order_or_sorting))
    {
      $order = $order_or_sorting;
      if(!$limit_or_where) $limit = null;
      else $limit = (int)$limit_or_where;
      if(!$page_or_filter) $page = null;
      else $page = (int)$page_or_filter;

      $where = $this->prefilterWhere($where_or_is_full);
      $filter = $this->prefilterArray($filter_or_var_table_name);
    }
    else
    {
      if(!empty($order_or_sorting['order']))
      {
        $order = $order_or_sorting['order'];
        $limit = $order_or_sorting['limit'];
        $page  = $order_or_sorting['page'];
        $where = $this->prefilterWhere($limit_or_where);
        $filter = $this->prefilterArray($page_or_filter);
        $is_full = $where_or_is_full;

        if($filter_or_var_table_name)
        {
          $var_table_name = $filter_or_var_table_name;
        }
      }
      else
      {
        $order = $order_or_sorting;
        if(!$limit_or_where) $limit = null;
        else $limit = (int)$limit_or_where;
        if(!$page_or_filter) $page = null;
        else $page = (int)$page_or_filter;

        $where = $this->prefilterWhere($where_or_is_full);
        $filter = $this->prefilterArray($filter_or_var_table_name);
      }

      if(!$order) $order = null;
      if(!$limit) $limit = null;
      if(!$page) $page = null;
    }

    $is_full = ($is_full ? 1 : 0);
    $var_table_name = (string)$var_table_name;
    $select = $this->prepareSelect($where, $var_table_name);
    $select = $this->prepareFilter($select, $filter, $var_table_name);
    $select = $this->applyOrderAndLimit($select, $order, $limit, $page, $var_table_name);
    $select = $this->joinRelatedTables($select, $is_full, $var_table_name);

    $table = $this->getTable($var_table_name);

    $rowset = $table->fetchAll($select);

    $result = $this->getTotalRecordsWithPagination($rowset, $limit, $page, $where, $filter, $var_table_name);

    if(isset($select)) unset($select);
    if(isset($table)) unset($table);
    if(isset($rowset)) unset($rowset);

    return $result;
  }

  /**
   * Count total records from rowset object and return paginated result
   *
   * @param <self::DEFAULT_INSTANCE_ROWSET> $rowset Rowset object
   * @param <Mixed> $limit How many records to get
   * @param <Mixed> $page Current page
   * @param <Array> $where Array with WHERE conditions to apply
   * @param <Array> $filter Array with columns to filter via <i>prepareFilter()</i>
   * @param <Mixed> $var_table_name Which table should be used
   * @return type
   * @see self::DEFAULT_INSTANCE_ROWSET
   * @see prepareFilter()
   * @final
   */
  final public function getTotalRecordsWithPagination($rowset, $limit = null, $page = null, $where = array(), $filter = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $where = $this->prefilterWhere($where);
    $filter = $this->prefilterArray($filter);

    if(!$this->isObjectAndInstance($rowset, self::DEFAULT_INSTANCE_ROWSET))
    {
      throw new App_Module_Exception_NotObject('Passed rowset must be an object with instance of '. self::DEFAULT_INSTANCE_ROWSET, $this->getExceptionCode(18));
    }

    // Temporary solution: Is null passed as $limit, then we count rowset, instead we duplicate query to database
    if($limit)
    {
      $total = $this->getTotalRecords($where, $filter, $var_table_name);
    }
    else
    {
      $total = $rowset->count('*');
    }

    $result = $this->getPagination($rowset, $total, $page, $limit);

    if(isset($total))  unset($total);
    if(isset($rowset)) unset($rowset);
    if(isset($where))  unset($where);
    if(isset($filter)) unset($filter);

    return $result;
  }

  /**
   * Return amount of rows which match WHERE conditions and filter
   *
   * @param <Array> $where Multidimensional array with WHERE conditions to apply
   * @param <Array> $filter Array for filter purposes
   * @param <String> $var_table_name Which table should be used
   * @return <Integer> Total records, matcing WHERE conditions and filter
   * @final
   */
  final public function getTotalRecords($where = array(), $filter = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $where = $this->prefilterWhere($where);
    $filter = $this->prefilterArray($filter);
    $table = $this->getTable($var_table_name);

    $select = $this->prepareSelect($where, $var_table_name);
    $select = $this->prepareFilter($select, $filter, $var_table_name);
    $select = $this->joinRelatedTables($select, true, $var_table_name);

    $count = $table->fetchAll($select)->count('*');

    if(isset($select)) unset($select);
    if(isset($table)) unset($table);
    if(isset($where)) unset($where);

    return $count;
  }

  /**
   * Prepare pagination in any format you want. You MUST override this function
   *
   * @param <self::DEFAULT_INSTANCE_ROWSET> $rowset Rowset, which will be paginated
   * @param <Integer> $total Total records, taken from getTotalRecords()
   * @param <Mixed> $page Current page
   * @param <Mixed> $limit How many records to get
   * @return <self::DEFAULT_INSTANCE_ROWSET_PAGINATE>
   * @see self::DEFAULT_INSTANCE_ROWSET
   * @see getTotalRecords()
   * @see self::DEFAULT_INSTANCE_ROWSET_PAGINATE
   */
  public function getPagination($rowset, $total, $page = null, $limit = null)
  {
    return array(
      'page'     => $page,
      'per_page' => $limit,
      'total'    => $total,
    );
  }

  /**
   * Create new row in table, stored in <b>$var_table_name</b>. If you overrided $this->validation method, then validation will be called
   *
   * @param <Array> $data Data, which row must be consist from
   * @param <String> $var_table_name Which table should be used
   * @return <Object>
   * @final
   */
  final public function createRow($data, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    /**
     * If method 'hookPreCreate' exists, it will be called, passing data array and $var_table_name
     *
     * Usually, this method should return data array, but it depends on your own needs
     */
    if(method_exists($this, 'hookPreCreate'))
    {
      $data = $this->hookPreCreate($data, $var_table_name);
    }

    //$data = $this->validate($data, 1, null, null, $var_table_name);

    $table = $this->getTable($var_table_name);

    try
    {
      $row = $table->createRow($data);
      $row->save();
    }
    catch(Exception $e)
    {
      $this->writeLog('Exception catched while trying to create new row, exception message - '. $e->getMessage(), self::LOG_WARNING, $e);

      $row = null;
    }

    /**
     * If method 'hookPostCreate' exists, it will be called, passing newely created row, data array and $var_table_name
     *
     * Usually, this method should return row object, but you can use any methods you want
     */
    if(method_exists($this, 'hookPostCreate'))
    {
      $row_post = $this->hookPostCreate($row, $data, $var_table_name);

      // In case someone forget to return $row object in hookPostCreate method
      if($row_post) $row = $row_post;
      if(isset($row_post)) unset($row_post);
    }

    return $row;
  }

  /**
   * Method, which will be called before creating row in createRow() method
   *
   * @param <Array> $data Data, passed to createRow() method
   * @param <String> $var_table_name Which table to use
   * @return <Mixed> Usually, this method should return row object, but you can use any methods you want
   * @see createRow()
   */
  public function hookPreCreate($data, $var_table_name = self::PLACEHOLDER_TABLENAME)
  {
    $data = $this->prefilterArray($data);
    if(isset($data[self::DEFAULT_KEY])) unset($data[self::DEFAULT_KEY]);

    if(empty($data))
    {
      throw new App_Module_Exception_DataRequired('Data required for this operation', $this->getExceptionCode(15));
    }

    $data['created'] = date(self::DEFAULT_DATETIME_STD);

    return $data;
  }

  /**
   * Method, which will be called after row is created in createRow() method
   *
   * @param <Mixed> $row Newely created row object or, possibly, NULL, if any errors were triggered, during <i>Zend_Db_Table::createRow()</i> method
   * @param <Array> $data Data, passed to createRow() method
   * @param <String> $var_table_name Which table to use
   * @return <Mixed> Usually, this method should return row object
   * @see Zend_Db_Table::createRow()
   */
  public function hookPostCreate($row, $data, $var_table_name = self::PLACEHOLDER_TABLENAME)
  {
    return $row;
  }

  /**
   * Updated record with $data by ID $id
   *
   * @param <Integer> $id ID of record, which must be updated
   * @param <Array> $data Data for update. Data will be validated via 'validate' method. If ID passed in data, it will be erased, due to data integrity
   * @param <Mixed> $var_table_name Which table should be used
   * @return <Mixed> Object is row updated or null if any error occurred
   * @final
   */
  final public function updateRecord($id, $data, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $id = $this->prefilterId($id);
    $row = $this->getRow($id, null, array(), false, $var_table_name);

    return $this->updateRow($row, $data);
  }

  /**
   * Update $row with $data
   *
   * @param <Object> $row Row, which should be updated
   * @param <Array> $data Data for update. Data will be validated via 'validate' method. If ID passed in data, it will be erased, due to data integrity
   * @return <Mixed> Object is row updated or null if any error occurred
   * @final
   */
  final public function updateRow($row, $data)
  {
    $row = $this->prefilterRow($row);
    $data = $this->prefilterArray($data);

    $data = $this->validate($data, 0, $row);
    if(isset($data['id'])) unset($data['id']);
    $_getter = self::DEFAULT_KEY_GET;
    $data[self::DEFAULT_KEY] = $row->$_getter();
    unset($_getter);
    if(isset($data['created'])) unset($data['created']);
    $data['updated'] = date(self::DEFAULT_DATETIME_STD);

    try
    {
      $row->setFromArray($data);
      $row->save();
    }
    catch(Exception $e)
    {
      $this->writeLog('Exception catched while trying to update row with new data, exception message - '. $e->getMessage(), self::LOG_WARNING, $e);

      $row = null;
    }

    return $row;
  }

  /**
   * Delete record from database
   *
   * @param <Mixed> $id_or_val ID or value to get row from database (for example, <b>$id_or_val</b> = 'test', <b>$key</b> = 'descr' - result: %table%.descr = 'test')
   * @param <Mixed> $key Column name for SELECT.
   *   Will be converted to string.
   *   If <b>$key</b> is empty or null, value will be <i>self::DEFAULT_KEY</i>
   * @param <Array> $where Multidimensional array with WHERE conditions to apply
   * @param <Mixed> $var_table_name Which table should be used
   * @return <Mixed> If row $row only marked as deleted, then will be returned row, else will be returned amount of deleted rows
   * @see self::DEFAULT_KEY
   * @final
   */
  final public function deleteRecord($id_or_val, $key = self::DEFAULT_KEY, $where = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $row = $this->getRow($id_or_val, $key, $where, false, $var_table_name);

    return $this->deleteRow($row);
  }

  /**
   * Mark row as deleted or delete row from database completely (according to your extended version of <b>Zend_Db_Table_Row_Abstract</b>)
   *
   * @param <self::DEFAULT_INSTANCE_ROW> $row Row to delete or mark as deleted
   * @return <Mixed> If row <b>$row</b> only marked as deleted, then will be returned row, else will be returned amount of deleted rows
   * @see Zend_Db_Table_Row_Abstract
   * @see self::DEFAULT_INSTANCE_ROW
   * @final
   */
  final public function deleteRow($row)
  {
    $row = $this->prefilterRow($row);

    return $row->delete();
  }

  /**
   * Mark row as deleted or delete row from database completely (according to your extended version of <b>Zend_Db_Table_Row_Abstract</b>)
   *
   * @param <self::DEFAULT_INSTANCE_ROW> $row Row to delete or mark as deleted
   * @return <Mixed> If row <b>$row</b> only marked as deleted, then will be returned row, else will be returned amount of deleted rows
   * @see Zend_Db_Table_Row_Abstract
   * @see self::DEFAULT_INSTANCE_ROW
   * @final
   */
  final public function forceDeleteRow($row)
  {
    $row = $this->prefilterRow($row);

    return $row->delete(1);
  }

  /**
   *
   * @param type $row_or_val
   * @param type $exclude_row_or_val
   * @param type $key
   * @param <Mixed> $var_table_name Which table should be used
   * @return type
   * @final
   */
  final public function doesRecordExists($row_or_val, $exclude_row_or_val = null, $key = self::DEFAULT_KEY, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(!$key || empty($key)) $key = self::DEFAULT_KEY;

    $table = $this->getTable($var_table_name);

    $val = null;
    $val_exclude = null;

    if(is_int($row_or_val) && $row_or_val > 0)
    {
      $val = $row_or_val;
    }
    elseif($this->isObjectAndInstance($row_or_val, self::DEFAULT_INSTANCE_ROW))
    {
      $_getter = self::DEFAULT_KEY_GET;
      $val = $row_or_val->$_getter();
      if(isset($_getter)) unset($_getter);

      $key = $this->prefilterArray($key);

      foreach($key as $k)
      {
        if(!$row_or_val->isColumnExists($k))
        {
          throw new App_Module_Exception('doesRecordExists - column `'. $k .'` not exists in row "'. get_class($row_or_val) .'"', $this->getExceptionCode(25));
        }
      }
    }
    elseif(is_string($row_or_val))
    {
      $val = $row_or_val;
    }
    else
    {
      throw new App_Module_Exception('Incorrect parameter received - $row_or_val', $this->getExceptionCode(27));
    }

    $where = array();

    if(is_array($key))
    {
      $_where_or = array();

      foreach($key as $k)
      {
        $_where_or[] = array(
          'condition' => self::PLACEHOLDER_TABLENAME .'.'. $k .' = ?',
          'value'     => $val,
        );
      }

      $where = $this->prepareWhereOr($_where_or, $var_table_name);
    }
    else
    {
      $where[] = array(
        'condition' => self::PLACEHOLDER_TABLENAME .'.'. $key .' = ?',
        'value'     => $val,
      );
    }

    if(!$exclude_row_or_val)
    {
      if(is_int($exclude_row_or_val) && $exclude_row_or_val > 0)
      {
        $val_exclude = $exclude_row_or_val;
      }
      elseif($this->isObjectAndInstance($exclude_row_or_val, self::DEFAULT_INSTANCE_ROW))
      {
        $getter = 'get_'. self::DEFAULT_KEY;
        $val_exclude = $exclude_row_or_val->$getter();
      }
      elseif(is_array($exclude_row_or_val))
      {
        $val_exclude = $exclude_row_or_val[self::DEFAULT_KEY];
      }
      elseif(is_string($exclude_row_or_val))
      {
        $val_exclude = $exclude_row_or_val;
      }
      else
      {
        $val_exclude = null;
      }
    }

    if($val_exclude)
    {
      $where[] = array(
        'condition' => self::PLACEHOLDER_TABLENAME .'.'. self::DEFAULT_KEY .' != ?',
        'value'     => $val_exclude,
      );
    }

    $select = $this->prepareSelect($where, $var_table_name);

    $row = $table->fetchRow($select);

    unset($select);
    unset($row_or_val);
    if(isset($exclude_row_or_val)) unset($exclude_row_or_val);

    return !is_null($row);
  }

  /**
   * Prefilter $var_table_name. Convert short name (%tablename%, _%tablename%, table_%tablename%) to full (_table_%tablename%)
   *
   * @param <String> $var_table_name Value to prefilter
   * @return <String> Valid $var_table_name
   * @final
   */
  final public function prefilterVarTableName($var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $var_table_name = (string)$var_table_name;
    if(!$var_table_name) $var_table_name = self::DEFAULT_VAR_TABLE_NAME;

    if(strpos($var_table_name, '_') !== false)
    {
      if(strpos($var_table_name, '_') !== 0)
      {
        $var_table_name = '_'. $var_table_name;
      }
    }
    else
    {
      $var_table_name = '_'. $var_table_name;
    }

    if(strpos($var_table_name, 'table') === false)
    {
      $var_table_name = '_table'. $var_table_name;
    }

    return $var_table_name;
  }

  /**
   * Return table object, stored under <b>$var_table_name</b>
   *
   * @param <Mixed> $var_table_name Which table should be used
   * @return <Mixed> Table object, if $this->$var_table_name exists, else throws exception
   * @throws App_Module_Exception If table not object and instance of <i>self::DEFAULT_INSTANCE_TABLE</i>
   * @final
   * @see self::DEFAULT_INSTANCE_TABLE
   */
  final public function getTable($var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if($this->isObjectAndInstance($var_table_name, self::DEFAULT_INSTANCE_TABLE))
    {
      return $var_table_name;
    }

    $var_table_name = $this->prefilterVarTableName($var_table_name);

    if(strpos($var_table_name, self::DEFAULT_VAR_TABLE_NAME_SEPARATOR) !== false)
    {
      list($var_table_name, $type) = explode(self::DEFAULT_VAR_TABLE_NAME_SEPARATOR, $var_table_name);
    }

    $table = $this->$var_table_name;

    if(!$this->isObjectAndInstance($table, self::DEFAULT_INSTANCE_TABLE))
    {
      throw new App_Module_Exception('Table not exists, not an object or not instance of '. self::DEFAULT_INSTANCE_TABLE, $this->getExceptionCode(155));
    }

    return $table;
  }

  /**
   * Validation function. Could be useful for data validation before writing in database (so you can always be sure, that only valid data will be written)
   *
   * @param <Array> $data Array to validate
   * @param <Boolean> $is_create Is you creating data or updating. Maybe you'll use different validation methods, based on updating you item or creating
   * @param <self::DEFAULT_INSTANCE_ROW> $row Row object. Can be useful, is you want to check some value in <b>$data</b> to be unique, but exclude value in <b>$row</b>
   * @param <String> $instanceof Check for <b>$row</b> object (if it's passed)
   * @param <Mixed> $var_table_name Which table should be used
   * @return <Mixed> If validation failed, null will be returned, else validated data array
   * @throws App_Module_Exception If <b>$instanceof</b> is not a valid string (@link isValidString())
   * @see self::DEFAULT_INSTANCE_ROW
   */
  public function validate($data, $is_create = false, $row = null, $instanceof = self::DEFAULT_INSTANCE_ROW, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if($row)
    {
      if(!$this->isValidString($instanceof))
      {
        throw new App_Module_Exception('$instanceof is not a valid string');
      }

      $instanceof = (string)$instanceof;
      if(!$instanceof) $instanceof = self::DEFAULT_INSTANCE_ROW;

      try
      {
        $row = $this->prefilterRow($row, $instanceof);
        $_getter = self::DEFAULT_KEY_GET;
        $data[self::DEFAULT_KEY] = $row->$_getter();
      }
      catch(App_Module_Exception $e)
      {
        return null;
      }
    }
    else
    {
      if(isset($data[self::DEFAULT_KEY])) unset($data[self::DEFAULT_KEY]);
    }

    return $data;
  }

  /**
   * Checks that passed argument is NOT resource, object or null
   *
   * @param <Mixed> $string Value to check
   * @return <Boolean> Is valid string or not
   * @final
   * @static
   */
  final static public function isValidString($string)
  {
    return !(is_resource($string) || is_object($string) || is_null($string));
  }

  /**
   * Get table name from table, stored in <b>$var_table_name</b>
   *
   * @param <Mixed> $var_table_name Which table should be used
   * @return <String> Table name
   * @throws App_Module_Exception If there is no such table
   * @throws App_Module_Exception Table name not defined (just in case)
   * @final
   */
  final public function getTableName($var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $table = null;

    if(is_string($var_table_name))
    {
      $table = $this->getTable($var_table_name);
    }
    elseif($this->isObjectAndInstance($var_table_name, self::DEFAULT_INSTANCE_TABLE))
    {
      $table = $var_table_name;
    }

    if(!$table)
    {
      throw new App_Module_Exception_NotExists('Table not exists', $this->getExceptionCode(22));
    }

    $table_name = null;
    $table_name = $table->getName();
    $table_name = (string)$table_name;

    if(!$table_name)
    {
      throw new App_Module_Exception_WrongData('Table name can\'t be empty', $this->getExceptionCode(20));
    }

    if(isset($table)) unset($table);

    return $table_name;
  }

  /**
   * Create new SELECT object with WHERE conditions applied for <b>$var_table_name table</b>
   *
   * @param <Array> $where WHERE conditions to apply
   * @param <Mixed> $var_table_name Which table should be used
   * @return <self::DEFAULT_INSTANCE_SELECT> SELECT object
   * @see self::DEFAULT_INSTANCE_SELECT
   * @final
   */
  final public function prepareSelect($where = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $where = $this->prefilterWhere($where);
    $table = $this->getTable($var_table_name);
    $table_name = $this->getTableName($var_table_name);

    $select = $table->select()->from(
      array(
        $table_name => $table_name,
      )
    );

    $select = $this->applyWhere($select, $where, $var_table_name);

    if(isset($table)) unset($table);
    if(isset($where)) unset($where);

    return $select;
  }

  /**
   * Returns database adapter. Useful for inline quoting
   *
   * @param <Mixed> $var_table_name If null passed, then users table will be used, else table stored in $var_table_name
   * @return <self::DEFAULT_INSTANCE_DATABASE_ADAPTER> Currently initialized database adapter
   * @see <self::DEFAULT_INSTANCE_DATABASE_ADAPTER>
   */
  public function getDatabaseAdapter($var_table_name = null)
  {
    $table = null;

    if($this->isObjectAndInstance($this->_adapter, self::DEFAULT_INSTANCE_DATABASE_ADAPTER))
    {
      return $this->_adapter;
    }

    if(!$var_table_name) $table = $this->getActor()->getTable();
    else $table = $this->getTable($var_table_name);

    $adapter = $table->getAdapter();
    $this->_adapter = $adapter;

    if(isset($table)) unset($table);
    if(isset($adapter)) unset($adapter);

    return $this->_adapter;
  }

  /**
   * Return array with related entries
   *
   * @param <Array> $from Special array with following structure:
   *  array(
   *    'columns' => array(
   *      %COLUMN_LIST%
   *    ),
   *    'value' => %STRING_WITH_KEYWORDS%,
   *  ),
   * @param <Mixed> $order_or_sorting Order string (for example, <i>self::PLACEHOLDER_TABLENAME</i> .'.id DESC') or sorting array
   * @param <Mixed> $limit_or_where Records limit or array with WHERE conditions
   * @param <Mixed> $page_or_var_table_name Page or which table to use
   * @param <Mixed> $where Array with WHERE conditions or empty array, if you passed pagination array as first parameter
   * @param <Boolean> $is_full Load related data or not
   * @param <Mixed> $var_table_name Which table to use
   * @return <Array> Array of related entries
   * @see self::PLACEHOLDER_TABLENAME
   */
  public function getRelatedEntries($from, $order_or_sorting = null, $limit_or_where = null, $page_or_var_table_name = null, $where = array(), $is_full = false, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $records = array();
    $rowset = $this->getRelatedEntriesRowset($from, $order_or_sorting, $limit_or_where, $page_or_var_table_name, $where, $is_full, $var_table_name);

    foreach($rowset as $row)
    {
      if(method_exists($row, 'getData'))
      {
        $data = $row->getData($is_full);
      }
      else
      {
        $data = $row->toArray();
      }

      if($row->isColumnExists(self::DEFAULT_KEY))
      {
        $_getter = self::DEFAULT_KEY_GET;
        $id = $row->$_getter();
        unset($_getter);
        $records[$id] = $data;
      }
      else
      {
        $records[] = $data;
      }
    }

    if(isset($rowset)) unset($rowset);

    return $records;
  }

  /**
   * Return rowset with related entries
   *
   * @param <Array> $from Special array with following structure:
   *  array(
   *    'columns' => array(
   *      %COLUMN_LIST%
   *    ),
   *    'value' => %STRING_WITH_KEYWORDS%,
   *  ),
   * @param <Mixed> $order_or_sorting Order string (for example, <i>self::PLACEHOLDER_TABLENAME</i> .'.id DESC') or sorting array
   * @param <Mixed> $limit_or_where Records limit or array with WHERE conditions
   * @param <Mixed> $page_or_var_table_name Page or which table to use
   * @param <Mixed> $where Array with WHERE conditions or empty array, if you passed pagination array as first parameter
   * @param <Boolean> $is_full Load related data or not
   * @param <Mixed> $var_table_name Which table to use
   * @return <self::DEFAULT_INSTANCE_ROWSET> Rowset with related entries
   * @throws <App_Module_Exception_InvalidStructure>
   * @throws <App_Module_Exception_WrongData>
   * @see self::PLACEHOLDER_TABLENAME
   * @see self::DEFAULT_INSTANCE_ROWSET
   */
  public function getRelatedEntriesRowset($from, $order_or_sorting = null, $limit_or_where = null, $page_or_var_table_name = null, $where = array(), $is_full = false, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(!is_array($order_or_sorting))
    {
      $order = $order_or_sorting;
      if(!$limit_or_where) $limit = null;
      else $limit = (int)$limit_or_where;
      if(!$page_or_var_table_name) $page = null;
      else $page = (int)$page_or_var_table_name;

      $where = $this->prefilterWhere($where);
    }
    else
    {
      if(!empty($order_or_sorting['order']))
      {
        $order = $order_or_sorting['order'];
        $limit = $order_or_sorting['limit'];
        $page  = $order_or_sorting['page'];
        $where = $this->prefilterWhere($limit_or_where);

        if($page_or_var_table_name)
        {
          $var_table_name = $page_or_var_table_name;
        }
      }
      else
      {
        $order = $order_or_sorting;
        if(!$limit_or_where) $limit = null;
        else $limit = (int)$limit_or_where;
        if(!$page_or_var_table_name) $page = null;
        else $page = (int)$page_or_var_table_name;

        $where = $this->prefilterWhere($where);
      }

      if(!$order) $order = null;
      if(!$limit) $limit = null;
      if(!$page) $page = null;
    }

    $var_table_name = (string)$var_table_name;
    $select = $this->prepareSelect($where, $var_table_name);
    $select = $this->resetSelectPart($select, Zend_Db_Select::FROM);

    $from = $this->prefilterArray($from, true);

    if(!$from['columns'] && $from['cols'])
    {
      $from['columns'] = $from['cols'];
      unset($from['cols']);
    }

    $from['columns'] = $this->prefilterArray($from['columns'], true);

    if(empty($from) || empty($from['columns']) || !is_string($from['value']) || !$from['value'])
    {
      throw new App_Module_Exception_InvalidStructure('From must be a valid array with following structure: "columns" => array of column to search, "value" => value to search revelancy');
    }

    foreach($from['columns'] as $k => $column)
    {
      if(is_array($column))
      {
        unset($from['columns'][$k]);

        continue;
      }

      $column = (string)$column;

      $r = array(
        'union',
        'select',
        'where',
      );

      $column = str_replace('', array_values($r), $column);

      if(!$column)
      {
        unset($from['columns'][$k]);
      }
    }

    if(is_array($from['value']) || !$from['value']) throw new App_Module_Exception_WrongData('Wrong value for FROM. It should be a string and not empty');

    $adapter = $this->getDatabaseAdapter();
    $table_name = $this->getTableName($var_table_name);

    $select->from(
      array(
        $table_name => $table_name,
      ),
      array(
        '*',
        'revelancy' => $adapter->quoteInto('MATCH('. implode(', ', $from['columns']) .') AGAINST(?)', $from['value']),
      )
    );

    $select->where('MATCH('. implode(', ', $from['columns']) .') AGAINST(?)', $from['value']);

    $select = $this->applyWhere($select, $where, $var_table_name);
    $order = $this->prefilterArray($order);
    $order = array_values($order);
    $order[-1] = 'revelancy DESC';
    ksort($order);
    $select = $this->applyOrderAndLimit($select, $order, $limit, $page, $var_table_name);

    $table = $this->getTable($var_table_name);

    $rowset = $table->fetchAll($select);

    if(isset($adapter)) unset($adapter);
    if(isset($table)) unset($table);

    return $rowset;
  }

  /**
   * Applies WHERE (AND) conditions to SELECT object
   *
   * @param <self::DEFAULT_INSTANCE_SELECT> $select Select object
   * @param <Array> $where Multidimensional array with WHERE conditions to apply
   * @param <Mixed> $var_table_name Which table should be used
   * @return <self::DEFAULT_INSTANCE_SELECT>
   * @throws <App_Module_Exception_WrongData>
   * @see self::DEFAULT_INSTANCE_SELECT
   * @final
   */
  final public function applyWhere($select, $where = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $select = $this->prefilterSelect($select, $var_table_name);
    $where = $this->prefilterWhere($where);
    $is_debug = $this->isDebug();

    if(!empty($where))
    {
      foreach($where as $condition)
      {
        $cond = $condition['condition'];

        if(is_array($condition['value']))
        {
          if(is_array($cond))
          {
            if(count($condition['value']) == count($cond))
            {
              $value = $condition['value'];
              $value = array_values($value);
              $cond = array_values($cond);
              $type = $condition['type'];

              if(is_array($type) && count($type) == count($cond))
              {
                $type = array_values($type);
              }
              else
              {
                throw new App_Module_Exception_WrongData('Inner type wrong format');
              }

              foreach($cond as $k => $cnd)
              {
                $cnd = (string)$cnd;
                $cnd = $this->_replacePlaceholders($cnd, $var_table_name);
                if(!$cnd || is_array($cnd)) throw new App_Module_Exception_WrongData('Inner condition empty or wrong type');

                try
                {
                  $select->where($cnd, $value[$k], $type[$k]);
                }
                catch(Zend_Db_Select_Exception $e)
                {
                  if(!$is_debug)
                  {
                    throw new App_Module_Exception_NotExists('Column "'. $cnd .'" not found in selected table, stored under "'. $var_table_name .'"');
                  }
                  else
                  {
                    $this->writeLog('Condition "'. $cnd .'" can\'t be applied (target column possibly not exists in selected table "'. $var_table_name .'"). Error: '. $e->getMessage(), self::LOG_WARNING);
                  }
                }
              }
            }
            else
            {
              throw new App_Module_Exception_WrongData('Wrong count for inner value');
            }
          }
          else
          {
            $cond = (string)$cond;
            $cond = $this->_replacePlaceholders($cond, $var_table_name);

            $select->where($cond, $condition['value'], $condition['type']);
          }
        }
        else
        {
          if(is_array(@$condition['type'])) throw new App_Module_Exception_WrongData('Type can\'t be an array in current situation');

          if(is_array($cond))
          {
            foreach($cond as $cnd)
            {
              if(!$cnd || is_array($cnd)) throw new App_Module_Exception_WrongData('Inner condition empty or wrong type');

              $cnd = $this->_replacePlaceholders($cnd, $var_table_name);

              $select->where($cnd, $condition['value'], $condition['type']);
            }
          }
          else
          {
            $cond = $this->_replacePlaceholders($cond, $var_table_name);

            $select->where($cond, @$condition['value'], @$condition['type']);
          }
        }
      }
    }

    return $select;
  }

  /**
   * Applies WHERE (OR) conditions to SELECT object. This function will not be called automatically.
   *
   * @param <Array> $where_or Multidimensional array with WHERE (OR) conditions to apply
   * @param <String> $var_table_name Which table should be used
   * @return <self::DEFAULT_INSTANCE_SELECT>
   * @throws <App_Module_Exception_DataRequired> Empty $where_or array received
   * @see self::DEFAULT_INSTANCE_SELECT
   * @final
   */
  final public function prepareWhereOr($where_or = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $where_or = $this->prefilterWhere($where_or);

    if(empty($where_or))
    {
      throw new App_Module_Exception_DataRequired('Empty array received instead of array with WHERE (OR) conditions');
    }

    $adapter = $this->getDatabaseAdapter($var_table_name);
    $where = array();
    $_where_or = array();

    foreach($where_or as $condition)
    {
      $cond = $condition['condition'];
      $cond = $this->_replacePlaceholders($cond, $var_table_name);
      $_where_or[] = '('. $adapter->quoteInto($cond, $condition['value'], $condition['type']) .')';
    }

    $where[] = array(
      'condition' => '('. implode(' OR ', $_where_or) .')',
      'value'     => '',
    );

    if(isset($where_or)) unset($where_or);
    if(isset($_where_or)) unset($_where_or);
    if(isset($adapter)) unset($adapter);

    return $where;
  }

  /**
   * Reset part or all parts of SELECT object
   *
   * @param <self::DEFAULT_INSTANCE_SELECT> $select Select object
   * @param <Mixed> $part What part to reset.
   *   If <b>$part</b> is empty or <i>NULL</i>, then all parts will be reseted
   *   @see self::DEFAULT_INSTANCE_SELECT::_partsInit
   * @return <self::DEFAULT_INSTANCE_SELECT>
   * @see self::DEFAULT_INSTANCE_SELECT
   * @final
   */
  final public function resetSelectPart($select, $part = null)
  {
    $select = $this->prefilterSelect($select);
    if(!$part) $part = null;
    $select = $select->reset($part);

    return $select;
  }

  /**
   * Add filter to <b>$select</b> object. By default, function just return <b>$select</b> object. This function must be overriden, in order to use
   *
   * @param <self::DEFAULT_INSTANCE_SELECT> $select SELECT object
   * @param <Array> $filter Array, where key is column to filter and value is required value (duuuh!)
   * @param <Mixed> $var_table_name Which table should be used
   * @return <self::DEFAULT_INSTANCE_SELECT> Prepared SELECT object
   * @see self::DEFAULT_INSTANCE_SELECT
   */
  public function prepareFilter($select, $filter = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    return $select;
  }

  /**
   * Return filter array, filled with rules values
   *
   * @param <Array> $filter Array with filter to apply
   * @param <Array> $rules Rules to apply
   * @param <Mixed> $var_table_name Which table should be used
   * @return <Array>
   * @throws <App_Module_Exception_WrongData> Incorrect value passed as rules array
   * @throws <App_Module_Exception_NotExists> Unknown filter method called
   * @final
   */
  final public function applyFilterRules($filter, $rules, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(empty($rules) || !$this->isMultiArray($rules))
    {
      throw new App_Module_Exception_WrongData('Rules empty or not multidimensional array');
    }

    $conditions = array();
    $filter = $this->prefilterArray($filter);

    foreach($rules as $type => $columns)
    {
      if(!empty($columns))
      {
        $type_method = explode('-', $type);

        foreach($type_method as $k => $v)
        {
          $type_method[$k] = ucfirst($v);
        }

        $type_method = 'filter'. implode('', $type_method);

        if(method_exists($this, $type_method))
        {
          foreach($columns as $_column => $column)
          {
            $parameters = array();
            $key = $column;

            $_cond = null;

            if(is_array($column))
            {
              $parameters = $column['parameters'];
              $parameters = $this->prefilterArray($parameters);

              if($column['apply_not'])
              {
                $parameters['apply_not'] = ($column['apply_not'] ? 1 : 0);
              }

              if(isset($column['apply_not'])) unset($column['apply_not']);

              if($column['is_datetime'])
              {
                $parameters['is_datetime'] = ($column['is_datetime'] ? 1 : 0);
              }

              if(isset($column['is_datetime'])) unset($column['is_datetime']);

              if($column['validation'])
              {
                $parameters['validation'] = ($column['validation'] ? 1 : 0);
              }

              if(isset($column['validation'])) unset($column['validation']);

              if($column['var_table_name'])
              {
                $var_table_name = $column['var_table_name'];
              }

              if($column['column'])
              {
                $t_column = $column['column'];
                $t_column = (string)$t_column;

                if($column['key'])
                {
                  $key = $column['key'];
                  $key = (string)$key;

                  $column = array(
                    'column' => $t_column,
                    'key'    => $key,
                  );
                }
                else
                {
                  $column = (string)$t_column;
                }
              }
              else
              {
                $column = $_column;
              }

              if(!$key || is_array($key)) $key = $_column;

              if(@$filter[$key] && !@empty($filter[$key]))
              {
                if(!$parameters)
                {
                  $_cond = $this->$type_method($filter[$key], $column, $var_table_name);
                }
                else
                {
                  $_cond = $this->$type_method($filter[$key], $column, $parameters, $var_table_name);
                }
              }
            }
            else
            {
              if($filter[$key] && !empty($filter[$key]))
              {
                $_cond = $this->$type_method($filter[$key], $key, $var_table_name);
              }
            }

            if($_cond)
            {
              if($this->isMultiArray($_cond))
              {
                foreach($_cond as $_cond_children)
                {
                  $conditions[] = $_cond_children;
                }
              }
              else
              {
                $conditions[] = $_cond;
              }
            }
          }
        }
        else
        {
          throw new App_Module_Exception_NotExists('Unknown filtration method "'. $type_method .'" requested');
        }
      }
    }

    return $conditions;
  }

  /**
   *
   * @param type $filter_id
   * @param type $column
   * @param type $parameters
   * @param type $var_table_name
   * @return string
   * @final
   */
  final public function filterId($filter_id, $column, $parameters = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $condition = array();

    if(is_array($column))
    {
      $key = $column['key'];
      $_column = $column['column'];
      $column = '';
      $column = $_column;
    }
    else
    {
      $column = (string)$column;
    }

    if(!$key) $key = $column;

    $parameters = $this->prefilterArray($parameters);
    $is_not = $parameters['apply_not'];
    $is_not = ($is_not ? 1 : 0);
    $table = $this->getTable($var_table_name);

    if(!$column || !$table->isColumnExists($column))
    {
      throw new App_Module_Exception('Column name not set or not exists');
    }

    try
    {
      $filter_id = $this->prefilterId($filter_id);

      if($is_not)
      {
        $condition = array(
          'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' != ?',
          'value'     => $filter_id,
        );
      }
      else
      {
        $condition = array(
          'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' = ?',
          'value'     => $filter_id,
        );
      }
    }
    catch(App_Module_Exception $e)
    {
    }

    if(empty($condition)) $condition = null;

    if(isset($table)) unset($table);

    return $condition;
  }

  /**
   *
   * @param type $filter_value
   * @param type $column
   * @param type $parameters
   * @param type $var_table_name
   * @return string
   * @final
   */
  final public function filterMultiple($filter_value, $column, $parameters = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $conditions = array();
    $parameters = $this->prefilterArray($parameters);
    $table = $this->getTable($var_table_name);

    if(is_array($column))
    {
      $key = $column['key'];
      $_column = $column['column'];
      $column = '';
      $column = $_column;
    }
    else
    {
      $column = (string)$column;
    }

    if(!$key) $key = $column;
    $is_not = $parameters['apply_not'];
    $is_not = ($is_not ? 1 : 0);
    $validation = $parameters['validation'];
    $adapter = $this->getDatabaseAdapter($var_table_name);

    if(!$filter_value)
    {
      return null;
    }

    if(!$column || !$table->isColumnExists($column))
    {
      throw new App_Module_Exception('Column name not set or not exists');
    }

    if(is_array($filter_value))
    {
      $_where_or = array();

      foreach ($filter_value as $_value)
      {
        if($_value)
        {
          if(!empty($validation))
          {
            if(Zend_Validate::is($_value, 'InArray', array($validation)))
            {
              if($is_not)
              {
                $_where_or[] = $adapter->quoteInto('('. self::PLACEHOLDER_TABLENAME .'.'. $column .' != ?)', $_value);
              }
              else
              {
                $_where_or[] = $adapter->quoteInto('('. self::PLACEHOLDER_TABLENAME .'.'. $column .' = ?)', $_value);
              }
            }
          }
          else
          {
            if($is_not)
            {
              $_where_or[] = $adapter->quoteInto('('. self::PLACEHOLDER_TABLENAME .'.'. $column .' != ?)', $_value);
            }
            else
            {
              $_where_or[] = $adapter->quoteInto('('. self::PLACEHOLDER_TABLENAME .'.'. $column .' = ?)', $_value);
            }
          }
        }
      }

      if(!empty($_where_or))
      {
        $conditions = array(
          'condition' => '('. implode(' OR ', $_where_or) .')',
          'value'     => '',
        );
      }
    }
    else
    {
      $filter_value = (string)$filter_value;

      if($filter_value)
      {
        if(!empty($validation))
        {
          if(Zend_Validate::is($filter_value, 'InArray', array($validation)))
          {
            if($is_not)
            {
              $conditions = array(
                'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' != ?',
                'value'     => $filter_value,
              );
            }
            else
            {
              $conditions = array(
                'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' = ?',
                'value'     => $filter_value,
              );
            }
          }
        }
        else
        {
          if($is_not)
          {
            $conditions = array(
              'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' != ?',
              'value'     => $filter_value,
            );
          }
          else
          {
            $conditions = array(
              'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' = ?',
              'value'     => $filter_value,
            );
          }
        }
      }
    }

    if(empty($conditions) || !is_array($conditions))
    {
      $conditions = null;
    }

    if(isset($table)) unset($table);
    if(isset($parameters)) unset($parameters);

    return $conditions;
  }

  /**
   *
   * @param type $filter_value
   * @param type $column
   * @param type $parameters
   * @param type $var_table_name
   * @return string
   * @final
   */
  final public function filterBoolean($filter_value, $column, $parameters = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $filter_value = ($filter_value ? 1 : 0);

    if(is_array($column))
    {
      $key = $column['key'];
      $_column = $column['column'];
      $column = '';
      $column = $_column;
    }
    else
    {
      $column = (string)$column;
    }

    if(!$key) $key = $column;
    $table = $this->getTable($var_table_name);
    $parameters = $this->prefilterArray($parameters);
    $is_not = $parameters['apply_not'];
    $is_not = ($is_not ? 1 : 0);

    if(!$column || !$table->isColumnExists($column))
    {
      throw new App_Module_Exception('Column name not set or not exists');
    }

    if($is_not)
    {
      $condition = array(
        'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' != ?',
        'value'     => $filter_value,
      );
    }
    else
    {
      $condition = array(
        'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' = ?',
        'value'     => $filter_value,
      );
    }

    if(isset($table)) unset($table);

    return $condition;
  }

  /**
   *
   * @param type $filter_range
   * @param type $column
   * @param type $parameters
   * @param type $var_table_name
   * @return string
   * @final
   */
  final public function filterRange($filter_range, $column, $parameters = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(!$filter_range) return array();

    if(is_array($filter_range))
    {
      $filter_range = implode(', ', $filter_range);
    }

    if(is_array($column))
    {
      $key = $column['key'];
      $_column = $column['column'];
      $column = '';
      $column = $_column;
    }
    else
    {
      $column = (string)$column;
    }

    if(!$key) $key = $column;

    $table = $this->getTable($var_table_name);
    $parameters = $this->prefilterArray($parameters);
    $is_not = $parameters['apply_not'];
    $is_not = ($is_not ? 1 : 0);
    $condition = null;

    if(!$column || !$table->isColumnExists($column))
    {
      throw new App_Module_Exception('Column name not set or not exists');
    }

    $filter_range_orig = $filter_range;
    $ranges = array();
    $replace = array(
      '–'       => '-',
      ' - '     => '-',
      '&ndash;' => '-',
    );
    $filter_range = preg_replace("'\s+'", ' ', $filter_range);
    $filter_range = preg_replace("[\n|\r]", ',', $filter_range);
    $filter_range = explode(',', $filter_range);

    if(is_array($filter_range) && count($filter_range))
    {
      foreach($filter_range as $k => $id_range)
      {
        $id_range = str_replace(array_keys($replace), array_values($replace), $id_range);
        $id_range = @trim($id_range);

        if(strpos($id_range, '-') === false)
        {
          unset($filter_range[$k]);
          continue;
        }

        list($val['left'], $val['right']) = explode("-", $id_range);
        $val['left'] = (int)$val['left'];
        $val['right'] = (int)$val['right'];

        if(!$val['left'] || !$val['right'])
        {
          unset($filter_range[$k]);
          continue;
        }

        $left = $val['left'];
        $right = $val['right'];

        if($val['left'] > $val['right'])
        {
          $left = $val['right'];
          $right = $val['left'];
        }

        $range = range($left, $right);

        if(!is_array($range))
        {
          unset($filter_range[$k]);
          continue;
        }

        unset($filter_range[$k]);
        $ranges = array_merge($ranges, $range);
      }
    }

    $ranges = array_unique($ranges);
    $filter_range = $filter_range_orig;

    $replace = array(
      '.',
      ';',
      '`',
      '|',
    );
    $filter_range = preg_replace("[\n|\r]", ',', $filter_range);
    $filter_range = explode(',', $filter_range);

    if(is_array($filter_range) && count($filter_range))
    {
      foreach($filter_range as $k => $id)
      {
        if(strpos($id, '-') !== false)
        {
          unset($filter_range[$k]);
          continue;
        }

        $id = str_replace($replace, ',', $id);
        $id = @trim($id);
        $id = (int)$id;

        if(!$id)
        {
          unset($filter_range[$k]);
          continue;
        }

        $filter_range[$k] = $id;
      }
    }

    $filter_range = array_merge($ranges, $filter_range);
    $filter_range = array_unique($filter_range);

    if(!empty($filter_range))
    {
      sort($filter_range);

      $cond = implode(', ', $filter_range);

      if(strpos($cond, ', ') === false)
      {
        $cond = (int)$cond;

        if($is_not)
        {
          $condition = array(
            'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' != ?',
            'value'     => $cond,
          );
        }
        else
        {
          $condition = array(
            'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' = ?',
            'value'     => $cond,
          );
        }
      }
      else
      {
        if($is_not)
        {
          $condition = array(
            'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' NOT IN ('. $cond .')',
            'value'     => '',
          );
        }
        else
        {
          $condition = array(
            'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' IN ('. $cond .')',
            'value'     => '',
          );
        }
      }
    }

    if(isset($table)) unset($table);
    if(isset($cond)) unset($cond);
    if(isset($filter_range)) unset($filter_range);

    return $condition;
  }

  /**
   *
   * @param type $filter_value
   * @param type $column
   * @param type $parameters
   * @param type $var_table_name
   * @return string
   * @final
   */
  final public function filterBetween($filter_value, $column, $parameters = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(is_array($column))
    {
      $key = $column['key'];
      $_column = $column['column'];
      $column = '';
      $column = $_column;
    }
    else
    {
      $column = (string)$column;
    }

    if(!$key) $key = $column;
    $min = $filter_value['min'];
    $min = (int)$min;
    $max = $filter_value['max'];
    $max = (int)$max;
    $condition = array();
    $parameters = $this->prefilterArray($parameters);

    if($max < $min)
    {
      $_max = $min;
      $min = $max;
      $max = $_max;
      unset($_max);
    }

    if(($max - $min) < 25)
    {
      $total = $min + $max;
      $total = $total / 2;
      $total = @round($total);
      $min = $total - 25;
      $max = $total + 25;
      if(isset($total)) unset($total);
    }

    $table = $this->getTable($var_table_name);

    if(!$column || !$table->isColumnExists($column))
    {
      throw new App_Module_Exception('Column name not set or not exists');
    }

    if(!is_null($filter_value['min']))
    {
      $condition[] = array(
        'condition' => '('. self::PLACEHOLDER_TABLENAME .'.'. $column .' >= ?)',
        'value'     => $min,
      );
    }

    if(!is_null($filter_value['max']))
    {
      $condition[] = array(
        'condition' => '('. self::PLACEHOLDER_TABLENAME .'.'. $column .' <= ?)',
        'value'     => $max,
      );
    }

    if(isset($table)) unset($table);
    if(empty($condition)) $condition = null;

    return $condition;
  }

  /**
   *
   * @param type $filter_value
   * @param type $column
   * @param type $parameters
   * @param type $var_table_name
   * @return type
   * @final
   */
  final public function filterDate($filter_value, $column, $parameters = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(is_array($column))
    {
      $key = $column['key'];
      $_column = $column['column'];
      $column = '';
      $column = $_column;
    }
    else
    {
      $column = (string)$column;
    }

    if(!$key) $key = $column;
    $filter_value = (string)$filter_value;
    $table = $this->getTable($var_table_name);
    $parameters = $this->prefilterArray($parameters);
    $is_not = $parameters['apply_not'];
    $is_not = ($is_not ? 1 : 0);
    $is_datetime = $parameters['is_datetime'];
    $is_datetime = ($is_datetime ? 1 : 0);
    $condition = null;

    if(!$column || !$table->isColumnExists($column))
    {
      throw new App_Module_Exception('Column name not set or not exists');
    }

    if($filter_value && $filter_value != 'Array')
    {
      $date = new Zend_Date($filter_value);
      $date_format = self::DEFAULT_DATE;
      if($is_datetime) $date_format = self::DEFAULT_DATETIME;
      $filter_value = $date->get($date_format);

      if($filter_value)
      {
        if($is_not)
        {
          $condition = array(
            'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' != ?',
            'value'     => $filter_value,
          );
        }
        else
        {
          $condition = array(
            'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' = ?',
            'value'     => $filter_value,
          );
        }
      }
    }

    if(isset($table)) unset($table);
    if(isset($date)) unset($date);

    return $condition;
  }

  /**
   *
   * @param type $filter_value
   * @param type $column
   * @param type $parameters
   * @param type $var_table_name
   * @return string
   * @final
   */
  final public function filterString($filter_value, $column, $parameters = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(is_array($column))
    {
      $key = $column['key'];
      $_column = $column['column'];
      $column = '';
      $column = $_column;
    }
    else
    {
      $column = (string)$column;
    }

    if(!$key) $key = $column;
    $filter_value = (string)$filter_value;
    $filter_value = @trim($filter_value);
    $table = $this->getTable($var_table_name);
    $parameters = $this->prefilterArray($parameters);
    $is_not = $parameters['apply_not'];
    $is_not = ($is_not ? 1 : 0);
    $condition = null;

    if(!$column || !$table->isColumnExists($column))
    {
      throw new App_Module_Exception('Column name not set or not exists');
    }

    $r = array(
      '%',
      '\'',
      '"',
      '<',
      '>',
      '\\',
      '/',
    );

    $filter_value = str_replace($r, '', $filter_value);
    $filter_value = preg_replace("'\s+'", ' ', $filter_value);
    $filter_value = htmlspecialchars($filter_value);

    if($filter_value)
    {
      if($is_not)
      {
        $condition = array(
          'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' != ?',
          'value'     => $filter_value,
        );
      }
      else
      {
        $condition = array(
          'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' = ?',
          'value'     => $filter_value,
        );
      }
    }

    return $condition;
  }

  /**
   *
   * @param type $filter_value
   * @param type $column
   * @param type $parameters
   * @param type $var_table_name
   * @return string
   * @final
   */
  final public function filterStringLike($filter_value, $column, $parameters = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(is_array($column))
    {
      $key = $column['key'];
      $_column = $column['column'];
      $column = '';
      $column = $_column;
    }
    else
    {
      $column = (string)$column;
    }

    if(!$key) $key = $column;
    $filter_value = (string)$filter_value;
    $filter_value = @trim($filter_value);
    $table = $this->getTable($var_table_name);
    $parameters = $this->prefilterArray($parameters);
    $is_not = $parameters['apply_not'];
    $is_not = ($is_not ? 1 : 0);
    $condition = null;

    if(!$column || !$table->isColumnExists($column))
    {
      throw new App_Module_Exception('Column name not set or not exists');
    }

    $r = array(
      '%',
      '\'',
      '"',
      '<',
      '>',
      '\\',
      '/',
    );

    $filter_value = str_replace($r, '', $filter_value);
    $filter_value = preg_replace("'\s+'", ' ', $filter_value);
    $filter_value = htmlspecialchars($filter_value);

    if($filter_value)
    {
      if($is_not)
      {
        $condition = array(
          'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' NOT LIKE ?',
          'value'     => '%'. $filter_value .'%',
        );
      }
      else
      {
        $condition = array(
          'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' LIKE ?',
          'value'     => '%'. $filter_value .'%',
        );
      }
    }

    if(isset($table)) unset($table);

    return $condition;
  }

  /**
   *
   * @param type $filter_value
   * @param type $column
   * @param type $parameters
   * @param type $var_table_name
   * @return string
   * @final
   */
  final public function filterUrl($filter_value, $column, $parameters = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(is_array($column))
    {
      $key = $column['key'];
      $_column = $column['column'];
      $column = '';
      $column = $_column;
    }
    else
    {
      $column = (string)$column;
    }

    if(!$key) $key = $column;
    $filter_value = (string)$filter_value;
    $filter_value = $this->prefilterUrl($filter_value);
    $parameters = $this->prefilterArray($parameters);
    $is_not = $parameters['apply_not'];
    $is_not = ($is_not ? 1 : 0);
    $table = $this->getTable($var_table_name);
    $condition = null;

    if(!$column || !$table->isColumnExists($column))
    {
      throw new App_Module_Exception('Column name not set or not exists');
    }

    if($filter_value)
    {
      if($is_not)
      {
        $condition = array(
          'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' != ?',
          'value'     => $filter_value,
        );
      }
      else
      {
        $condition = array(
          'condition' => self::PLACEHOLDER_TABLENAME .'.'. $column .' = ?',
          'value'     => $filter_value,
        );
      }
    }

    if(isset($table)) unset($table);
    if(isset($parameters)) unset($parameters);

    return $condition;
  }

  /**
   * Apply order and limit, also change current page
   *
   * @param <self::DEFAULT_INSTANCE_SELECT> $select SELECT object
   * @param <Mixed> $order Apply order rule
   * @param <Mixed> $limit Limit amount of results
   * @param <Mixed> $page Current page
   * @param <Mixed> $var_table_name Which table should be used
   * @return <self::DEFAULT_INSTANCE_SELECT> SELECT object with ORDER BY and LIMIT applied
   * @see self::DEFAULT_INSTANCE_SELECT
   * @final
   */
  final public function applyOrderAndLimit($select, $order = null, $limit = null, $page = null, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $order = $this->_replacePlaceholders($order, $var_table_name);
    $select->order($order);
    $limit = (int)$limit;

    if($limit > 0)
    {
      $select->limitPage($page, $limit);
    }

    return $select;
  }

  /**
   * Join related tables to <i>self::DEFAULT_INSTANCE_SELECT</i> object
   *
   * @param <self::DEFAULT_INSTANCE_SELECT> $select SELECT object
   * @param <Boolean> $select_fields Select additional fields or not
   * @param <Mixed> $var_table_name Which table should be used
   * @return <self::DEFAULT_INSTANCE_SELECT> SELECT object with joined tables. Keep in mind, that you can't update or delete result row, unless you set 'setReadOnly' to 'false'
   * @see self::DEFAULT_INSTANCE_SELECT
   */
  public function joinRelatedTables($select, $select_fields = true, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $select = $this->prefilterSelect($select, $var_table_name);

    return $select;
  }

  /**
   * Prefilter ID, convert it into integer and checking that it's more than 0.
   *
   * @param <Mixed> $id ID to prefilter
   * @return <Integer> Result ID as integer
   * @throws <App_Module_Exception> If result ID equal or less that 0
   * @final
   */
  final public function prefilterId($id)
  {
    if(!is_numeric($id)) $id = null;
    $id = (int)$id;

    if($id <= 0)
    {
      throw new App_Module_Exception('ID required for operation', $this->getExceptionCode(55));
    }

    return $id;
  }

  /**
   * Prefilter WHERE conditions, trying to validate all incorrect input
   *
   * @param <Array> $where WHERE conditions to prefilter
   * @return <Array> Prefiltered array with correct WHERE conditions and correct structure
   * @final
   */
  final public function prefilterWhere($where)
  {
    $where = $this->prefilterArray($where);

    if(!$this->isMultiArray($where))
    {
      $where = array();
    }

    // All WHERE must have 'condition' (but don't need to have 'value')
    foreach($where as $k => $where_arr)
    {
      // Use case: Someone passed array without associative keys, but with numeric. So we assume, that 0 => 'condition' and 1 => 'value'
      if(!$where_arr['condition'] && isset($where_arr[0]))
      {
        $where_arr['condition'] = $where_arr[0];
        unset($where_arr[0]);
      }

      if(!$where_arr['value'] && isset($where_arr[1]))
      {
        $where_arr['value'] = $where_arr[1];
        unset($where_arr[1]);
      }

      if(!$where_arr['condition']) unset($where[$k]);

      // Someone can pass conditions as array and only one value. We must be sure, that condition key properly placed
      if(is_array($where_arr['condition'])) $where[$k]['condition'] = @array_values($where_arr['condition']);

      // In case Zend will change something in the future and start requiring value to every WHERE
      if(!isset($where_arr['value'])) $where[$k]['value'] = '';
    }

    $where = $this->prefilterArray($where);

    return $where;
  }

  /**
   * Making any input into array
   *
   * @param <Mixed> $array Input, which should be prefiltered
   * @param <Boolean> $unique_only Check for unique values in array or not
   * @return <Array> Prefiltered value
   * @final
   */
  final public function prefilterArray($array, $unique_only = false)
  {
    if(!$array || is_object($array) || is_resource($array)) $array = array();
    if(!is_array($array)) $array = array($array);
    if($unique_only) $array = array_unique($array);

    return $array;
  }

  final public function prefilterUrl($url, $protocol = 'http')
  {
    $_protocols = array(
      'http',
      'https',
    );

    if(!in_array($protocol, $_protocols)) $protocol = 'http';

    $url = str_replace('php://', '', $url);

    if($url)
    {
      if(strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) $url = $protocol .'://'. $url;

      $url = mb_strtolower($url);
    }

    return $url;
  }

  /**
  * Checks if array is multidimensional
  *
  * @param <Array> $a Array to check
  * @param <Boolean> $is_strict Should all inner values be array or not
  * @return <Boolean>
  */
  final public function isMultiArray($a, $is_strict = false)
  {
    if(!is_array($a)) return false;

    foreach($a as $v)
    {
      if(!$is_strict)
      {
        if(is_array($v)) return true;
      }
      else
      {
        if(!is_array($v)) return false;
      }
    }

    return ($is_strict ? true : false);
  }

  /**
   *
   * @param <self::DEFAULT_INSTANCE_ROW> $row Row to check
   * @param <String> $instanceof Instance of which object it should be
   * @return <self::DEFAULT_INSTANCE_ROW>
   * @final
   * @see self::DEFAULT_INSTANCE_ROW
   */
  final public function prefilterRow($row, $instanceof = self::DEFAULT_INSTANCE_ROW)
  {
    $instanceof = (string)$instanceof;

    if(!$instanceof)
    {
      $instanceof = self::DEFAULT_INSTANCE_ROW;
    }

    if($instanceof == self::DEFAULT_INSTANCE_ROW)
    {
      if($this->isObjectAndInstance($row, $instanceof))
      {
        try
        {
          if($row->isColumnExists('role'))
          {
            $instanceof = $row->getRowClassFromTable($row->get_role());
          }
          else
          {
            $instanceof = $row->getRowClassFromTable();
          }
        }
        catch(Zend_Db_Table_Exception $e)
        {
          $instanceof = self::DEFAULT_INSTANCE_ROW;
        }
      }
    }

    if(!$instanceof) $instanceof = self::DEFAULT_INSTANCE_ROW;

    if(!$this->isObjectAndInstance($row, $instanceof))
    {
      throw new App_Module_Exception_NotObject('Row is empty, not object or not instance of '. $instanceof, $this->getExceptionCode(58));
    }

    return $row;
  }

  /**
   * Checks SELECT object. If SELECT object is not instance of <i>self::DEFAULT_INSTANCE_SELECT</i>, new SELECT object will be created
   *
   * @param <self::DEFAULT_INSTANCE_SELECT> $select SELECT object
   * @param <Mixed> $var_table_name Which table should be used
   * @return <self::DEFAULT_INSTANCE_SELECT>
   * @see self::DEFAULT_INSTANCE_SELECT
   * @final
   */
  final public function prefilterSelect($select, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $var_table_name = $this->prefilterVarTableName($var_table_name);

    if(!$this->isObjectAndInstance($select, self::DEFAULT_INSTANCE_SELECT))
    {
      $select = $this->prepareSelect(array(), $var_table_name);
    }

    return $select;
  }

  /**
   * Replace placeholders in $haystack with values (for example, replace <i>self::PLACEHOLDER_TABLENAME</i> with <b>$var_table_name</b> table name
   *
   * @param <Mixed> $haystack Haystack with values, which should be replaced
   * @param <String> $var_table_name Which table should be used
   * @return <Mixed>
   * @see self::PLACEHOLDER_TABLENAME
   * @final
   */
  final protected function _replacePlaceholders($haystack, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $table_name = $this->getTableName($var_table_name);

    $r = array(
      self::PLACEHOLDER_TABLENAME => $table_name,
    );

    if(is_array($haystack))
    {
      foreach($haystack as $k => $value)
      {
        if(!is_string($value)) continue;

        $haystack[$k] = str_replace(array_keys($r), array_values($r), $value);
      }
    }
    else
    {
      $haystack = (string)$haystack;

      $haystack = str_replace(array_keys($r), array_values($r), $haystack);
    }

    return $haystack;
  }

  /**
   * Return pure SQL code from prepared SELECT object
   *
   * @param <Array> $where Array with WHERE conditions to apply
   * @param <Array> $filter Array with columns to filter via <i>prepareFilter()</i>
   * @param <String> $var_table_name Which table to use
   * @return <String> Prepared SQL with WHERE and filter conditions applied
   */
  final protected function _getSelectAsString($where = array(), $filter = array(), $rules = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $select = $this->prepareSelect($where, $var_table_name);

    if(is_string($rules)) $var_table_name = $rules;

    if(isset($filter['rules']))
    {
      $rules = $filter['rules'];
      $filter = $filter['filter'];
    }

    $conditions = $this->applyFilterRules($filter, $rules, $var_table_name);
    $conditions = $this->prefilterWhere($conditions);

    if(!empty($conditions))
    {
      $select = $this->applyWhere($select, $conditions, $var_table_name);
    }

    $select = $this->prepareFilter($select, $filter, $var_table_name);

    $sql = $select->__toString();

    if(isset($select)) unset($select);

    return $sql;
  }

  /**
   * Applies password hashing algorythm, including salt (if passed as argument). You can change positioning rule
   *
   * @param <String> $password Password to hash
   * @param <Mixed> $salt Salt to apply
   * @param <String> $pos_rule Position rule. You can use:
   *   #PASS# (full password string),
   *   #SALT# (full salt string),
   *   #PA# (half password string),
   *   #SS# (other half password string),
   *   #SA# (half salt string),
   *   #LT# (other half salt string)
   * @return <String>
   * @throws App_Module_Exception_DataRequired If $password is not a valid string
   * @final
   */
  final public function applyPasswordHashing($password, $salt = null, $pos_rule = '#PASS##SALT#')
  {
    if(!$this->isValidString($password)) throw new App_Module_Exception_DataRequired('Password required for generation');
    if(!$this->isValidString($pos_rule)) $pos_rule = '#PASS##SALT#';

    $pass_half = substr($password, 0, round(strlen($password) / 2));
    $pass_half2 = substr($password, round(strlen($password) / 2), strlen($password));
    $salt_half = '';
    $salt_half2 = '';

    if($salt)
    {
      $salt = (string)$salt;
      $salt_half = substr($salt, 0, round(strlen($salt) / 2));
      $salt_half2 = substr($salt, round(strlen($salt) / 2), strlen($salt));
    }

    $r = array(
      '#PASS#' => $password,
      '#SALT#' => $salt,
      '#PA#'   => $pass_half,
      '#SS#'   => $pass_half2,
      '#SA#'   => $salt_half,
      '#LT#'   => $salt_half2,
    );

    $password = str_replace(array_keys($r), array_values($r), $pos_rule);

    if(function_exists('hash'))
    {
      $password = hash('sha256', $password);
      $password = hash('whirlpool', $password);
    }

    return $password;
  }

  /**
   * Return current user row
   *
   * @return <self::DEFAULT_INSTANCE_ROW> Current user row
   * @see self::DEFAULT_INSTANCE_ROW
   * @final
   */
  final public function getActor()
  {
    return $this->_actor;
  }

  /**
   * Set current actor
   *
   * @param <self::DEFAULT_INSTANCE_ROW> $user_row Current user row
   * @return <$this> Current object, so you could chain calls
   * @see self::DEFAULT_INSTANCE_ROW
   * @final
   */
  final public function setActor($user_row)
  {
    if(!$this->isObjectAndInstance($user_row, self::DEFAULT_INSTANCE_USER_ROW))
    {
      throw new App_Module_Exception('User row must be instance of '. self::DEFAULT_INSTANCE_USER_ROW, $this->getExceptionCode(59));
    }

    $this->_actor = $user_row;

    return $this;
  }

  /**
   * Return configuration array or configuration object (based on your settings)
   *
   * @return <Mixed> Configuration array or configuration object
   * @final
   */
  final public function getConfig()
  {
    if(Zend_Registry::isRegistered('Zend_Config'))
    {
      return Zend_Registry::get('Zend_Config');
    }

    return null;
  }

  /**
   * Return Log object
   *
   * @return <Mixed> Zend_Log object, if logs initialized or NULL if not
   * @final
   */
  final public function getLog()
  {
    if(Zend_Registry::isRegistered('Zend_Log'))
    {
      return Zend_Registry::get('Zend_Log');
    }

    return null;
  }

  /**
   * Write data in log file via log adapter
   *
   * @param <String> $message Message to write in log
   * @param <Integer> $priority Priority of message
   * @param <Array> $extras Extra data to write
   * @return <Mixed> Log object if logs initialized and no error occured or NULL if logs not initialized or any error catched
   * @final
   */
  final public function writeLog($message, $priority = self::LOG_INFO, $extras = array())
  {
    $log = null;

    try
    {
      $log = $this->getLog();

      if($log)
      {
        $log->log($message, $priority, $extras);
      }
    }
    catch(Zend_Log_Exception $e)
    {
      return null;
    }

    return $log;
  }

  /**
   * Format code into 10-chars integer
   *
   * @param <Integer> $code Event code to format
   * @return <Mixed> Formatted exception code or <i>NULL</i> if <i>self::_EXCEPTION_CODE</i> is not present
   * @see self::_EXCEPTION_CODE
   * @final
   */
  final public function getExceptionCode($code)
  {
    $base = self::_EXCEPTION_CODE;

    $code = (string)$code;
    $length = strlen($code);

    if(!$code)
    {
      return 0;
    }

    if($length > 5)
    {
      $_exception_code .= '00002';
      throw new App_Module_Exception_WrongData('Incorrect code length. It can\'t be more than 5 characters', $_exception_code);
    }

    if($length != 5)
    {
      while($length < 5)
      {
        $code = '0'. $code;
        $length++;
      }
    }

    return $base . $code;
  }

  /**
   * Format date in correct way
   *
   * @param <String> $date Date to format
   * @param <Boolean> $is_datetime Is datetime format (self::DEFAULT_DATETIME) or just date (self::DEFAULT_DATE)
   * @return <String> Formatted date
   * @final
   */
  final public function prepareDate($date, $is_datetime = false)
  {
    $result = null;

    if($date)
    {
      $date_object = new Zend_Date($date);

      if($is_datetime)
      {
        $result = $date_object->get(self::DEFAULT_DATETIME);
      }
      else
      {
        $result = $date_object->get(self::DEFAULT_DATE);
      }
    }

    return $result;
  }

  /**
   * Return Translate object
   *
   * @return <Object>
   * @final
   */
  final public function getTranslate()
  {
    try
    {
      return new Zend_Translate();
    }
    catch(Zend_Translate_Exception $e)
    {
      return null;
    }
  }

  /**
   * Abstract method for data loading. Useful if you must load some related data
   *
   * @param <Array> $array_or_object Array, which should be loaded with data
   * @param <Int> $level How many data should be loaded
   * @param <String> $var_table_name Which table to use
   * @return <Array> Array with loaded data
   */
  public function loadData($array_or_object, $level = 3, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $level = (int)$level;
    if(!$level) $level = 3;
    $array = array();

    if($this->isObjectAndInstance($array_or_object, self::DEFAULT_INSTANCE_ROWSET_PAGINATE))
    {
      foreach($array_or_object as $obj)
      {
        $array[$obj->get_id()] = $obj->getData(($level > 3 ? true : false));
      }
    }
    elseif(is_array($array_or_object))
    {
      if(@isset($array_or_object['records']))
      {
        $array = $array_or_object['records'];
      }
      else
      {
        $array = $array_or_object;
      }
    }

    return $array;
  }

  /**
   * Prepare Zend_Db_Expr with replaced placeholders
   *
   * @param <String> $expression Expression to prepare
   * @param <String> $var_table_name Which table to use
   * @return <Zend_Db_Expr>
   * @final
   * @see Zend_Db_Expr
   */
  final public function prepareDbExpression($expression, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(!is_string($expression) || !$expression)
    {
      throw new App_Module_Exception('Wrong data passed as expression');
    }

    $expression = $this->_replacePlaceholders($expression, $var_table_name);

    return new Zend_Db_Expr($expression);
  }

  /**
  * Check is passed variable $object not empty, is object and instance of $instanceof
  *
  * @param <Object> $object Object to check
  * @param <Mixed> $instancename Instance of which object $object should be. Can be object (class name will be used), string or array
  * @return <Mixed> If $instancename is array, will be returned instance class name or false, else true or false will be returned
  */
  public function isObjectAndInstance($object, $instancename)
  {
    if(is_object($instancename)) $instancename = get_class($instancename);
    if(is_resource($instancename)) throw new App_Module_Exception_WrongData('Instance name can\'t be a resource');

    if(is_array($instancename))
    {
      if(empty($instancename)) throw new App_Module_Exception_WrongData('Instance name passed as empty array');
      
      foreach($instancename as $instance)
      {
        if($object && is_object($object) && ($object instanceof $instance))
        {
          return $instance;
        }
      }

      return false;
    }

    $instancename = (string)$instancename;
    if(!$instancename) return false;

    if($object && is_object($object) && ($object instanceof $instancename))
    {
      return true;
    }

    return false;
  }

  /**
   * Is debug enabled or not
   *
   * @return <Boolean>
   */
  public function isDebug()
  {
    return ($this->_is_debug ? true : false);
  }
}