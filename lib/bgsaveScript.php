<?php
class RCT_BgsaveScript
{
	protected $script_tpl_file = null;

	protected $redis_conf = null;

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
		$servers = $this->redis_conf->getServers();
		$max_memory = $this->redis_conf->getMaxmemory();
		$servers_by_host = array();
		foreach ( $servers as $s_v ) {
			$servers_by_host[$s_v['host']][] = $s_v;
		}
		$require_memory = ceil( $max_memory * 1.5 );
		foreach ( $servers_by_host as $s_b_h_k => $s_b_h_v ) {
			$_servers_str = var_export( $s_b_h_v, true );
			$_script = str_replace( array(
				'{{SERVERS}}',
				'{{REQUIRE_MEMORY}}',
			), array(
				$_servers_str,
				$require_memory,
			), $script_tpl );
			$_conf_file = new RCT_File( RCT_PATH . '/tmp/bgsave/' . $s_b_h_k . '/bgsave.' . RCT_FLAG . '.php' );
			$_conf_file->write( $_script );
		}
	}
}