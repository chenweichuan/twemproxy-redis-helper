<?php
class RCT_RedisConf
{
	protected $ini_file = null;
	protected $conf_tpl_file = null;
	protected $ini = array();

	public function __construct( $ini_file, $conf_tpl_file )
	{
		$this->ini_file = $ini_file;
		$this->conf_tpl_file = $conf_tpl_file;
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
			list( $_redis, $_master ) = preg_split( '/\s+/', $o_v . ' ' );
			list( $_redis_h, $_redis_p ) = explode( ':', $_redis );
			list( $_master_h, $_master_p ) = explode( ':', $_master . ':' );
			$_redis_p = false === strpos( $_redis_p, '-' ) ? array( $_redis_p ) : call_user_func_array( 'range', explode( '-', $_redis_p ) );
			$_master_p = false === strpos( $_master_p, '-' ) ? $_master_p : call_user_func_array( 'range', explode( '-', $_master_p ) );
			foreach ( $_redis_p as $_r_p_k => $_r_p_v ) {
				$_parsed_ini = array(
					'host' => $_redis_h,
					'port' => $_r_p_v,
				);
				$_parsed_ini['master'] = is_array( $_master_p ) && isset( $_master_p[$_r_p_k] ) ? array(
					'host' => $_master_h,
					'port' => $_master_p[$_r_p_k],
				) : ( is_string( $_master_p ) && $_master_p ? array(
					'host' => $_master_h,
					'port' => $_master_p,
				) : array() );
				$parsed_ini_arr[] = $_parsed_ini;
			}
		}
	}

	public function getMaxmemory()
	{
		$host_num = count( array_unique( array_map( function( $v ) {
			return $v['host'];
		}, $this->ini ) ) );
		$instance_num = count( $this->ini );
		// 预留10GB 内存给系统开销、Redis 持久化、Redis 内存碎片和其他一些程序或脚本
		return (int) ( ( 64 - 10 ) / ( $instance_num / $host_num + 1 ) * pow( 1024, 3 ) );
	}

	public function generateConf()
	{
		$conf_tpl = file_get_contents( $this->conf_tpl_file );
		$maxmemory = $this->getMaxmemory();
		foreach ( $this->ini as $i_v ) {
			$_redis_conf = str_replace( array(
				'{{PORT}}',
				'{{BIND}}',
				'{{MAXMEMORY}}',
			), array(
				$i_v['port'],
				"{$i_v['host']}    127.0.0.1",
				$maxmemory,
			), $conf_tpl );
			$_conf_file = new RCT_File( RCT_PATH . '/tmp/redis/' . $i_v['host'] . '/redis.' . RCT_FLAG . $i_v['port'] . '.conf' );
			$_conf_file->write( $_redis_conf );
			if ( $i_v['master'] ) {
				$_master_conf = str_replace( array(
					'{{PORT}}',
					'{{BIND}}',
					'{{MAXMEMORY}}',
				), array(
					$i_v['master']['port'],
					"{$i_v['master']['host']}    127.0.0.1",
					$maxmemory,
				), $conf_tpl );
				$_conf_file = new RCT_File( RCT_PATH . '/tmp/redis/' . $i_v['host'] . '/redis.' . RCT_FLAG . $i_v['port'] . '.conf' );
				$_conf_file->write( $_master_conf );
			}
		}
	}

	public function getServers()
	{
		return $this->ini;
	}
}