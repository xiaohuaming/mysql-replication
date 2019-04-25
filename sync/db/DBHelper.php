<?php
/**
 * 获取mysql相关信息 执行query
 * Created by PhpStorm.
 * User: zhaozhiqiang
 * Date: 15/11/17
 * Time: 下午1:17
 */
class DBHelper {

    /**
     * @brief 获取字段相关信息
     * @param $schema
     * @param $table
     * @return array|bool
     */
    public static function getFields($schema, $table) {

        $db  = DBMysql::createDBHandle(Config::$DB_CONFIG, Config::$DB_CONFIG['db_name']);
        $sql = "SELECT
                COLUMN_NAME,COLLATION_NAME,CHARACTER_SET_NAME,COLUMN_COMMENT,COLUMN_TYPE,COLUMN_KEY
                FROM
                information_schema.columns
                WHERE
                table_schema = '{$schema}' AND table_name = '{$table}'";
        $result = DBMysql::query($db,$sql);
        DBMysql::releaseDBHandle($db);
        return $result;
    }

    /**
     * @brief 是否使用checksum
     * @return array|bool
     */
    public static function isCheckSum() {
        $db  = DBMysql::createDBHandle(Config::$DB_CONFIG, Config::$DB_CONFIG['db_name']);
        $sql = "SHOW GLOBAL VARIABLES LIKE 'BINLOG_CHECKSUM'";
        $res = DBMysql::getRow($db,$sql);
        DBMysql::releaseDBHandle($db);
        if($res['Value']) return true;
        return false;
    }

    /**
     * @breif 获取主库状态pos，file
     * @return FALSE表示执行失败
     */
    public static function getPos() {
        $db     = DBMysql::createDBHandle(Config::$DB_CONFIG, Config::$DB_CONFIG['db_name']);
        $sql    = "SHOW MASTER STATUS";
        $result = DBMysql::getRow($db,$sql);
        DBMysql::releaseDBHandle($db);
        return $result;
    }
}
