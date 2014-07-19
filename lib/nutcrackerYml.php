<?php
class RCT_NutcrackerYml
{
	protected $ini_file = null;
	protected $yml_tpl_file = null;
	protected $ini = array();

	protected $redis_conf = null;

	public function __construct( $ini_file, $yml_tpl_file )
	{
		$this->ini_file = $ini_file;
		$this->yml_tpl_file = $yml_tpl_file;
		$this->parseIni();
	}

	protected function parseIni()
	{
		$ori_ini = file_get_contents( $this->ini_file );
		$ori_ini_arr = explode( "\n", $ori_ini );
		$parsed_ini_arr = & $this->ini;
		foreach ( $ori_ini_arr as $o_v ) {
			if ( empty( $o_v ) ) {
				continue;
			}
			$parsed_ini_arr[] = explode( '/', $o_v );
		}
	}

	public function setRedisConf( RCT_RedisConf $redis_conf )
	{
		$this->redis_conf = $redis_conf;
	}

	public function generateYml()
	{
		$yml_tpl = file_get_contents( $this->yml_tpl_file );
		$redis_servers = $this->redis_conf->getServers();
		$servers = '';
		foreach ( $redis_servers as $r_s_v ) {
			$servers .= "   - {$r_s_v['host']}:{$r_s_v['port']}:1 port{$r_s_v['port']}\n";
		}
		foreach ( $this->ini as $i_v ) {
			$_nutcracker_yml = '';
			$_nutcracker_yml .= str_replace( array(
				'{{NUMBER}}',
				'{{IP}}',
				'{{SERVERS}}',
			), array(
				0,
				'0.0.0.0',
				$servers,
			), $yml_tpl );
			$_nutcracker_yml .= "\n";
			$_yml_file = new RCT_File( RCT_PATH . '/tmp/nutcracker/' . $i_v[0] . '/nutcracker.' . RCT_FLAG . '.yml' );
			$_yml_file->write( $_nutcracker_yml );
		}
	}

	public function getServers()
	{
		return $this->ini;
	}
}
