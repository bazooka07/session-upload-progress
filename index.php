<?php
/*
 * Some documentation :
 *
 * http://php.net/manual/en/session.upload-progress.php
 * https://www.sitepoint.com/tracking-upload-progress-with-php-and-javascript/
 * https://stackoverflow.com/questions/21703081/php-upload-progress-in-php-5-4-is-not-working-session-variables-not-set
 *
 * session.auto_start ???
 * */

const PREFIX = 'session.upload_progress.';

const DEBUG = false;

$uploads_dir = __DIR__ .'/uploads';
if(!is_dir($uploads_dir) and !mkdir($uploads_dir)) {
	exit('Impossible de créer le dossier '.$uploads_dir);
}

const MATRIX = 'abcdefghijklmnpqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const MATRIX_LEN = 60; // strlen(MATRIX) - 1;

function charAleatoire($size=16) {
	list($usec, $sec) = explode(' ', microtime());
	mt_srand($sec + $usec * 1000000);
	$outputs = array();
	for($i=0; $i<$size; $i++) { $outputs[] = MATRIX[mt_rand(0, MATRIX_LEN)]; }
	return implode('', $outputs);
}

session_start();

$_SESSION['foo'] = 'Hello';

if(!empty($_GET['key'])) {
	$key = ini_get(PREFIX.'prefix').$_GET['key'];

	$contentType = (DEBUG) ? 'text/plain' : 'application/json';
	header('Content-Type: '.$contentType.'; charset=UTF-8');
	header('Expires: Tue, 01 May 2018 00:00:00 GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: post-check=0, pre-check=0', FALSE);
	header('Pragma: no-cache');
	if(DEBUG) {
		echo "\$key : $key\n";
		print_r($_SESSION);
	} else {
		echo json_encode($_SESSION[$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
	echo "\n\n";
	exit;
} elseif(!empty($_FILES['pictures'])) {
	// auto-cleanup
	$old_files = glob($uploads_dir.'/*');
	foreach($old_files as $f) {
		unlink($f);
	}

	foreach ($_FILES['pictures']['error'] as $key => $error) {
	    if ($error == UPLOAD_ERR_OK) {
	        $tmp_name = $_FILES['pictures']['tmp_name'][$key];
	        // basename() may prevent filesystem traversal attacks;
	        // further validation/sanitation of the filename may be appropriate
	        $name = basename($_FILES['pictures']['name'][$key]);
	        move_uploaded_file($tmp_name, "$uploads_dir/$name");
	    }
	}
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=Edge" />
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Upload tracking</title>
	<style type="text/css">
		* { margin: 0; padding: 0; box-sizing: border-box; }
		html { background-color: #444; }
		body { margin: 2rem auto 0; padding: 0.3rem; max-width: 42rem; background-color: #fff; font-family: 'Noto Sans', Arial, Sans-Serif; font-size: 12pt; }
		pre { margin-bottom: 1rem; padding: 0.2rem 0.5rem; background-color: #eee; border: 2px outset #444; }
		form { display: flex; width: 100%; }
		input[type="file"] { flex-grow: 1; }
		progress { width: 100%; height: 0.5rem; }
		#id_tracking,
		ul.listing { display: table; width: 100%; }
		#id_tracking > p,
		ul.listing > li { display: table-row; }
		#id_tracking > p:nth-of-type(2n+1),
		ul.listing li:nth-of-type(2n+1) { background-color: #eee; }
		#id_tracking > p > span,
		ul.listing > li > span { display: table-cell; padding: 0.1rem 0.3rem; }
		#id_tracking > p > span:not(:first-of-type) { border-left: 1px solid #444; }
		ul.listing > li > span:nth-of-type(2) { text-align: right; padding-right: 1rem; }
		ul.listing > li > span:nth-of-type(3) { text-align: center; }
	</style>
</head><body>
	<p>PHP version : <?php echo phpversion(); ?></p>
	<h1>Parametrage php.ini</h1>
	<pre>
<?php
const PATTERN = "%-32s : %s\n";

$entries = explode("\n", trim('
enabled
cleanup
prefix
name
freq
min_freq
'));
foreach($entries as $entry) {
	$name = PREFIX.$entry;
	$value = ini_get($name);
	printf(PATTERN, $name, $value);
}
echo "\n";

foreach(explode(' ', 'upload_max_filesize max_file_uploads post_max_size') as $entry) {
	$value = ini_get($entry);
	printf(PATTERN, $entry, $value);
}
?>
	</pre>
<?php
$name = ini_get('session.upload_progress.name');
$key = charAleatoire();
?>

	<form id="id_form1" method="post" enctype="multipart/form-data" data-key="<?php echo $name; ?>">
		<input type="hidden" name="<?php echo $name; ?>" value="<?php echo $key; ?>">
		<input type="file" name="pictures[]"  multiple accept="image/*" />
		<input type="submit" />
	</form>

	<progress id="id_progress" value="0" min="0" max="100"></progress>

	<h1>Envoi en cours</h1>
	<div id="id_tracking">
	</div>

	<h1>Fichiers téléchargés</h1>
	<ul class="listing">
<?php
const KO = 1024;
const MO = 1048576;
$files = glob($uploads_dir.'/*');
foreach($files as $f) {
	$stats = stat($f);
	$date1 = date('Y-m-d H:i', $stats['mtime']);
	$caption = basename($f);
	$size = ($stats['size'] < KO) ? sprintf('%3d    ',intval($stats['size'])) :
		($stats['size'] < MO) ? sprintf('%6.1f Ko', $stats['size'] / KO) :
		sprintf('%6.1f Mo', $stats['size'] / MO);
	echo <<< EOT
		<li><span>$caption</span><span>$size</span><span>$date1</span></li>\n
EOT;
}
?>
	</ul>

	<script>
		(function() {
			// https://developer.mozilla.org/fr/docs/Learn/JavaScript/Objects/JSON
			'use strict';

			function _id(id) {
				return document.getElementById('id_' + id);
			}

			const form1 = _id('form1');
			const key = form1.elements[form1.dataset.key].value;
			const progressBar = _id('progress');
			const fields = ['field_name', 'name', 'tmp_name', 'bytes_processed', 'error', 'done'];
			const responseTest = '{"start_time":1544441565,"content_length":2623568,"bytes_processed":2216512,"done":false,"files":[{"field_name":"pictures[]","name":"ordi-perso-800px.jpg","tmp_name":"/tmp/phpDR7Zdc","error":0,"done":true,"start_time":1544441565,"bytes_processed":454593},{"field_name":"pictures[]","name":"P1000290.JPG","tmp_name":"/tmp/phpDCagzi","error":0,"done":true,"start_time":1544441569,"bytes_processed":1745920},{"field_name":"pictures[]","name":"P1000339-1.JPG","tmp_name":null,"error":0,"done":false,"start_time":1544441585,"bytes_processed":15357}]}';

			const tracking = _id('tracking');
			function parseResponse(value) {
				const datas = JSON.parse(value);
				if(datas != null && datas.content_length > 0) {
					progressBar.value = progressBar.max * datas.bytes_processed / datas.content_length;

					// Heading of columns
					tracking.textContent = '';
					const headers = document.createElement('P');
					fields.forEach(function(field) {
						const span = document.createElement('SPAN');
						span.textContent = field;
						headers.appendChild(span);
					});
					tracking.appendChild(headers);

					datas.files.forEach(function(item) {
						const paragraph = document.createElement('P');
						fields.forEach(function(field) {
							const span = document.createElement('SPAN');
							// console.log(field, item[field]);
							span.textContent = item[field];
							paragraph.appendChild(span);
						});
						tracking.appendChild(paragraph);
					});
				}
			}

			var timer1 = null;
			const xhr = new XMLHttpRequest();
			const xhrParams = 'key=' + key;
			xhr.onreadystatechange= function() {
				if(xhr.readyState == XMLHttpRequest.DONE && xhr.status == 200) {
<?php
if(DEBUG) {
?>
					console.log(xhr.responseText);
<?php
} else {
?>
					parseResponse(xhr.responseText);
<?php
}
?>
					clearTimeout(timer1);
					timer1 = setTimeout(function() {
						// console.log('Envoi requête');
						xhr.open('GET', form1.action + '?' + xhrParams);
						xhr.send();
					}, 1500);
				}
			};

			form1.onsubmit = function(event) {
				timer1 = setTimeout(function() {
					// console.log('Envoi requête 1');
					xhr.open('GET', form1.action + '?' + xhrParams);
					xhr.send();
				}, 1000);
				// console.log('Envoi formulaire');
			};

			// parseResponse(responseTest);

		})();
	</script>
</body></html>
