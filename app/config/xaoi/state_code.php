<?php
return [
	'app'	=> [
		'error'			=> [500],		//php执行错误
	],
	'mysql'	=> [
		'connect'		=> [-1001],	//连接数据库失败
		'error'			=> [-1002],	//执行出错
		'pool_timeout'	=> [-1003]	//获取连接池连接失败
	],
	'redis'	=> [
		'connect'		=> [-1004],	//连接数据库失败
		'error'			=> [-1005],	//执行出错
		'pool_timeout'	=> [-1006]	//获取连接失败
	]
];