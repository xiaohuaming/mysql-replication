<?php
/**
 * @Copyright (C), 2018-2019, 卓士网络科技有限公司, shawn.sean@foxmail.com
 * @Name Redis.php
 * @Author Shawn
 * @Version v1.0
 * @Date: 2019/4/18
 * @Time: 9:46
 * @Description redis
 */
class RedisInstance
{
    private static $_instance = null; //静态实例

    /**
     * 初始化
     * Redis constructor.
     */
    private function __construct()
    { //私有的构造方法
        global $erpConfig;
        self::$_instance = new \Redis();

        self::$_instance->pconnect($erpConfig['REDIS_CONFIG']['host'],$erpConfig['REDIS_CONFIG']['port']);
        if (isset($erpConfig['REDIS_CONFIG']['pwd']) && !empty($erpConfig['REDIS_CONFIG']['pwd'])) {
            self::$_instance->auth($erpConfig['REDIS_CONFIG']['pwd']);
        }
        if($erpConfig['REDIS_CONFIG']['db_index'] > 0){
            self::$_instance->select($erpConfig['REDIS_CONFIG']['db_index']);
        }
    }

    //获取静态实例
    public static function getRedis()
    {
        if (!self::$_instance) {
            new self;
        }

        return self::$_instance;
    }

    /*
     * 禁止clone
     */
    private function __clone()
    {
    }
}
