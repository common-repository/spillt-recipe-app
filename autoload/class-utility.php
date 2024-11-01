<?php
/**
 * This is a utility class, it contains useful methods
 *
 * @since      1.0.0
 */

namespace Spillt;

class Utility {


	/**
	 * Get content of a given file
	 *
	 * @since 1.0.0
	 * @param string $file
	 * @param mixed $vars
	 * @return mixed
	 */
	public static function get_tpl($file = '', $vars = []){
		extract($vars);

		$path = PLUGIN_DIR . '/' . $file . '.php';

		$c = '';

		if (file_exists($path)) {
			ob_start();

			include $path;

			$c = ob_get_clean();

		}
		return $c;
	}


	/**
	 * Get content of a given file
	 *
	 * @since 1.0.0
	 * @param string $file
	 * @param mixed $vars
	 * @return mixed
	 */
	public static function tpl($file = '', $vars = array()) {
		echo self::get_tpl($file, $vars);
	}


	/**
	 * Get a specific property of an array without needing to check if that property exists.
	 *
	 * Provide a default value if you want to return a specific value if the property is not set.
	 *
	 * @since  1.0.0
	 * @param array  $array   Array from which the property's value should be retrieved.
	 * @param string $prop    Name of the property to be retrieved.
	 * @param string $default Optional. Value that should be returned if the property is not set or empty. Defaults to null.
	 *
	 * @return null|string|mixed The value
	 */
	public static function rgar( $array, $prop, $default = null ) {

		if ( ! is_array( $array ) && ! ( is_object( $array ) && $array instanceof ArrayAccess ) ) {
			return $default;
		}

		if ( isset( $array[ $prop ] ) ) {
			$value = $array[ $prop ];
		} else {
			$value = '';
		}

		return empty( $value ) && $default !== null ? $default : $value;
	}



	/**
	 * Gets a specific property within a multidimensional array.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array  $array   The array to search in.
	 * @param string $name    The name of the property to find.
	 * @param string $default Optional. Value that should be returned if the property is not set or empty. Defaults to null.
	 *
	 * @return null|string|mixed The value
	 */
	public static function rgars( $array, $name, $default = null ) {

		if ( ! is_array( $array ) && ! ( is_object( $array ) && $array instanceof ArrayAccess ) ) {
			return $default;
		}

		$names = explode( '/', $name );
		$val   = $array;
		foreach ( $names as $current_name ) {
			$val = self::rgar( $val, $current_name, $default );
		}

		return $val;
	}


	/**
	 * Log errors in a error.log file in the root of the plugin folder
	 *
	 * @since 1.0.0
	 * @param mixed $msg
	 * @param string $code
	 * @return void
	 */
	public static function error_log($msg, $code = ''){

		if(!is_string($msg)){
			$msg = print_r( $msg, true );
		}

		error_log('Error '.$code.' ['.date('Y-m-d h:m:i').']: '.$msg.PHP_EOL, 3, ERROR_PATH);
	}

	/**
	 * @param $obj
	 * @return array|mixed|object
	 */
	public static function obj_to_arr($obj){
		return json_decode(json_encode($obj), true);
	}

    /**
     * @param array $pairs
     * @param array $atts
     * @return array
     */
	public static function atts($pairs = array(), $atts = array()) {
		$atts = (array)$atts;
		$out = array();

		foreach ($pairs as $name => $default) {
			if ( array_key_exists($name, $atts) )
				$out[$name] = $atts[$name];
			else
				$out[$name] = $default;
		}

		return $out;
	}

}
