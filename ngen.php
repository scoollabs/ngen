<?php

// Usage: C:\xampp\php\php.exe ngen.php g "test.user|name,password,salt,emal,phone"

if (count($argv) == 1) {
  print_help(); die();
} else if (count($argv) != 3) {
  echo 'Invalid arguments'; die();
}

list($file, $command, $parameter) = $argv;
list($project, $name, $columns) = parse_arg($parameter);
initialize_directories();
if ($command == 'g') {
  append('doc/schema.sql', generate_mysql_schema($name, $columns));
  append('doc/schema-sqlite.sql', generate_sqlite_schema($name, $columns));
  save('Models/BaseModel.cs', generate_basemodel($project), true);
  save('Models/' . ucwords($name) . '.cs', generate_model($project, $name, $columns), true);
  // save('Repositories/SQLite/BaseSQLiteRepository.cs', generate_base_sqlite_repository($project), true);
  save('Repositories/BaseRepository.cs', generate_base_repository($project), true);
  save('Repositories/SQLite/SQLite' . ucwords($name) . 'Repository.cs', generate_sqlite_repository($project, $name, $columns), true);
  save('Repositories/MySql/MySql' . ucwords($name) . 'Repository.cs', generate_mysql_repository($project, $name, $columns), true);
} else {
  echo 'Command not supported';
}

function print_help() {
  echo 'Usage: ngen.php [command] [parameter]
  
  g           Generate components (controller=, model, views)
  c           Create controller component
  h           Create helper component
  m           Create model component
  v           Create view components
  
  parameter   The parameters that will be generated. See format example below
              
  Example:    "user|name,password,salt,email,phone"
              Will generate a user with properties name, password, salt, email and phone
';
}

function initialize_directories() {
  make_dir('doc');
  make_dir('Models');
  make_dir('Helpers');
  make_dir('Repositories');
  make_dir('Repositories/SQLite');
  make_dir('Repositories/MySql');
  make_dir('Repositories/Sql');
}

function make_dir($dir) {
  if (!file_exists($dir)) {
    mkdir($dir, 0755, true);
  }
}

function append($file, $contents) {
  echo 'Adding ' . $file . "...\n";
  file_put_contents($file, $contents, FILE_APPEND | LOCK_EX);
}

function save($file, $contents, $overwrite = false) {
  echo 'Saving ' . $file . '... ';
  if (file_exists($file) && !$overwrite) {
    echo 'file exists';
  } else {
    file_put_contents($file, $contents, LOCK_EX);
    echo 'OK';
  }
  echo "\n";
}

function get_sql_type($type) {
  if ($type == 'string') {
    return 'varchar(255)';
  }
  return $type;
}

function generate_sqlite_schema($name, $columns) {
  $cols = '';
  $i = 0;
  foreach ($columns as $column) {
    $sql_type = get_sql_type($column->type);
    $not_null = $column->not_null ? ' not null' : '';
    $primary_key = $column->primary_key ? ' primary key' : '';
    $cols .= "  $column->name $sql_type" . $not_null . $primary_key;
    if ($i++ < count($columns) - 1) {
      $cols .= ",\r\n";
    }
  }
  $str = 'create table __VAR__s(
__COLS__
);
';
  $str = str_replace("__VAR__", $name, $str);
  $str = str_replace("__COLS__", $cols, $str);
  $str = str_replace('"', "'", $str);
  return $str;
}

function generate_mysql_schema($name, $columns) {
  $cols = '';
  $i = 0;
  foreach ($columns as $column) {
    $sql_type = get_sql_type($column->type);
    $not_null = $column->not_null ? ' not null' : '';
    $primary_key = $column->primary_key ? ' primary key' : '';
    $auto_increment = $column->auto_increment ? ' auto_increment' : '';
    $cols .= "  $column->name $sql_type" . $not_null . $primary_key. $auto_increment;
    if ($i++ < count($columns) - 1) {
      $cols .= ",\r\n";
    }
  }
  $str = 'create table __VAR__s(
__COLS__
);
';
  $str = str_replace("__VAR__", $name, $str);
  $str = str_replace("__COLS__", $cols, $str);
  $str = str_replace('"', "'", $str);
  return $str;
}

function parse_arg($arg) {
  list($project, $cols) = explode('|', $arg);
  $cols = explode(',', $cols);
  $columns = array();
  foreach ($cols as $col) {
    $x = explode(':', $col);
    if (count($x) > 1) {
      $column = new Column($x[0], $x[1]);
    } else {
      $column = new Column($x[0], 'string');
    }
    $columns[] = $column;
  }
  if (!column_name_exists('id', $columns)) {
    array_unshift($columns, new Column('id', 'integer', true, true, true));
  }
  list($project, $name) = explode('.', $project);
  return array($project, $name, $columns);
}

function column_name_exists($name, $columns) {
  foreach ($columns as $col) {
    if ($col->name == $name) {
      return true;
    }
  }
  return false;
}

class Column {

  function __construct($name, $type, $not_null = false, $primary_key = false, $auto_increment = false) {
    $this->name = $name;
    $this->type = $type;
    $this->not_null = $not_null;
    $this->primary_key = $primary_key;
    $this->auto_increment = $auto_increment;
  }

}

function generate_basemodel($project) {
  $str = 'using System;

namespace __PROJECT__.Models
{
    public class BaseModel
    {
        public int Id { get; set; }
    }
}';
  $str = str_replace("__PROJECT__", ucwords($project), $str);
  return $str;
}

function generate_model($project, $model, $columns) {
  $properties = '';
  $i = 0;
  foreach ($columns as $column) {
    if ($column->name != 'id') {
      $properties .= '        public string ' . ucwords($column->name) . ' { get; set; }';
      if ($i < count($columns)) {
        $properties .= "\r\n";
      }
    }
    $i++;
  }
  $str = 'using System;

namespace __PROJECT__.Models
{
    public class __NAME__ : BaseModel
    {
__PROPERTIES__
    }
}';
  $str = str_replace("__NAME__", ucwords($model), $str);
  $str = str_replace("__PROPERTIES__", $properties, $str);
  $str = str_replace("__PROJECT__", ucwords($project), $str);
  return $str;
}

function generate_base_repository($project) {
  $str = 'using System;
using System.Configuration;
using System.Data;

namespace __PROJECT__.Repositories
{
    public class BaseRepository
    {
        IDbConnection connection;
        
        public BaseRepository(IDbConnection connection)
        {
            this.connection = connection;
        }
        
        protected DateTime? GetDateTime(IDataReader rs, int index)
        {
            return GetDateTime(rs, index, null);
        }
        
        protected DateTime? GetDateTime(IDataReader rs, int index, DateTime? def)
        {
            return !rs.IsDBNull(index) ? rs.GetDateTime(index) : def;
        }
        
        protected string GetString(IDataReader rs, int index)
        {
            return GetString(rs, index, "");
        }
        
        protected string GetString(IDataReader rs, int index, string def)
        {
            if (!rs.IsDBNull(index)) {
                return rs.GetString(index);
            } else {
                return def;
            }
        }
        
        protected int GetInt32(IDataReader rs, int index)
        {
            return GetInt32(rs, index, 0);
        }
        
        protected int GetInt32(IDataReader rs, int index, int def)
        {
            if (!rs.IsDBNull(index)) {
                return rs.GetInt32(index);
            } else {
                return def;
            }
        }
        
        protected double GetDouble(IDataReader rs, int index)
        {
            return GetDouble(rs, index, 0);
        }
        
        protected double GetDouble(IDataReader rs, int index, double def)
        {
            if (!rs.IsDBNull(index)) {
                return rs.GetDouble(index);
            } else {
                return def;
            }
        }
        
        protected void OpenConnection()
        {
            if (connection.State == ConnectionState.Closed) {
                connection.Open();
            }
        }
        
        protected void CloseConnection()
        {
            if (connection.State == ConnectionState.Open) {
                connection.Close();
            }
        }
        
        protected IDataReader ExecuteReader(string query, params IDbDataParameter[] parameters)
        {
            OpenConnection();
            var cmd = connection.CreateCommand();
            cmd.CommandText = query;
            foreach (var p in parameters) {
                cmd.Parameters.Add(p);
            }
            return cmd.ExecuteReader();
        }
        
        protected void ExecuteNonQuery(string query, params IDbDataParameter[] parameters)
        {
            OpenConnection();
            var cmd = connection.CreateCommand();
            cmd.CommandText = query;
            foreach (var p in parameters) {
                cmd.Parameters.Add(p);
            }
            cmd.ExecuteNonQuery();
            CloseConnection();
        }
        
        protected object ExecuteScalar(string query, params IDbDataParameter[] parameters)
        {
            OpenConnection();
            var cmd = connection.CreateCommand();
            cmd.CommandText = query;
            foreach (var p in parameters) {
                cmd.Parameters.Add(p);
            }
            var o = cmd.ExecuteScalar();
            CloseConnection();
            return o;
        }
    }
}';
  $str = str_replace("__PROJECT__", ucwords($project), $str);
  return $str;
}

function generate_sqlite_repository($project, $model, $columns) {
  $cols = '';
  $vals = '';
  $colvals = '';
  $params = '';
  $propvals = "                        Id = GetInt32(rs, 0),\r\n";
  $i = 0;
  foreach ($columns as $column) {
    if ($column->name != 'id') {
      $cols .= $column->name;
      $vals .= '@' . $column->name;
      $colvals .= '    ' . $column->name . ' = @' . $column->name;
      $params .= '                            new SQLiteParameter("@' . $column->name . '", ' . $model . '.' . ucwords($column->name) . ')';
      $propvals .= '                        ' . ucwords($column->name) . ' = GetString(rs, ' . $i . ')';
      if ($i < count($columns) - 1) {
        $cols .= ', ';
        $vals .= ', ';
        $colvals .= ", \r\n";
        $params .= ", \r\n";
        $propvals .= ", \r\n";
      }
    }
    $i++;
  }
  $str = 'using System;
using System.Collections.Generic;
using System.Configuration;
using System.Data.SQLite;
using __PROJECT__.Models;

namespace __PROJECT__.Repositories.SQLite
{
    public class SQLite__NAME__Repository : BaseRepository
    {
        public SQLiteUserRepository(string connectionString) : base(new SQLiteConnection(connectionString))
        {
        }
      
        public List<__NAME__> FindAll()
        {
            string query = @"
SELECT id, __COLS__
FROM __VAR__s";
            var __VAR__s = new List<__NAME__>();
            using (var rs = ExecuteReader(query)) {
                while (rs.Read()) {
                    var __VAR__ = new __NAME__ {
__PROPVALS__
                    };
                    __VAR__s.Add(__VAR__);
                }
            }
            return __VAR__s;
        }

        public __NAME__ Read(int id)
        {
            string query = @"
SELECT id, __COLS__
FROM __VAR__s
WHERE id = @id";
            __NAME__ __VAR__ = null;
            using (var rs = ExecuteReader(query, new SQLiteParameter("@id", id))) {
                if (rs.Read()) {
                    __VAR__ = new __NAME__ {
__PROPVALS__
                    };
                }
            }
            return __VAR__;
        }

        public void Save(__NAME__ __VAR__)
        {
            string query = @"
INSERT INTO __VAR__s(__COLS__)
VALUES(__VALS__)";
            ExecuteNonQuery(query,
__PARAMS__);
        }

        public void Update(__NAME__ __VAR__, int id)
        {
            string query = @"
UPDATE FROM __VAR__s SET
__COLVALS__
WHERE id = @id";
            ExecuteNonQuery(query,
__PARAMS__,
                            new SQLiteParameter("@id", id));
        }

        public void Delete(int id)
        {
            string query = @"
DELETE FROM __VAR__s
WHERE id = @id";
            ExecuteNonQuery(query, new SQLiteParameter("@id", id));
        }
    }
}';
  $str = str_replace("__NAME__", ucwords($model), $str);
  $str = str_replace("__VAR__", $model, $str);
  $str = str_replace("__COLS__", $cols, $str);
  $str = str_replace("__VALS__", $vals, $str);
  $str = str_replace("__COLVALS__", $colvals, $str);
  $str = str_replace("__PARAMS__", $params, $str);
  $str = str_replace("__PROPVALS__", $propvals, $str);
  $str = str_replace("__PROJECT__", ucwords($project), $str);
  return $str;
}

function generate_base_mysql_repository($project, $model, $columns) {
}

function generate_mysql_repository($project, $model, $columns) {
  $cols = '';
  $vals = '';
  $colvals = '';
  $params = '';
  $propvals = "                        Id = GetInt32(rs, 0),\r\n";
  $i = 0;
  foreach ($columns as $column) {
    if ($column->name != 'id') {
      $cols .= $column->name;
      $vals .= '@' . $column->name;
      $colvals .= '    ' . $column->name . ' = @' . $column->name;
      $params .= '                            new MySqlParameter("@' . $column->name . '", ' . $model . '.' . ucwords($column->name) . ')';
      $propvals .= '                        ' . ucwords($column->name) . ' = GetString(rs, ' . $i . ')';
      if ($i < count($columns) - 1) {
        $cols .= ', ';
        $vals .= ', ';
        $colvals .= ", \r\n";
        $params .= ", \r\n";
        $propvals .= ", \r\n";
      }
    }
    $i++;
  }
  $str = 'using System;
using System.Collections.Generic;
using System.Configuration;
using MySql.Data.MySqlClient;
using __PROJECT__.Models;

namespace __PROJECT__.Repositories.MySql
{
    public class MySql__NAME__Repository : BaseRepository
    {
        public MySqlUserRepository() : base(new MySqlConnection(ConfigurationManager.AppSettings["MySql-Connection"]))
        {
        }
      
        public List<__NAME__> FindAll()
        {
            string query = @"
SELECT id, __COLS__
FROM __VAR__s";
            var __VAR__s = new List<__NAME__>();
            using (var rs = ExecuteReader(query)) {
                while (rs.Read()) {
                    var __VAR__ = new __NAME__ {
__PROPVALS__
                    };
                }
            }
            return __VAR__s;
        }

        public __NAME__ Read(int id)
        {
            string query = @"
SELECT id, __COLS__
FROM __VAR__s
WHERE id = @id";
            __NAME__ __VAR__ = null;
            using (var rs = ExecuteReader(query, new MySqlParameter("@id", id))) {
                if (rs.Read()) {
                    __VAR__ = new __NAME__ {
__PROPVALS__
                    };
                }
            }
            return __VAR__;
        }

        public void Save(__NAME__ __VAR__)
        {
            string query = @"
INSERT INTO __VAR__s(__COLS__)
VALUES(__VALS__)";
            ExecuteNonQuery(query,
__PARAMS__);
        }

        public void Update(__NAME__ __VAR__, int id)
        {
            string query = @"
UPDATE FROM __VAR__s SET
__COLVALS__
WHERE id = @id";
            ExecuteNonQuery(query,
__PARAMS__,
                            new MySqlParameter("@id", id));
        }

        public void Delete(int id)
        {
            string query = @"
DELETE FROM __VAR__s
WHERE id = @id";
            ExecuteNonQuery(query, new MySqlParameter("@id", id));
        }
    }
}';
  $str = str_replace("__NAME__", ucwords($model), $str);
  $str = str_replace("__VAR__", $model, $str);
  $str = str_replace("__COLS__", $cols, $str);
  $str = str_replace("__VALS__", $vals, $str);
  $str = str_replace("__COLVALS__", $colvals, $str);
  $str = str_replace("__PARAMS__", $params, $str);
  $str = str_replace("__PROPVALS__", $propvals, $str);
  $str = str_replace("__PROJECT__", ucwords($project), $str);
  return $str;
}