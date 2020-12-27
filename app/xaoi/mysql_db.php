<?php
namespace xaoi;

class mysql_db{
	private $dbname;
	private $conn;
	private $pre = '';
	private $is_begin = false;
	function __construct($dbname){
		$this->dbname = $dbname;
		$mysql = config('xaoi/mysql');
		if(empty($mysql[$dbname]))exit('no mysql');
		$conf = $mysql[$dbname];
		if(!empty($conf['pre']))$this->pre = $conf['pre'];
		try {
			$this->conn = @new \PDO('mysql:host='.$conf['host'].';port='.$conf['port'].';dbname='.$conf['database'].';charset='.$conf['charset'],$conf['user'],$conf['password']);
			$this->conn->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
			$this->conn->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
			$this->conn->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES,false);
		} catch(\PDOException $e) {
			_json([-1000,'Could not connect to the database:<br/>' . $e,'database link error']);
		}
	}

	function pre(){
		return $this->pre;
	}

	function begin(){
		if($this->is_begin === false){
			$this->is_begin = true;
			return $this->conn->beginTransaction();
		}
	}

	function rollback(){
		if($this->is_begin === true){
			$this->is_begin = false;
			return $this->conn->rollBack();
		}
	}

	function commit(){
		if($this->is_begin === true){
			$this->is_begin = false;
			return $this->conn->commit();
		}
	}

	function is_limit($v){
		if(is_numeric($v))return true;
		if(is_array($v) && count($v) === 2 && isset($v[0]) && isset($v[1]) && is_numeric($v[0]) && is_numeric($v[1]))return true;
	}

	function error($conn,$sql = null,$bind = null){
		if(APP_DEBUG){
			$bug = debug_backtrace();
			$_bug = [];
			array_splice($bug,0,3);
			array_splice($bug,count($bug)-1,1);
			foreach($bug as $v){
				$item = [];
				$item['function'] = $v['function'];
				if(!empty($v['line']))$item['line'] = $v['line'];
				if(!empty($v['file']))$item['file'] = $v['file'];
				if(!empty($v['class']))$item['class'] = $v['class'];
				$_bug[] = $item;
			}
			$f = getcwd() . '/../log/mysql/error/'.date('y_n/j').'.txt';
			if(!is_dir(dirname($f)))mkdir(dirname($f),0755,true);
			file_put_contents($f,'['.date('Y-m-d H:i:s').'] '.
				(empty($sql)?'':'sql: '.$sql).
				(empty($conn->errorInfo()[2])?'':"\n".'msg: '.$conn->errorInfo()[2]).
				(empty($bind)?'':"\n".'bind: '.print_r($bind,true))."\n",FILE_APPEND);
			_echo([
				'sql'=>$sql,
				'bind'=>$bind,
				'code'=>$conn->errorCode(),
				'info'=>$conn->errorInfo(),
				'bug'=>$_bug,
			]);
		}
	}

	function exec($sql){
		return $this->conn->exec($sql);
	}

	function query($sql,$type = null){
		try{
			$ret = $this->conn->query($sql);
		}catch(\PDOException $e){
			$this->db->error($this->conn,$sql);
			_exit(config('xaoi/state_code.mysql.error'));
			return false;
		}
		switch($type){
			case 'add':
				return $this->conn->lastInsertId();
			break;
			case 'set':
			case 'del':
				return $ret->rowCount();
			break;
		}
		return $ret->fetchAll(\PDO::FETCH_ASSOC);
	}

	function _query($sql,$bind,$type = null){
		try{
			$stmt = $this->conn->prepare($sql);
		}catch(\PDOException $e){
			$this->db->error($this->conn,$sql,$bind);
			_exit(config('xaoi/state_code.mysql.error'));
			return false;
		}
		try{
			$stmt->execute($bind);
		}catch(\PDOException $e){
			$this->db->error($stmt,$sql,$bind);
			_exit(config('xaoi/state_code.mysql.error'));
			return false;
		}
		switch($type){
			case 'add':
				return $this->conn->lastInsertId();
			break;
			case 'set':
			case 'del':
				return $stmt->rowCount();
			break;
		}
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	function prepare($sql){
		try{
			$stmt = $this->conn->prepare($sql);
		}catch(\PDOException $e){
			$this->db->error($this->conn,$sql,$bind);
			return false;
		}
		return $stmt;
	}

	function add($tab,$d){
		$sql = ['insert','into',strpos($tab,'{pre}') !== false?str_replace('{pre}',$this->pre,$tab):$this->pre.$tab,'field'=>'','values','value'=>''];
		$field = [];
		$str = [];
		$bind = [];
		foreach($d as $k => $v){
			$field[] = $k;
			$str[] = '?';
			$bind[] = $v;
		}
		$sql['field'] = '(`'.implode('`,`',$field).'`)';
		$sql['value'] = '('.implode(',',$str).')';
		return $this->_query(implode(' ',$sql),$bind,'add');
	}

	function get($tab){
		$where = [];
		$field = '';
		$group = '';
		$order = '';
		$limit = '';
		$d = func_get_args();
		array_shift($d);
		switch(count($d)){
			case 1:
				if($this->is_limit($d[0])){
					$limit = $d[0];
				}else{
					$where = $d[0];
				}
			break;
			case 2:
				if($this->is_limit($d[1])){
					list($where,$limit) = $d;
				}else{
					list($where,$field) = $d;
				}
			break;
			case 3:
				if($this->is_limit($d[2])){
					list($where,$field,$limit) = $d;
				}else{
					list($where,$field,$order) = $d;
				}
			break;
			case 4:
				if($this->is_limit($d[3])){
					list($where,$field,$order,$limit) = $d;
				}else{
					list($where,$field,$group,$order) = $d;
				}
			break;
			case 5:
				list($where,$field,$group,$order,$limit) = $d;
			break;
		}

		$sql = ['select','field'=>'*','from',strpos($tab,'{pre}') !== false?str_replace('{pre}',$this->pre,$tab):$this->pre.$tab,'where'=>'','group'=>'','order'=>'','limit'=>''];
		$sql_bind = [];
		if(!empty($field)){
			$field = explode(',',$field);
			$l = [];
			foreach($field as $v){
				if(stripos($tab,'as') || stripos($v,'as')){
					$l[] = $v;
				}else{
					$l[] = '`'.$v.'`';
				}
			}
			$sql['field'] = implode(',',$l);
		}
		if(!empty($where)){
			$str = [];
			foreach($where as $k => $v){
				$s = substr_count($k,'?');
				if($s === 0){
					if(is_array($v)){
						$ins = [];
						foreach($v as $v2){
							$ins[] = '?';
							$sql_bind[] = $v2;
						}
						$str[] = $k.' in('.implode(',',$ins).')';
					}else{
						$str[] = $k.' = ?';
						$sql_bind[] = $v;
					}
				}elseif($s === 1){
					$str[] = $k;
					$sql_bind[] = is_array($v)?$v[0]:$v;
				}else{
					$str[] = $k;
					foreach($v as $v2){
						$sql_bind[] = $v2;
					}
				}
			}
			$sql['where'] = 'where '.implode(' and ',$str);
		}
		if(!empty($group)){
			if(is_string($group))
				$sql['group'] = 'group by ' . $group;
			else if(is_array($group)){
				$sql['group'] = 'group by '.implode(',',$group);
			}
		}
		if(!empty($order)){
			if(is_string($order))
				$sql['order'] = 'order by ' . $order;
			else if(is_array($order)){
				$str = [];
				foreach($order as $k => $v){
					$str[] = $k.' '.($v?'desc':'asc');
				}
				$sql['order'] = 'order by '.implode(',',$str);
			}
		}
		if(!empty($limit)){
			if(is_numeric($limit)){
				$start	= $limit;
			}else{
				$start	= (int)$limit[0];
				$size	= (int)$limit[1];
			}
			$sql['limit'] = 'limit ' . $start . (!empty($size)? ','.$size: '');
		}
		$ret = $this->_query(implode(' ',$sql),$sql_bind);
		return $limit === 1?(empty($ret)?[]:$ret[0]):$ret;
	}

	function set($tab){
		$where = [];
		$set = '';
		$d = func_get_args();
		array_shift($d);
		switch(count($d)){
			case 1:
				$set = $d[0];
			break;
			case 2:
				$where = $d[0];
				$set = $d[1];
			break;
		}

		$sql = ['update',strpos($tab,'{pre}') !== false?str_replace('{pre}',$this->pre,$tab):$this->pre.$tab,'set','set'=>'','where'=>''];
		$sql_bind = [];
		if(is_array($set)){
			$str = [];
			foreach($set as $k => $v){
				$str[] = '`'.$k.'`=?';
				$sql_bind[] = $v;
			}
			$sql['set'] = implode(',',$str);
		}else{
			$sql['set'] = $set;
		}
		if(!empty($where)){
			$str = [];
			foreach($where as $k => $v){
				$s = substr_count($k,'?');
				if($s === 0){
					if(is_array($v)){
						$ins = [];
						foreach($v as $v2){
							$ins[] = '?';
							$sql_bind[] = $v2;
						}
						$str[] = $k.' in('.implode(',',$ins).')';
					}else{
						$str[] = $k.' = ?';
						$sql_bind[] = $v;
					}
				}elseif($s === 1){
					$str[] = $k;
					$sql_bind[] = is_array($v)?$v[0]:$v;
				}else{
					$str[] = $k;
					foreach($v as $v2){
						$sql_bind[] = $v2;
					}
				}
			}
			$sql['where'] = 'where '.implode(' and ',$str);
		}
		return $this->_query(implode(' ',$sql),$sql_bind,'set');
	}

	function del($tab,$d){
		$sql = ['delete','from',strpos($tab,'{pre}') !== false?str_replace('{pre}',$this->pre,$tab):$this->pre.$tab,'where'=>''];
		$sql_bind = [];
		$str = [];
		foreach($d as $k => $v){
			$s = substr_count($k,'?');
			if($s === 0){
				if(is_array($v)){
					$ins = [];
					foreach($v as $v2){
						$ins[] = '?';
						$sql_bind[] = $v2;
					}
					$str[] = $k.' in('.implode(',',$ins).')';
				}else{
					$str[] = $k.' = ?';
					$sql_bind[] = $v;
				}
			}elseif($s === 1){
				$str[] = $k;
				$sql_bind[] = is_array($v)?$v[0]:$v;
			}else{
				$str[] = $k;
				foreach($v as $v2){
					$sql_bind[] = $v2;
				}
			}
		}
		$sql['where'] = 'where '.implode(' and ',$str);
		return $this->_query(implode(' ',$sql),$sql_bind,'del');
	}
}