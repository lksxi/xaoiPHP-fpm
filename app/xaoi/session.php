<?php
namespace xaoi;

class session{
	private $sessions = null;
	private $sid;

	function __construct($sid){
		$this->sid = $sid;

		$this->sessions = [];
		if(redis($this->sid)->hLen() > 0){
			$keys = redis($this->sid)->hKeys();
			foreach($keys as $k){
				$this->sessions[$k] = redis($this->sid)->hGet($k);
			}
		}
		redis($this->sid)->expire(config('xaoi/session.expire'));
	}

	function del($sid = null){
		$r = redis($sid?$sid:$this->sid)->del();
		return $r;
	}

	function session_id(){
		switch(func_num_args()){
			case 0:
				return $this->sid;
			break;
			case 1:
				$sid = func_get_arg(0);
				redis($sid)->hMset($this->sessions);
        		redis($sid)->expire(config('xaoi/session.expire'));
				redis($this->sid)->del();
				$this->sid = $sid;
			break;
		}
	}

	function session(){
		switch(func_num_args()){
			case 0:
				return $this->sessions;
			break;
			case 1:
				$k = func_get_arg(0);
				if(is_null($k)){
					$this->sessions = null;
					$r = redis($this->sid)->del();
					return $r;
				}elseif(is_array($k)){
					$this->sessions = $k;
					redis($this->sid)->del();
					return redis($this->sid)->hMset($this->sessions);
				}elseif(!empty($k)){
					$k = explode('.',$k);
					$p = &$this->sessions;
					for($i=0,$l=count($k);$i!=$l;++$i){
						if(!is_array($p))return;
						$p = &$p[$k[$i]];
					}
					return $p;
				}
			break;
			case 2:
				$k = func_get_arg(0);
				$v = func_get_arg(1);
				if(!empty($k)){
					$k = explode('.',$k);
					$p = &$this->sessions;
					for($i=0,$l=count($k);$i!=$l;++$i){
						if(!is_array($p))$p=array();
						$p = &$p[$k[$i]];
					}
					$p = $v;
					$r = is_null($this->sessions[$k[0]])?redis($this->sid)->hDel($k[0]):redis($this->sid)->hSet($k[0],$this->sessions[$k[0]]);
					return $r;
				}
			break;
		}
	}
}
