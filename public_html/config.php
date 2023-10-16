<?php
// all images above this size are tiled (and temporarily shown as a max_size rescaled version)
$max_width = 4000;

// send content type header and prevent caching
header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// get normalized filename
if (array_key_exists('f', $_GET))
  $file_name = str_replace(' ', '_', ucfirst($_GET['f']));
else {
  echo '{ "error": "No file name supplied" }';
  exit;
}

// cache identifier
$md5 = md5($file_name);
$cache_prefix = 'cache/' . $md5;
$cache_file = $cache_prefix . '.jpg';

// connect to database
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$db = mysqli_connect("p:commonswiki.labsdb", $ts_mycnf['user'], $ts_mycnf['password'], "commonswiki_p");
unset($ts_mycnf, $ts_pw);

// get last upload date and image dimensions from database
$sql = sprintf("SELECT img_timestamp, img_width, img_height FROM image WHERE img_name = '%s'", mysqli_real_escape_string($db, $file_name));
$res = mysqli_query($db, $sql);

if (mysqli_num_rows($res) != 1)
{
  echo '{ "error": "Database error (found ' . mysqli_num_rows($res) . ' results; should be 1)" }';
  exit;
}

// fetch data on the current image
$row = mysqli_fetch_array($res);
$width = intval($row['img_width']);

// if we are only polling to check if the multires is done we skip all the db stuff!
if (!array_key_exists('p', $_GET))
{
  // see if we have an up to date untiled version in the cache
  $fetch_file = false;
  if (is_readable($cache_file))
  {
    // get cache modification time
    $mtime = strftime("%Y%m%d%H%M%S", filemtime($cache_file));

    // we need to re-fetch the file if the last upload reported by the database is NEWER than the file we have cached
    $fetch_file =  $row['img_timestamp'] > $mtime;
  }
  else
    $fetch_file = true;

  // need to (re-)fetch the file from commons
  if ($fetch_file)
  {
    // either not cached before, or cached version too old
    ini_set('user_agent', 'panoviewer/1.0 (https://panoviewer.toolforge.org/)');

    // make sure the file is a valid JPG file
    $fullfile = 'https://upload.wikimedia.org/wikipedia/commons/' . substr($md5, 0, 1) . '/' . substr($md5, 0, 2) . '/' . urlencode($file_name);
    $image = file_get_contents($fullfile, false, null, 0, 100);
    if (substr($image, 6, 4)  != 'JFIF' &&
        substr($image, 6, 4)  != 'Exif' &&
        substr($image, 6, 9)  != 'Photoshop' &&
	substr($image, 6, 20) != 'http://ns.adobe.com/')
    {
      echo '{ "error": "This file does not look like a valid JPEG" }';
      exit;
    }

    // delete a potentially existing older multires cache
    rmdir($cache_prefix);

    // for large images we prepare a downscaled preview while the tiling is in progress
    if ($width > $max_width)
    {
      $preview = 'https://commons.wikimedia.org/w/thumb.php?w=' . $max_width . '&f=' . urlencode($file_name);
      $command = 'jsub -mem 2048m -N ' . escapeshellarg('pano_' . $md5) . ' -once ./multires.sh cache/ ' . escapeshellarg($md5) . ' ' . escapeshellarg(urlencode($file_name));
      exec ($command, $out, $ret);
    }
    else
      $preview = $fullfile;

    // open the file on commons and the local cache version
    umask(022);
    $src = fopen($preview, 'rb');
    $dst = fopen($cache_file, 'wb');
    if (!$src || !$dst) {
	echo '{ "fullfile": "' . $fullfile . '", "src": "'.$src.'", "dst": "'.$dst.'" }';
	exit;
    }

    // copy the file in 8kB chunks
    while (!feof($src))
    {
      $chunk = fread($src, 8192);
      fwrite($dst, $chunk);
    }
    fclose($src);
    fclose($dst);
  }
} // simplified polling skips the previous block

// build the return object
$cache_config = $cache_prefix . '/config.json';
if (is_readable($cache_config))
{
  // redirect to config.json file
  $config = json_decode(file_get_contents($cache_config));
  $config->multiRes->path = $cache_prefix . $config->multiRes->path;
  $config->multiRes->fallbackPath = $cache_prefix . $config->multiRes->fallbackPath;
  $json = (object) [
    'pannellum' => $config,
    'width' => $width
  ];
}
else
{
  // return the preview (which is the full version for small images)
  $json = (object) [
    'multires_pending' => ($width > $max_width),
    'pannellum' => (object)[
      'panorama' => $cache_file,
      'width' => $width
    ]
  ];
}

// force auto loading
$json->pannellum->autoLoad = true;

echo json_encode($json);
?>
