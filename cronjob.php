<?php
date_default_timezone_set('Europe/Belgrade');
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/cronjob_php_errors.log');
error_reporting(E_ALL);
ini_set('display_errors', 1);

//set_error_handler('exceptions_error_handler');

$locales = [
    // type
    "OFFICE" => "kancelarijski-prostor",
    "RETAIL" => "maloprodajni-prostor",
    "LAND" => "zemljista",
    "INDUSTRIAL" => "skladista",
    "RESIDENTIAL" => "stambeni-prostor",
    // subtype
    "OFFICEBUILD" => "poslovna-zgrada",
    "OFFICEUNIT" => "kancelarijska-jedinica",
    "RESIBUILD" => "RESIBUILD",
    "APARTMENT" => "APARTMENT",
    "SHOPPCENTER" => "SHOPPCENTER",
    "WAREHOUSE" => "WAREHOUSE",
    "AGRICULTURE" => "AGRICULTURE",
    "HIGHSTREET" => "HIGHSTREET",
    "DEPARTSTORE" => "DEPARTSTORE",
    "RETAILPARK" => "RETAILPARK",
    "STREETRETUNIT" => "lokal",
    "CONSTRUCT" => "CONSTRUCT",
    "COMPLEX" => "COMPLEX",
    "PRODUCTION" => "PRODUCTION",
    "WAREOFFICE" => "WAREOFFICE",
    "PRODSPACE" => "PRODSPACE",
    "BUILDPROJECT" => "BUILDPROJECT",
    "HOUSE" => "HOUSE",
    "BIG_BOX" => "BIG_BOX",
    "MIXED_USE" => "MIXED_USE",
    "SINGLE_STAND_BUILDING" => "SINGLE_STAND_BUILDING",
    "UNIT_IN_OFFICE_BUILDING" => "lokal-u-poslovnoj-zgradi",
    // attributes
    "rent_id" => "iznajmljivanje",
    "sale_id" => "prodaja",
];

function sanitize_string($string) {
    $unwanted_array = ['Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
        'Č' => 'C', 'č' => 'c', 'Ć' => 'C', 'ć' => 'c', 'Đ' => 'DJ', 'đ' => 'dj',
        'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
        'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
        'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
        'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'];
    return strtr($string, $unwanted_array);
}

function exceptions_error_handler($severity, $message, $filename, $lineno) {
	if (error_reporting() == 0) {
		return;
	}
	if (error_reporting() & $severity) {
		throw new ErrorException($message, 0, $severity, $filename, $lineno);
	}
}

if (!function_exists('exif_imagetype')) {
    function exif_imagetype($filename) {
        if ((list($width, $height, $type, $attr) = getimagesize(stripslashes($filename))) !== false) {
            return $type;
        }
        return false;
    }
}

function imageCreateFromAny($filepath) {
	$type = exif_imagetype($filepath); //NOTE: If you don't have exif you could use getImageSize()
	$allowedTypes = array(
		1, //NOTE: gif
		2, //NOTE: jpg
		3, //NOTE: png
		6, //NOTE: bmp
    );
	if (!in_array($type, $allowedTypes)) {
		return false;
    }
	switch ($type) {
		case 2:
			$im = @imagecreatefromjpeg($filepath);
			break;
		case 1:
			$_im = @imagecreatefromgif($filepath);
		case 3:
			if (empty($_im)) {
				$_im = @imagecreatefrompng($filepath);
			}
			$im = @imagecreatetruecolor(@imagesx($_im), @imagesy($_im));
			@imagefill($im, 0, 0, @imagecolorallocate($im, 255, 255, 255));
			@imagealphablending($im, TRUE);
			@imagecopy($im, $_im, 0, 0, 0, 0, @imagesx($_im), @imagesy($_im));
			@imagedestroy($_im);
			break;
		case 6:
			return false;
// 			$im = imagecreatefrombmp($filepath);
			break;
	}
	return $im;
}

try {
    define('SHORTINIT', true);
    
    include_once(__DIR__ . '/../wp-load.php');

    include_once(__DIR__ . '/../wp-includes/default-constants.php');
    if (!defined('WP_CONTENT_URL')) {
		define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content'); // Copied from default-constants.php
	}
	if (!defined('COOKIEHASH')) {
		define('COOKIEHASH', md5(get_option('siteurl'))); // Copied from default-constants.php
	}
	if (!defined('SECURE_AUTH_COOKIE')) {
		define('SECURE_AUTH_COOKIE', 'wordpress_sec_' . COOKIEHASH); // Copied from default-constants.php
	}
	
    include_once(__DIR__ . '/../wp-includes/class-wp-block-parser.php');
    include_once(__DIR__ . '/../wp-includes/blocks.php');
    include_once(__DIR__ . '/../wp-includes/formatting.php');
    include_once(__DIR__ . '/../wp-includes/kses.php');
    include_once(__DIR__ . '/../wp-includes/class-wp-query.php');
    include_once(__DIR__ . '/../wp-includes/class-wp-meta-query.php');
	include_once(__DIR__ . '/../wp-includes/class-wp-post.php');
	include_once(__DIR__ . '/../wp-includes/pluggable.php');
	include_once(__DIR__ . '/../wp-includes/class-wp-rewrite.php');
	include_once(__DIR__ . '/../wp-settings.php');
	include_once(__DIR__ . '/../wp-includes/post.php');
	include_once(__DIR__ . '/../wp-includes/theme.php');
    include_once(__DIR__ . '/../wp-includes/user.php');
    include_once(__DIR__ . '/../wp-includes/comment.php');
    include_once(__DIR__ . '/../wp-includes/taxonomy.php');
    include_once(__DIR__ . '/../wp-includes/wp-db.php');
    include_once(__DIR__ . '/../wp-includes/revision.php');
    include_once(__DIR__ . '/../wp-includes/meta.php');
    include_once(__DIR__ . '/../wp-includes/shortcodes.php');
    include_once(__DIR__ . '/../wp-includes/media.php');
	include_once(__DIR__ . '/../wp-includes/l10n.php');
	include_once(__DIR__ . '/../wp-includes/class-wp-tax-query.php');
	include_once(__DIR__ . '/../wp-includes/rest-api.php');
	include_once(__DIR__ . '/../wp-includes/class-wp-user.php');
	include_once(__DIR__ . '/../wp-includes/capabilities.php');
	include_once(__DIR__ . '/../wp-includes/cron.php');
	include_once(__DIR__ . '/../wp-includes/theme-templates.php');

    include_once(__DIR__ . '/../wp-admin/includes/image.php');

    $wp_rewrite = new WP_Rewrite();
    global $wp_rewrite;
    
    $messages = [];
    
    $messages[] = 'Start: ' . date('d-m-Y H:i:s');

    set_time_limit(0);

	define('AUTH_COOKIE', '');
	define('LOGGED_IN_COOKIE', '');
	//$wp_upload_dir = wp_get_upload_dir();
	
    $_date = new DateTime();
	$_date->add(DateInterval::createFromDateString('yesterday'));
	$date = $_date->format('Y-m-d 01:00:01');
	//$date = '2025-09-11 01:00:01';
	//$date = '1990-01-01 01:00:01';
    
    $username = 'vulesic';
    $password = 'BErjIckjithImN6#';
    $data = json_encode([
        'actionToPerform' => 'Fetch property data',
        'data' => [
            'lastUpdatedFrom'=> $date,
        ]
    ]);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);
    curl_setopt($curl, CURLOPT_USERPWD, "{$username}:{$password}");
    curl_setopt($curl, CURLOPT_URL, 'https://fms.omega.rs/sportvision/api/v01.00.php');
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSLVERSION, 6);
    
    $result = curl_exec($curl);
    $error = curl_error($curl);
    $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
file_put_contents(
    '/tmp/cronjob_http.log',
    date('c') . " HTTP=$http ERR=" . $error . "\nRESULT_HEAD=" . substr($result ?? '', 0, 500) . "\n\n",
    FILE_APPEND
);
    if (!empty($error)) {
        $messages[] = 'CURL ERROR: ' . json_encode($error);
        goto end;
    }
    $response = json_decode($result, true);
    curl_close($curl);

    @umask(0);
    
    $api_responses_path = ABSPATH . 'wp-content/api_responses/';
    if (!is_dir($api_responses_path)) {
    	if (!@mkdir($api_responses_path)) {
    		$messages[] = 'ERROR: Invalid path to save API response to';
    		goto end;
    	}
    }
    if (!file_put_contents($api_responses_path . 'api_response_for_' . date('Y_m_d', strtotime($date)) . '_on_' . date('Y_m-d') . '.json', json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        $messages[] = 'ERROR: Could not write API response';
        goto end;
    }

	//NOTE: To print curl respone, keep this line uncommented
	//header('Content-Type: application/json'); echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);die();
    
    if (!empty($response['error'])) {
        $messages[] = 'API ERROR: ' . json_encode($response['error']);
        goto end;
    }
    
    if (empty($response)) {
        $messages[] = 'API ERROR: Empty response';
        goto end;
    }
    if (empty($response['success'])) {
        $messages[] = 'API EMPTY SUCCESS ERROR: ' . (isset($response['error']) ? $response['error'] : '');
        goto end;
    }
	
    $properties = isset($response['output']) && isset($response['output']['properties']) ? (array)$response['output']['properties'] : [];
    if (empty($properties)) {
        $messages[] = 'INFO: No properties in returned from API';
        goto end;
    }

    $dirpath = ABSPATH . '/wp-content/tmp_images/';
    if (!is_dir($dirpath)) {
    	if (!@mkdir($dirpath)) {
    		$messages[] = 'ERROR: Invalid path to save images to';
    		goto end;
    	}
    }

    // $stamp = @imagecreatefrompng('https://cw-cbs.rs/wp-content/uploads/2018/09/CW-CBS-watermark.png');
    $stamp = @imagecreatefrompng('https://cw-cbs.rs/wp-content/uploads/2025/09/CW-CBS-watermark.png');
    if (!empty($stamp)) {
    	$sx = imagesx($stamp);
    	$sy = imagesy($stamp);
    	
    	//NOTE: Transparency/opacity of the watermark
    	/*
    	@imagealphablending($stamp, false);
        @imagesavealpha($stamp, true);
        $transparency = 0.5;
        @imagefilter($stamp, IMG_FILTER_COLORIZE, 0, 0, 0, 127 * $transparency);
        */
    }

    foreach ($properties as $property) {
        $propertyid = isset($property['webId']) ? strval($property['webId']) : null;
        if (empty($propertyid)) {
    		//TODO: Notify?
            continue;
		}

		//$title = isset($property['title']) ? strval($property['title']) : $propertyid;
		$title = $propertyid;
    	
    	$countryId = isset($property['countryId']) ? intval($property['countryId']) : null;
    	if (empty($countryId) || $countryId !== 2) {
    		//TODO: Notify?
    		continue;
    	}
    	
    	$attributes = isset($property['attributes']) ? (array)$property['attributes'] : [];
    	if (empty($attributes)) {
    		//TODO: Notify?
    		continue;
    	}

    	$post = $wpdb->get_results($wpdb->prepare("SELECT * FROM wpuy_posts WHERE post_name = %s;", $propertyid . '-2'));
    	if (!empty($post)) {
    		//NOTE: We delete everything about post and re-create it
    		$images = $wpdb->get_results($wpdb->prepare("SELECT SUBSTRING(guid, LOCATE('wp-content/uploads/', guid)) AS relative_path FROM wpuy_posts WHERE post_parent = %d AND post_mime_type LIKE 'image/%';", $post[0]->ID));
    		if (!empty($images)) {
    			foreach ($images as $image) {
    				@unlink(ABSPATH . $image->relative_path);
    			}
    			$res = $wpdb->get_results($wpdb->prepare("DELETE FROM wpuy_posts WHERE post_parent = %d;", $post[0]->ID));
    		}
    		$res = $wpdb->get_results($wpdb->prepare("DELETE FROM wpuy_postmeta WHERE post_id = %d;", $post[0]->ID));
    		$res = $wpdb->get_results($wpdb->prepare("DELETE FROM wpuy_posts WHERE ID = %d;", $post[0]->ID));
		}

    	if (!isset($attributes['FORWEB']) || (int)$attributes['FORWEB'] !== 1){
    	   continue;
    	}
    	
    	$sql = $wpdb->prepare("INSERT INTO wpuy_posts (ID, post_author, post_date, post_date_gmt, post_content, post_content_filtered, "
    		. "post_title, post_excerpt, post_status, post_type, comment_status, ping_status, post_password, post_name, "
    		. "to_ping, pinged, post_modified, post_modified_gmt, post_parent, menu_order, post_mime_type, guid) VALUES ("
    		. "NULL, " //ID
    		. "1, " //post_author //TODO: Set post_author
    		. "CURRENT_TIMESTAMP, " //post_date
    		. "CURRENT_TIMESTAMP, " //post_date_gmt
    		. "'', " //post_content
    		. "'', " //post_content_filtered
    		. "%s, " //post_title
    		. "'', " //post_excerpt
    		. "'publish', " //post_status
    		. "'nekretnine', " //post_type
    		. "'closed', " //comment_status
    		. "'closed', " //ping_status
    		. "'', " //post_password
    		. "%s, " //post_name
    		. "'', " //to_ping
    		. "'', " //pinged
    		. "CURRENT_TIMESTAMP, " //post_modified
    		. "CURRENT_TIMESTAMP, " //post_modified_gmt
    		. "0, " //post_parent
    		. "0, " //menu_order
    		. "'', " //post_mime_type
    		. "''" //guid
    		. ");", $title, $propertyid . '-2');
    	$new_post = $wpdb->query($sql);
    	$post = $wpdb->get_results($wpdb->prepare("SELECT * FROM wpuy_posts WHERE post_name = %s;", $propertyid . '-2'));
    	if (empty($post)) {
    		$messages[] = 'ERROR: Error executing query: ' . $sql;
    		goto end;
    	}
    	/*
    	$sql = $wpdb->prepare("UPDATE wpuy_posts SET post_title = %s WHERE ID = %d;", $post[0]->ID, $post[0]->ID);
    	$res = $wpdb->get_results($sql);
    	if (empty($post)) {
    		$messages[] = 'ERROR: Error executing query: ' . $sql;
    		goto end;
    	}
    	*/
    
    	$prices = isset($property['prices']) ? (array)$property['prices'] : [];
    	//NOTE: Price
    	foreach ($prices as $price) {
    		switch ($price['type']) {
    			case 'NET':
    				$meta_key = 'price_id';
    				$meta_value = isset($price['value']) ? floatval($price['value']) : 0;
    				break;
    			case 'MONTHRENT':
    				$meta_key = 'price_monthrent';
    				$meta_value = isset($price['value']) ? floatval($price['value']) : 0;
    				break;
    			default:
    				$meta_key = null;
    				$meta_value = null;
    		}
    		if (empty($meta_key)) {
    			continue;
    		}
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    		. "NULL, " //meta_id
    		. "%d, " //post_id
    		. "%s, " //meta_key
    		. "%s);" //meta_value
    		, $post[0]->ID, $meta_key, $meta_value);
    		$res = $wpdb->get_results($sql);
    	}

        //NOTE: Copy/paste this code to add new attributes when needed
        //NOTE: Change $attribute, ATTR_NAME, ATTR_META_KEY accordingly
        /*
        $attribute = isset($attributes['ATTR_NAME']) ? strval($attributes['ATTR_NAME']) : null;
        if (!is_null($attribute)) {
        	$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
        		. "NULL, " //meta_id
        		. "%d, " //post_id
        		. "'ATTR_META_KEY', " //meta_key
        		. "%s);" //meta_value
        		, $post[0]->ID, trim($attribute));
        	$res = $wpdb->get_results($sql);
        }
        */
		
		$hotprop = isset($attributes['HOTPROP']) ? strval($attributes['HOTPROP']) : null;
		if (!is_null($hotprop)) {
			$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
				. "NULL, " //meta_id
				. "%d, " //post_id
				. "'hotprop', " //meta_key
				. "%s);" //meta_value
				, $post[0]->ID, trim($hotprop));
			$res = $wpdb->get_results($sql);
		}
		
    	//NOTE: Localized description
    	$description = (!empty($attributes['DESCENG']) ? "[:en]{$attributes['DESCENG']}" : '') . (!empty($attributes['DESCSRB']) ? " [:sr]{$attributes['DESCSRB']}" : '');
    	if (!is_null($description)) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'description_id', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($description) . ' [:]');
    		$res = $wpdb->get_results($sql);
    	}

    	//NOTE: Description EN
    	$description_en = (!empty($attributes['DESCENG']) ? $attributes['DESCENG'] : '');
    	if (!is_null($description_en)) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'description_en', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($description_en));
    		$res = $wpdb->get_results($sql);
    	}

    	//NOTE: Description SR
    	$description_sr = (!empty($attributes['DESCSRB']) ? $attributes['DESCSRB'] : '');
    	if (!is_null($description_sr)) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'description_sr', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($description_sr));
    		$res = $wpdb->get_results($sql);
    	}

        $area_id = null;
    	$addresses = isset($property['addresses']) ? (array)$property['addresses'] : [];
    	foreach ($addresses as $address) {
    		if (!is_null($address['placeMunicipality'])) {
    		    $area_id = trim($address['placeMunicipality']);
    			$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'area_id', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, $area_id);
    			$res = $wpdb->get_results($sql);
    		}
    		if (!is_null($address['longitude'])) {
    			$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'longitude_id', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($address['longitude']));
    			$res = $wpdb->get_results($sql);
    		}
    		if (!is_null($address['latitude'])) {
    			$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'latitude_id', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($address['latitude']));
    			$res = $wpdb->get_results($sql);
    		}
    		break;
    	}
    
    	//NOTE: Parking spaces
    	if (isset($attributes['NOPARK']) && !is_null($attributes['NOPARK'])) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'parking_id', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($attributes['NOPARK']));
    		$res = $wpdb->get_results($sql);
    	}
    
    	//NOTE: Square size (m^2)
    	if (isset($attributes['SIZE']) && !is_null($attributes['SIZE'])) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'size_id', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($attributes['SIZE']));
    		$res = $wpdb->get_results($sql);
    	}
    
    	//NOTE: Floor
    	if (isset($attributes['FLOOR']) && !is_null($attributes['FLOOR'])) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'floor_id', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($attributes['FLOOR']));
    		$res = $wpdb->get_results($sql);
    	}
    	
    	//NOTE: Furnishing
    	$furnishing = (!empty($attributes['FURNISH']) ? $attributes['FURNISH'] : '') . ',' . (!empty($attributes['FURNITURE']) ? $attributes['FURNITURE'] : '');
    	if (!empty($furnishing)) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'furniture_id', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($furnishing));
    		$res = $wpdb->get_results($sql);
    	}
    
    	//NOTE: Property ID
    	$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    		. "NULL, " //meta_id
    		. "%d, " //post_id
    		. "'propertyid_id', " //meta_key
    		. "%s);" //meta_value
    		, $post[0]->ID, $propertyid);
    	$res = $wpdb->get_results($sql);
    
    	//NOTE: Property type ID
    	$type = isset($property['type']) ? ucfirst(strtolower(strval($property['type']))) : null;
    	switch ($type) {
    		case 'Office':
    			$subtype = isset($property['subtype']) ? strval($property['subtype']) : null;
    			$type = $type . ($subtype === 'OFFICEUNIT' ? ' Unit' : ' Building');
    			break;
    		case 'Industrial':
    			$type = 'Warehouse';
    			break;
    		default:
    			break;
    	}
    	$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    		. "NULL, " //meta_id
    		. "%d, " //post_id
    		. "'property_type_id', " //meta_key
    		. "%s);" //meta_value
    		, $post[0]->ID, $type);
    	$res = $wpdb->get_results($sql);

    	//NOTE: Property True type 
    	$true_type = isset($property['type']) ? strval($property['type']) : null;
    	if (!is_null($true_type)) {
	    	$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
	    		. "NULL, " //meta_id
	    		. "%d, " //post_id
	    		. "'property_type', " //meta_key
	    		. "%s);" //meta_value
	    		, $post[0]->ID, $true_type);
	    	$res = $wpdb->get_results($sql);
    	}

    	//NOTE: Property property_subtype 
    	$true_subtype = isset($property['subtype']) ? strval($property['subtype']) : null;
    	if (!is_null($true_subtype)) {
	    	$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
	    		. "NULL, " //meta_id
	    		. "%d, " //post_id
	    		. "'property_subtype', " //meta_key
	    		. "%s);" //meta_value
	    		, $post[0]->ID, $true_subtype);
	    	$res = $wpdb->get_results($sql);
    	}

    	//NOTE: Sale
    	if (isset($attributes['SALE']) && !is_null($attributes['SALE'])) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'sale_id', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($attributes['SALE']));
    		$res = $wpdb->get_results($sql);
    	}
    	
    	//NOTE: Rent
    	if (isset($attributes['RENT']) && !is_null($attributes['RENT'])) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'rent_id', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($attributes['RENT']));
    		$res = $wpdb->get_results($sql);
    	}
    	
    	//NOTE: DISCLAIMER_SRB
    	$disclaimer_srb = (!empty($attributes['DISCLAIMER_SRB']) ? $attributes['DISCLAIMER_SRB'] : '');
    	if (!is_null($disclaimer_srb)) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'disclaimer_srb', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($disclaimer_srb));
    		$res = $wpdb->get_results($sql);
    	}
    	
    	//NOTE: DISCLAIMER_ENG
    	$disclaimer_eng = (!empty($attributes['DISCLAIMER_ENG']) ? $attributes['DISCLAIMER_ENG'] : '');
    	if (!is_null($disclaimer_eng)) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'disclaimer_eng', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($disclaimer_eng));
    		$res = $wpdb->get_results($sql);
    	}
    	
    	//NOTE: RENT_PRICE_MU
    	$rent_price_mu = (!empty($attributes['RENT_PRICE_MU']) ? $attributes['RENT_PRICE_MU'] : '');
    	if (!is_null($rent_price_mu)) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'rent_price_mu', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, trim($rent_price_mu));
    		$res = $wpdb->get_results($sql);
    	}
    	
        //NOTE: CLASS_FORWEB
        $class_forweb = (!empty($attributes['CLASS_FORWEB']) ? intval($attributes['CLASS_FORWEB']) : '0');
        if (!is_null($class_forweb)) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
                . "NULL, " //meta_id
                . "%d, " //post_id
                . "'class_forweb', " //meta_key
                . "%s);" //meta_value
                , $post[0]->ID, $class_forweb);
            $res = $wpdb->get_results($sql);
    	}

        //NOTE: Custom Permalinks
        $url = 'nekretnine/' . $propertyid;
        if (!empty($true_type)) {
            $url .= '/' . str_replace(' ', '-', strtolower(isset ($locales[$true_type]) ? $locales[$true_type] : $true_type));
        }
        if (!empty($true_subtype)) {
            $url .= '/' . str_replace(' ', '-', strtolower(isset ($locales[$true_subtype]) ? $locales[$true_subtype] : $true_subtype));
        }
        if (!empty($attributes['RENT'])) {
            $url .= '/' . str_replace(' ', '-', strtolower($locales['rent_id']));
        } elseif (!empty($attributes['SALE'])) {
            $url .= '/' . str_replace(' ', '-', strtolower($locales['sale_id']));
        }
        if (!empty($area_id)) {
            $url .= '/' . sanitize_string(str_replace(' ', '-', strtolower(trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", str_replace("-", " ", $area_id)))))));
        }
        $sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
			. "NULL, " //meta_id
			. "%d, " //post_id
			. "'custom_permalink', " //meta_key
			. "%s);" //meta_value
			, $post[0]->ID, $url);
		$res = $wpdb->get_results($sql);
		
		
    	//NOTE: We take first image and make it a thumbnail
    	$firstImage = true;
    	$featuredImages = [];
    	$images = isset($property['pictures']) ? (array)$property['pictures'] : [];
    	uasort($images, function($a, $b) {
    		return $a['ordinal'] - $b['ordinal'];
    	});
    	foreach ($images as $image) {
    		$pathToOriginalImage = isset($image['pathToOriginalImage']) ? str_replace(' ', '%20', stripslashes(strval($image['pathToOriginalImage']))) : null;
    		if (empty($pathToOriginalImage) || !filter_var($pathToOriginalImage, FILTER_VALIDATE_URL) || empty($image['ordinal'])) {
    			continue;
    		}
    		$imageNameParts = pathinfo($pathToOriginalImage);
    		if (!isset($imageNameParts['filename'])) {
    			continue;
    		}
    		$filename = str_replace('%20', '_', $imageNameParts['filename']);
    		$filepath = "{$dirpath}/{$filename}.jpg";

    		//NOTE: Download image to a local temp file using curl to avoid getimagesize() and
    		//      imagecreatefrom*() failing when allow_url_fopen is disabled in php.ini
    		$tmpFile = tempnam(sys_get_temp_dir(), 'crm_img_');
    		$_ch = curl_init($pathToOriginalImage);
    		curl_setopt($_ch, CURLOPT_RETURNTRANSFER, true);
    		curl_setopt($_ch, CURLOPT_SSL_VERIFYPEER, false);
    		curl_setopt($_ch, CURLOPT_SSLVERSION, 6);
    		curl_setopt($_ch, CURLOPT_FOLLOWLOCATION, true);
    		curl_setopt($_ch, CURLOPT_TIMEOUT, 30);
    		$_imageData = curl_exec($_ch);
    		$_httpCode = curl_getinfo($_ch, CURLINFO_HTTP_CODE);
    		curl_close($_ch);
    		if ($_imageData === false || $_httpCode !== 200) {
    			@unlink($tmpFile);
    			continue;
    		}
    		file_put_contents($tmpFile, $_imageData);

    		$im = imageCreateFromAny($tmpFile);
    		@unlink($tmpFile);
    		if (empty($im)) {
    			//TODO: Notify?
    			continue;
    		}
    		if (!empty($stamp)) {
    			//NOTE: Watermark in the middle of the image
    			//imagecopy($im, $stamp, (imagesx($im) - $sx) / 2, (imagesy($im) - $sy) / 2, 0, 0, imagesx($stamp), imagesy($stamp));
    			imagecopy($im, $stamp, imagesx($im) - $sx, imagesy($im) - $sy - (imagesy($im) / 5), 0, 0, imagesx($stamp), imagesy($stamp));
    		}
    
    		//imagepng($im, $filepath);
    		//NOTE: We lower image quality to reduce size
    		$quality = 80;
		    imagejpeg($im, $filepath, $quality);

		    $new_file_path = $wp_upload_dir['path'] . '/' . basename($filepath);
    		try {
                shell_exec('cp ' . $filepath . ' ' . $new_file_path);
                $image_id = wp_insert_attachment( array(
            		'guid'           => $new_file_path, 
            		'post_mime_type' => 'image/jpeg',
            		'post_title'     => $imageNameParts['filename'],
            		'post_content'   => '',
            		'post_status'    => 'inherit'
            	), $new_file_path );
            	wp_update_attachment_metadata($image_id, wp_generate_attachment_metadata($image_id, $new_file_path));
    		} catch (Exception $e) {
    file_put_contents('/tmp/cronjob_catch.log',
        date('c') . " EXCEPTION: " . $e->getMessage() . "\n" .
        $e->getTraceAsString() . "\n\n",
        FILE_APPEND
    );
}

    		if ($firstImage) {
    			$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    				. "NULL, " //meta_id
    				. "%d, " //post_id
    				. "'_thumbnail_id', " //meta_key
    				. "%s);" //meta_value
    				, $post[0]->ID, $image_id);
    			$res = $wpdb->get_results($sql);
    			$firstImage = false;
    		} else {
    			$featuredImages[] = $image_id;
    		}
    
    		unlink($filepath);
    		imagedestroy($im);
    	}
    	if (!empty($featuredImages)) {
    		$sql = $wpdb->prepare("INSERT INTO wpuy_postmeta (meta_id, post_id, meta_key, meta_value) VALUES ("
    			. "NULL, " //meta_id
    			. "%d, " //post_id
    			. "'properties_images_meta', " //meta_key
    			. "%s);" //meta_value
    			, $post[0]->ID, implode(',', $featuredImages));
    		$res = $wpdb->get_results($sql);
    	}
    }
} catch (Exception $e) {
	$messages[] = 'ERROR Exception: ' . $e->getMessage();
}

end:
    file_put_contents('/tmp/cronjob_end.log', "HIT END\n", FILE_APPEND);
$messages[] = 'End: ' . date('d-m-Y H:i:s');
@mail('poopenator94@gmail.com', 'CronJob', "Date: {$date}\r\nMessages: \r\n" . implode("\r\n", $messages));
?>