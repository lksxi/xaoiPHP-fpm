<?php
namespace xaoi;

class redis{
	private $db_name;
	private $tab_name;
	private static $redis = [];
	private $obj;
	function __construct($db_name,$tab_name){
		$this->db_name = $db_name;
		$this->tab_name = $tab_name;

		if(empty(self::$redis[$this->db_name])){
			$config = config('xaoi/redis');
			if(empty($config[$this->db_name])){
				$msg = config('xaoi/state_code.redis.empty');
				$msg[1] = 'Could not connect to the database';
				_json($msg);
			}
			
			$redis = new \Redis;
			$redis->connect($config[$this->db_name]['host'],$config[$this->db_name]['port'],1);
			if(isset($config[$this->db_name]['pwd']))$redis->auth($config[$this->db_name]['pwd']);
			if(isset($config[$this->db_name]['database']))$redis->select($config[$this->db_name]['database']);

			self::$redis[$this->db_name] = $redis;
		}

		$this->obj = self::$redis[$this->db_name];
	}

	function expire($d){
		return $this->obj->expire($this->tab_name,$d);
	}

	function exists(){
		return $this->obj->exists($this->tab_name);
	}

	function get(){
		return unserialize($this->obj->get($this->tab_name));
	}

	function set($d){
		return $this->obj->set($this->tab_name,serialize($d));
	}

	function del(){
		return $this->obj->del($this->tab_name);
	}

	function hExists($k){
		return $this->obj->hExists($this->tab_name,$k);
	}

	function hLen(){
		return $this->obj->hLen($this->tab_name);
	}

	function hKeys(){
		return $this->obj->hKeys($this->tab_name);
	}

	function hGet($k){
		return unserialize($this->obj->hGet($this->tab_name,$k));
	}

	//获取全部键
	function hGetAll(){
		$r = $this->obj->hGetAll($this->tab_name);
		foreach($r as &$v)$v = unserialize($v);unset($v);
		return $r;
	}

	function hSet($k,$d){
		return $this->obj->hSet($this->tab_name,$k,serialize($d));
	}

	function hMset($d){
		foreach($d as &$v)$v = serialize($v);unset($v);
		return $this->obj->hMset($this->tab_name,$d);
	}

	function hDel($k){
		return $this->obj->hDel($this->tab_name,$k);
	}
}