<?php
ini_set('memory_limit', '1024M');
error_reporting(E_NOTICE);
date_default_timezone_set("PRC");

// 调试
define('DEBUG', false);
define('ROOT', dirname(__FILE__) .'/');

class BinLogException extends Exception
{
    public function __construct($errmsg)
    {
        parent::__construct($errmsg);
    }
}

require_once "Connect.php";
require_once "db/Redis.php";
$tryCount       = 0;
$count          = 0;
$erpConfig      = include(__DIR__."/config.php");//erp配置
$syncTime       = microtime(true);//同步时间
$checkTime      = time();
$data           = [];
while(1) {
    try {
        $flag = false;
        $tryCount++;
        Connect::init();
        while (1) {
                $result = Connect::analysisBinLog($flag);
                $flag = false;
                if ($result) {
                    $nowTime = microtime(true);//当前时间
                    $tempData = checkData($result);
//                if(!empty($tempData)){
//                    var_dump($tempData);
//                }
//                print_r($tempData);
//                continue;
                    if (!empty($tempData)) {
                        $data = array_merge($data, $tempData);
                        $count++;
                        Log::out($count);
                    }
                    //数量达到100或者2分钟写一次
                    $booleanData = (($count % 100 == 0) && $count > 0) ? true : false;
                    $booleanTime = ((($nowTime - $syncTime) > 120) && !empty($data)) ? true : false;
//             保存到队列
                    if ($booleanData || $booleanTime) {
                        while (1) {
                            if (pushDataToList($data) === true) {
                                $count = 0;
                                $data = [];
                                $flag = true;//保存当前读取位置
                                $syncTime = microtime(true);//同步时间
                                Log::out("push to redis success" . round(memory_get_usage() / 1024 / 1024, 2) . 'MB');
                                break;
                            } else {
                                sleep(5);
                            }
                        }
                    }
                }
            }
    } catch (BinLogException $e) {
        Log::error('try count ' . $tryCount, 'binlog', Config::$LOG['binlog']['error']);
        Log::error(var_export($e, true), 'binlog', Config::$LOG['binlog']['error']);
        sleep(5);
    }
}

/**
 * @desc 检查数据是否需要加入队列
 * @param $data
 * @return array
 * @author Shawn
 * @date 2019/4/16
 */
function checkData($data)
{
    global $erpConfig,$checkTime;
    $return = [];
    if(empty($data))
    {
        return $return;
    }
    //100秒记录一次，用于检测程序是否挂掉
    if((time() - $checkTime) > 100){
        $msg = "Time:".$checkTime;
        Log::heartbeat($msg,"heartbeat",Config::$heartbeat);
        $checkTime = time();
    }
    $needSyncTable = $erpConfig['NEED_SYNC_TABLE_DATA'];
    foreach ($data as $k=>$v)
    {
        $action     = $k;
        $tempData   = $v;
        if(count($tempData) == count($tempData, 1)){
            $tempData = array($tempData);
        }
        foreach ($tempData as $key=>$value)
        {
            $table_name = trim($value['table_name']);
            //检查是否需要同步的表
            if(array_key_exists($table_name,$needSyncTable)){
                $fields = $needSyncTable[$table_name];//需要同步字段
                $fieldArr = explode(",",$fields);
                if(DEBUG){
                    var_dump($value);
                    var_dump($fieldArr);
                }
                foreach ($fieldArr as $vo){
                    $vo                           = trim($vo);
                    $return[$key]['table_name']   = $table_name;
                    $return[$key]['action']       = $action;
                    if(isset($value[$vo])){
                        $return[$key]['data'][$vo] = $value[$vo];
                    }
                }
            }
        }
        return $return;
    }
}

/**
 * @desc 加入数据到队列
 * @param $data
 * @return bool
 * @author Shawn
 * @date 2019/4/16
 */
function pushDataToList($data)
{
    global $erpConfig;
    if(empty($data)){
        return false;
    }
    $storeIds   = $erpConfig["NEED_SYNC_STORE_DATA"];
    $redisKey   = $erpConfig["REDIS_CONFIG"]['key'];
    if(empty($storeIds)){
        return false;
    }
    $redisService   = RedisInstance::getRedis();//redis服务
    foreach($storeIds as $storeId)
    {
        // push to redis list
        foreach ($data as $k=>$value)
        {
            if(empty($value)){
                continue;
            }
            $tableName  = $value['table_name'];
            $action     = $value['action'];
            $key        = $redisKey.$tableName.':'.$storeId;
            $list       = json_encode(["data"=>$value['data'],'action'=>$action]);
            $result     = $redisService->lpush($key,$list);
            if(!$result){
                Log::error("push to redis error,storeId:".$storeId.",data:".print_r($value,true),"error",Config::$LOG_ERROR_PATH);
            }
        }
    }
    return true;
}

