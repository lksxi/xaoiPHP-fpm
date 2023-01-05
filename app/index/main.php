<?php
namespace index;

class main extends \xaoi\init{

	public function __construct(){
		if(!empty($_SERVER['HTTP_AUTHORIZATION'])){
			$token = explode(' ',$_SERVER['HTTP_AUTHORIZATION'],2);
			$token = !empty($token[1])?$token[1]:null;
		}
		if(empty($token))_exit([301,null,'请先登陆']);
		bind('session',new \xaoi\session($token));

		$user = session('user');
		if(empty($user))_exit([301,null,'请先登陆']);
	}

}
