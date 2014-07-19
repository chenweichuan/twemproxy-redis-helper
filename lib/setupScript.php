<?php
class RCT_SetupScript
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

	public function setNutcrackerYml( RCT_NutcrackerYml $nutcracker_yml )
	{
		$this->nutcracker_yml = $nutcracker_yml;
	}

	public function generateScript()
	{
		$script_tpl = file_get_contents( $this->script_tpl_file );
		$redis_servers = $this->redis_conf->getServers();
		$redis_setup_by_host = array();
		foreach ( $redis_servers as $r_s_v ) {
			$redis_setup_by_host[$r_s_v['host']][] = '/path/to/redis/bin/redis-server /path/to/redis/conf/redis.' . RCT_FLAG . $r_s_v['port'] . '.conf';
		}
		foreach ( $redis_setup_by_host as $r_s_b_h_k => $r_s_b_h_v ) {
			$_script = str_replace( array(
				'{{REDIS}}',
				'{{IP}}',
			), array(
				implode( "\n", $r_s_b_h_v ),
				$r_s_b_h_k,
			), $script_tpl );
			$_script_file = new RCT_File( RCT_PATH . '/tmp/setup/' . $r_s_b_h_k . '/setup.' . RCT_FLAG . '.sh' );
			$_script_file->write( $_script );
		}
	}
}