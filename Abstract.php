<?php
/**
 * Class for work with SQL databases and performing common operations.
 *
 * @category   App
 * @package    App_Module
 * @copyright  Copyright 2012 staticall <staticall AT gmail DOT com>
 * @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License, Version 2.0
 */
class App_Module_Abstract
{
  protected $_table = null;

  const _EXCEPTION_CODE = 10050;
  const DEFAULT_INSTANCE_ROW = 'Zend_Db_Table_Row_Abstract';
  const DEFAULT_INSTANCE_ROWSET = 'Zend_Db_Table_Rowset';
  const DEFAULT_INSTANCE_TABLE = 'Zend_Db_Table';
  const DEFAULT_INSTANCE_SELECT = 'Zend_Db_Select';
  const DEFAULT_VAR_TABLE_NAME = 'table';
  const DEFAULT_KEY = 'id';
  const DEFAULT_DATE = 'yyyy-MM-dd';
  const DEFAULT_DATETIME = 'yyyy-MM-dd HH:mm:ss';
  const PLACEHOLDER_TABLENAME = '#TABLENAME#';

  final public function getRecord($id_or_val, $key = self::DEFAULT_KEY, $where = array(), $is_full = false, $instanceof = self::DEFAULT_INSTANCE_ROW, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(is_int($id_or_val)) $id_or_val = $this->prefilterId($id_or_val);
    else $id_or_val = (string)$id_or_val;
    $data = array();

    $row = $this->getRow($id_or_val, $key, $where, $is_full, $var_table_name);

    try
    {
      $row = $this->prefilterRow($row, $instanceof);
      $data = $row->getData();
    }
    catch(App_Module_Exception $e)
    {
      $data = null;
    }

    return $data;
  }

  final public function getRow($id_or_val, $key = self::DEFAULT_KEY, $where = array(), $is_full = false, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(!$key) $key = self::DEFAULT_KEY;
    $key = (string)$key;
    if(is_int($id_or_val)) $id_or_val = $this->prefilterId($id_or_val);
    else $id_or_val = (string)$id_or_val;
    $table_name = $this->getTableName($var_table_name);
    $table = $this->getTable($var_table_name);

    $where = $this->prefilterWhere($where);

    $where[] = array(
      'condition' => $table_name .'.'. $key .' = ?',
      'value'     => $id_or_val,
    );

    if($table->isColumnExists($table::COLUMN_IS_DELETED))
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

  final public function getDefaultRow($where = array(), $is_full = false, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $where = $this->prefilterWhere($where);
    $is_full = ($is_full ? 1 : 0);
    $table = $this->getTable($var_table_name);

    if($table->isColumnExists($table::COLUMN_IS_DEFAULT))
    {
      return $this->getRow(1, $table::COLUMN_IS_DEFAULT, $where, $is_full, $var_table_name);
    }

    throw new App_Module_Exception('Column `'. $table::COLUMN_IS_DEFAULT .'` not exists');
  }

  final public function getRecords($order_or_sorting = null, $limit_or_where = null, $page_or_filter = null, $where_or_is_full = array(), $filter_or_var_table_name = array(), $is_full = false, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $records = array();

    $rowset = $this->getRowset($order_or_sorting, $limit_or_where, $page_or_filter, $where_or_is_full, $filter_or_var_table_name, $is_full, $var_table_name);

    foreach($rowset as $row)
    {
      $data = $row->getData();

      if($row->isColumnExists('id'))
      {
        $id = $row->get_id();
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
      $is_full = ($is_full ? 1 : 0);
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
        $is_full = ($where_or_is_full ? 1 : 0);

        if($filter_or_var_table_name)
        {
          $var_table_name = $filter_or_var_table_name;
          $var_table_name = (string)$var_table_name;
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
        $is_full = ($is_full ? 1 : 0);
      }

      if(!$order) $order = null;
      if(!$limit) $limit = null;
      if(!$page) $page = null;
    }

    $select = $this->prepareSelect($where, $var_table_name);
    $select = $this->prepareFilter($select, $filter, $var_table_name);
    $select = $this->applyOrderAndLimit($select, $order, $limit, $page, $var_table_name);
    $select = $this->joinRelatedTables($select, $is_full, $var_table_name);

    $table = $this->getTable($var_table_name);

    $rowset = $table->fetchAll($select);

    $result = $this->getTotalRecordsWithPagination($rowset, $limit_or_where, $page_or_filter, $where_or_is_full, $filter_or_var_table_name, $var_table_name);

    if(isset($select)) unset($select);
    if(isset($table)) unset($table);
    if(isset($rowset)) unset($rowset);

    return $result;
  }

  final public function getTotalRecordsWithPagination($rowset, $limit = null, $page = null, $where = array(), $filter = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $where = $this->prefilterWhere($where);
    $filter = $this->prefilterArray($filter);

    $total = $this->getTotalRecords($where, $filter, $var_table_name);
    $result = $this->getPagination($rowset, $total, $limit, $page);

    if(isset($total)) unset($total);
    if(isset($rowset)) unset($rowset);

    return $result;
  }

  final public function getTotalRecords($where, $filter, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $where = $this->prefilterWhere($where);
    $filter = $this->prefilterArray($filter);
    $table = $this->getTable($var_table_name);

    $select = $this->prepareSelect($where, $var_table_name);
    $select = $this->prepareFilter($select, $filter, $var_table_name);
    $select = $this->joinRelatedTables($select, true, $var_table_name);

    $rowset = $table->fetchAll($select)->count('*');

    if(isset($select)) unset($select);
    if(isset($table)) unset($table);

    return $rowset;
  }

  final public function getPagination($rowset, $total, $page = null, $limit = null)
  {
    $pagination_array = array(
      'page'     => $page,
      'per_page' => $limit,
      'total'    => $total,
    );

    $result = new App_Db_Table_Rowset_Paginate($rowset, $pagination_array);

    if(isset($total)) unset($total);
    if(isset($rowset)) unset($rowset);

    return $result;
  }

  final public function createRow($data, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $data = $this->prefilterArray($data);
    if (isset($data['id'])) unset($data['id']);

    if (empty($data))
    {
      throw new App_Module_Exception_DataRequired('Data required for this operation', $this->getExceptionCode(15));
    }

    $table = $this->getTable($var_table_name);
    $data['created'] = date('Y-m-d H:i:s');

    $row = $table->createRow($data);
    $row->save();

    return $row;
  }

  final public function updateRecord($id, $data, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $id = $this->prefilterId($id);

    $row = $this->getRow($id, null, array(), false, $var_table_name);

    return $this->updateRow($row, $data);
  }

  final public function updateRow($row, $data)
  {
    $row = $this->prefilterRow($row);
    $data = $this->prefilterArray($data);

    $data['id'] = $row->get_id();
    if(isset($data['created'])) unset($data['created']);
    $data['updated'] = date('Y-m-d H:i:s');

    $row->setFromArray($data);
    $row->save();

    return $row;
  }

  final public function deleteRecord($id_or_val, $key = self::DEFAULT_KEY, $where = array(), $is_full = false, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $row = $this->getRow($id_or_val, $key, $where, $is_full, $var_table_name);

    return $this->deleteRow($row);
  }

  final public function deleteRow($row)
  {
    $row = $this->prefilterRow($row);

    if($row->isColumnExists('deleted'))
    {
      $date_deleted = date('Y-m-d H:i:s');
      $row->set_deleted($date_deleted);
      $row->save();
    }

    return $row->delete();
  }

  final public function doesRecordExists($row_or_val, $exclude_row_or_val = null, $key = self::DEFAULT_KEY, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $key = (string)$key;
    if(!$key) $key = self::DEFAULT_KEY;

    $table = $this->getTable($var_table_name);
    $table_name = $this->getTableName($var_table_name);

    $val = null;
    $val_exclude = null;

    if(is_int($row_or_val) && $row_or_val > 0)
    {
      $val = $row_or_val;
    }
    elseif(isObjectAndInstance($row_or_val, self::DEFAULT_INSTANCE_ROW))
    {
      $val = $row_or_val->get_id();

      if(!$row_or_val->isColumnExists($key))
      {
        throw new App_Module_Exception('doesRecordExists - column `'. $key .'` not exists in row "'. get_class($row_or_val) .'"', $this->getExceptionCode(25));
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
    $where[] = array(
      'condition' => $table_name .'.'. $key .' = ?',
      'value'     => $val,
    );

    if(!is_null($exclude_row_or_val))
    {
      if(is_int($exclude_row_or_val) && $exclude_row_or_val > 0)
      {
        $val_exclude = $exclude_row_or_val;
      }
      elseif(isObjectAndInstance($exclude_row_or_val, self::DEFAULT_INSTANCE_ROW))
      {
        $val_exclude = $exclude_row_or_val->get_id();
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
        'condition' => $table_name .'.'. $key .' != ?',
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

  final public function prefilterVarTableName($var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $var_table_name = (string)$var_table_name;
    if(!$var_table_name) $var_table_name = self::DEFAULT_VAR_TABLE_NAME;

    if(strpos($var_table_name, '_') !== 0)
    {
      $var_table_name = '_'. $var_table_name;
    }

    return $var_table_name;
  }

  final public function getTable($var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $var_table_name = $this->prefilterVarTableName($var_table_name);
    $table = $this->$var_table_name;

    if (!isObjectAndInstance($table, self::DEFAULT_INSTANCE_TABLE))
    {
      throw new App_Module_Exception('Table not exists, not an object or not instance of '. self::DEFAULT_INSTANCE_TABLE, $this->getExceptionCode(155));
    }

    return $table;
  }

  final public function getTableName($var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $table = $this->getTable($var_table_name);

    $table_name = null;
    $table_name = $table->getName();
    $table_name = (string)$table_name;

    if (!$table_name)
    {
      throw new App_Module_Exception('Table name can\'t be empty', $this->getExceptionCode(20));
    }

    if(isset($table)) unset($table);

    return $table_name;
  }

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

    return $select;
  }

  final public function applyWhere($select, $where = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $select = $this->prefilterSelect($select, $var_table_name);
    $where = $this->prefilterWhere($where);

    if (!empty($where))
    {
      foreach($where as $condition)
      {
        $cond = $condition['condition'];
        $cond = $this->_replacePlaceholders($cond, $var_table_name);

        $select->where($cond, $condition['value'], $condition['type']);
      }
    }

    return $select;
  }

  public function prepareFilter($select, $filter = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    return $select;
  }

  final public function applyFilterRules($filter, $rules, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(empty($rules) || !is_multi_array($rules))
    {
      throw new App_Module_Exception('Rules empty or not multidimensional array');
    }

    $conditions = array();
    $filter = $this->prefilterArray($filter);

    if(!is_multi_array($rules))
    {
      throw new App_Module_Exception('Multidimensional array required');
    }

    foreach($rules as $type => $columns)
    {
      if(!empty($columns))
      {
        $type_method = explode("-", $type);

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
              if(is_multi_array($_cond))
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
          throw new App_Module_Exception('Unknown method "'. $type_method .'" requested');
        }
      }
    }

    return $conditions;
  }

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

  final public function filterMultiple($filter_value, $column, $parameters = array(), $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $conditions = array();
    $table = $this->getTable($var_table_name);
    $parameters = $this->prefilterArray($parameters);

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
    $adapter = $table->getAdapter();

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
        if ($_value)
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

      if (!empty($_where_or))
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

    if((!$min || $min < 0) && (!$max || $max < 0))
    {
      return null;
    }

    if($max < $min)
    {
      $_max = $min;
      $min = $max;
      $max = $_max;
      unset($_max);
    }

    $table = $this->getTable($var_table_name);

    if(!$column || !$table->isColumnExists($column))
    {
      throw new App_Module_Exception('Column name not set or not exists');
    }

    if($min && $min > 0)
    {
      $condition[] = array(
        'condition' => '('. self::PLACEHOLDER_TABLENAME .'.'. $column .' >= ?)',
        'value'     => $min,
      );
    }

    if($max && $max > 0)
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
      $date = new App_Date($filter_value);
      $date_format = 'yyyy-MM-dd';
      if($is_datetime) $date_format .= ' HH:mm:ss';
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
    $filter_value = App_Validate_Abstract::prefilterUrl($filter_value);
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
   * @param <Object Zend_Db_Select> $select - Select object
   * @param <Null || String || Array> $order - Apply order rule
   * @param <Null || Integer> $limit - Limit amount of results
   * @param <Null || Integer> $page - Current page
   * @param <String> $var_table_name - Table to use
   * @return <Object Zend_Db_Select>
   */
  final public function applyOrderAndLimit($select, $order = null, $limit = null, $page = null, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $order = $this->_replacePlaceholders($order, $var_table_name);
    $select->order($order);

    if(!is_null($limit))
    {
      $select->limitPage($page, $limit);
    }

    return $select;
  }

  /**
   * Join related tables to Zend_Db_Select object
   *
   * @param <Object Zend_Db_Select> $select - Select object
   * @param <Boolean> $select_fields - Select additional fields or not
   * @param <String> $var_table_name - Table to use
   * @return <Object Zend_Db_Select>
   */
  public function joinRelatedTables($select, $select_fields = true, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $select = $this->prefilterSelect($select, $var_table_name);
    $table = $this->getTable($var_table_name);

    return $select;
  }

  /**
   * Format ID
   *
   * @param <Integer || String> $id - ID to prefilter
   * @return <Integer>
   * @throws <App_Module_Exception>
   */
  final public function prefilterId($id)
  {
    $id = (int)$id;

    if ($id <= 0)
    {
      throw new App_Module_Exception('ID required for operation', $this->getExceptionCode(55));
    }

    return $id;
  }

  final public function prefilterWhere($where)
  {
    $where = $this->prefilterArray($where);

    if (!is_multi_array($where))
    {
      $where = array();
    }

    // All WHERE must have 'condition' (but don't need to have 'value')
    foreach($where as $k => $where_arr)
    {
      if(!$where_arr['condition']) unset($where[$k]);
    }

    return $where;
  }

  final public function prefilterArray($array, $unique_only = false)
  {
    if (is_null($array)) $array = array();
    if (!is_array($array)) $array = array($array);
    if ($unique_only) $array = array_unique($array);

    return $array;
  }

  final public function prefilterRow($row, $instanceof = self::DEFAULT_INSTANCE_ROW)
  {
    $instanceof = (string)$instanceof;

    if (!$instanceof)
    {
      $instanceof = self::DEFAULT_INSTANCE_ROW;
    }

    if ($instanceof == self::DEFAULT_INSTANCE_ROW)
    {
      if(isObjectAndInstance($row, $instanceof))
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
        catch(App_Db_Table_Exception $e)
        {
          $instanceof = self::DEFAULT_INSTANCE_ROW;
        }
      }
    }

    if(!$instanceof) $instanceof = self::DEFAULT_INSTANCE_ROW;

    if (!isObjectAndInstance($row, $instanceof))
    {
      throw new App_Module_Exception('Row is empty, not object or not instance of '. $instanceof, $this->getExceptionCode(58));
    }

    return $row;
  }

  final public function prefilterSelect($select, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    if(!isObjectAndInstance($select, self::DEFAULT_INSTANCE_SELECT))
    {
      $select = $this->prepareSelect(array(), $var_table_name);
    }

    return $select;
  }

  final protected function _replacePlaceholders($haystack, $var_table_name = self::DEFAULT_VAR_TABLE_NAME)
  {
    $r = array(
      App_Const::DB_PLACEHOLDER_TABLENAME => $this->getTableName($var_table_name),
    );

    if(is_array($haystack))
    {
      foreach($haystack as $k => $value)
      {
        if(is_array($value)) continue;

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
   * Return current object
   *
   * @return <Array || Object>
   */
  final public function getConfig()
  {
    return Zend_Registry::get('Zend_Config');
  }

  /**
   * Format code into 10-chars integer
   *
   * @param <Integer> $code Event code to format
   * @return <String>
   */
  final public function getExceptionCode($code)
  {
    $base = self::_EXCEPTION_CODE;
    $_exception_code = 50005;

    if(!$base)
    {
      $_exception_code .= '00001';
      throw new App_Exception('Base for exception code generation required', $_exception_code);
    }

    $code = (string)$code;
    $length = strlen($code);

    if(!$code)
    {
      return 0;
    }

    if($length > 5)
    {
      $_exception_code .= '00002';
      throw new App_Exception('Incorrect code length. It can\'t be more than 5 characters', $_exception_code);
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
   * @param <String> $date - Date to format
   * @param <Boolean> $is_datetime - Is datetime format (Y-m-d H:i:s) or not (Y-m-d)
   * @return <String>
   */
  final public function prepareDate($date, $is_datetime = false)
  {
    $result = null;

    if($date)
    {
      $date_object = new App_Date($date);

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
   * Abstract method for data loading. Usefull if you must load some related data
   *
   * @param <Array> $array_or_object Array, which should be loaded with data
   * @param <Int> $level How many data should be loaded
   * @return <Array>
   */
  public function loadData($array_or_object, $level = 5)
  {
    $level = (int)$level;
    if(!$level) $level = 5;
    $array = array();

    if(isObjectAndInstance($array_or_object, self::DEFAULT_INSTANCE_ROWSET))
    {
      foreach($array_or_object as $obj)
      {
        $array[$obj->get_id()] = $obj->getData(($level > 3 ? true : false));
      }
    }
    else
    {
      $array = $array_or_object;
    }

    return $array;
  }
}