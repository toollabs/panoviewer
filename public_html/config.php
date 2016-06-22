<?php
// all images above this size are tiled (and temporarily shown as a max_size rescaled version)
$max_width = 4000;

// send content type header and prevent caching
header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// get normalized filename
$f = str_replace(' ', '_', ucfirst($_GET['f']));
if ($f == "")
{
  echo '{ "error": "No file name supplied" }';
  exit;
}

// cache identifier
$md5 = md5($f);
$cache_prefix = 'cache/' . $md5;
$cache_file = $cache_prefix . '.jpg';

// connect to database
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$db = mysqli_connect("p:commonswiki.labsdb", $ts_mycnf['user'], $ts_mycnf['password'], "commonswiki_p");
unset($ts_mycnf, $ts_pw);

// get last upload date and image dimensions from database
$sql = sprintf("SELECT img_timestamp, img_width, img_height FROM image WHERE img_name = '%s'", mysqli_real_escape_string($db, $f));
$res = mysqli_query($db, $sql);

if (mysqli_num_rows($res) != 1)
{
  echo '{ "error": "Database error (found ' . mysqli_num_rows($res) . 'results; should be 1)" }';
  exit;
}

// fetch data on the current image
$row = mysqli_fetch_array($res);
$width = intval($row['img_width']);

// see if we have an up to date untiled version in the cache
$fetch_file = false;
if (is_readable($cache_file))
{
  // get cache modification time
  $ctime = strftime("%Y%m%d%H%M%S", filectime($cache_file));

  // we need to re-fetch the file if the last upload reported by the database is NEWER than the file we have cached
  $fetch_file =  $row['img_timestamp'] > $ctime;
}
else
  $fetch_file = true;

// need to (re-)fetch the file from commons
if ($fetch_file)
{
  // either not cached before, or cached version too old
  ini_set('user_agent', 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');

  // make sure the file is a valid JPG file
  $fullfile = 'https://upload.wikimedia.org/wikipedia/commons/' . substr($md5, 0, 1) . '/' . substr($md5, 0, 2) . '/' . $f;
  $image = file_get_contents($fullfile, false, null, 0, 100);
  if (substr($image, 6, 4) != 'JFIF' &&
      substr($image, 6, 4) != 'Exif' &&
      substr($image, 6, 9) != 'Photoshop')
  {
    echo '{ "error": "This file does not look like a valid JPEG" }';
    exit;
  }

  // for large images we prepare a downscaled preview while the tiling is in progress
  if ($width > $max_width)
  {
    $preview = 'https://commons.wikimedia.org/w/thumb.php?w=' . $max_width . '&f=' . $f;

    // TODO dispatch the tiling job in the background, while we serve the preview
  }
  else
    $preview = $fullfile;

  // open the file on commons and the local cache version
  umask(022);
  $src = fopen($preview, 'rb');
  $dst = fopen($cache_file, 'wb');
  if (!$src || !$dst) exit;

  // copy the file in 8kB chunks
  while (!feof($src))
  {
    $chunk = fread($src, 8192);
    fwrite($dst, $chunk);
  }
  fclose($src);
  fclose($dst);
}

// build the return object
$cache_config = $cache_prefix . '/config.json';
if (is_readable($cache_config))
{
  // redirect to config.json file
  $config = json_decode(file_get_contents($cache_config));
  $config->multiRes->path = $cache_prefix . $json->multiRes->path;
  $config->multiRes->fallbackPath = $dir . $json->multiRes->fallbackPath;
  $json = (object) [
    'pannellum' => $config
  ];
}
else
{
  // return the preview (which is the full version for small images)
  $json = (object) [
    'multires_pending' => ($width > $max_width),
    'pannellum' => (object)[
      'panorama' => $cache_file
    ]
  ];
}

echo json_encode($json);
?>
