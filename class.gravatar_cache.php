<?php

/**
 * Class WP_GravatarCache
 *
 *      global $wpgc;
 *      $wpgc = new WP_GravatarCache(
 *          array(
 *              'ttl_day'  => 10,
 *              'ttl_hour' => 0,
 *              'ttl_min'  => 0,
 *          ),
 *          $priority = 1000000000
 *      );
 *
 * @link    https://github.com/DevDiamondCom/WP_GravatarCache
 * @version 1.0.3.2
 * @author  DevDiamond <me@devdiamond.com>
 */
class WP_GravatarCache
{
	private $plugin_name       = 'WP Gravatar Cache';
    private $upload_folder     = '/gravatar/';
	private $cache_option_slug = 'wpgc_avatars_cache';

	private $default_options;
    private $upload_url;
    private $upload_path;
	private $siteurl;

	private $_messages = array();
	private $_allowed_message_types = array('info', 'warning', 'error');

	/**
	 * WP_GravatarCache constructor.
	 *
	 * @param array $args     - defoult param {'ttl_day' => 10, 'ttl_hour' => 0, 'ttl_min' => 0}
	 * @param int   $priority - Priority get_avatar Hook
	 */
    function __construct( $args = array(), $priority = 1000000000 )
    {
	    $this->default_options['ttl_day'] = (int) (@$args['ttl_day'] ?: 10);
	    $this->default_options['ttl_hour'] = (int) (@$args['ttl_hour'] ?: 0);
	    $this->default_options['ttl_min'] = (int) (@$args['ttl_min'] ?: 0);

	    $this->siteurl = (string) get_option('siteurl');

	    if ( get_option( 'upload_url_path' ) )
        {
            $this->upload_url  = get_option( 'upload_url_path' );
            $this->upload_path = get_option( 'upload_path' );
        }
        else
        {
            $up_dir = wp_upload_dir();
            $this->upload_url  = $up_dir['baseurl'];
            $this->upload_path = $up_dir['basedir'];
        }

        if ( ! is_writable( $this->upload_path . $this->upload_folder ) && is_dir( $this->upload_path . $this->upload_folder ) )
	        $this->add_message( 'error', 'Please set write permissions for "'. $this->upload_path . $this->upload_folder .'"' );
        elseif ( @!mkdir( $this->upload_path . $this->upload_folder, 0777 ) && ! is_dir( $this->upload_path . $this->upload_folder ) )
            $this->add_message( 'error', 'Could not create directory "gravatar". Please set write permissions for "'. $this->upload_path . $this->upload_folder .'"'  );

	    add_filter( 'get_avatar', array( $this, 'get_cached_avatar' ), (int) $priority, 5 );
	    add_action( 'admin_notices', array( $this, 'admin_help_notice' ) );
    }

    /**
     * Convert ttl option to second
     */
    private function cache_to_second()
    {
	    $cache_second = 0;
        foreach ( $this->default_options as $key => $value )
        {
            switch ( $key )
            {
                case 'ttl_min':
                    $cache_second = ($value != 0 ? ($value*60) : $cache_second);
                    break;
                case 'ttl_hour':
                    $cache_second = ($value != 0 ? (( $value*60*60 ) + $cache_second) : $cache_second);
                    break;
                case 'ttl_day':
                    $cache_second = ($value != 0 ? (( $value*60*60*24 ) + $cache_second) : $cache_second);
                    break;
            }
        }

        if ( $cache_second == 0 )
            return 864000; // TTL of cache in seconds (10 days)
		else
	        return $cache_second;
    }

	/**
	 * Add notice text
	 *
	 * @param string $type - Message type
	 * @param string $text - Text message
	 */
	private function add_message($type, $text)
	{
		if ( in_array($type, $this->_allowed_message_types))
			$this->_messages[$type][] = $text;
		else
			$this->_messages['error'][] = 'Message not added!';
	}

	/**
	 * Admin page help notices
	 */
	public function admin_help_notice()
	{
		if( empty( $this->_messages ) )
			return;

		foreach ( $this->_messages as $type => $contents )
		{
			if ( $type == 'error' )
			{
				echo '<div class="'. $type .' fade">';
				foreach ( $contents as $content )
					echo '<p><strong>'. $this->plugin_name .': </strong>' . $content . '</p>';
				echo '</div>';
			}
			elseif ( $type != 'error' )
			{
				echo '<div class="updated fade">';
				foreach ( $contents as $content )
					echo '<p><strong>'. ucfirst($type) .': </strong>' . $content . '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Get Cached Avatar
	 *
	 * @param string     $source       - Gravatar URL
	 * @param int|string $id_or_email  - User ID or Email
	 * @param int        $size         - Icon size
	 * @param string     $default      - Default
	 * @param string     $alt          - alt attribute
	 *
	 * @return string - HTML IMG
	 */
    public function get_cached_avatar( $source, $id_or_email, $size, $default, $alt )
    {
        if ( ! is_writable( $this->upload_path . $this->upload_folder ) || is_admin() )
            return $source;

	    if ( $this->siteurl && strpos( $source, $this->siteurl, 0 ) !== false )
	    	return $source;

	    $time = $this->cache_to_second();

        preg_match('/d=([^&]*)/', $source, $d_tmp);
        $g_url_default_sorce = isset($d_tmp[1]) ? $d_tmp[1] : false;

        preg_match('/forcedefault=([^&]*)/', $source, $d_tmp);
        $g_forcedefault = isset($d_tmp[1]) ? $d_tmp[1] : false;

        preg_match('/avatar\/([a-z0-9]+)\?s=(\d+)/', $source, $tmp);
        $garvatar_id = $tmp[1];

        $file_name      = md5($garvatar_id.$g_url_default_sorce);
        $g_path         = $this->upload_path.$this->upload_folder.$file_name.'-s'.$size.'.jpg';
        $g_path_default = $this->upload_path.$this->upload_folder.'default'.'-s'.$size.'.jpg';
        $g_url          = $this->upload_url.$this->upload_folder.$file_name.'-s'.$size.'.jpg';
        $g_url_default  = $this->upload_url.$this->upload_folder.'default'.'-s'.$size.'.jpg';

        // Check cache
        static $wpgc_avatars_cache = null;
        if ( $wpgc_avatars_cache === null )
        	$wpgc_avatars_cache = get_option($this->cache_option_slug);
        if ( ! is_array($wpgc_avatars_cache) )
        	$wpgc_avatars_cache = array();

        if ( isset($wpgc_avatars_cache[ $garvatar_id ][ $size ]) )
        {
            $g_url  = $wpgc_avatars_cache[ $garvatar_id ][ $size ]['url'];
            $g_path = $wpgc_avatars_cache[ $garvatar_id ][ $size ]['path'];
        }

        if ( ! is_file($g_path) || (time() - filemtime($g_path)) > $time)
        {
            $curl_url = 'http://www.gravatar.com/avatar/'.$garvatar_id.'?s='.$size.'&r=G&d='.$g_url_default_sorce;

            $ch = curl_init($curl_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $response    = curl_exec($ch);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header      = substr($response, 0, $header_size);

            // Checking for redirect
            $header_array = array();
            preg_match('/^Location\: (.*)$/m', $header, $header_array);
            $redirect_url = isset($header_array[1]) ? $header_array[1] : false;

            if ($redirect_url)
            {
                $g_url  = $g_url_default;
                $g_path = $g_path_default;
                if ( ! is_file($g_path) || (time()-filemtime($g_path)) > $time )
                    copy($redirect_url, $g_path);
            }
            else
            {
                // Check mime type
                $mime_str   = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
                $mime_array = array();
                preg_match( '#/([a-z]*)#i', $mime_str, $mime_array );

	            if ( isset( $mime_array[ 1 ] ) )
	            {
		            $img_ext = array('jpg', 'gif', 'jpeg', 'png');

		            if ( false !== strpos( $g_path, '.' ) )
		            {
			            $ext = explode('.', $g_path);
                        $ext = $ext[count($ext)-1];
		            }
		            else
			            $ext = ' ';

		            if ( is_string( $ext ) )
		            {
			            if ( in_array( strtolower($ext), $img_ext ) )
			            {
				            if ( is_writable( $this->upload_path . $this->upload_folder ) )
				            {
					            $fp   = fopen( $g_path, "wb" );
					            $body = substr( $response, $header_size );
					            fwrite( $fp, $body );
					            fclose( $fp );
				            }
			            }
			            else
			            {
				            $this->add_message( 'error', 'Please set write permissions for "' . $this->upload_path . $this->upload_folder .'"' );
			            }
		            }

	            }
            }
            curl_close($ch);

            $wpgc_avatars_cache[ $garvatar_id ][ $size ]['url']  = $g_url;
            $wpgc_avatars_cache[ $garvatar_id ][ $size ]['path'] = $g_path;
            update_option( $this->cache_option_slug, $wpgc_avatars_cache );
        }

        return '<img alt="'.$alt.'" src=\''.$g_url.'\' class="avatar avatar-'.$size.'" width="'.$size.'" height="'.$size.'" />';
    }

	/**
	 * Clear Cache
	 */
    public function clear_cache()
    {
        $dir = $this->upload_path . $this->upload_folder;
        $no_permision_to_delete = false;

        // Open directory
        if ( is_dir( $dir ) )
        {
            if ( $opendir = opendir( $dir ) )
            {
                $count = 0;
                while ( ( $file = readdir( $opendir ) ) !== false )
                {
                    if ( filetype( $dir . $file ) == 'file' )
                    {
                        if ( @unlink( $dir . $file ) )
                            $count++;
                        else
                            $no_permision_to_delete = true;
                    }
                }
                if ( $no_permision_to_delete )
                {
	                $this->add_message( 'error','Unable to clear the cache' );
                }
                else
                {
                    update_option($this->cache_option_slug, array() );
	                $this->add_message( 'info','The cache is cleared! Removed '.$count.' files' );
                }
                closedir( $opendir );
            }
        }
	}

	/**
	 * Get Cache Info
	 *
	 * @return array - Cache info
	 */
    public function get_cache_info()
    {
		$dir  = $this->upload_path . $this->upload_folder;
		$skip = array('.','..');
		$unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');

        if ( is_dir( $dir ) )
        {
			$file_list = scandir( $dir );

			// delete . and ..
			foreach ( $skip as $value )
				unset( $file_list[ array_search( $value, $file_list ) ] );

			// sum files size
			$all_size = 0;
			foreach ( $file_list as $file )
			{
				$size     = filesize( $dir . $file );
			    $all_size = $all_size + $size;
			}
        }

	    $readable_form = @round( $all_size / pow( 1024, ( $i = floor( log( $all_size, 1024) ) ) ), 2 ) . ' ' . $unit[$i];

	    return array( 'amount' => count( $file_list ) , 'used_space' => $readable_form );
	}

} // End Class