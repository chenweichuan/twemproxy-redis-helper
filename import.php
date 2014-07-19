<?php
// 必须在cli 模式下运行
if ( 'cli' !== PHP_SAPI ) {
	exit( 'Fatal error.' );
}
ini_set('memory_limit', '5000M');
set_time_limit(0);
ob_implicit_flush( true );

// $_GET
$cli_argv = $_SERVER['argv'];
for ( $i = 2, $l = $_SERVER['argc']; $i < $l; $i += 2 ) {
	$_REQUEST[$cli_argv[$i - 1]] = $_GET[$cli_argv[$i - 1]] = $cli_argv[$i];
}

if ( 'log' === $_GET['step'] ) {
	set_include_path(
	    implode(PATH_SEPARATOR, array(
	        realpath(__DIR__ . '/lib'),
	        get_include_path(),
	        ))
	);

	spl_autoload_register(function($className)
	{
		$classFile = str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
		if (function_exists('stream_resolve_include_path')) {
			$file = stream_resolve_include_path($classFile);
		} else {
			foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
				if (file_exists($path . '/' . $classFile)) {
					$file = $path . '/' . $classFile;
					break;
				}
			}
		}
		/* If file is found, store it into the cache, classname <-> file association */
		if (($file !== false) && ($file !== null)) {
			include $file;
			return;
		}

		throw new RuntimeException($className. ' not found');
	});

	// pid dir
    $pid_dir = __DIR__ . '/log/import_workers';
    if (!file_exists($pid_dir)) mkdir($pid_dir);
    // save log pid
    file_put_contents( $pid_dir . '/log.pid', posix_getpid() );

	// zookeeper address (one or more, separated by commas)
	$zkaddress = '192.168.1.211:2121,192.168.1.212:2121,192.168.1.213:2121';

	// kafka topic to consume from
	$topic = 'topic_name';

	// start and monitor kafka by host and port
    while ( true ) {
    	try {
		    $zookeeper = new Zookeeper( $zkaddress );
		    $topicRegistry = new Kafka_Registry_Topic( $zookeeper );
		    $brokerRegistry = new Kafka_Registry_Broker( $zookeeper );
		    // all hosts and ports
			foreach ( $topicRegistry->partitions( $topic ) as $broker => $nPartitions ) {
				// get host and port
				list( $host, $port ) = explode( ':', $brokerRegistry->address( $broker ) );
				$lockfile = "{$pid_dir}/kafka_{$host}:{$port}.pid";
		        $pid = @file_get_contents( $lockfile );
		        if ( $pid === false || posix_getsid( $pid ) === false ) { 
		            file_put_contents( __DIR__ . '/log/kafka_error_' . date( 'Ymd' ) . '.log', '[' . date( 'H:i:s' ) . ']' . "Process({$host}:{$port}) has died! restarting...\n", FILE_APPEND );
		            system( "/path/to/php/bin/php ". __DIR__ . "/import.php step kafka host {$host} port {$port} > /dev/null 2>&1 &" );
		        }
			}
		} catch ( Exception $exception ) {
		    file_put_contents( __DIR__ . '/log/kafka_error_' . date( 'Ymd' ) . '.log', '[' . date( 'H:i:s' ) . ']' . 'EXCEPTION: ' . $exception->getMessage() . "\n", FILE_APPEND );
		}
        sleep( 1 );
    }
}

if ( 'kafka' === $_GET['step'] ) {
	set_include_path(
	    implode(PATH_SEPARATOR, array(
	        realpath(__DIR__ . '/lib'),
	        get_include_path(),
	        ))
	);

	spl_autoload_register(function($className)
	{
		$classFile = str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
		if (function_exists('stream_resolve_include_path')) {
			$file = stream_resolve_include_path($classFile);
		} else {
			foreach (explode(PATH_SEPARATOR, get_include_path()) as $path) {
				if (file_exists($path . '/' . $classFile)) {
					$file = $path . '/' . $classFile;
					break;
				}
			}
		}
		/* If file is found, store it into the cache, classname <-> file association */
		if (($file !== false) && ($file !== null)) {
			include $file;
			return;
		}

		throw new RuntimeException($className. ' not found');
	});

	// host
    $host = $_GET['host'];
    $port = $_GET['port'];

    // just read the specified host/port's partitions
	class Kafka_ZookeeperConsumer_Rewrite extends Kafka_ZookeeperConsumer
	{
		/**
		 * [Rewrite] Rewind the iterator
		 *
		 * @return void
		 */
		public function rewind() {
			$this->iterators = array();
			$this->nIterators = 0;
			foreach ($this->topicRegistry->partitions($this->topic) as $broker => $nPartitions) {
				for ($partition = 0; $partition < $nPartitions; ++$partition) {
					list($host, $port) = explode(':', $this->brokerRegistry->address($broker));
					// just keep the specified host/port
					if ( $host == $GLOBALS['host'] && $port == $GLOBALS['port'] ) {
						$offset = $this->offsetRegistry->offset($this->topic, $broker, $partition);
						$this->iterators[] = (object) array(
							'consumer'          => null,
							'host'              => $host,
							'port'              => $port,
							'broker'            => $broker,
							'partition'         => $partition,
							'offset'            => $offset,
							'uncommittedOffset' => 0,
							'messages'          => null,
						);
						++$this->nIterators;
					}
				}
			}
			if (0 == count($this->iterators)) {
				throw new Kafka_Exception_InvalidTopic('Cannot find topic ' . $this->topic);
			}
			// get a random broker/partition every time
			$this->shuffle();
		}
	}

	// pid dir
    $pid_dir = __DIR__ . '/log/import_workers';
    if (!file_exists($pid_dir)) mkdir($pid_dir);
    // save kafka pid
    $pidfile = "{$pid_dir}/kafka_{$host}:{$port}.pid";
    file_put_contents( $pidfile, posix_getpid() );

	// zookeeper address (one or more, separated by commas)
	$zkaddress = '192.168.1.211:2121,192.168.1.212:2121,192.168.1.213:2121';

	// kafka topic to consume from
	$topic = 'topci_name';

	// kafka consumer group
	$group = 119105;

	// socket buffer size: must be greater than the largest message in the queue
	$socketBufferSize = 10485760; //10 MB

	// approximate max number of bytes to get in a batch
	$maxBatchSize = 104857600; //100 MB

    // redis log cache list
	$redises_config = array(
		array( '127.0.0.1', 6379 ),
	);

	$list_per_redis = 20;

	$log_per_redis = 2000000;

	$redises = array();
	foreach ( $redises_config as $r_c_v ) {
		list( $_host, $_port ) = $r_c_v;
		$_redis = new Redis();
		if ( ! $_redis->connect( $_host, $_port ) ) {
			throw new Exception( "Log redis({$_host}:{$_port}) unavailable." );
		}
		$redises[] = $_redis;
	}

	while ( true ) {
		$log_list_length = 0;
		foreach ( $redises as $r_v ) {
			$_keys = $r_v->keys( 'LOG_LIST_*' );
			foreach ( $_keys as $_k_v ) {
				$log_list_length += $r_v->lLen( $_k_v );
			}
		}
	    if ( $log_list_length > count( $redises ) * $log_per_redis ) {
	        sleep( 1 );
	        continue;
	    }

	    $zookeeper = new Zookeeper( $zkaddress );
	    $zkconsumer = new Kafka_ZookeeperConsumer_Rewrite(
	        new Kafka_Registry_Topic( $zookeeper ),
	        new Kafka_Registry_Broker( $zookeeper ),
	        new Kafka_Registry_Offset( $zookeeper, $group ),
	        $topic,
	        $socketBufferSize
	    );

	    $messages = array();
	    try {
	        foreach ( $zkconsumer as $message ) {
	            // either process each message one by one, or collect them and process them in batches
	            $messages[] = $message;
	            if ( $zkconsumer->getReadBytes() >= $maxBatchSize ) {
	                break;
	            }
	        }
	    } catch ( Kafka_Exception_OffsetOutOfRange $exception ) {
	        // if we haven't received any messages, resync the offsets for the next time, then bomb out
	        if ( $zkconsumer->getReadBytes() == 0 ) {
	            $zkconsumer->resyncOffsets();
	            file_put_contents( __DIR__ . '/log/kafka_error_' . date( 'Ymd' ) . '.log', '[' . date( 'H:i:s' ) . ']' . 'EXCEPTION: ' . $exception->getMessage() . "\n", FILE_APPEND );
	            exit( $exception->getMessage() );
	        }
	        // if we did receive some messages before the exception, carry on.
	    } catch ( Kafka_Exception_Socket_Connection $exception ) {
        	// deal with it below
	    } catch ( Kafka_Exception $exception ) {
        	// deal with it below
	    }

	    if ( isset( $exception ) ) {
	        // if we haven't received any messages, bomb out
	        if ( $zkconsumer->getReadBytes() == 0 ) {
	            file_put_contents( __DIR__ . '/log/kafka_error_' . date( 'Ymd' ) . '.log', '[' . date( 'H:i:s' ) . ']' . 'EXCEPTION: ' . $exception->getMessage() . "\n", FILE_APPEND );
	            exit( $exception->getMessage() );
	        }
	        // otherwise log the error, commit the offsets for the messages read so far and return the data
	    }

	    // we haven't received any messages
	    if ( $zkconsumer->getReadBytes() == 0 ) {
            file_put_contents( __DIR__ . '/log/kafka_error_' . date( 'Ymd' ) . '.log', '[' . date( 'H:i:s' ) . ']' . 'NOTICE: Don\'t receive any messages.' . "\n", FILE_APPEND );
	    }

		// process the data in batches, wait for ACK
	    foreach ( $messages as $message ) {
	    	// parse message...
	    	// get key...
	    	$key = 'key_value';

		    if ( ! empty( $key ) ) {
		    	$_hash = abs( crc32( $key ) );
		    	$_mod = $_hash % ( count( $redises ) * $list_per_redis );
		    	$redises[floor( $_mod / $list_per_redis)]->lPush( 'LOG_LIST_' . ( $_mod % $list_per_redis ), $message );
	        } else {
	    		file_put_contents( __DIR__ . '/log/log_error_' . date( 'Ymd' ) . '.log', $message . "\n", FILE_APPEND );
	        }
	    }

		// Once the data is processed successfully, commit the byte offsets.
	    $zkconsumer->commitOffsets();
	    unset( $zookeeper );
	    usleep( 200000 );
	}
}

if ( 'import' === $_GET['step'] ) {
	// pid dir
    $pid_dir = __DIR__ . '/log/import_workers';
    if (!file_exists($pid_dir)) mkdir($pid_dir);
    // save import pid
    file_put_contents( $pid_dir . '/import.pid', posix_getpid() );
    // log redis
    $redis = new Redis();
	if ( ! $redis->connect( '127.0.0.1', 6379 ) ) {
		throw new Exception( "Log redis(127.0.0.1:6379) unavailable." );
	}
	// start and monitor worker
    while(true) {
		$keys = $redis->keys( 'LOG_LIST_*' );
		foreach ( $keys as $k_v ) {
			$lockfile = $pid_dir . '/' . $k_v . '.pid';
            $pid = @file_get_contents( $lockfile );
            if ( $pid === false || posix_getsid( $pid ) === false ) { 
	            file_put_contents( __DIR__ . '/log/import_error_' . date( 'Ymd' ) . '.log', '[' . date( 'H:i:s' ) . ']' . "Process {$k_v} has died! restarting...\n", FILE_APPEND );
                system( "/path/to/php/bin/php ". __DIR__ . "/import.php step worker list " . str_replace( 'LOG_LIST_', '', $k_v ) . " > " . __DIR__ . "/log/worker_error_" . date( 'Ymd' ) . ".log 2>&1 &" );
            } else {
                continue;
            }
		}
	   	file_put_contents( __DIR__ . '/log/import_error_' . date( 'Ymd' ) . '.log', '[' . date( 'H:i:s' ) . ']' . "Process import sleep...\n", FILE_APPEND );
        sleep( 1 );
    }
}

if ( 'worker' === $_GET['step'] ) {
	// list num
    $list_id = $_GET['list'];
    // save worker pid
    $pidfile = __DIR__ . '/log/import_workers/LOG_LIST_' . $list_id . '.pid';
    file_put_contents( $pidfile, posix_getpid() );
	// redis cluster
	$redis_cluster = new Redis();
	if ( ! $redis_cluster->connect( '192.168.4.105', 8002 ) ) {
		throw new Exception( "Ubhvrcache redis(192.168.4.105:8002) unavailable." );
	}
    // log redis
	$redis = new Redis();
	$redis->connect( '127.0.0.1', 5379 );
    // consump redis list
    $key = 'LOG_LIST_' . $list_id;
	while ( $message = $redis->rPop( $key ) ) {
		// parse message...
		// save cache...
	}
    // delete pid file when worker ends
    unlink( $pidfile );
}
