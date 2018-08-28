<?php
namespace Clue\Database{
    /**
     * Clue/Database/Microsoft SQL Server
     * Need to trap sql server error
     *
     * Linux下安装MSSQL Driver（需要PHP >= 7）
     *
     * sudo apt-get install unixodbc-dev
     * sudo pecl install sqlsrv
     * echo "extension=sqlsrv.so" | sudo tee /etc/php/7.0/mods-available/sqlsrv.ini
     * sudo phpenmod sqlsrv
     *
     * 另外需要微软官方驱动
     * https://docs.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server?view=sql-server-2017
     */
    class MSSql extends \Clue\Database{
        protected $_stmt;

        function __construct(array $param){
            // Make sure oci extension is enabled
            if(!extension_loaded('sqlsrv')) throw new \Exception(__CLASS__.": extension MSSQL/SQLSRV is missing!");

            // Check Parameter, TODO

            $this->dbh=sqlsrv_connect($param['host'], array(
                'UID'=>$param['username'],
                'PWD'=>$param['password'],
                'Database'=>$param['db']
            ));

            if(!$this->dbh){
                $this->setError("Can't connect to sql server.");
            }
        }

        function __destruct(){
            $this->free_result();

            if($this->dbh){
                sqlsrv_close($this->dbh);
                $this->dbh=null;
            }
        }

        function free_result(){
            if($this->_stmt){
                sqlsrv_free_stmt($this->_stmt);
                $this->_stmt=null;
            }
        }

        function exec($sql){
            parent::exec($sql);

            $this->free_result();
            $this->_stmt=sqlsrv_query($this->dbh, $sql);
            if(!$this->_stmt){
                $this->setError("Error in sql");
                return false;
            }

            return true;
        }

        function get_var($sql){
            if(!$this->exec($sql)) return false;

            $row=sqlsrv_fetch_array($this->_stmt, SQLSRV_FETCH_NUMERIC);
            return $row[0];
        }

        function get_row($sql, $mode=OBJECT){
            if(!$this->exec($sql)) return false;

            if($mode==OBJECT)
                return sqlsrv_fetch_object($this->_stmt);
            else if($mode==ARRAY_A)
                return sqlsrv_fetch_array($this->_stmt, SQLSRV_FETCH_ASSOC);
            else if($mode==ARRAY_N)
                return sqlsrv_fetch_array($this->_stmt, SQLSRV_FETCH_NUMERIC);
            else
                return sqlsrv_fetch_array($this->_stmt, SQLSRV_FETCH_BOTH);
        }

        function get_col($sql){
            if(!$this->exec($sql)) return false;

            $result=array();
            while($a=sqlsrv_fetch_array($this->_stmt, SQLSRV_FETCH_NUMERIC)){
                $result[]=$a[0];
            }

            return $result;
        }

        function get_results($sql, $mode=OBJECT){
            if(!$this->exec($sql)) return false;

            $result=array();

            if($mode==OBJECT){
                while($o=sqlsrv_fetch_object($this->_stmt)){
                    $result[]=$o;
                }
            }
            else if($mode==ARRAY_A)
                while($o=sqlsrv_fetch_array($this->_stmt, SQLSRV_FETCH_ASSOC)){
                    $result[]=$o;
                }
            else if($mode==ARRAY_N)
                while($o=sqlsrv_fetch_array($this->_stmt, SQLSRV_FETCH_NUMERIC)){
                    $result[]=$o;
                }
            else
                while($o=sqlsrv_fetch_array($this->_stmt, SQLSRV_FETCH_BOTH)){
                    $result[]=$o;
                }

            return $result;
        }

        function has_table($table){
            // $cnt=$this->get_var("select count(*) from user_tables where table_name='$table'");

            $tables=$this->get_col("SELECT Distinct TABLE_NAME FROM information_schema.TABLES");
            return in_array($table, $tables);
        }
    }
}
?>
