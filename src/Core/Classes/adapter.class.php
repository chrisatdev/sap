<?php

namespace Classes\db;

use \PDO;
use DI\ContainerBuilder;

date_default_timezone_set("America/Asuncion");

class Adapter
{
  // Properties
  private $host;
  private $user;
  private $pass;
  private $dbname;
  private $type;
  private $dsn;
  private $conn;
  private $dbset;
  public  $table;
  public  $fields = "*";
  public  $error = [];

  function __construct()
  {
    $this->connect();
  }

  /**
   * query
   * @param string $sql An SQL string
   * @param array $array Paramters to bind
   * @param constant $fetchMode A PDO Fetch mode
   * @return mixed
   */
  public function query($sql, $fetchMode = \PDO::FETCH_ASSOC)
  {
    if (!empty($sql)) {
      if ($this->conn == null) {
        $this->connect();
      }

      $sth = $this->conn->query($sql);

      if ($this->conn->errorInfo()[1]) {
        return JSONResponseError(500, $this->conn->errorInfo()[2]);
      } else {
        return $sth->fetchAll($fetchMode);
      }
    } else {
      return JSONResponseError(500, _("Query was empty"));
    }
  }

  /**
   * insert
   * @param string $data An associative array
   */
  public function insert($data = [])
  {

    if ($this->conn == null) {
      $this->connect();
    }

    $fieldNames = NULL;
    $fieldValues = NULL;
    foreach ($data as $key => $value) {
      if (!empty($value)) {
        $fieldNames .= "`$key`,";
        if (is_array($value)) {
          $value = json_encode($value);
        }
        $fieldValues .= "'" . addslashes($value) . "',";
      } else {
        if ($value === '0' || $value === 0) {
          $value = "0";
        } else {
          $value = NULL;
        }
        $fieldNames .= "`$key`,";
        $fieldValues .= "'$value',";
      }
    }
    $fieldNames = rtrim($fieldNames, ',');
    $fieldValues = rtrim($fieldValues, ',');

    $sql = "INSERT INTO `{$this->dbname}`.`{$this->table}` ($fieldNames) VALUES ($fieldValues)";
    $sth = $this->conn->query($sql);
    //pr($this->conn->errorInfo());
    //pr($this->conn->errorCode());hule();
    if (number($this->conn->errorInfo()[1]) > 0) {
      return JSONResponseError(500, $this->conn->errorInfo()[2]);
    } else {
      return JSONResponseCreated(["id" => $this->conn->lastInsertId()]);
    }
  }

  /**
   * update
   * @param string $data An associative array
   * @param string $where the WHERE query part
   */
  public function update($data = [], $where = "", $primary_key = null)
  {

    if ($this->conn == null) {
      $this->connect();
    }

    $fieldDetails = NULL;
    foreach ($data as $key => $value) {
      if (!empty($value) && !is_numeric($value)) {
        if (is_array($value)) {
          $value = json_encode($value);
        }
        $fieldDetails .= "`$key`='" . addslashes($value) . "',";
      } else {
        //if( !empty( $value ) && is_numeric($value) ){
        if ($value === '0' || $value === 0) {
          $fieldDetails .= "`$key`=$value,";
        } else if (!empty($value) && is_numeric($value)) {
          $fieldDetails .= "`$key`=$value,";
        } else {
          $fieldDetails .= "`$key`=NULL,";
        }
      }
    }
    $fieldDetails = rtrim($fieldDetails, ',');

    $sql = "UPDATE `{$this->dbname}`.`{$this->table}` SET {$fieldDetails} WHERE {$where}";

    $this->conn->query($sql);

    if (number($this->conn->errorCode()) == 0) {
      return JSONResponseOK();
    } else {
      return JSONResponseError(500, $this->conn->errorInfo()[2]);
    }
  }

  /**
   * delete
   * @param string $where
   * @param integer $limit
   * @return integer Affected Rows
   */
  public function delete($where, $limit = 1)
  {

    if ($this->conn == null) {
      $this->connect();
    }

    $sql = "DELETE FROM `{$this->dbname}`.`{$this->table}` WHERE {$where} LIMIT {$limit}";

    $this->conn->query($sql);
    if (number($this->conn->errorCode()) == 0) {
      //return JSONResponseOK();
      return true;
    } else {
      return JSONResponseError(500, $this->conn->errorInfo()[2]);
    }
  }

  /**
   * find
   * @param string $field
   * @param string $value
   * @param string $options
   * @return mixed Affected Rows
   */
  public function find($field, $value, $options = "")
  {
    if ($this->conn == null) {
      $this->connect();
    }

    if (strlen($options) > 0) :
      $opt = "AND {$options}";
    else :
      $opt = "";
    endif;

    $sql = "SELECT * FROM `{$this->dbname}`.`{$this->table}` WHERE {$field} = '{$value}' " . $opt;

    return $this->query($sql);
  }

  /**
   * call_procedure
   * @param string $procedure_name
   * @param array $in
   * @return mixed Affected Rows
   */
  public function call_procedure($procedure_name, $in = null)
  {

    if (is_array($in)) {

      $in_arguments = "";

      foreach ($in as $argument) {
        $in_arguments .= "\"{$argument}\",";
      }

      $in_arguments = substr($in_arguments, 0, -1);
    } else {
      $in_arguments = "\"$in\"";
    }

    $in_arguments = $in_arguments == null ? "" : $in_arguments;

    $data = $this->query("CALL {$procedure_name}({$in_arguments})");

    return count($data) > 1 ? $data : $data[0];
  }

  /**
   * select
   * @param mixed $options
   * @return mixed Affected Rows
   */
  public function select($options = [])
  {

    if (!is_array($options)) {
      return JSONResponseError(500, _("Query was empty"));
    }

    if (empty(trim($this->fields)) || empty(trim($this->table))) {
      return JSONResponseError(500, "Options \"fields\" and \"from\" on methods select of adapter must have values");
    }

    $join  = "";
    $where  = "";
    $group  = "";
    $order  = "";
    $limit  = "";

    if (haveRows($options)) {
      foreach ($options as $key => $value) {
        switch ($key) {
          case "join":
            if (!empty($options[$key]))
              if (!is_array($options[$key])) {
                JSONResponseError(500, "JOIN values must be an array");
              } else {
                foreach ($options[$key] as $join_options) {
                  if (!isset($join_options['type'])) {
                    $join .= " JOIN `{$join_options['table']}` ON {$join_options['on']}";
                  } else {
                    $join .= strtoupper($join_options['type']) . " JOIN `{$join_options['table']}` ON {$join_options['on']}";
                  }
                }
              }
            break;
          case "where":
            if (!empty($options[$key]))
              $where = " WHERE " . $options[$key];
            break;
          case "group":
            if (!empty($options[$key]))
              $group = " GROUP BY " . $options[$key];
            break;
          case "order":
            if (!empty($options[$key]))
              $order = " ORDER BY " . $options[$key];
            break;
          case "limit":
            if (!empty($options[$key]))
              $limit = " LIMIT " . $options[$key];
            break;
          case "from":
          case "fields":
            break;
          default:
            JSONResponseError(500, sprintf("Undefined option: %s on select method of adapter", $key));
        }
      }
    }

    $full_fields = "";
    $joins = "";
    $columns = $this->get_table_columns();
    if (haveRows($columns)) {
      foreach ($columns as $column) {
        $foreign_key_fields = $this->foreign_key_fields($column['COLUMN_NAME']);
        if (!empty($foreign_key_fields)) {
          $fk_columns = $this->get_table_columns($foreign_key_fields);
          $full_fields .= "`{$foreign_key_fields}`.*, ";
          $joins .= " INNER JOIN `{$foreign_key_fields}` ON `{$foreign_key_fields}`.`{$column['COLUMN_NAME']}` = `{$this->table}`.`{$column['COLUMN_NAME']}`";
        } else {
          $full_fields .= "`{$this->table}`.{$column['COLUMN_NAME']}, ";
        }
      }
    }

    $fields = $this->fields != "*" ? $this->fields : substr($full_fields, 0, -2);
    $join = $this->fields != "*" ? $join : $joins;

    $sql = "SELECT {$fields} FROM `{$this->dbname}`.`{$this->table}` {$join}{$where}{$group}{$order}{$limit}";
    //pr( $sql );hule();
    $result = $this->query($sql);
    return $result;
  }

  public function primary_key()
  {
    $sql = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$this->dbname}' AND TABLE_NAME = '{$this->table}'";
    $columns = $this->query($sql);
    $primary_key = "";
    foreach ($columns as $column) {
      if ($column['COLUMN_KEY'] == "PRI") {
        $primary_key = $column['COLUMN_NAME'];
      }
    }
    return $primary_key;
  }

  public function columns()
  {
    $sql = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$this->dbname}' AND TABLE_NAME = '{$this->table}'";
    $columns = $this->query($sql);
    $names = "";
    foreach ($columns as $column) {
      $names .= "`{$this->table}`.{$column['COLUMN_NAME']},";
    }
    $names = substr($names, 0, -1);
    return $names;
  }

  public function get_fields_settings()
  {
    $sql = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$this->dbname}' AND TABLE_NAME = '{$this->table}'";
    $columns = $this->query($sql);
    $fields = [];
    $x = 0;
    foreach ($columns as $column) {
      if ($column['COLUMN_DEFAULT'] !== 'CURRENT_TIMESTAMP' && $column['COLUMN_KEY'] !== "PRI") {
        $fields[$x]['name']     = $column['COLUMN_NAME'];
        $fields[$x]['type']     = $column['DATA_TYPE'];
        $fields[$x]['maxlength']   = is_numeric($column['CHARACTER_MAXIMUM_LENGTH']) && number($column['CHARACTER_MAXIMUM_LENGTH']) > 0 ? $column['CHARACTER_MAXIMUM_LENGTH'] : $column['NUMERIC_PRECISION'];
        $fields[$x]['position']   = $column['ORDINAL_POSITION'];
        $fields[$x]['value']     = $column['COLUMN_DEFAULT'] != null ? $column['COLUMN_DEFAULT'] : "";
        $fields[$x]['required']   = $column['IS_NULLABLE'] == 'NO' ? true : false;
        $x++;
      }
    }
    return $fields;
  }

  public function get_tables_settings()
  {
    $sql = "SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$this->dbname}'";
    $columns = $this->query($sql);
    $tables = [];
    foreach ($columns as $column) {
      $tables[] = $column['TABLE_NAME'];
    }
    return $tables;
  }

  public function get_table_columns($table = "")
  {
    if (empty($table)) {
      $table = $this->table;
    }
    $sql = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$this->dbname}' AND TABLE_NAME = '{$table}'";
    $columns = $this->query($sql);
    return $columns;
  }

  public function foreign_key_fields($reference)
  {
    $rows = "";
    $sql = "SELECT REFERENCED_TABLE_NAME, 
						REFERENCED_COLUMN_NAME 
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
				WHERE REFERENCED_TABLE_SCHEMA IS NOT NULL AND 
				TABLE_SCHEMA = '{$this->dbname}' AND TABLE_NAME = '{$this->table}' AND REFERENCED_COLUMN_NAME = '{$reference}'";
    $referenced = $this->query($sql);
    if (haveRows($referenced)) {
      $rows = $referenced[0]['REFERENCED_TABLE_NAME'];
    }
    return $rows;
  }

  public function foreign_key_values($reference, $fields = null)
  {
    $rows = "";
    $sql = "SELECT REFERENCED_TABLE_NAME, 
						REFERENCED_COLUMN_NAME 
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
				WHERE REFERENCED_TABLE_SCHEMA IS NOT NULL AND 
				TABLE_SCHEMA = '{$this->dbname}' AND TABLE_NAME = '{$this->table}' AND REFERENCED_COLUMN_NAME = '{$reference}'";
    $referenced = $this->query($sql);
    if (haveRows($referenced)) {
      if ($fields == null) {
        $fieldDetails = "*";
      } else {
        $i = explode(',', $fields);
        if (haveRows($i)) {
          $fields = 'CONCAT( ';
          for ($x = 0; $x < count($i); $x++) {
            $fields .= "{$i[$x]}, ' ',";
          }
          $fields = substr($fields, 0, -6);
          $fields .= ' )';
        }

        $fieldDetails = "{$reference} AS id, {$fields} AS name";
      }

      $sql = "SELECT {$fieldDetails} FROM `{$referenced[0]['REFERENCED_TABLE_NAME']}`";
      //hule($sql);
      $rows = $this->query($sql);
    }
    return $rows;
  }

  public function validate($ob, $id)
  {


    foreach ($ob->fields as $fieldName => $field) {

      $value = isset($field['value']) ? trim($field['value']) : "";


      if (strlen($value) == 0) {

        if (array_key_exists("required", $field)) {
          if ($field['required'] === 1) {
            $error[$fieldName] = _("Required field");
          }
        }
      } else {

        if (array_key_exists("validation", $field) && $field['validation'] != "none") {

          $validations = explode(",", $field['validation']);

          foreach ($validations as $validation) {

            switch ($validation) {
              case "email":
                $regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,16})$/';
                if (!preg_match($regex, $field['value'])) {
                  $error[$fieldName] = sprintf(_("%s Is not a valid email address"), $field['value']);
                }

                break;
              case "number":
                if (!is_numeric($field['value'])) {
                  $error[$fieldName] = _("Must be a number");
                }
                break;
              case "unique":
                $sql = "SELECT {$fieldName} FROM " . $ob->table_name . " WHERE {$fieldName} = '{$field['value']}' AND " . $ob->primary_key . " != {$id}";
                $res = $this->query($sql);
                if (is_array($res) && count($res) > 0) {
                  $error[$fieldName] = sprintf(_("%s already exist"), $field['value']);
                }
                break;
              case "maxlen":
                if (array_key_exists("maxlen", $field)) {
                  if (strlen(trim($field['value'])) > $field['maxlen']) {
                    $error[$fieldName] = sprintf(_("Must be shorter than %s characters."), $field['maxlen']);
                  }
                }
                break;
              case "minlen":
                if (array_key_exists("minlen", $field)) {
                  if (strlen(trim($field['value'])) < $field['minlen']) {
                    $error[$fieldName] = sprintf(_("Must be at least %s characters long"), $filed['minlen']);
                  }
                }
                break;
            }
          }
        }
      }
    }

    $error = isset($error) ? $error : NULL;

    $this->error = $error;

    return count($this->error) ? false : true;
  }

  private function connect()
  {
    $containerBuilder = new ContainerBuilder();
    $settings       = require __DIR__ . '/../../../app/settings.php';
    $settings($containerBuilder);
    $container     = $containerBuilder->build();
    $dbSettings    = $container->get('settings')['db'];

    $this->host     = $dbSettings['host'];
    $this->user     = $dbSettings['user'];
    $this->pass     = $dbSettings['pass'];
    $this->dbname   = $dbSettings['name'];
    $this->type     = $dbSettings['type'];
    $this->dsn      = $this->type . ':host=' . $this->host . ';dbname=' . $this->dbname . ';charset=utf8';

    if ($this->conn !== null) {
      // already have an open connection so lets reuse it
      return $this->conn;
    }

    try {
      $this->conn = new \PDO(
        $this->dsn,
        $this->user,
        $this->pass,
        array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT, \PDO::ATTR_PERSISTENT => true)
      );
      $this->conn->exec("SET time_zone='-4:00';");
    } catch (\PDOException $e) {
      hule($e->getMessage());
    }
    return $this->conn;
  }
}
