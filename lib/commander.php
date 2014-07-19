<?php
class RCT_Commander
{
	protected $argv = null;

	public function __construct( $argv )
	{
		$this->argv = $argv;
		$this->parseArgv();
	}

	public function __set( $param, $value )
	{
		$this->$param = $value;
	}

	public function __get( $param )
	{
		return null;
	}

	protected function parseArgv()
	{
		$argv = $this->argv;
		array_shift( $argv );
		for ( $i = 0, $l = count( $argv ); $i < $l; ++ $i ) {
			if ( 0 === strpos( $argv[$i], '-' ) ) {
				$_param = str_replace( '-', '_', $argv[$i] );
				$_value = ( isset( $argv[$i + 1] ) && 0 !== strpos( $argv[$i + 1], '-' ) ) ? $argv[$i + 1] : true;
				$this->$_param = $_value;
			}
		}
	}

	public function showHelp( $arg = null )
	{
		static $_tips = array();
		if ( empty( $_tips ) ) {
			$_tips['-h'] = $_tips['--help'] = '-h)--help)    Show command list or command info.';
			$_tips['-g'] = '-g)    Generate conf file of redis, yml file of nutcracker, ' . "\n";
			$_tips['-g'] .= '       bgsave script, restart script, setup script.';
			$_tips['-t'] = '-t)    Test that all redises and nutcrackers are available.';
			$_tips['-i'] = '-i)    Show redises info: dbsize, lastsave';
			$_tips['-e'] = '-e)    Some methods that help to do the expansion,' . "\n";
			$_tips['-e'] .= '       use sub command "tips" to see more.';
			$_tips['-p'] = '-p)    Specify server port, supported by -i.';
		}
		if ( isset( $_tips[$arg] ) ) {
			echo "{$_tips[$arg]}\n";
		} else {
			$_show_tips = array_unique( $_tips );
			foreach ( $_show_tips as $t_k => $t_v ) {
				echo "{$t_v}\n";
			}
		}
	}
}