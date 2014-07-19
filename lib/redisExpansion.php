<?php
class RCT_RedisExpansion
{
	protected $nutcracker_yml = null;
	protected $redis_conf = null;
	protected $expansion_conf = null;

	public function setNutcrackerYml( RCT_NutcrackerYml $nutcracker_yml )
	{
		$this->nutcracker_yml = $nutcracker_yml;
	}

	public function setRedisConf( RCT_RedisConf $redis_conf )
	{
		$this->redis_conf = $redis_conf;
	}

	public function setExpansionConf( RCT_RedisConf $expansion_conf )
	{
		$this->expansion_conf = $expansion_conf;
	}

	public function showTips()
	{
		$tips = array(
			'server) set new server environments by hand.',
			'conf) generate new configs.',
			'slave) set new redis as slave.',
			'check) check whether these slaves are ready.',
			'nutcracker) restart nutcrackers by hand.',
			'script) update scripts by hand.',
			'shutdown) shutdown old master redis.',
			'maxmemory) reset maxmemory.',
		);
		foreach ( $tips as $t_k => $t_v ) {
			$tips[$t_k] = "{$t_k}. {$t_v}";
		}
		echo implode( "\n", $tips ) . "\n";
	}

	public function setUpServers()
	{
		$tips = array(
			'Set up new servers by hand:',
			'install: redis, nutcracker, php',
			'redisdir: /data/redis/data,log,run,script',
			'phpdir: /opt/baofeng-data/ubhvrcache/common/log,tmp',
			'        /opt/baofeng-data/ubhvrcache/revs',
			'etc.',
		);
		echo implode( "\n", $tips ) . "\n";
	}

	public function generateConfigs()
	{
		$tips = array(
			'Generate and deploy new configs and scripts by -g by hand',
			'and set up new redis',
		);
		echo implode( "\n", $tips ) . "\n";
	}

	public function setSlave()
	{
		$redis = new Redis();
		$servers = $this->expansion_conf->getServers();
		for ( $i = 0, $l = count( $servers ); $i < $l; ++ $i ) {
			// 检测Redis 是否连通，并设置主从
			$available = $redis->connect( $servers[$i]['host'], $servers[$i]['port'] );
			if ( $available ) {
				$redis->slaveof( $servers[$i]['master']['host'], $servers[$i]['master']['port'] );
				echo( '[' . date( 'Y-m-d H:i:s' ) . ']' . "Processing sync: {$servers[$i]['host']}:{$servers[$i]['port']}\n" );
				sleep( 30 );
				echo( '[' . date( 'Y-m-d H:i:s' ) . ']' . "Complete sync: {$servers[$i]['host']}:{$servers[$i]['port']}\n\n" );
			} else {
				echo( '[' . date( 'Y-m-d H:i:s' ) . ']' . "Unavailable: {$servers[$i]['host']}:{$servers[$i]['port']}\n\n" );
			}
		}
		echo( '[' . date( 'Y-m-d H:i:s' ) . ']' . "Complete all slave sync\n\n" );
	}

	public function checkSlave()
	{
		$redis = new Redis();
		$date = date( 'Y-m-d H:i:s' );
		$servers = $this->expansion_conf->getServers();
		foreach ( $servers as $s_v ) {
			$_host = $s_v['host'];
			$_port = $s_v['port'];
			echo "[{$date}]Redis {$_host}:{$_port}'s dbsize is ";
			$available = $redis->connect( $_host, $_port, 0.1 );
			if ( ! $available || '+PONG' !== $redis->ping() ) {
				echo "ERR";
			} else {
				echo "[{$redis->dbsize()}]";
			}
			echo "\n";
		}
	}

	public function restartNutcracker()
	{
		$tips = array();
		$tips[] = 'Restart these nutcrackers by hand';
		echo implode( "\n", $tips ) . "\n";
	}

	public function updateScripts()
	{
		$tips = array();
		$tips[] = 'Update these servers\' scripts by hand';
		echo implode( "\n", $tips ) . "\n";
	}

	public function shutdownMaster()
	{
		$redis = new Redis();
		$date = date( 'Y-m-d H:i:s' );
		$servers = $this->expansion_conf->getServers();
		foreach ( $servers as $s_v ) {
			$_host = $s_v['host'];
			$_port = $s_v['port'];
			$_m_host = $s_v['master']['host'];
			$_m_port = $s_v['master']['port'];
			$redis->connect( $_host, $_port );
			$redis->slaveof();
			echo( '[' . date( 'Y-m-d H:i:s' ) . ']' . "Redis slave {$_host}:{$_port} is canceled\n" );
			$output = null;
			$return_var = null;
			exec( "/path/to/redis/bin/redis-cli -h {$_m_host} -p {$_m_port} shutdown", $output, $return_var );
			if ( $return_var !== 0 ) {
				echo( '[' . date( 'Y-m-d H:i:s' ) . ']' . 'Redis shutdown command error( ' . json_encode( $output ) . ' )' . "\n" );
			} else {
				echo( '[' . date( 'Y-m-d H:i:s' ) . ']' . "Old redis {$_m_host}:{$_m_port} is shutdown\n" );
			}
			echo "\n";
		}
		echo( '[' . date( 'Y-m-d H:i:s' ) . ']' . "Complete shutdown\n" );
	}

	public function resetMaxmemory()
	{
		$redis = new Redis();
		$date = date( 'Y-m-d H:i:s' );
		$servers = $this->redis_conf->getServers();
		$maxmemory = $this->redis_conf->getMaxmemory();
		foreach ( $servers as $s_v ) {
			$_host = $s_v['host'];
			$_port = $s_v['port'];
			echo "[{$date}]Redis {$_host}:{$_port} ";
			$available = $redis->connect( $_host, $_port );
			if ( ! $available ) {
				echo "ERR";
			} else {
				if ( $redis->config( 'set', 'maxmemory', $maxmemory ) ) {
					echo "maxmemory is resetted to {$maxmemory}";
				} else {
					echo "maxmemory reset failed";
				}
			}
			echo "\n";
		}
		echo "Complete setting maxmemory.\n";
	}
}