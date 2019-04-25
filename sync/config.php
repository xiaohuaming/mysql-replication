<?php
/**
 * @Copyright (C), 2018-2019, shawn.sean@foxmail.com
 * @Name config.php
 * @Author Shawn
 * @Version v1.0
 * @Date: 2019/4/15
 * @Time: 16:24
 * @Description 配置文件
 */
return array(
    //需要同步仓库ID
    'NEED_SYNC_STORE_DATA' => array(
      '196'
    ),
    //需要同步数据表，字段
    'NEED_SYNC_TABLE_DATA'   => array(
        'test'                    => 'id',

    ),
    //redis配置
    'REDIS_CONFIG' => array(
        'host'	    =>	'127.0.0.1',
        'pwd'       => '',
        'port'	    =>	'6379',
        'db_index'	=>	'4',
        'key'       => 'need_sync_data:',
    ),
);