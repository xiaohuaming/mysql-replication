php-mysql-replication
===============
参考php版本：https://github.com/fengxiangyun/mysql-replication

##需求描述：

由于公司业务扩展需要同步当前ERP数据到多个仓库，要求仓库线下系统与线上系统数据一致。

##运行环境：

> 数据库utf8编码

> PHP5.4以上

> Mysql版本5.1以上

> php sockets 扩展

> 需要有创建文件权限

> php redis 扩展

##配置

数据库配置，相关文件路径配置
~~~
/config/Config.php 
~~~
mysql binlog配置
~~~
log-bin=mysql-bin
server-id=1
binlog_format=row
~~~
同步仓库id，同步数据表、字段，redis配置
~~~
config.php
~~~

##启动服务

运行run.php 目前只支持row模式,只鉴定了增、删、改事件

可以用supervisor监控 run.php 进程

##持久化

file-pos保存了当前读取到binlog的filename和pos，保证程序异常退出后能继续读取binlog
新项目运行时 要删除file-pos，从当前show master status,读取到的filename pos开始读取
可以设置file-pos，程序则从当前设置的位置读取binlog

##流程图

![image](https://github.com/xiaohuaming/mysql-replication/blob/master/sync/mysql-replication.png) 
![image](https://github.com/xiaohuaming/mysql-replication/blob/master/sync/sync.png)

