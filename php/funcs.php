<?php
/*
 * Get a web resource (HTML, XHTML, XML, image, etc.) from a URL.  Return an
 * array containing the HTTP server response header fields and content.
 */

/*define('URL_UNCLASSIFIED', 0);
define('URL_ABSOLUTE', 1);
define('URL_RELATIVE', 2);
define('URL_DOMAIN_RELATIVE', 3);
define('URL_SCHEME_RELATIVE', 4);
define('URL_MAILTO', 5);
define('URL_JAVASCRIPT', 6);
define('URL_OTHER', 7);*/




/**
 * Use cURL to get the contents of a page at a given URL
 * @param string The URL to get the contents from
 * @param array Additional cURL options, to override the defaults
 * @param int A handle to a curl object
 * @return array The cURL results
 */
function curl_get_contents($url, $options = array(), $ch = FALSE)
{
	$close_ch = TRUE;
	if ($ch) { $close_ch = FALSE; }
	
	$defaults = array(
		CURLOPT_RETURNTRANSFER => TRUE,  // Returns the requested resource's contents
		CURLOPT_HEADER         => FALSE, // Returns the requested resource's HTTP headers
		CURLOPT_FOLLOWLOCATION => TRUE,  // Follow HTTP 30X redirects
		CURLOPT_ENCODING       => '',
		CURLOPT_USERAGENT      => 'dev.msp link checker v.1',
		CURLOPT_AUTOREFERER    => TRUE,
		CURLOPT_CONNECTTIMEOUT => 20,
		CURLOPT_TIMEOUT        => 20,
		CURLOPT_MAXREDIRS      => 10
	);
	
	// Combine defaults with caller options
	$options = $options + $defaults;

	// Disable multiple redirects if open_basdir or safe_mode is enabled
	if (ini_get('open_basedir') || ini_get('safe_mode'))
	{
		unset($options[CURLOPT_FOLLOWLOCATION]);
	}

	// If the cURL handle is invalid, initialize one
	if (! $ch) { $ch = curl_init($url); }
	if (! $ch)
	{
		$retval['errmsg']  = 'cURL error: Could not initialize the cURL session.';
	}
	else
	{
		// Set the session options
		curl_setopt_array($ch, $options);
		
		// Execute the curl session
		$content = curl_exec($ch);
	
		// Test for success or failure then return the results or error message.
		if(curl_errno($ch))
		{
			$retval['errmsg']  = curl_error($ch);
		}
		else 
		{
			$retval = curl_getinfo($ch);
			$retval['content'] = $content;
		}
	
		if ($close_ch) { curl_close($ch); }
	}
	
	return $retval;
}


/**
 * Display errors in an HTML unordered list
 * @param array the list of error messages
 * @return void
 */
function display_errors($errors)
{
	if (! empty($errors))
	{
		echo '<ul class="errors">';
		
		if (is_array($errors))
		{
			foreach($errors as $error_msg)
			{
				echo "<li>$error_msg</li>";
			}
		}
		else
		{
			echo "<li>$errors</li>";
		}
		
		echo '</ul>';
	}
	return;
}

/**
 * Same as q() but exits the app after displaying the data
 * @param mixed The datastructure to display
 * @return void
 * @see q()
 */
function a($data)
{
	q($data);
	exit(0); 
}

/**
 * Print out array and other data structure in a readable HTML format
 * @param mixed The datastructure to display
 * @return void
 */
function q($data, $color='red')
{
	echo '<pre style="color:'.$color.'">'; 
	print_r($data);
	echo '</pre>'; 
}

/** ************************************************************************************************
 * Take an href string and break it down into components
 *	href        // the original, raw href
 *	scheme      // i.e. http
 *	host        // i.e. www.example.com
 *	path        // i.e. /abc/1
 *	query       // i.e. ?param=234
 *	fragment    // i.e. #fragment
 *	path_type   // Can be one of 0-unclassified, 1-absolute, 2-relative, 3-domain relative, 4-scheme relative, 5-mailto, 6-javascript or 9-other
 *	errors      // And array of errors
 */
function href_dissect($href)
{
	$retval = array(
		'href' => trim($href),
		// mailto_addr => '',
		// scheme => '',
		// host => '',
		//'path_raw' => '',
		//'filename' => '',
		// query => '',
		// fragment => '',
		//'url' => trim($href),
		'path_type' => PathType::undefined
		//'url' => PathType::undefined,
		//'errors' => array()
	);

	// Check if an empty href was passed to this method
	if (! mb_strlen($retval['href'])) { return array('errors' => array('missing_url' => 'An empty href was provided')); }

	// Flag a mailto href
/*	if (mb_strtolower(mb_substr($retval['href'], 0, 7)) === 'mailto:')
	{
		$retval['path_type'] = PathType::mailto;
		return $retval;
	}

	// Flag a javascript href
	if (mb_strtolower(mb_substr($retval['href'], 0, 11)) === 'javascript:')
	{
		$retval['path_type'] = PathType::javascript;
		return $retval;
	}*/
	
	// Extract the fragment, if there is one
	$segments = explode('#', $retval['href'], 2);
	if (count($segments) == 2)
	{
		if (mb_strlen($segments[1]))
		{
			$retval['fragment'] = '#'.$segments[1];
		}
		
		if (mb_strlen($segments[0]) == 0)
		{
			$retval['path_type'] = PathType::relative;
			return $retval;
		}
	}
	
	// Extract the query, if there is one
	$segments = explode('?', $segments[0], 2);
	if (count($segments) == 2)
	{
		if (mb_strlen($segments[1]))
		{
			$retval['query'] = '?'.$segments[1];
		}
		
		if (mb_strlen($segments[0]) == 0)
		{
			$retval['path_type'] = PathType::relative;
			return $retval;
		}
	}

	// Split the URL to try and get the scheme
	$segments = explode('://', $segments[0], 2);
	if (count($segments) == 2) // Get the scheme
	{
		if ($segments[0] === '') // Test for an empty scheme
		{
			//$retval['path_type'] = PathType::scheme_relative;
			//$segments[0] = $segments[1];
			$retval['errors']['invalid_scheme'] = 'Invalid href: Missing scheme in '.$retval['href'];
			return $retval;
		}
		else if ($segments[0] !== 'http' && $segments[0] !== 'https') // Test for a valid scheme
		{
			$retval['errors']['invalid_scheme'] = 'Invalid href: The scheme should be "http" or "https" in '.$retval['href'];
			return $retval;
		}

		// Test for an empty host. If it's empty then we need to return an error - the href is invalid
		if ($segments[1] === '')
		{
			$retval['errors']['invalid_host'] = 'Invalid href: Missing host in '.$retval['href'];
			return $retval;
		}

		// We've got a valid scheme, store it
		$retval['scheme'] = $segments[0];

		$segments = explode('/', $segments[1], 2);

		if (! ctype_alpha(mb_substr($segments[0], 0, 1))) // The host must start with an alpha character
		{
			$retval['errors']['invalid_host'] = 'Invalid href: Host must start with an alpha character in '.$retval['href'];
			return $retval;
		}

		// We've got a valid host, store it and flag a absolute href
		$retval['host'] = $segments[0];
		$retval['path_type'] = PathType::absolute;

		//
		if (isset($segments[1])) //
		{
			$retval['path_raw'] = '/'.$segments[1];
		}
		else
		{
			$retval['path_raw'] = '';
		}
		return $retval;
	}
	
	// Check for a scheme relative href
	if (mb_substr($segments[0], 0, 2) === '//')
	{
		$segments[0] = mb_substr($segments[0], 2);
		
		if (empty($segments[0]))
		{
			$retval['errors']['invalid_host'] = 'Invalid href: Missing host in '.$retval['href'];
			return $retval;
		}
		else if (! ctype_alpha(mb_substr($segments[0], 0, 1)))
		{
			$retval['errors']['invalid_host'] = 'Invalid href: Host must start with an alpha character in '.$retval['href'];
			return $retval;
		}

		$segments = explode('/', $segments[0], 2);
		
		$retval['host'] = $segments[0];
		$retval['path_type'] = PathType::scheme_relative;

		//
		if (isset($segments[1])) //
		{
			$retval['path_raw'] = '/'.$segments[1];
		}
		else
		{
			$retval['path_raw'] = '';
		}
		return $retval;
	}
	
	// At this point, the href is either realtive or domain relative
	if (mb_substr($segments[0], 0, 1) === '/')
	{
		$retval['path_type'] = PathType::domain_relative;
	}
	else
	{
		$retval['path_type'] = PathType::relative;
	}
	
	$retval['path_raw'] = $segments[0];
	return $retval;
}


/**
 *
 */
function href_resolve($href, $rel_scheme='', $rel_host='', $rel_path='')
{
	
	
}

/**
 * Resolves a path
 *
 * @access	public
 * @param	string
 * @param	array
 * @return	array
 */
function get_resolved_path($path, $default_to_folder = array('index.php'=>'','index.html'=>'','index.htm'=>''))
{
	$retval = array('path_resolved' => '', 'filename' => ''); // Function return value
	$segments = explode('/', str_replace("\\", '/', $path)); // Split the path into segments
	$skip_counter = 0; // incremented when ../ is found in the path

	$seg_cnt = count($segments)-1; // Store the total number of segments

	// loop through all segments and re-build the path, effectively removing ./ and ../
	for($i = $seg_cnt; $i >= 0; $i--)
	{
		if ($segments[$i] == '..')
		{
			$skip_counter++;
			continue;
		}
		elseif ($segments[$i] == '.' || $segments[$i] == '') { continue; } // Skip empty segments (consecutive slashes), or single dots
		
		// Test if this segment is the filename
		if ($i == $seg_cnt && mb_strpos($segments[$seg_cnt], '.') !== FALSE)
		{
			$retval['filename'] = $segments[$seg_cnt];
			continue;
		}

		// Skip this segment if the parent double dot (..) segment was previosuly encountered
		if ($skip_counter > 0)
		{
			$skip_counter--;
			continue;
		}
		
		// Otherwise, prepend the segment to the semi-parsed resolved path
		$retval['base_path'] = "/{$segments[$i]}{$retval['base_path']}";
	}
	
	$retval['base_path'] = $retval['base_path'].'/';
	
	// Determine if the filename should be included in the resolved path
	if ( isset($default_to_folder[$retval['filename']]) )
	{
		$retval['resolved_path'] = $retval['base_path'];
	}
	else
	{
		$retval['resolved_path'] = $retval['base_path'].$retval['filename'];
	}

	return $retval;
}

function resolve_url(&$url_data, $scheme='http', $host='', $base_path='')
{
	if ($url_data['path_type'] == PathType::relative)
	{
		
	}
	else
	{
		
	}
}

/*function real_url_path($path, $append_filename=TRUE)
{
	// returns full resolved URL
	// returns URL base path 
	
	$file = ''; // File name in the path
	$real_path = str_replace("\\", '/', $path); // Replace back slashes with front slashes, in the path
	$retval = ''; // Function return value 
	$skip_counter = 0; // incremented when ../ is found in the path

	$url_segments = explode('/', $real_path); // Split the path into segments
	$seg_cnt = count($url_segments)-1; // Store the total number of segments
	
	// loop through all segments and re-build the path, effectively removing ./ and ../
	for($i = $seg_cnt; $i >= 0; $i--)
	{
		if ($url_segments[$i] == '..')
		{
			$skip_counter++;
			continue;
		}
		
		 // Skip this segment because it is a filename, but save it for later
		if ($i == $seg_cnt && mb_strpos($url_segments[$seg_cnt], '.') !== false)
		{
			$file = $url_segments[$seg_cnt];
			continue;
		}
		
		if (! strlen($url_segments[$i]) || $url_segments[$i] == '.') { continue; } // Skip empty segments (multiple consecutive slashes), or dots

		// At this point, the value of $url_segments[$i] should be a text string, so skip it if the parent (..) segment was previosuly encountered
		if ($skip_counter > 0)
		{
			$skip_counter--;
			continue;
		}
		
		// Otherwise, prepend the segment to the real path
		$retval = "/{$url_segments[$i]}{$retval}";
	}
	
	if ($append_filename) return $retval.'/'.$file;
	
	return $retval;
}*/
