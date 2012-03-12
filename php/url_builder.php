<?php

abstract class HrefType
{
    const undefined = 0;
    const unclassified = 1;
    const mailto = 2;
    const javascript = 3;
    const absolute = 4;
    const relative = 5;
    const domain_relative = 6;
    const scheme_relative = 7;
    const other = 8;
}

class URL_Builder
{
	private $options = array(
		'case_sensitive' => FALSE,
		'resolve_with_query' => FALSE,
		'directory_indexes' => array('index.php'=>'', 'index.html'=>'', 'index.htm'=>'')
	);
	
	private $force_scheme;
	private $force_host;
	private $force_path;

	/**
	 *
	 */
	public function __construct($opts=array(), $f_scheme='', $f_host='', $f_path='')
	{
		$this->set_options($opts);
		
		$this->force_scheme = $f_scheme;
		$this->force_host = $f_host;
		$this->force_path = $f_path;
	}
	
	// Setters
	public function set_force_scheme($str)
	{
		$this->force_scheme = $str;	
	}
	
	public function set_force_host($str)
	{
		$this->force_host = $str;	
	}
	
	public function set_force_path($str)
	{
		$this->force_path = $str;	
	}
	
	public function set_force_vars($mixed)
	{
		if( isset($mixed['scheme']) ) { $this->force_scheme = $mixed['scheme']; }
		if( isset($mixed['host']) ) { $this->force_host = $mixed['host']; }
		if( isset($mixed['path']) ) { $this->force_path = $mixed['path']; }
	}
	
	/**
	 *
	 */
	public function set_options($opts)
	{
		foreach($opts as $key => $val)
		{
			$this->options[$key] = $val;
		}	
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
	public function parse($href)
	{
		$retval = array(
			'href' => trim($href),
			// mailto_addr => '', // Not currently used
			// js => '', // Not currently used
			// scheme => '', 
			// host => '',
			//'path_raw' => '', // This is the extracted path, from the href
			//'path' => '', // This is the full, resolved path
			//'url' => '', // This is the resolved URL
			//'filename' => '',
			// query => '',
			// fragment => '',
			'path_type' => HrefType::undefined
			//'errors' => array()
		);
		
		// Extract the fragment if there is one
		$segments = explode('#', $retval['href'], 2);
		if (! empty($segments[1])) { $retval['fragment'] = $segments[1]; }
		
		// Extract the query string, if there is one
		$segments = explode('?', $segments[0], 2);
		if (! empty($segments[1])) { $retval['query'] = $segments[1]; }

		// Split the URL at the first colon to try and get the scheme
		$segments = explode(':', $segments[0], 2);
		if (count($segments) === 2) // Get the scheme
		{
			if ($segments[0] === '')
			{
				// The href starts with a colon - treat the href as a relative path
				$retval['path_type'] = HrefType::relative;
				$segments[0] = $retval['href'];
			}
			else if (mb_strpos(str_replace('\\', '/', $segments[0]), '/') !== FALSE)
			{
				// Something is weird in the href - There seems to be a path before the first
				// colon where there would normally be a scheme. Proceed as if this is a relative or domain relative path
				$segments[0] .= ':'.$segments[1];
			}
			else if (! (empty($this->force_scheme) || $segments[0] === $this->force_scheme) )
			{
				$retval['errors']['invalid_scheme'] = 'Invalid protocol: Not configured to follow '.$segments[0].' links';
				return $retval;
			}
			else if ($segments[0] === 'mailto')
			{
				//$retval['scheme'] = 'mailto';
				$retval['path_type'] = HrefType::mailto;
				return $retval;
			}
			else if ($segments[0] === 'javascript')
			{
				//$retval['scheme'] = 'javascript';
				$retval['path_type'] = HrefType::javascript;
				return $retval;
			}
			else if ($segments[0] !== 'http' && $segments[0] !== 'https') // Test for a valid scheme
			{
				$retval['errors']['invalid_scheme'] = 'Invalid href: The scheme should be "http" or "https" in '.$retval['href'];
				return $retval;
			}
			else if (! empty($this->force_scheme) && $segments[0] !== $this->force_scheme) // Test for a valid scheme
			{
				$retval['errors']['invalid_scheme'] = 'Invalid href: The scheme is out of context in '.$retval['href'];
				return $retval;
			}
			else
			{
				// Everything's clear. Store the scheme and set the href type
				$retval['scheme'] = $segments[0];
				$retval['path_type'] = HrefType::absolute;
				$segments[0] = $segments[1];
			}
		}
			
		// Check if the parsed href starts with //
		// If it does, there must be a host name. Extract it.
		if (mb_substr($segments[0], 0, 2) === '//') // 
		{
			// Split the parsed href and try to extract a host name
			$segments = explode('/', mb_substr($segments[0], 2), 2);
			
			// Signal any errors
			if ($segments[0] == '') // Error: three slashes after the scheme
			{
				$retval['errors']['invalid_href'] = 'Invalid href';
				return $retval;
					
				/*if (isset($segments[1]))
				{
					$retval['errors']['invalid_host'] = 'Invalid href: Triple slashes at critical point';
					return $retval;
				}
				else
				{
					$retval['errors']['invalid_host'] = 'Invalid href: Double slashes at critical point';
					return $retval;
				}*/
			}
			else if (! ctype_alpha(mb_substr($segments[0], 0, 1))) // Error: the host appears to use an invalid name
			{
				$retval['errors']['invalid_host'] = 'Invalid href: Host must start with an alphabetical character in '.$retval['href'];
				return $retval;
			}
			else if ( ! empty($this->force_host) && $segments[0] != $this->force_host)
			{
				$retval['errors']['invalid_host'] = 'Invalid href: Host name is out of context in '.$retval['href'];
				return $retval;
			}

			if ($retval['path_type'] == HrefType::undefined)
			{
				$retval['path_type'] = HrefType::scheme_relative;
			}
	
			// Error checking passed - Store the host name
			// and proceed with further parsing
			$retval['host'] = $segments[0];	

			if (isset($segments[1]))
			{
				$segments[0] = '/'.$segments[1];
			}
			else
			{
				$segments[0] = '';
			}
		}

		// Check if the remaining (possibly parsed) href starts with /		
		if (mb_substr($segments[0], 0, 1) === '/') // 
		{
			if ($retval['path_type'] == HrefType::undefined)
			{
				$retval['path_type'] = HrefType::domain_relative;
			}
		}

		if ($retval['path_type'] == HrefType::undefined)
		{
			$retval['path_type'] = HrefType::relative;	
		}
		
		$retval['path_raw'] = $segments[0];
		return $retval;
	}
	
	/**
	 *
	 */
	public function resolve_href($href_struct, $scheme='', $host='', $path='', $file='')
	{
		$retval = $href_struct;

		// Some hrefs are not meant to be resolved. Exit if encountered.
		if (! isset($retval['path_type']) || $retval['path_type'] < 4  || $retval['path_type'] > 7) { return FALSE; }

		if (empty($retval['scheme'])) { $retval['scheme'] = $scheme; }
		if (empty($retval['host'])) { $retval['host'] = $host; }
		
		// Resolve the path in the href struct
		if (empty($retval['path_raw'])) { $retval['path_raw'] = ''; } 
		
		$retval['path'] = '';

		// If the path is relative, we need to first append the context path to the raw href path so that the full path is resolved properly
		$path_to_resolve = '';
		if ($retval['path_type'] == HrefType::relative) { $path_to_resolve .= $path; }
		$path_to_resolve .= $retval['path_raw'];

		$skip_counter = 0; // incremented when ../ is found in the path
		$segments = explode('/', $path_to_resolve); // Split the path into segments
		$seg_cnt = count($segments)-1; // Store the total number of segments

		// Loop through all segments and re-build the path, effectively removing ./ and ../
		for($i = $seg_cnt; $i >= 0; $i--)
		{
			if ($segments[$i] == '..')
			{
				$skip_counter++;
				continue;
			}
			elseif ($segments[$i] == '.' || $segments[$i] == '') { continue; } // Skip empty segments (consecutive slashes), or single dots
			
			// Test if the first segment is the filename
			if ($i == $seg_cnt)
			{
				if (mb_strpos($segments[$seg_cnt], '.') !== FALSE)
				{
					$retval['filename'] = $segments[$seg_cnt];
					continue;
				}
			}
	
			// Skip this segment if the parent double dot (..) segment was previously encountered
			if ($skip_counter > 0) {
				$skip_counter--;
				continue;
			}
			
			// Otherwise, prepend the segment to the semi-parsed resolved path
			$retval['path'] = '/'.$segments[$i].$retval['path'];
		}
		$retval['path'] .= '/';
		
		// If this is a relative href and there is no file in the href but a context file, assign it
		if (empty($retval['filename']) && $retval['path_type'] == HrefType::relative) { $retval['filename'] = $file; }

		// Check if we are forcing a path. If the parsed path doesn't match, then flag an error
		if (! empty($this->force_path) &&  mb_substr($retval['path'], 0, mb_strlen($this->force_path)) !== $this->force_path)
		{
			$retval['errors']['invalid_path'] = 'Invalid URL: The path "'.$retval['path_raw'].'" is out of context in "'.$path.'"';
			return $retval;
		}

		if ($seg_cnt == 0)
		{
			if (empty($retval['path_raw']))
			{
				$retval['filename'] = $file;
			}
			else
			{
				$retval['filename'] = $retval['path_raw'];
			}
		}

		if (! $this->options['case_sensitive'])
		{
			$retval['path'] = mb_strtolower($retval['path']);
		}

		$retval['url'] = $retval['scheme'].'://'.$retval['host'].$retval['path'];
		
		if (! empty($retval['filename']) && ! isset($this->options['directory_indexes'][$retval['filename']]))
		{
			$retval['url'] .= $retval['filename'];
		}
		
		if ($this->options['resolve_with_query'] && ! empty($retval['query']))
		{
			$retval['url'] .= '?'.$retval['query'];
		}

		return $retval;
	}
}
