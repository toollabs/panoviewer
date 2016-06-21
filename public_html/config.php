<?php
// all images above this size are tiled (and temporarily shown as a max_size rescaled version)
$max_size = 4000;

// get normalized filename
$f = str_replace(' ', '_', ucfirst($_GET['f']));
if ($f == "")
{
  echo "Supply a filename!";
  exit;
}

// cache identifier
$md5 = md5($f);
$c = 'cache/' . $md5 . '.jpg';

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
  echo "Database error.";
  exit;
}

// fetch data on the current image
$row = mysqli_fetch_array($res);
$width = intval($row['img_width']);

echo "{ 'popel': 'nase' }";
exit 0;

// see if we have an up to date untiled version in the cache
$fetch_file = false;
if (is_readable($c))
{
  // get cache modification time
  $ctime = strftime("%Y%m%d%H%M%S", filectime($c));

  // we need to re-fetch the file if the last upload reported by the database is NEWER than the file we have cached
  $fetch_file =  $row['img_timestamp'] > $ctime;
}
else
  $fetch_file = true;

if (!is_readable($c))
{
  if ($t == '')
    $fullfile = 'https://upload.wikimedia.org/wikipedia/commons/' . substr($md5,0,1) . '/' . substr($md5,0,2) . '/' . $f;
  else
    $fullfile = 'https://commons.wikimedia.org/w/thumb.php?w=' . $t . '&f=' . $f;

  // either not cached before, or cached version too old
  ini_set('user_agent', 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');

  // if we are not getting a thumbnail make sure the file is a valid JPG file
  if ($t == '')
  {
    $image = file_get_contents($fullfile, false, null, 0, 100);
    if (substr($image,6,4) != 'JFIF' && substr($image,6,4) != 'Exif' && substr($image,6,9) != 'Photoshop')
      exit;
  }

  umask(022);
  $handle = fopen($c, 'wb');

  $handle2 = fopen($fullfile, 'rb');
  if (!$handle2) exit;

  while (!feof($handle2))
  {
    $chunk = fread($handle2, 8192);
    fwrite($handle, $chunk);
  }
  fclose($handle2);
  fclose($handle);
}
?>