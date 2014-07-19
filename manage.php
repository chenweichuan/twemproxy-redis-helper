<?php

// 必须在cli 模式下运行
if ( 'cli' !== PHP_SAPI ) {
	exit( 'Fatal error.' );
}
ob_implicit_flush( true );

// 业务标记
define( 'RCT_FLAG', 'ubhvrcache' );
// 脚本根目录
define( 'RCT_PATH', dirname( __FILE__ ) );

/*
 * 库类文件加载
 */
function RCT_loadLib( $lib_name )
{
	static $_loaded_libs = array();
	if ( ! isset ( $_loaded_libs[$lib_name] ) ) {
		require_once RCT_PATH . '/lib/' . $lib_name . '.php';
		$_loaded_libs[$lib_name] = true;
	}
}

RCT_loadLib( 'bgsaveScript' );
RCT_loadLib( 'commander' );
RCT_loadLib( 'file' );
RCT_loadLib( 'nutcrackerYml' );
RCT_loadLib( 'redisConf' );
RCT_loadLib( 'redisExpansion' );
RCT_loadLib( 'redisRestartScript' );
RCT_loadLib( 'setupScript' );

// 命令
$commander = new RCT_Commander( $argv );
// redis 配置
$redis_conf = new RCT_RedisConf( RCT_PATH . '/etc/redis.ini', RCT_PATH . '/tpl/redis.conf.tpl' );
// nutcracker 配置
$nutcracker_yml = new RCT_NutcrackerYml( RCT_PATH . '/etc/nutcracker.ini', RCT_PATH . '/tpl/nutcracker.yml.tpl' );

// 命令帮助
$help = $commander->_h ? $commander->_h : $commander->__help;
if ( true === $help ) {
	$commander->showHelp();
} else if ( $help ) {
	$commander->showHelp( strlen( $help ) > 1 ? "--{$help}" : "-{$help}" );
}

// 生成配置文件
if ( $commander->_g ) {
	// redis 配置
	if ( 'redis' === $commander->_g || true === $commander->_g ) {
		$redis_conf->generateConf();
	}
	// nutcracker 配置
	if ( 'nutcracker' === $commander->_g || true === $commander->_g ) {
		$nutcracker_yml->setRedisConf( $redis_conf );
		$nutcracker_yml->generateYml();
	}
	// bgsave 脚本
	if ( 'bgsave' === $commander->_g || true === $commander->_g ) {
		$bgsave_script = new RCT_BgsaveScript( RCT_PATH . '/tpl/bgsave.php.tpl' );
		$bgsave_script->setRedisConf( $redis_conf );
		$bgsave_script->generateScript();
	}
	// restart 脚本
	if ( 'restart' === $commander->_g || true === $commander->_g ) {
		$setup_script = new RCT_RedisRestartScript( RCT_PATH . '/tpl/restartRedis.sh.tpl' );
		$setup_script->setRedisConf( $redis_conf );
		$setup_script->generateScript();
	}
	// setup 脚本
	if ( 'setup' === $commander->_g || true === $commander->_g ) {
		$setup_script = new RCT_SetupScript( RCT_PATH . '/tpl/setup.sh.tpl' );
		$setup_script->setRedisConf( $redis_conf );
		$setup_script->setNutcrackerYml( $nutcracker_yml );
		$setup_script->generateScript();
	}
}

// 扩容辅助
if ( $commander->_e ) {
	// expansion 配置
	$expansion_conf = new RCT_RedisConf( RCT_PATH . '/etc/expansion.ini', null );
	// expansion manager
	$redis_expansion = new RCT_RedisExpansion();
	$redis_expansion->setNutcrackerYml( $nutcracker_yml );
	$redis_expansion->setRedisConf( $redis_conf );
	$redis_expansion->setExpansionConf( $expansion_conf );
	// show tips
	if ( 'tips' === $commander->_e || true === $commander->_e ) {
		$redis_expansion->showTips();
	}
	// set up servers
	if ( 'server' === $commander->_e ) {
		$redis_expansion->setUpServers();
	}
	// generate configs
	if ( 'config' === $commander->_e ) {
		$redis_expansion->generateConfigs();
	}
	// build slave
	if ( 'slave' === $commander->_e ) {
		$redis_expansion->setSlave();
	}
	// check slave
	if ( 'check' === $commander->_e ) {
		$redis_expansion->checkSlave();
	}
	// restart nutcracker
	if ( 'nutcracker' === $commander->_e ) {
		$redis_expansion->restartNutcracker();
	}
	// update some scripts and process
	if ( 'script' === $commander->_e ) {
		$redis_expansion->updateScripts();
	}
	// shutdown master
	if ( 'shutdown' === $commander->_e ) {
		$redis_expansion->shutdownMaster();
	}
	// change maxmemory
	if ( 'maxmemory' === $commander->_e ) {
		$redis_expansion->resetMaxmemory();
	}
}

// 测试
if ( $commander->_t ) {
	$redis = new Redis();
	$date = date( 'Y-m-d H:i:s' );
	// redis
	if ( 'redis' === $commander->_t || true === $commander->_t ) {
		$redis_servers = $redis_conf->getServers();
		foreach ( $redis_servers as $r_s_v ) {
			$_host = $r_s_v['host'];
			$_port = $r_s_v['port'];
			echo "[{$date}]Redis {$_host}:{$_port} is ";
			$available = $redis->connect( $_host, $_port, 0.5 );
			if ( ! $available ) {
				echo "UNAVAILABLE";
			} else if ( '+PONG' !== $redis->ping() ) {
				echo "UNPING";
			} else {
				echo "OK";
			}
			echo "\n";
		}
	}
	// nutcracker
	if ( 'nutcracker' === $commander->_t || true === $commander->_t ) {
		$nutcracker_servers = $nutcracker_yml->getServers();
		$ports = array( 'r' => 8001, 'rw' => 8002 );
		foreach ( $nutcracker_servers as $n_s_v ) {
			foreach ( $n_s_v as $n_s_v_v ) {
				foreach ( $ports as $p_v ) {
					$_host = $n_s_v_v;
					$_port = $p_v;
					echo "[{$date}]Nutcracker {$_host}:{$_port} is ";
					$available = $redis->connect( $_host, $_port, 0.5 );
					if ( ! $available ) {
						echo "UNAVAILABLE";
					} else {
						echo "OK";
					}
					echo "\n";
				}
			}
		}
	}
}

if ( true === $commander->_i ) {
	echo "Please specifing a kind of info to show.\n";
} else if ( $commander->_i ) {
	$redis = new Redis();
	$date = date( 'Y-m-d H:i:s' );
	$redis_servers = $redis_conf->getServers();
	foreach ( $redis_servers as $r_s_v ) {
		$_host = $r_s_v['host'];
		$_port = $r_s_v['port'];
		if ( $commander->_p && ( $_port != $commander->_p ) ) {
			continue;
		}
		echo "[{$date}]Redis {$_host}:{$_port}'s ";
		$available = $redis->connect( $_host, $_port, 0.5 );
		if ( ! $available || '+PONG' !== $redis->ping() ) {
			echo "ERR";
		} else {
			switch ( $commander->_i ) {
				case 'dbsize' :
					echo "dbsize: [{$redis->dbsize()}]";
					break;
				case 'lastsave' :
					echo 'lastsave: [' . date( 'Y-m-d H:i:s', $redis->lastSave() ) . ']';
					break;
				default :
					echo $commander->_i . ': ' . "\n";
					foreach ( $redis->info( $commander->_i ) as $i_k => $i_v ) {
						echo "{$i_k}: {$i_v}\n";
					}
			}
		}
		echo "\n";
	}
}

if ( $commander->__get ) {
	$nutcracker_servers = $nutcracker_yml->getServers();
	$host = $nutcracker_servers[0][0];
	$port = 8001;
	$redis = new Redis();
	$redis->connect( $host, $port, 0.5 );
	$behaviors = $redis->hGetall( $commander->__get );
	foreach ( $behaviors as $b_k => $b_v ) {
		$behaviors[$b_k] = msgpack_unpack( $b_v );
	}
	var_dump( $behaviors );
	echo "\n";
}

