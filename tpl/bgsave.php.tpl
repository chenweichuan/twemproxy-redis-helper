<?php

$redis = new Redis();
$servers = {{SERVERS}};
$log_file = dirname( __FILE__ ) . '/bgsave.log';
$require_memory = {{REQUIRE_MEMORY}};

while ( true ) {
	for ( $i = 0, $l = count( $servers ); $i < $l; ++ $i ) {
		try {
			// 检测内存是否足够持久化
			do {
				$output = null;
				$return_var = null;
				exec( "free -m", $output, $return_var );
				if ( $return_var !== 0 ) {
					file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . ']' . 'Free command error( ' . json_encode( $output ) . ' )' . "\n", FILE_APPEND );
					sleep( 10 );
				}
				preg_match( '/(Mem\:\s+[0-9]+\s+[0-9]+\s+([0-9]+)\s+[0-9]+\s+[0-9]+\s+([0-9]+))/i', implode( "\n", $output ), $memory_params );
				if ( ( $memory_params[2] + $memory_params[3] ) * 1024 * 1024  < $require_memory ) {
					file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . ']' . 'Memory not enough( ' . $memory_params[1] . ' )' . "\n", FILE_APPEND );
					sleep( 10 );
				} else {
					break;
				}
			} while( true );
			// 检测Redis 是否连通，并执行持久化
			$available = $redis->connect( $servers[$i]['host'], $servers[$i]['port'] );
			if ( $available ) {
				$lastsave = $redis->lastSave();
				file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . ']' . "Starting bgsave: {$servers[$i]['host']}:{$servers[$i]['port']}\n", FILE_APPEND );
				$redis->bgSave();
				while ( $redis->lastSave() === $lastsave ) {
					file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . ']' . "Processing bgsave: {$servers[$i]['host']}:{$servers[$i]['port']}\n", FILE_APPEND );
					sleep( 10 );
				}
				file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . ']' . "Complete bgsave: {$servers[$i]['host']}:{$servers[$i]['port']}\n\n", FILE_APPEND );
			} else {
				file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . ']' . "Unavailable: {$servers[$i]['host']}:{$servers[$i]['port']}\n\n", FILE_APPEND );
			}
		} catch ( Exception $e ) {
				file_put_contents( $log_file, '[' . date( 'Y-m-d H:i:s' ) . ']' . "Exception: {$servers[$i]['host']}:{$servers[$i]['port']} {$e->getMessage()}\n\n", FILE_APPEND );
		}
		sleep( 10 );
	}
}
