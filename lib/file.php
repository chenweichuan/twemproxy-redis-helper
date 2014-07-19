<?php
class RCT_File
{
    protected $file = null;
    protected $permission = null;

    public function __construct( $file )
    {
        $this->file = $file;
    }

    public function setPermission( $permission )
    {
        $this->permission = $permission;
    }

	public function read()
	{
		return is_readable( $this->file ) ? file_get_contents( $this->file ) : null;
	}

	public function write( $str )
	{
        $this->_makeDir();
        file_put_contents( $this->file, $str );
        empty( $this->permission ) || chmod( $this->file, $this->permission );
	}

    public function append( $str )
    {
        $this->_makeDir();
        file_put_contents( $this->file, $str, FILE_APPEND );
        empty( $this->permission ) || chmod( $this->file, $this->permission );
    }

    /**
     * 尝试解析配置文件
     * @param string    $env        环境
     * @return array|false
     */
    public function parseIni( $env = null )
    {
        $config = parse_ini_file( $this->file, true );
        return isset( $config[$env] ) ? $config[$env] : $config;
    }

    private function _makeDir()
    {
        $dir = dirname( $this->file );
        //若目录不存在， 创建目录
        if ( ! is_dir( $dir ) ) {
            mkdir( $dir, 0777, true );
        }
        if ( !is_writable( $dir ) ) {
            throw new Exception( $dir . ' is not writable.' );
        }
    }
}
