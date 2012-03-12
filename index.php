<?php
// TESTED ON PHP 5.1.6
// File: index.php (link checker)
// Version: 0.1
// Author: Mat Price
// Last modification date 2011/5/3
// Known issues: need to isolate or parse filename seaparate from paths in get_resolved_path()

// **********************
// Page initializations
ini_set('display_errors', 1);
ini_set('html_errors', 1);
error_reporting(-1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Montreal');

session_name('linkchecker_sess');
session_start();

define('MAX_URL_LENGTH', 1024);




// ********************
// The referrer and search term are valid. Proceed with the request by initializing the db connection
//try {
//
//	// Init the DB
//	define('BASEPATH', $_SERVER['DOCUMENT_ROOT']);
//	require BASEPATH . '/inc/db-config.php';
//	$dbh = new PDO("pgsql:host={$db_conf['webcomm']['host']};dbname={$db_conf['webcomm']['name']};port={$db_conf['webcomm']['port']}", $db_conf['webcomm']['user'], $db_conf['webcomm']['pw']);
//	
//	// Keep the following commented version as a reference
//	$sql = "SELECT name, url FROM concordiasitelist WHERE lower(name) LIKE '".pg_escape_string(mb_strtolower($_GET['term'], 'utf-8'))."%' OR lower(description) LIKE '%".pg_escape_string(mb_strtolower($_GET['term'], 'utf-8'))."%' ORDER BY name LIMIT 20";
//
//	// Create an array with the results. Note the single char key in $retval - this is to save bytes during transfer since it will be encoded to JSON
//	foreach($dbh->query($sql) as $row)
//	{
//		$retval[] = array(
//			'label' => $row['name'],
//			'url' => $row['url']);
//	}
//
//	$dbh = null;
//}
//catch(PDOException $e)
//{
//	exit($e->getMessage());
//}
//




// Set Session defaults
if (! isset($_SESSION['form'])) { $_SESSION['form'] = array(); }
//if (! isset($_SESSION['form']['initial_url'])) { $_SESSION['form']['initial_url'] = 'http://www-redesign-dev.concordia.ca/'; }
//if (! isset($_SESSION['form']['blacklist_pattern'])) { $_SESSION['form']['blacklist_pattern'] = '#^(/now/|/fr/)#i'; }
//if (! isset($_SESSION['form']['whitelist_pattern'])) { $_SESSION['form']['whitelist_pattern'] = '#^(/about/)#i'; }

if (! isset($_SESSION['form']['initial_url'])) { $_SESSION['form']['initial_url'] = 'http://wevalue-dev.concordia.ca/'; }
if (! isset($_SESSION['form']['blacklist_pattern'])) { $_SESSION['form']['blacklist_pattern'] = ''; }
if (! isset($_SESSION['form']['whitelist_pattern'])) { $_SESSION['form']['whitelist_pattern'] = ''; }

if (! isset($_SESSION['form']['max_recurse'])) { $_SESSION['form']['max_recurse'] = 1; }
if (! isset($_SESSION['form']['crawl_404s'])) { $_SESSION['form']['crawl_404s'] = '0'; }
if (! isset($_SESSION['form']['resolve_with_query'])) { $_SESSION['form']['resolve_with_query'] = '0'; }
if (! isset($_SESSION['form']['case_sensitive'])) { $_SESSION['form']['case_sensitive'] = '0'; }
if (! isset($_SESSION['form']['protocol'])) { $_SESSION['form']['protocol'] = 'http'; }
if (! isset($_SESSION['form']['follow_all'])) { $_SESSION['form']['follow_all'] = 0; }
if (! isset($_SESSION['form']['ignore_links_to_self'])) { $_SESSION['form']['ignore_links_to_self'] = '1'; }
if (! isset($_SESSION['form']['record_hrefs'])) { $_SESSION['form']['record_hrefs'] = '1'; }
if (! isset($_SESSION['form']['record_referrers'])) { $_SESSION['form']['record_referrers'] = '1'; }


// **********************
// Take care of logging
define ('ENABLE_LOG', FALSE);
/*if (ENABLE_LOG)
{
	include_once 'modules/klogger/KLogger.php';
	$log = new KLogger ( "log.txt" , KLogger::DEBUG );
}*/

// **********************
// Global includes
include_once 'php/funcs.php';
include_once 'php/url_builder.php';


// **********************
// Default page variables
$msgs = array('errors' => array());

// **************************************
// Validate the form
if (isset($_POST['link_checker_form_submit']))
{
	// Defaults
	$initial_url_data = array(); // Data containing info about the intially entered URL
	$referrers = array();        // A list of referrering URLs for each processed page
	$urls_checked = array();     // The list of URLS which have been processed
	$urls_to_check = array();    // The list of URLS which have not yet been processed

	// Form Validation
	if (empty($_POST['initial_url'])) // Empty
	{
		$msgs['errors']['initial_url'] = 'Please provide a valid initial URL';
	}
	else if (mb_strlen($_POST['initial_url']) > MAX_URL_LENGTH)
	{
		$msgs['errors']['initial_url'] = 'The URL exceeds the maximum length. Maximum length = ' . MAX_URL_LENGTH;
	}
	else // Non-empty
	{
		$_SESSION['form']['initial_url'] = mb_substr(trim($_POST['initial_url']), 0, MAX_URL_LENGTH); // Sanitize
	}

	// ------------
	if (isset($_POST['blacklist_pattern']))
	{
		$_SESSION['form']['blacklist_pattern'] = trim($_POST['blacklist_pattern']);
	}
	// ------------
	if (isset($_POST['whitelist_pattern']))
	{
		$_SESSION['form']['whitelist_pattern'] = trim($_POST['whitelist_pattern']);
	}
	// ------------
	if (isset($_POST['max_recurse']))
	{
		$_SESSION['form']['max_recurse'] = (int)$_POST['max_recurse'];
		if ($_SESSION['form']['max_recurse'] < 1) { $_SESSION['form']['max_recurse'] = 1; }
	}
	// ------------
	if (isset($_POST['crawl_404s']))
	{
		$_SESSION['form']['crawl_404s'] = (bool)$_POST['crawl_404s'];
	}
	// ------------
	if (isset($_POST['resolve_with_query']))
	{
		$_SESSION['form']['resolve_with_query'] = (bool)$_POST['resolve_with_query'];
	}
	// ------------
	if (isset($_POST['case_sensitive']))
	{
		$_SESSION['form']['case_sensitive'] = (bool)$_POST['case_sensitive'];
	}
	// ------------
	if (isset($_POST['protocol']))
	{
		$_SESSION['form']['protocol'] = $_POST['protocol'];
	}
	// ------------
	if (isset($_POST['follow_all']))
	{
		$_SESSION['form']['follow_all'] = $_POST['follow_all'];
	}
	// ------------
	if (isset($_POST['ignore_links_to_self']))
	{
		$_SESSION['form']['ignore_links_to_self'] = (bool)$_POST['ignore_links_to_self'];
	}
	// ------------
	if (isset($_POST['record_hrefs']))
	{
		$_SESSION['form']['record_hrefs'] = (bool)$_POST['record_hrefs'];
	}
	// ------------
	if (isset($_POST['record_referrers']))
	{
		$_SESSION['form']['record_referrers'] = (bool)$_POST['record_referrers'];
	}
	
	if (count($msgs['errors']) == 0)
	{
		$url = new URL_Builder(array(
				'case_sensitive' => $_SESSION['form']['case_sensitive'],
				'resolve_query' => $_SESSION['form']['resolve_with_query']
			));
			
		$initial_url_data = $url->parse($_SESSION['form']['initial_url']);

		// Get the raw href data and register any errors
		if (! empty($initial_url_data['errors']))
		{
			$msgs['errors'] = $initial_url_data['errors'];
		}
		else if ($initial_url_data['path_type'] != HrefType::absolute)
		{
			$msgs['errors']['initial_url'] = 'Invalid URL: Please provide an absolute URL';
		}
	}

	if (count($msgs['errors']) == 0)
	{
		$initial_url_data = $url->resolve_href($initial_url_data);

		// Set the whitelist of possible schemes to follow
		$url->set_force_vars(array(
			'scheme' => $_SESSION['form']['protocol'],
			'host' => $initial_url_data['host'],
			'path' => (($_SESSION['form']['follow_all']) ? '' : $initial_url_data['path'])
			));

		// Add the resolved URL to the array of URLs to check
		$urls_to_check[$initial_url_data['url']] = $initial_url_data;

		// ************************
		// Start the crawling
		// Loop through the list of resolved URLs, get their contents and parse it to extract hrefs. From this,
		// create a list of additional resolveds URLs to check, until all have been checked or until
		// we've reached the max iterations specified by the config
		$loop_ctr = 0;
		while(($current_url = key($urls_to_check)) && $loop_ctr < $_SESSION['form']['max_recurse'])
		{
			$loop_ctr++;

			//if (ENABLE_LOG) { $log->LogDebug("URL: {$current_url}"); }

			// Grab the contents of the URL we are checking
			$curl_contents = curl_get_contents($current_url);

			// Process CURL errors
			if (isset($curl_contents['errmsg']) && strlen($curl_contents['errmsg']))
			{
				//if (ENABLE_LOG) { $log->LogDebug("cURL error: {$curl_contents['errmsg']}"); }
				$msgs['errors']['curl'] = $curl_contents['errmsg'];
				$urls_checked[$current_url] = '';
				unset($urls_to_check[$current_url]); // Remove this URL from the list of URLs to check
				continue;
			}

			// If there is content, extract all hrefs unless the returned code is greater than 399 or the http code is 404 and we want to crawl 404s
			if ( ! empty($curl_contents['content']) && (($_SESSION['form']['crawl_404s'] && $curl_contents['http_code'] == 404) || $curl_contents['http_code'] < 400) )
			{
		
// Specify configuration
/*$config = array(
           'indent'         => true,
           'output-xhtml'   => true,
           'wrap'           => 200);

// Tidy
$tidy = new tidy;
$tidy->parseString($curl_contents['content'], $config, 'utf8');
$tidy->cleanRepair();

// Output
echo $tidy;*/

//a($curl_contents);
//exit(0);				

				$pattern = <<<EOT
/<a\s.*?href\s*=\s*(("([^"]*)")|('([^']*)'))/i
EOT;
//"
				preg_match_all($pattern, $curl_contents['content'], $results);
				$results = (isset($results[3])) ? $results[3] : array(); // Clear memory

				/*
				// From http://www.the-art-of-web.com/php/parse-links/
				$pattern = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
				preg_match_all("/$pattern/siU", $curl_contents['content'], $results);
				$results = (isset($results[2])) ? $results[2] : array();
				*/
				unset($curl_contents['content']);
			}
			else
			{
				$results = array();
			}
			
			//a($results); // view the parsing results

			// Cycle through the hrefs and either ignore them or add them to the list of URLs to check
			foreach($results as $key => $href)
			{

				// Extract as much data as possible from the href
				$href_data = $url->parse($href);
				
				if ( ! empty($href_data['errors']) || $href_data['path_type'] < 4 || $href_data['path_type'] > 7)
				{
					if ( mb_substr($href_data['href'], 0, 1) != "#" )
					{
						unset($results[$key]);
					}
					continue;
				}
				
				// Continue parsing until we get an absolute URL
				$href_data = $url->resolve_href(
					$href_data,
					$urls_to_check[$current_url]['scheme'],
					$urls_to_check[$current_url]['host'],
					$urls_to_check[$current_url]['path'],
					(isset($urls_to_check[$current_url]['filename']) ? $urls_to_check[$current_url]['filename'] : '')
				);


				// An error may have occured if we're forcing a path but the resolved URL uses a different one
				if (! empty($href_data['errors']))
				{
					unset($results[$key]);
					continue;
				}
				// Skip blacklisted and process only whitelisted paths
				if( (mb_strlen($_SESSION['form']['blacklist_pattern']) && preg_match($_SESSION['form']['blacklist_pattern'], $href_data['path'])) ||
					 (mb_strlen($_SESSION['form']['whitelist_pattern']) && ! preg_match($_SESSION['form']['whitelist_pattern'], $href_data['path'])) )
				{
					unset($results[$key]);
					continue;
				}
				
				// Temp output to page for testing
				$results[$key] .= ' <br /><span style="color:red;">'.$href_data['url'].'</span>';

				// Don't record links to self in referrers array
				if ($_SESSION['form']['record_referrers'] && (! $_SESSION['form']['ignore_links_to_self'] || $href_data['url'] != $current_url) )
				{
					$referrers[$href_data['url']][$current_url] = ''; //' ('.$href_data['resolved_path'].')';
				}

				// Continue if this resolved URL is already in the checked, or, to-check arrays
				if ( isset($urls_to_check[$href_data['url']]) || isset($urls_checked[$href_data['url']]) )
				{
					if ($href_data['url'] == $current_url && $_SESSION['form']['ignore_links_to_self'])
					{
						unset($results[$key]);
					}
					continue;
				}

				$urls_to_check[$href_data['url']] = $href_data;
				
				unset($href_data);
			} // end foreach

			$urls_checked[$current_url] = $curl_contents + $urls_to_check[$current_url]; // q($urls_checked[$current_url]);
			$urls_checked[$current_url]['page_urls'] = ($_SESSION['form']['record_hrefs']) ? $results : array();
			unset($urls_to_check[$current_url]);
			unset($results);
		}
	}
}
?><!DOCTYPE HTML>
<html>
<head>
<meta charset="utf-8">
<title>dev.msp link checker</title>
<link href="css/styles.css" rel="stylesheet"/>
<script src="js/jquery.min.js"></script>
<script>
function toggle_urls(self)
{
	var $self = $(self);
	var text = $self.text();
	var $target = $('#' + $self.attr('id') + 'div');

	if (text == 'show')
	{
		$self.text('hide');
		$target.slideDown('fast');
			
	}
	else if(text == 'hide')
	{
		$self.text('show');
		$target.slideUp('fast');
	}
}

var view_state = 1;
function toggle_view()
{
	if (view_state == 1)
	{
		$('thead').hide();
		$('td').not('.url').hide();
		view_state = 0;
	}
	else
	{
		$('thead').show();
		$('td').show();
		view_state = 1;
	}
}
</script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<body>

<div class="page">
	<h1>dev.msp Linkchecker </h1>
<?php
display_errors($msgs['errors']);
// If the form was submitted and there are results, print them
// Otherwise, print the default form
if (isset($_POST['link_checker_form_submit']) && count($msgs['errors']) == 0)
{?>
<div style="text-align:right"><a href="#" onclick="toggle_view();return false;">Toggle view</a></div>
<table id="hrefs" class="table_1">
	<thead>
		<tr>
			<th width="2%">#</th>
			<th width="50%">URL</th>
			<th width="5%">Details</th>
			<th>HTTP Code</th>
			<th width="30%">Content type</th>
		</tr>
	</thead>
	<tbody>
<?php
ksort($urls_checked);
$i = 0;
foreach($urls_checked as $url_key => $info)
{
	$href_toggler = '&nbsp;';
	$hrefs = '<div id="urls'.$i.'div" class="urls_block">';
	if (isset($referrers[$url_key]) && count($referrers[$url_key]))
	{
		$href_toggler = '<a href="#" id="urls'.$i.'" onclick="toggle_urls(this);return false;">show</a>';
		$hrefs .= '<strong>Pages which contain links that point to this URL</strong><ol>';
		foreach($referrers[$url_key] as $key => $val)
		{
			$hrefs .= <<<HREFS
<li>{$key}{$val}</li>
HREFS;
		}
		$hrefs .= '</ol>';
	}

	if (count($info['page_urls']))
	{
		$href_toggler = '<a href="#" id="urls'.$i.'" onclick="toggle_urls(this);return false;">show</a>';
		$hrefs .= '<strong>hrefs in this page</strong><ol>';
		foreach($info['page_urls'] as $key => $val)
		{
			$hrefs .= <<<HREFS
<li>{$val}</li>
HREFS;
		}
		$hrefs .= '</ol>';
	}
	if (! $_SESSION['form']['record_hrefs'] && ! $_SESSION['form']['record_hrefs'])
	{
		$href_toggler = '&nbsp;';
	}
	$hrefs .= '</div>';
	

	$code_styles = '';
	if ($info['http_code'] > 399)
	{
		$code_styles = ' class="row_red"';
	}
	elseif($info['http_code'] > 399)
	{
		$code_styles = ' class="row_yellow"';
	}
	else
	{
		$code_styles = ' class="row_green"';
	}

	$j = $i+1;
	
	echo <<<TABLEROW
		<tr>
			<td>{$j}</td>
			<td class="url"><a href="$url_key" target="_blank">$url_key</a><br />$hrefs</td>
			<td>$href_toggler</td>
			<td$code_styles>{$info['http_code']}</td>
			<td>{$info['content_type']}</td>
		</tr>
TABLEROW;
	$i++;
}?>
	</tbody>
</table>

<h2>Remaining URLs to check(<?php echo count($urls_to_check) ?>)</h2>
<ol>
<?php
foreach($urls_to_check as $key => $val)
{
	echo '<li>'.$key.'</li>';
}
?>
</ol>


<?php
}
else
{?>
<form id="link_checker_form" action="./" class="form_2" method="post">

	<div class="form_row">
		<label for="initial_url">Initial URL</label><br />
		<input id="initial_url" name="initial_url" class="text_field" type="text" value="<?php echo $_SESSION['form']['initial_url'] ?>" maxlength="1024" />
		<!--<select id="initial_url" name="initial_url" style="width:400px;">
			<option value="www-redesign-dev.concordia.ca">http://www-redesign-dev.concordia.ca</option>
		</select>-->
	</div>

	<div class="form_row">
		<label for="blacklist_pattern">Path blacklist regex</label><br />
		<input id="blacklist_pattern" name="blacklist_pattern" class="text_field" type="text" value="<?php echo $_SESSION['form']['blacklist_pattern'] ?>" maxlength="4096" />
	</div>

	<div class="form_row">
		<label for="whitelist_pattern">Path whitelist regex</label><br />
		<input id="whitelist_pattern" name="whitelist_pattern" class="text_field" type="text" value="<?php echo $_SESSION['form']['whitelist_pattern'] ?>" maxlength="4096" />
	</div>

	<div class="form_row_2 first_child clearfix">
		<label for="max_recurse" class="col_half left">Maximum number of links to follow</label>
		<div class="col_half">
			<input id="max_recurse" name="max_recurse" type="text" class="text_field width_quarter" value="<?php echo $_SESSION['form']['max_recurse'] ?>" maxlength="4" />
		</div>
	</div>
	
	<div class="form_row_2 clearfix">
		<label class="col_half left radio_btn_label"><strong>Protocol to follow</strong></label>
		<div class="col_half">
			<input type="radio" id="follow_http" name="protocol"<?php if ($_SESSION['form']['protocol']== 'http') { echo ' checked="checked"'; } ?> value="http" /> <label for="follow_http" class="radio_btn_label">HTTP</label>
			&nbsp;&nbsp;
			<input type="radio" id="follow_https" name="protocol"<?php if ($_SESSION['form']['protocol'] == 'https') { echo ' checked="checked"'; } ?> value="https" /> <label for="follow_https" class="radio_btn_label">HTTPS</label>
			&nbsp;&nbsp;
			<input type="radio" id="follow_http_https" name="protocol"<?php if (empty($_SESSION['form']['protocol'])) { echo ' checked="checked"'; } ?> value="" /> <label for="follow_http_https" class="radio_btn_label">HTTP + HTTPS</label>
		</div>
	</div>

	<div class="form_row_2 clearfix">
		<label for="crawl_404s_yes" class="col_half left radio_btn_label"><strong>Crawl 404 pages?</strong></label>
		<div class="col_half">
			<input type="radio" id="crawl_404s_yes" name="crawl_404s"<?php if ($_SESSION['form']['crawl_404s'] == '1') { echo ' checked="checked"'; } ?> value="1" /> <label for="crawl_404s_yes" class="radio_btn_label">Yes</label>
			&nbsp;&nbsp;
			<input type="radio" id="crawl_404s_no" name="crawl_404s"<?php if ($_SESSION['form']['crawl_404s'] == '0') { echo ' checked="checked"'; } ?> value="0" /> <label for="crawl_404s_no" class="radio_btn_label">No</label>
		</div>
	</div>
	
	<div class="form_row_2 clearfix">
		<label class="col_half left radio_btn_label"><strong>Case sensitive URLs?</strong></label>
		<div class="col_half">
			<input type="radio" id="case_sensitive_yes" name="case_sensitive"<?php if ($_SESSION['form']['case_sensitive'] == '1') { echo ' checked="checked"'; } ?>  value="1" /> <label for="case_sensitive_yes" class="radio_btn_label">Yes</label>
			&nbsp;&nbsp;
			<input type="radio" id="case_sensitive_no" name="case_sensitive"<?php if ($_SESSION['form']['case_sensitive'] == '0') { echo ' checked="checked"'; } ?> value="0" /> <label for="case_sensitive_no" class="radio_btn_label">No</label>
		</div>
	</div>
	
	<div class="form_row_2 clearfix">
		<label for="resolve_with_query_yes" class="col_half left radio_btn_label"><strong>Include query in resolved URL?</strong></label>
		<div class="col_half">
			<input type="radio" id="resolve_with_query_yes" name="resolve_with_query"<?php if ($_SESSION['form']['resolve_with_query'] == '1') { echo ' checked="checked"'; } ?>  value="1" /> <label for="resolve_with_query_yes" class="radio_btn_label">Yes</label>
			&nbsp;&nbsp;
			<input type="radio" id="resolve_with_query_no" name="resolve_with_query"<?php if ($_SESSION['form']['resolve_with_query'] == '0') { echo ' checked="checked"'; } ?> value="0" /> <label for="resolve_with_query_no" class="radio_btn_label">No</label>
		</div>
	</div>
	
	<div class="form_row_2 clearfix">
		<label class="col_half left radio_btn_label"><strong>Follow links above the initial URL's path?</strong></label>
		<div class="col_half">
			<input type="radio" id="follow_all_yes" name="follow_all"<?php if ($_SESSION['form']['follow_all'] == '1') { echo ' checked="checked"'; } ?> value="1" /> <label for="follow_all_yes" class="radio_btn_label">Yes</label>
			&nbsp;&nbsp;
			<input type="radio" id="follow_all_no" name="follow_all"<?php if ($_SESSION['form']['follow_all'] == '0') { echo ' checked="checked"'; } ?> value="0" /> <label for="follow_all_no" class="radio_btn_label">No</label>
		</div>
	</div>
	
	<div class="form_row_2 clearfix">
		<label for="record_hrefs_yes" class="col_half left radio_btn_label"><strong>Record each page's hrefs?</strong></label>
		<div class="col_half">
			<input type="radio" id="record_hrefs_yes" name="record_hrefs"<?php if ($_SESSION['form']['record_hrefs'] == TRUE) { echo ' checked="checked"'; } ?> value="1" /> <label for="record_hrefs_yes" class="radio_btn_label">Yes</label>
			&nbsp;&nbsp;
			<input type="radio" id="record_hrefs_no" name="record_hrefs"<?php if ($_SESSION['form']['record_hrefs'] == FALSE) { echo ' checked="checked"'; } ?> value="0" /> <label for="record_hrefs_no" class="radio_btn_label">No</label>
		</div>
	</div>
	
	<div class="form_row_2 last_child clearfix">
		<label for="record_referrers_yes" class="col_half left radio_btn_label"><strong>Record each page's referrers?</strong></label>
		<div class="col_half">
			<input type="radio" id="record_referrers_yes" name="record_referrers"<?php if ($_SESSION['form']['record_referrers'] == TRUE) { echo ' checked="checked"'; } ?> value="1" /> <label for="record_referrers_yes" class="radio_btn_label">Yes</label>
			&nbsp;&nbsp;
			<input type="radio" id="record_referrers_no" name="record_referrers"<?php if ($_SESSION['form']['record_referrers'] == FALSE) { echo ' checked="checked"'; } ?> value="0" /> <label for="record_referrers_no" class="radio_btn_label">No</label>
		</div>
		
		<label for="ignore_links_to_self_yes" class="col_half left radio_btn_label">&nbsp;&nbsp;&nbsp;&gt; <strong>Ignore links that point to their own source?</strong></label>
		<div class="col_half">
			<input type="radio" id="ignore_links_to_self_yes" name="ignore_links_to_self"<?php if ($_SESSION['form']['ignore_links_to_self'] == TRUE) { echo ' checked="checked"'; } ?> value="1" /> <label for="ignore_links_to_self_yes" class="radio_btn_label">Yes</label>
			&nbsp;&nbsp;
			<input type="radio" id="ignore_links_to_self_no" name="ignore_links_to_self"<?php if ($_SESSION['form']['ignore_links_to_self'] == FALSE) { echo ' checked="checked"'; } ?> value="0" /> <label for="ignore_links_to_self_no" class="radio_btn_label">No</label>
		</div>
	</div>
	

	<div class="form_row">
		<input name="link_checker_form_submit" type="submit" value="Send" />
	</div>

</form>
<?php
}?>
</div>
<div>Memory usage: <?php echo memory_get_usage() ?></div>

</body>
</html>
