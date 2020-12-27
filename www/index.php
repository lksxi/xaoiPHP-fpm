<?php
namespace xaoi{
// v20.5.2

// 开启调试模式 建议开发阶段开启 部署阶段注释或者设为false
define('APP_DEBUG',true);

// 定义应用目录
define('APP_PATH','../app');

// 检测PHP环境
if(version_compare(PHP_VERSION,'5.4.0','<')) die('require PHP > 5.4.0 !');

define('_APP_',url_path(__DIR__.'/'.APP_PATH));
define('_ROOT_',__DIR__);
define('__ROOT__','');
define('_HOME_',__DIR__);
define('__UPLOAD__','/upload');
define('_UPLOAD_',_ROOT_.__UPLOAD__);

\xaoi\container::init();
bind(config('xaoi/bind'));

app('app')->start();

class app{
	private $up = [
		'cls'		=> [],
		'config'	=> [],
		'class'		=> [],
		'view'		=> [],
	];
	private $cls = [
		'config'	=> [],
		'view'		=> [],
	];
	private $new_cls = [];
	private $new_view = [];

	//设置环境
	function start(){
		date_default_timezone_set('Asia/Shanghai');

		if(APP_DEBUG){
			ini_set('error_log', _APP_ . '/error_log.txt');
			ini_set('display_errors','On');
			set_error_handler(function($errno, $errstr, $errfile, $errline){
				_echo($errstr, $errfile.':'.$errline);
			});
		}else{
			set_error_handler(function($errno, $errstr, $errfile, $errline){
				//echo err(0,'错误信息：'.$errstr."\n\t文件：".$errfile."\n\t行数：".$errline."\n\n");
			});
			ini_set('display_errors','Off');
		}

		// 设置自动加载
		spl_autoload_register(function($c){
			$c = str_replace('\\', '/', $c);
			$file = _APP_ . '/' . $c . '.php';
			if(is_file($file)){
				$this->new_cls[] = $c;
				require $file;
				return true;
			}
			return false;
		});

		if(empty($_SERVER['PATH_INFO'])){
			$route = $this->route(key($_GET));
		}else{
			$len = strlen($_SERVER['PATH_INFO']);
			if(strripos($_SERVER['PATH_INFO'],'.html') === $len - 6){
				$route = $this->route(substr($_SERVER['PATH_INFO'],-6));
			}else{
				$route = $this->route($_SERVER['PATH_INFO']);
			}
		}

		define('ROUTE_CLASS',$route['class']);
		define('ROUTE_FN',$route['fn']);

		$load_path = str_replace('\\', '/', $route['class']).'/'.$route['fn'];
		$this->load($load_path);

		try{
			$obj = self::get_code($route['class'],$route['fn']);
			if($obj){
				$is_call = true;
				$i = 0;
				$arr = [];
				foreach($obj[1] as $k => $param){
					if($param[0] === 1){
						$arr[] = $param[1];
					}elseif(isset($route['args'][$i])){
						$arr[] = $route['args'][$i];
						++$i;
					}elseif(isset($_GET[$k])){
						$arr[] = $_GET[$k];
						++$i;
					}elseif($param[0] === 2){
						$arr[] = $param[1];
						++$i;
					}else{
						$is_call = false;
						break;
					}
				}
				if($is_call){
					$r = call_user_func_array([$obj[0],$route['fn']],$arr);
					$this->save($load_path);
					if(is_null($r)){

					}elseif(is_string($r)){
						exit($r);
					}elseif(is_int($r)){
						exit('['.$r.']');
					}elseif(is_array($r)){
						exit(json($r));
					}
				}else{
					_json([-1004,'Parameter not defined']);
				}
			}else{
				exit('<html>
<head><title>404 Not Found</title></head>
<body>
<center><h1>404 Not Found</h1></center>
<hr><center>xaoi</center>
</body>
</html>');
			}
		}catch(\Exception $e){
			_json($e->getMessage());
		}
	}

	function view(){
		$p = '';
		$d = [];
		switch(func_num_args()){
			case 0:
				
			break;
			case 1:
				$k = func_get_arg(0);
				if(is_string($k)){
					$p = $k;
				}else{
					$d = $k;
				}
			break;
			case 2:
				$p = func_get_arg(0);
				$d = func_get_arg(1);
			break;
		}

		$r = explode('\\',ROUTE_CLASS);
		$m = array_shift($r);
		$f = $m.'/view'.url_path((empty($r)?'':('/'.implode('/',$r))).'/'.(empty($p)?ROUTE_FN:$p)).'.php';
		$_f = _APP_.'/'.$f;
		if(!is_file($_f))exit('no view file');
		if(empty($this->cls['view'][$f])){
			$this->up['view'][] = [$f,filemtime($_f)];
			$this->cls['view'][$f] = include($_f);
			$this->new_view[] = $f;
		}

		echo call_user_func($this->cls['view'][$f],$d);
	}

	private function route($url){
		if(empty($url)){
			$url = '';
		}else{
			if($url[0] == '/')$url = substr($url,1);
		}
		if($p = strripos($url,config('xaoi/route.suffix')))$url = substr($url,0,$p);
		$vars = explode(config('xaoi/route.space_var'),strtolower($url),2);
		if(empty($vars[1])){
			$args = [];
		}else{
			$args = explode(config('xaoi/route.space'),$vars[1]);
		}
		$class = empty($vars[0])?[]:explode(config('xaoi/route.space'),$vars[0]);
		$def = config('xaoi/route.default');
		foreach($def as $k => $v){
			if(empty($class[$k]))$class[$k] = $def[$k];
		}

		$fn = array_pop($class);

		return ['class'=>implode('\\',$class),'fn'=>$fn,'args'=>$args];
	}

	// 对象缓存
	static private $code_objs = [];
	static function get_code($class,$fn){
		if(empty(self::$code_objs[$class])){
			if(class_exists($class)){
				if(!is_subclass_of($class,'\xaoi\init'))return false;
				self::$code_objs[$class] = ['obj'=>new $class];
				$fns = get_class_methods(self::$code_objs[$class]['obj']);
				self::$code_objs[$class]['fns'] = [];
				foreach($fns as $action){
					$method = new \ReflectionMethod($class,$action);
					$arr = [];
					$params = $method->getParameters();
					foreach ($params as $key => $param)
					{
						$c = $param->getClass();
						$is_def = $param->isDefaultValueAvailable();
						if($is_def){
							$arr[$param->getName()] = [2,$param->getDefaultValue()];
						}elseif($c){
							$arr[$param->getName()] = [1,app($c->getName())];
						}else{
							$arr[$param->getName()] = [0];
						}
					}
					self::$code_objs[$class]['fns'][$action] = $arr;
				}
				if(isset(self::$code_objs[$class]['fns'][$fn])){
					return [self::$code_objs[$class]['obj'],self::$code_objs[$class]['fns'][$fn]];
				}else{
					return false;
				}
			}else
				return false;
		}else{
			if(isset(self::$code_objs[$class]['fns'][$fn])){
				return [self::$code_objs[$class]['obj'],self::$code_objs[$class]['fns'][$fn]];
			}else{
				return false;
			}
		}
	}

	private function load($k){
		$f = _APP_.'/'.config('xaoi/route.runtime').'/'.$k.'.cls.php';
		if(is_file($f)){
			if(APP_DEBUG){
				$up = include(_APP_.'/'.config('xaoi/route.runtime').'/'.$k.'.up.php');
				if(!$this->is_update($up)){
					$this->up = $up;
					$this->cls = include($f);
					if(!empty($this->cls['config']))config($this->cls['config']);
				}
			}else{
				$this->cls = include($f);
				if(!empty($this->cls['config']))config($this->cls['config']);
			}
		}
	}

	private function is_update($info){
		clearstatcache();
		//class
		foreach($info['class'] as $v){
			if(!is_file(_APP_.'/'.$v[0].'.php') || filemtime(_APP_.'/'.$v[0].'.php') > $v[1]){
				return true;
			}
		}
		//view
		foreach($info['view'] as $v){
			if(!is_file(_APP_.'/'.$v[0]) || filemtime(_APP_.'/'.$v[0]) > $v[1]){
				return true;
			}
		}
		//config
		foreach($info['config'] as $v){
			if(!is_file(_APP_.'/config/'.$v[0].'.php') || filemtime(_APP_.'/config/'.$v[0].'.php') > $v[1]){
				return true;
			}
		}
	}

	private function save($path){
		if(empty($this->new_cls) && empty($this->new_view))return;
		$this->up['cls'] = array_merge($this->up['cls'],$this->new_cls);
		$lis = array_reverse($this->up['cls']);
	
		$str = "<?php";
		foreach($lis as $v){
			$n = explode('/',$v);
			$f = array_pop($n);
			$str .= "\r\nnamespace ".implode('\\',$n)."{\r\n";
			$str .= $this->get_fn($v);
			$str .= "\r\n}";
		}

		$fns = [];
		foreach($this->cls['view'] as $k => $v){
			preg_match_all('/<?php\s+return\s+([\S\s]+);\s*$/',F(_APP_ . '/' . $k),$all);
			$fns[] = '\''.$k.'\'=>'.$all[1][0];
		}

		$conf = config();

		$str .= "\n".'namespace {return [\'config\'=>'.var_export($conf,true).',\'view\'=>['.implode(',',$fns).']];}';
		
		$f = _APP_.'/'.config('xaoi/route.runtime').'/'.$path.'.cls.php';
		F($f,$str);
		F($f,php_strip_whitespace($f));

		foreach($this->new_cls as $v){
			$this->up['class'][] = [$v,filemtime(_APP_.'/'.$v.'.php')];
		}
		foreach($conf as $k2 => $v){
			$this->up['config'][] = [$k2,filemtime(_APP_.'/config/'.$k2.'.php')];
		}
		$f = _APP_.'/'.config('xaoi/route.runtime').'/'.$path.'.up.php';
		F($f,'<?php return '.var_export($this->up,true).';');
		F($f,php_strip_whitespace($f));
	}

	private function get_fn($c){
		$fstr = F(_APP_ . '/' . $c . '.php');
		$pre = '/namespace\s+(\S+);([\S\s]+)$/';
		preg_match_all($pre,$fstr,$all);
		if(empty($all[2][0])){
			$pre2 = '/<?php\s+([\S\s]+)/';
			preg_match_all($pre2,$fstr,$all2);
			return $all2[1][0];
		}else{
			return $all[2][0];
		}
	}
}

class container{

	protected static $instance;

	protected $instances = [];

	protected $bind = [];

	//获取当前容器的实例（单例）
	static function init()
    {
		static::$instance = new static;
    }

	//获取当前容器的实例（单例）
	static function getInstance()
    {
        return static::$instance;
    }

	//设置当前容器的实例
	static function setInstance($instance)
    {
        static::$instance = $instance;
    }

	function has(string $abstract)
    {

        return isset($this->bind[$abstract]) || isset($this->instances[$abstract]);
    }

	function exists(string $abstract)
    {

        return isset($this->instances[$abstract]);
    }

	public function make($abstract, $vars = [], $newInstance = false)
    {
		if (isset($this->instances[$abstract]) && !$newInstance) {
			return $this->instances[$abstract];
		}

		if (isset($this->bind[$abstract]) && $this->bind[$abstract] instanceof \Closure) {
			$object = call_user_func_array($this->bind[$abstract], $vars);
		} elseif(isset($this->bind[$abstract])) {
			$object = $this->invokeClass($this->bind[$abstract], $vars);
		}else{
			$object = $this->invokeClass($abstract, $vars);
		}

		if (!$newInstance) {
			$this->instances[$abstract] = $object;
		}

		return $object;
    }

    public function bind($abstract, $concrete = null)
    {
        if (is_array($abstract)) {
            foreach ($abstract as $key => $val) {
                $this->bind($key, $val);
            }
        } elseif ($concrete instanceof \Closure) {
            $this->bind[$abstract] = $concrete;
        } elseif (is_object($concrete) || is_array($concrete)) {
			$this->instances[$abstract] = $concrete;
        } else {
            $this->bind[$abstract] = $concrete;
        }

        return $this;
    }

    function invokeClass($class, $vars = [])
    {
        try {
            $reflect = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
			_json([-1000,$class.' does not exist']);
        }

		$constructor = $reflect->getConstructor();
		$args = $constructor ? $this->bindParams($constructor, $vars) : [];

        return $reflect->newInstanceArgs($args);
    }

    protected function bindParams(\ReflectionFunctionAbstract $reflect, array $vars = [])
    {
        if ($reflect->getNumberOfParameters() == 0) {
            return [];
        }

        // 判断数组类型 数字数组时按顺序绑定参数
        reset($vars);
        $type   = key($vars) === 0 ? 1 : 0;
        $params = $reflect->getParameters();
        $args   = [];

        foreach ($params as $param) {
            $name      = $param->getName();
            $class     = $param->getClass();

            if ($class) {
                $args[] = $this->getObjectParam($class->getName(), $vars);
            } elseif (1 == $type && !empty($vars)) {
                $args[] = array_shift($vars);
            } elseif (0 == $type && isset($vars[$name])) {
                $args[] = $vars[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                _json([-1000,'method param miss:' . $name]);
            }
        }

        return $args;
    }

    protected function getObjectParam(string $className, array &$vars)
    {
        $array = $vars;
        $value = array_shift($array);

        if ($value instanceof $className) {
            $result = $value;
            array_shift($vars);
        } else {
            $result = $this->make($className);
        }

        return $result;
    }
}

class init{

}

}
namespace{

// 获取或修改文件
function F(){
	switch(func_num_args()){
		case 1:
			return file_get_contents(func_get_arg(0));
		break;
		case 2:
			$p = func_get_arg(0);
			$v = func_get_arg(1);
			if(!is_string($v) && !is_int($v))$v = serialize($v);
			if(!is_dir(dirname($p)))mkdir(dirname($p),0755,true);
			file_put_contents($p,$v);
		break;
	}
}

//获取配置 路径/文件.变量名
function config($name = ''){
	static $_ps = [];
	static $_file = [];
	static $_conf = [
		'xaoi/route'	=> [
			'runtime'		=> 'runtime/call',
			//ajax域名限制
			'host'			=> [
				'127.0.0.1',
			],

			//默认路径名称
			'default'	=> ['index','index','index'],

			//分隔符
			'space'	=> '/',
			'space_var'	=> '-',
			
			//后缀
			'suffix'=> '.html',

		],
		'xaoi/bind'	=> [
			'app'		=> '\xaoi\app',
			'log'		=> '\xaoi\log',
		]
	];

	if(empty($name))return $_file;
	if(is_array($name)){$_conf += $name;return;}
	if(!empty($_ps[$name]))return $_ps[$name];

	if(strpos($name,'::') !== false){
		$name = str_replace(['\\','::'],['/','/'],substr($name,4));
	}

	
	$k = explode('.',$name);
	$file = array_shift($k);

	if(!isset($_conf[$file])){
		$_conf[$file] = array();
		$path = _APP_.'/config/'.$file.'.php';
		if(is_file($path)){
			$_conf[$file] = include($path);
			$_file[$file] = $_conf[$file];
		}
	}
	
	$p = &$_conf[$file];

	for($i=0,$l=count($k);$i!=$l;++$i){
		if(!is_array($p))return;
		$p = &$p[$k[$i]];
	}

	$_ps[$name] = &$p;
	return $p;
}

function view(){
	call_user_func_array([app('app'),'view'],func_get_args());
}

// 格式化url
function url_path($path){
	$path=str_replace('\\','/',$path);
	$last='';
	while($path!=$last){
		$last=$path;
		$path=preg_replace('/\/[^\/]+\/\.\.\//','/',$path);
	}
	$last='';
	while($path!=$last){
		$last=$path;
		$path=preg_replace('/([\.\/]\/)+/','/',$path);
	}
	return $path;
}

function app($name, $args = [], $newInstance = false){
	return \xaoi\container::getInstance()->make($name, $args, $newInstance);
}

function bind($abstract, $concrete = null){
	return \xaoi\container::getInstance()->bind($abstract, $concrete);
}

function db(){
	static $dbs = [];
	static $tabs = [];
	switch(func_num_args()){
		case 0:
			$dbname = 'default';
			$tab = null;
		break;
		case 1:
			$dbname = 'default';
			$tab = func_get_arg(0);
		break;
		case 2:
			$dbname = func_get_arg(0);
			$tab = func_get_arg(1);
		break;
	}
	if(empty($dbs[$dbname])){
		$dbs[$dbname] = new \xaoi\mysql_db($dbname);
		$tabs[$dbname] = [];
	}
	if(!empty($tab) && is_string($tab)){
		if(empty($tabs[$dbname][$tab])){
			$tabs[$dbname][$tab] = new \xaoi\mysql_tab($dbs[$dbname],$tab);
		}
		return $tabs[$dbname][$tab];
	}if(is_callable($tab)){
		$dbs[$dbname]->begin();
		try{
			$ref = new \ReflectionFunction($tab);
			$par = $ref->getParameters();
			array_shift($par);
			$args = [$tabs[$dbname]];
			foreach($par as $n){
				$tabname = $n->getName();
				if(empty($tabs[$dbname][$tabname])){
					$tabs[$dbname][$tabname] = new \xaoi\mysql_tab($dbs[$dbname],$tabname);
				}
				$args[] = $tabs[$dbname][$tabname];
			}
			$r = call_user_func_array($tab,$args);
			if(is_null($r)){
				$dbs[$dbname]->commit();
			}else{
				$dbs[$dbname]->rollback();
			}
			return $r;
		}catch(\Exception $e){
			$dbs[$dbname]->rollback();
			return $e->getMessage();
		}
	}else{
		return $dbs[$dbname];
	}
}

// 调试输出
function P($v){
	echo '<meta content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=0,minimal-ui" name="viewport" /><style>pre{position:relative;z-index:1000;padding:10px;border-radius:5px;background:#f5f5f5;border:1px solid #aaa;font-size:14px;line-height:18px;opacity:0.9;}</style>'."\n\n\n".
		'<pre>'."\n".
			(is_bool($v)?($v?'true':'false'):(is_null($v)?'NULL':print_r($v,true))).
		"\n</pre>\n\n";
}

function _P($v){
	P($v);exit;
}

function _echo(){
	if(!APP_DEBUG)return;
	$args = func_get_args();
	foreach($args as $v){
		//_fsockopen('http://echo.com:1350/echo',is_null($v)?'NULL':print_r($v,true),true);
		_fsockopen('http://127.0.0.1:8000',['str'=>is_null($v)?'NULL':print_r($v,true)],true);
	}
}

// 判断是否ajax访问
function is_ajax(){
	return !empty($_SERVER['HTTP_AJAX']) && $_SERVER['HTTP_AJAX'] === 'XAOI';
}

// json编码
function json($d,$is_obj = false){
	return json_encode($d,$is_obj?JSON_FORCE_OBJECT|JSON_UNESCAPED_UNICODE:JSON_UNESCAPED_UNICODE);
}

// json编码-输出-退出
function _json($d){
	exit(json_encode($d,JSON_UNESCAPED_UNICODE));
}

// 退出输出
function _exit(){
	switch(func_num_args()){
		case 0:
			throw new \Exception;
		break;
		case 1:
			$arg = func_get_arg(0);
			throw new \Exception(is_string($arg)?$arg:json($arg));
		break;
		default:
			throw new \Exception(json(func_get_args()));
		break;
	}
}

function errcode($a){
	return function($i,$d=null)use($a){
		$args = func_get_args();
		$i = array_shift($args);
		_json(count($args)?[$i,$a[$i],$args]:[$i,$a[$i]]);
	};
}

// url-base64
function url_base64_encode($string) {
	$data = base64_encode($string);
	$data = str_replace(array('+','/','='),array('-','_',''),$data);
	return $data;
}

function url_base64_decode($string) {
	$data = str_replace(array('-','_'),array('+','/'),$string);
	$mod4 = strlen($data) % 4;
	if ($mod4) {
		$data .= substr('====', $mod4);
	}
	return base64_decode($data);
}

function is_ssl() {
    if(isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))){
        return true;
    }elseif(isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'] )) {
        return true;
    }
    return false;
}

// 获取url数据-file_get_contents
function post($url,$data=' ',$cookie = ''){
	if(is_array($data)){
		$data = http_build_query($data);
		if(empty($data))$data=' ';
	}
	if(is_array($cookie)){
		foreach($cookie as $k => &$v){
			$v = $k.'='.$v;
		}
		$cookie = implode('; ',$cookie);
	}
	return file_get_contents($url,false,stream_context_create(array('http'=>array(
		'method'=>'POST',
		'header'=>
			'Content-type: application/x-www-form-urlencoded'."\r\n".
			($cookie != ''?('Cookie: '.$cookie."\r\n"):'').
			'Content-length: '.strlen($data)."\r\n",
		'content'=>$data))));
}

// 获取url数据-curl
function _curl($url, $data = array(),$cookie = ''){
	if(is_array($cookie)){
		foreach($cookie as $k => &$v){
			$v = $k.'='.$v;
		}
		$cookie = implode(';',$cookie);
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	if(!empty($data)){
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}
	if(!empty($cookie)){
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
	$r = curl_exec($ch);
	curl_close($ch);
	return $r;
}

// 获取url数据-fsockopen-可异步
function _fsockopen($url,$post = array(),$exit = false,$referer = ''){
	$par = parse_url($url);
	if($par['scheme'] === 'http' || $par['scheme'] === 'https'){
		if( $par['scheme'] === 'https'){
			$ssl = 'ssl:// ';
			if(!isset($par['port']))$par['port'] = 443;
		}else{
			$ssl = '';
			if(!isset($par['port']))$par['port'] = 80;
		}

		if(isset($par['path'])){
			$path = substr($url,strpos($url,'/',strpos($url,$par['host'])+strlen($par['host'])));
		}else{
			$path = '/';
		}

		if($post) {
			if(is_array($post))
			{
				$post = http_build_query($post);
			}
			$out = "POST ".$path." HTTP/1.0\r\n";
			$out .= "Accept: */*\r\n";
			if(!empty($referer))$out .= "Referer: ".$referer."\r\n";
			$out .= "Accept-Language: zh-cn\r\n";
			$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$out .= "Host: ".$par['host']."\r\n";
			$out .= 'Content-Length: '.strlen($post)."\r\n";
			$out .= "Connection: Close\r\n";
			$out .= "Cache-Control: no-cache\r\n\r\n";
			$out .= $post;
		} else {
			$out = "GET ".$path." HTTP/1.0\r\n";
			$out .= "Accept: */*\r\n";
			if(!empty($referer))$out .= "Referer: ".$referer."\r\n";
			$out .= "Accept-Language: zh-cn\r\n";
			$out .= "Host: ".$par['host']."\r\n";
			$out .= "Connection: Close\r\n";
			$out .= "Cache-Control: no-cache\r\n\r\n";
		}

		$fp = fsockopen($ssl.$par['host'], $par['port'], $errno, $errstr, 30);
		if(!$fp)return false;

		stream_set_timeout($fp,1);
		fwrite($fp, $out);
		if($exit)return;
		$r = '';
		while (!feof($fp)) {
			$r .= fgets($fp, 128);
		}
		fclose($fp);
		return $r;
	}
}

// 批量获取url数据
function posts($arr){
	$mh = curl_multi_init();
	$chs = [];
	foreach($arr as $v){
		$url = $v['url'];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		if(!empty($v['data'])){
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $v['data']);
		}
		if(!empty($v['cookie'])){
			if(is_array($v['cookie'])){
				foreach($v['cookie'] as $k2 => &$v2){
					$v2 = $k2.'='.$v2;
				}
				$v['cookie'] = implode(';',$v['cookie']);
			}
			curl_setopt($ch, CURLOPT_COOKIE, $v['cookie']);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');

		curl_multi_add_handle($mh, $ch);
		$chs[] = $ch;
	}

	$active = null;
	do{
		while(($mrc = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM);
		if($mrc != CURLM_OK)break;
		while ($done = curl_multi_info_read($mh)) {

			$k = array_search($done['handle'],$chs);
			$arr[$k]['code'] = curl_getinfo($done['handle'])['http_code'];
			$arr[$k]['error'] = curl_error($done['handle']);
			$arr[$k]['body'] = curl_multi_getcontent($done['handle']);

			curl_multi_remove_handle($mh, $done['handle']);
			curl_close($done['handle']);
		}
		if($active > 0)curl_multi_select($mh);
	}while($active);
	curl_multi_close($mh);
	return $arr;
}

// 获取并过滤变量
function I($_p,$data = null){
	if(is_array($_p)){
		foreach($_p as &$v){
			$v = I($v,$data);
		}
	}elseif(!is_string($_p)){
		return $_p;
	}else{
		$tmp = explode('?',$_p,2);
		$zz = isset($tmp[1])?$tmp[1]:null;
		$tmp = explode('=',$tmp[0],2);
		$def = isset($tmp[1])?$tmp[1]:null;
		$tmp = explode('/',$tmp[0],2);
		$type = isset($tmp[1])?$tmp[1]:null;
		$a = explode('.',$tmp[0]);
		$key = array_shift($a);
		switch($key){
			case 'get':
				$p = &$_GET;
			break;
			case 'post':
				$p = &$_POST;
			break;
			case 'file':
				$p = &$_FILES;
			break;
			case 'request':
				$p = &$_REQUEST;
			break;
			case 'session':
				$p = &$_SESSION;
			break;
			case 'cookie':
				$p = &$_COOKIE;
			break;
			case 'server':
				$p = &$_SERVER;
			break;
			case 'data':
				$p = $data;
			break;
			default:
				return $_p;
			break;
		}
		for($i=0,$l=count($a);$i!=$l;++$i){
			if(!is_array($p)){
				break;
			}else{
				$p = &$p[$a[$i]];
			}
		}
		$_p = $p;
		if(is_null($_p) || $_p === ''){
			if(is_null($def)){
				_json([-1004,'input error:'.(APP_DEBUG?$tmp[0]:'')]);
			}else{
				$_p = $def;
			}
		}elseif(!is_null($zz)){
			if(1 !== preg_match($zz,(string)$_p)){
				if(is_null($def)){
					_json([-1004,'input error:'.(APP_DEBUG?$tmp[0]:'')]);
				}else{
					$_p = $def;
				}
			}
		}
		switch($type){
			case 'i':
			case 'int':
				$_p = (int)$_p;
			break;
			case 'I':
				$_p = (int)$_p;
				if($_p < 0)$_p = 0;
			break;
			case 'f':
			case 'float':
				$_p = (float)$_p;
			break;
			case 'd':
			case 'double':
				$_p = (double)$_p;
			break;
			case 'n':
			case 'number':
				preg_match_all('/^\d+/',$_p,$arr);
				$_p = empty($arr[0][0])?'0':ltrim($arr[0][0],'0');
			break;
			case 's':
			case 'string':
				$_p = htmlspecialchars($_p);
			break;
			case 'b':
			case 'bool':
				$_p = (bool)$_p;
			break;
			case 'a':
			case 'array':
				$_p = (array)$_p;
			break;
			case 'o':
			case 'object':
				$_p = (object)$_p;
			break;
			case 'json':
				$_p = empty($_p)?null:json_decode($_p,true);
			break;
			case 'file':
				if(empty($_p)){
					$_p = null;
				}else{
					$d = json_decode($_p,true);
					if(is_array($d)){
						$_p = form(I('file'),'file_'.implode('_',$a),$d);
					}else{
						$_p = null;
					}
				}
			break;
		}
	}
	return $_p;
}

function form($f,$n,$d){
	foreach($d as $k => $v){
		$_n = $n.'_'.$k;
		if(is_array($v)){
			$d[$k] = form($f,$_n,$v);
		}else{
			if(!empty($f[$_n])){
				$d[$k] = $f[$_n];
			}
		}
	}
	return $d;
}

function base64_file($str){
	if(strpos($str,'data:') !== 0)return;
	list($tem,$body)	= explode(',',$str,2);
	list($t1,$t2)		= explode(':',$tem,2);
	list($gs,$type)		= explode(';',$t2,2);

	list($d,$f) = explode('/',$gs,2);

	$index = '';
	switch($d){
		case 'image':
			switch($f){
				case 'jpeg':
				case 'jpg':
					$index = '.jpg';
				break;
				case 'png':
					$index = '.png';
				break;
				case 'gif':
					$index = '.gif';
				break;
				case 'bmp':
					$index = '.bmp';
				break;
				default:
					$index = '.jpg';
				break;
			}
		break;
	}

	$r = null;

	switch($type){
		case 'base64':
			$r = ['name'=>dec62(str_replace('.','',''.microtime(true)).mt_rand(1000, 9999)) . $index,'file'=>base64_decode($body)];
		break;
	}

	return $r;
}

//10进制转62进制
function dec62($n) {
	$base = 62;
	$index = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$ret = '';
	for($t = floor(log10($n) / log10($base)); $t >= 0; $t --) {
	$a = floor($n / pow($base, $t));
	$ret .= substr($index, $a, 1);
	$n -= $a * pow($base, $t);
	}
	return $ret;
}

//62进制转10进制
function dec10($s) {  
	$base = 62;  
	$index = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';  
	$ret = 0;  
	$len = strlen($s) - 1;  
	for($t = 0; $t <= $len; $t ++) {
	$ret += strpos($index, substr($s, $t, 1)) * pow($base, $len - $t);  
	}  
	return $ret;
}

// 设置cookie
function cookie(){
	switch(func_num_args()){
		case 0:
			return $_COOKIE;
		break;
		case 1:
			$k = func_get_arg(0);
			if(isset($_COOKIE[$k])){
				return $_COOKIE[$k];
			}elseif(is_null($k) || $k == ''){
				foreach($_COOKIE as $key => &$value){
					setcookie($key,null,null,'/');
				}
				$c = $_COOKIE;
				$_COOKIE = array();
				return $c;
			}
		break;
		case 2:
			$k = func_get_arg(0);
			$v = func_get_arg(1);
			if(!is_null($k)){
				if(is_null($v) || $v == ''){
					unset($_COOKIE[$k]);
					setcookie($k,null,null,'/');
				}else{
					$_COOKIE[$k] = $v;
					setcookie($k,$v,null,'/');		
				}
			}
		break;
		case 3:
			$k = func_get_arg(0);
			$v = func_get_arg(1);
			$o = func_get_arg(2);
			if(!is_null($k)){
				if(is_null($v) || $v == ''){
					unset($_COOKIE[$k]);
					setcookie($k,null,null,'/');
				}else{
					$_COOKIE[$k] = $v;
					if(is_numeric($o)){
						setcookie($k,$v,$o,'/');
					}elseif(is_array($o)){
						setcookie(
							$k,
							$v,
							!empty($o['expire']) && is_numeric($o['expire'])?$o['expire']:(time()+3600*24),
							!empty($o['path'])?$o['path']:'/',
							!empty($o['domain'])?$o['domain']:'',
							!empty($o['secure'])?$o['secure']:''
						);
					}
				}
			}
		break;
	}
}

// 设置session
function session(){
	switch(func_num_args()){
		case 0:
			if(empty($_SESSION)){
				$sess_name = session_name();
				session_start();
				setcookie($sess_name, session_id(), null, '/', null, null, true);
			}
			return $_SESSION;
		break;
		case 1:
			$k = func_get_arg(0);
			if(is_array($k)){
				if(empty($_SESSION)){
					$sess_name = session_name();
					session_start();
					setcookie($sess_name, session_id(), null, '/', null, null, true);
				}
				foreach($k as $key => &$value){
					$_SESSION[$key] = $value;	
				}
			}elseif(is_null($k)){
				session_start();
				session_destroy();
			}elseif(!empty($k)){
				if(empty($_SESSION)){
					$sess_name = session_name();
					session_start();
					setcookie($sess_name, session_id(), null, '/', null, null, true);
				}
				$k = explode('.',$k);
				$p = &$_SESSION;
				for($i=0,$l=count($k);$i!=$l;++$i){
					if(!is_array($p))return;
					$p = &$p[$k[$i]];
				}
				return $p;
			}
		break;
		case 2:
			if(empty($_SESSION)){
				$sess_name = session_name();
				session_start();
				setcookie($sess_name, session_id(), null, '/', null, null, true);
			}
			$k = func_get_arg(0);
			$v = func_get_arg(1);
			if(!empty($k)){
				$k = explode('.',$k);
				$p = &$_SESSION;
				for($i=0,$l=count($k);$i!=$l;++$i){
					if(!is_array($p))$p=array();
					$p = &$p[$k[$i]];
				}
				$p = $v;
			}
		break;
	}
}

//分页工具
function page($db,$where = '',$field = '',$order = '',$group = ''){
	$page = I('post.page/I');
	$limit = I('post.limit/I');
	try{
		$count = $db->get($where,'count(*) as count',1)['count'];
		if(empty($count)||!isset($count))$count = 0;
		if($page < 1)$page = 1;
		if($limit < 1)$limit = 1;
		if($limit > 100)$limit = 100;
		$sum = ceil($count/$limit);
		//if($sum > 0 && $page > $sum)$page = $sum;
		$data = $db->get($where,$field,$group,$order,[($page-1)*$limit,$limit]);
	}catch(\Exception $e){
		
		return [
			'code'=> 0,
			'msg'=> -500,
			'limit'=> 0,
			'count'=> 0,
			'data'=>[]
		];
	}

	return [
		'code'=> 0,
		'msg'=> '',
		'limit'=> $limit,
		'count'=> $count,
		'data'=>$data
	];
}

//分页工具-获取ids
function page_ids(&$d){
	$n = func_num_args();
	if($n === 2){
		$k = func_get_arg(1);
		$ids = [];
		foreach($d as $v){
			if(!in_array($v[$k],$ids))$ids[] = $v[$k];
		}
		return $ids;
	}else{
		$k = func_get_arg(1);
		$l = func_get_arg(2);
		$i = func_get_arg(3);
		$p = func_get_arg(4);
		$t = [];
		foreach($l as $v){
			$t[$v[$i]] = $v;
		}
		foreach($d as &$v){
			foreach($p as $kp => $vp){
				$v[$kp] = is_string($vp)?$t[$v[$k]][$vp]:$vp($t[$v[$k]]);
			}
		}
		unset($v);
		return $d;
	}
}

function order($oa){
	$order = I('post.order/s=');
	$desc = I('post.desc/I=');

	$_order = [];
	if(in_array($order,$oa)){
		$_order[$order] = $desc === 0?false:true;
	}

	return $_order;
}

}