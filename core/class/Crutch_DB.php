<?php

class oldDB{
    var $mode = NULL;

    function query($sql, $addTablePrefix=FALSE, $error_pass=FALSE){
        if ($addTablePrefix) {
            $table = PHPWS_DB::extractTableName($query);
            $sql = str_replace($table, $prefix . $table, $sql);
        }
        $GLOBALS['PEAR_DB']->setFetchMode(DB_FETCHMODE_ASSOC);
        return PHPWS_DB::query($sql);
    }// END FUNC query()


    function sqlInsert ($db_array, $table_name, $check_dup=FALSE, $returnId=FALSE, $show_sql=FALSE, $autoIncrement=TRUE) {
        $db = & new PHPWS_DB($table_name);
        $db->addValue($db_array);
        $result = $db->insert();

        if ($show_sql)
            echo $db->lastQuery();

        return $result;
    }

    function sqlUpdate($db_array, $table_name, $match_column=NULL, $match_value=NULL, $compare='=', $and_or='and') {
        $db = & new PHPWS_DB($table_name);
        $db->addValue($db_array);
        oldDB::addWhere($db, $match_column, $match_value, $compare, $and_or);
        return $db->update();
    }

    function sqlImport($filename, $write=TRUE, $suppress_error=FALSE){
        PHPWS_Core::initCoreClass('File.php');
        $text = PHPWS_File::readFile($filename);

        $db = & new PHPWS_DB;
        $result = $db->import($text, FALSE);

        if (PEAR::isError($result)) {
            PHPWS_Error::log($result);
            return FALSE;
        } else {
            return TRUE;
        }
    }

    function sqlDelete($table_name, $match_column=NULL, $match_value=NULL, $compare='=', $and_or='and') {
        $db = & new PHPWS_DB($table_name);
        oldDB::addWhere($db, $match_column, $match_value, $compare, $and_or);
        return $db->delete();
    }

    function sqlSelect($table_name, $match_column=NULL, $match_value=NULL, $order_by=NULL, $compare=NULL, $and_or=NULL, $limit=NULL, $mode=NULL, $test=FALSE) {
        $db = & new PHPWS_DB($table_name);
        if (isset($this->mode)) {
            $db->setMode($this->mode);
        }
        oldDB::addWhere($db, $match_column, $match_value, $compare, $and_or);
        return $db->select();
    }

    function addWhere(&$db, $match_column, $match_value, $compare, $and_or){
        if (isset($match_column)){
            if (is_array($match_column)){
                foreach ($match_column as $columnName=>$columnValue){
                    $operator = $conj = NULL;

                    if (is_array($compare) && isset($compare[$columnName]))
                        $operator = $compare[$columnName];
          
                    if (is_array($and_or) && isset($and_or[$columnName]))
                        $conj = $and_or[$columnName];
          
                    $db->addWhere($columnName, $columnValue, $operator, $conj);
                }
            } else {
                $db->addWhere($match_column, $match_value, $compare, $and_or);
            }
        }
    }

    function setFetchMode($fetchMode){
        $this->mode = $fetchMode;
    }

    function getCol($sql){
        $db = & new PHPWS_DB;
        return $db->getCol($sql);
    }

    function getAll($sql){
        $db = & new PHPWS_DB;
        return $db->getAll($sql);
    }

    function sqlMaxValue($table, $column){
        $db = & new PHPWS_DB($table);
        $db->addColumn($column, NULL, 'max');

        $result = $db->select('one');
    }

}



?>