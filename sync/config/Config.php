<?php
/**
 * Created by PhpStorm.
 * User: baidu
 * Date: 15/11/17
 * Time: 下午7:34
 */
class Config {
    /**
     * 测试数据库
     * @var array
     */
    public static $DB_CONFIG = array(
        'username'  => 'slave',
        'hostname'  => '127.0.0.1',
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'password'  => '123456',
        'db_name'   => 'test',
        'heartbeat' => 5// 心跳检测0不开启，>0 开启，（second）

    );

    /**
     * 线上数据库
     * @var array
     */
//    public static $DB_CONFIG = array(
//        'username'  => 'wstrpl1',
//        'hostname'  => '192.168.1.94',
//        'host'      => '192.168.1.94',
//        'port'      => 3336,
//        'password'  => 'E6fa7nkT',
//        'db_name'   => 'v3-all',
//        'heartbeat' => 5// 心跳检测0不开启，>0 开启，（second）
//    );


    // 默认100次mysql dml操作记录一次 pos，filename到文件
    public static $BINLOG_COUNT = 100;

    // 记录当前执行到的pos，filename
    public static $BINLOG_NAME_PATH  = 'file-pos';
    //
    public static $OUT               = 'out.log';
    public static $heartbeat         = 'heartbeat.log';

    // log记录
    public static $LOG_ERROR_PATH  = 'log/error.log';
    public static $LOG_WARN_PATH   = 'log/warn.log';
    public static $LOG_NOTICE_PATH = 'log/notice.log';

    public static $LOG = [
        'binlog' => [
            'error' => 'binlog-error.log'
        ],
        'kafka' => [
            'error'  => 'kafka-error.log',
            'notice' => 'kafka-notice.log'
        ],
        'mysql'  => [
            'error'  => 'mysql-error.log',
            'notice' => 'mysql-notice.log',
            'warn'   => 'mysql-error.log'
        ],
    ];

    /**
     * @desc 初始化日志路径
     * @author Shawn
     * @date 2019/4/16
     */
    public static function init() {
        self::$BINLOG_NAME_PATH  = ROOT . 'file-pos';
        self::$OUT               = ROOT ."log/".date("Ymd") . 'out.log';
        self::$LOG_ERROR_PATH    = ROOT ."log/".date("Ymd") . 'error.log';
        self::$LOG_WARN_PATH     = ROOT ."log/".date("Ymd") . 'warn.log';
        self::$LOG_NOTICE_PATH   = ROOT ."log/".date("Ymd") . 'notice.log';
        self::$heartbeat         = ROOT ."log/".date("Ymd") . 'heartbeat.log';
        foreach(self::$LOG as $key => $value) {
            foreach($value as $k => $v) {
                self::$LOG[$key][$k] = ROOT ."log/".date("Ymd") . $v;
            }
        }
    }
}

Config::init();

