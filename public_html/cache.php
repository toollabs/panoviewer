<?php
// http://commons.wikimedia.org/w/thumb.php?w=48&f=Harding%20Icefield%201.jpg

$f = str_replace(' ', '_', ucfirst($_GET['f']));
if (array_key_exists('t', $_GET))
  $t = intval($_GET['t']);
else
  $t = '';

if ($f == "")
{
  echo "Supply a filename!";
  exit;
}

$c = 'cache/' . md5( $f.$t) . '.jpg';
$md5 = md5($f);

if ($t == '')
  $fullfile = 'http://upload.wikimedia.org/wikipedia/commons/' . substr($md5,0,1) . '/' . substr($md5,0,2) . '/' . $f;
else
  $fullfile = 'http://commons.wikimedia.org/w/thumb.php?w=' . $t . '&f=' . $f;

if( !is_readable($c) )
{
  // either not cached before, or cached version too old
  ini_set('user_agent', 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');
  
  if($t == '') 
  {
    $image = file_get_contents( $fullfile, false, null, 0, 100 );
    if (substr($image,6,4) != 'JFIF' && substr($image,6,4) != 'Exif') exit;
  }

  umask(022);
  $handle = fopen($c, 'wb');

  $handle2 = fopen($fullfile, 'rb');
  if (!$handle2) exit;

  while (!feof($handle2)) 
  {
    $chunk = fread( $handle2, 8192 );
    fwrite($handle, $chunk);
  }
  fclose( $handle2 );
  fclose( $handle );
}

header('Location: //tools.wmflabs.org/panoviewer/'.$c);
?>
