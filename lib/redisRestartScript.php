<?php
class RCT_RedisRestartScript
{
	protected $script_tpl_file = null;

	protected $redis_conf = null;
	protected $nutcracker_yml = null;

	public function __construct( $script_tpl_file )
	{
		$this->script_tpl_file = $script_tpl_file;
	}

	public function setRedisConf( RCT_RedisConf $redis_conf )
	{
		$this->redis_conf = $redis_conf;
	}

	public function generateScript()
	{
		$script_tpl = file_get_contents( $this->script_tpl_file );
		$redis_servers = $this->redis_conf->getServers();
		$redis_restart_by_host = array();
		foreach ( $redis_servers as $r_s_v ) {
			$redis_restart_by_host[$r_s_v['host']][] = 'echo "["`date "+%Y-%m-%d %H:%M:%S"`"]Restarting: ' . $r_s_v['host'] . ':' . $r_s_v['port'] . '"';
			$redis_restart_by_host[$r_s_v['host']][] = '/path/to/redis/bin/redis-cli -p ' . $r_s_v['port'] . ' shutdown save';
			$redis_restart_by_host[$r_s_v['host']][] = 'sleep 1';
			$redis_restart_by_host[$r_s_v['host']][] = '/path/to/redis/bin/redis-server /path/to/redis/conf/redis.' . RCT_FLAG . $r_s_v['port'] . '.conf';
			$redis_restart_by_host[$r_s_v['host']][] = '';
		}
		foreach ( $redis_restart_by_host as $r_r_b_h_k => $r_r_b_h_v ) {
			$_script = str_replace( array(
				'{{RESTART_REDIS}}',
			), array(
				implode( "\n", $r_r_b_h_v ),
			), $script_tpl );
			$_script_file = new RCT_File( RCT_PATH . '/tmp/restart/' . $r_r_b_h_k . '/restart.' . RCT_FLAG . '.redis.sh' );
			$_script_file->write( $_script );
		}
	}
}