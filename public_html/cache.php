<?php
// https://commons.wikimedia.org/w/thumb.php?w=48&f=Harding%20Icefield%201.jpg

$file_name = str_replace(' ', '_', ucfirst($_GET['f']));
if (array_key_exists('t', $_GET))
  $thumb_width = intval($_GET['t']);
else
  $thumb_width = '';

if ($file_name == "")
{
  echo "Supply a filename!";
  exit;
}

// connect to database
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$db = mysqli_connect("p:commonswiki.labsdb", $ts_mycnf['user'], $ts_mycnf['password'], "commonswiki_p");
unset($ts_mycnf, $ts_pw);

// get last upload date and image dimensions from database
$sql = sprintf("SELECT img_timestamp, img_width, img_height FROM image WHERE img_name = '%s'", mysqli_real_escape_string($db, $file_name));
$res = mysqli_query($db, $sql);

if (mysqli_num_rows($res) == 1)
{
  $row = mysqli_fetch_array($res);

  // do not fetch a thumbnail if the full image is already smaller than the
  // requested size
  if (intval($row['img_width']) < $thumb_width) $thumb_width = '';
}
else
{
  echo "Database error.";
  exit;
}

$c = 'cache/' . md5($file_name . $thumb_width) . '.jpg';
$md5 = md5($file_name);


$fetch_file = false;
if (is_readable($c))
{
  // get cache modification time
  $ctime = strftime("%Y%m%d%H%M%S", filemtime($c));

  // we need to re-fetch the file if the last upload reported by the database is NEWER than the file we have cached
  $fetch_file =  $row['img_timestamp'] > $ctime;
}
else
  $fetch_file = true;

if (!is_readable($c))
{
  if ($thumb_width == '')
    $fullfile = 'https://upload.wikimedia.org/wikipedia/commons/' . substr($md5,0,1) . '/' . substr($md5,0,2) . '/' . $file_name;
  else
    $fullfile = 'https://commons.wikimedia.org/w/thumb.php?w=' . $thumb_width . '&f=' . $file_name;

  // either not cached before, or cached version too old
  ini_set('user_agent', 'panoviewer/1.0 (https://panoviewer.toolforge.org/)');

  // if we are not getting a thumbnail make sure the file is a valid JPG file
  if ($thumb_width == '')
  {
    $image = file_get_contents($fullfile, false, null, 0, 100);

    if (substr($image, 6, 4)  != 'JFIF' &&
        substr($image, 6, 4)  != 'Exif' &&
        substr($image, 6, 9)  != 'Photoshop' &&
        substr($image, 6, 20) != 'http://ns.adobe.com/')
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

header('Location: //tools.wmflabs.org/panoviewer/' . $c);
?>
