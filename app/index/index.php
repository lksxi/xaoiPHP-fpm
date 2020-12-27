<?php
namespace index;

class index extends \xaoi\init{
	
	function index(){

		header('Content-Type:text/html;charset=utf-8');
		
		echo 'xaoiPHP 框架 <a target="_blank" href="https://github.com/lksxi/xaoiPHP-fpm.git">github</a>';
	}

}