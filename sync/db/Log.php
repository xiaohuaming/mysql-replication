<?php
/**
 * Created by PhpStorm.
 * User: zhaozhiqiang
 * Date: 16/1/6
 * Time: 下午5:05
 */
class Log {



    public static function out($message, $category = 'out') {
        $file = Config::$OUT;
        return self::_write($message, $category, $file);
    }
    public static function error($message, $category, $file) {
        return self::_write($message, $category, $file);
    }

    public static function warn($message, $category, $file ) {
        return self::_write($message, $category, $file);
    }

    public static function notice($message, $category, $file ) {
        return self::_write($message, $category, $file);
    }

    public static function heartbeat($message, $category, $file)
    {
        return self::_write($message, $category, $file);
    }

    /**
     * @desc 写日志文件
     * @param $message
     * @param $category
     * @param $file
     * @return bool|int
     * @author Shawn
     * @date 2019/4/16
     */
    private static function _write($message, $category, $file) {

        //日志跨天 文件改变
        if(preg_match("/(\d{8})/",$file,$m)){
            if($m[1]!=date('Ymd')){
                $file=preg_replace("/\d{8}/",date('Ymd'),$file);
            }
        }

        $index = strripos($file, '/');
        if (!file_exists($file) && strripos($file, '/') !== false) {
            $fileDir = substr($file, 0, $index);

            if (!file_exists($fileDir)) {
                mkdir($fileDir, 0777, true);
                chmod($fileDir,0777); // 有写环境 mkdir 的 0777  无效
            }
        }

        return	file_put_contents(
            $file,
            $category . '|' . date('Y-m-d H:i:s') . '|'. $message . "\n",
            FILE_APPEND
        );
    }
}
