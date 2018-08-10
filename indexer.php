<?php
require('functions.php');
$year = date('Y');
$month = date('n');
$day = date('j');
$query = "select url from web
          where year = '0' or year <> '$year' limit 1";
if($result = mysqli_query($db, $query))
{
 $row = mysqli_fetch_row($result);
 $url = $row[0];
 if(@ $read = file_get_contents($url))
 {
  $read = preg_replace('/<script.*?>.*?<\/script.*?>/si', ' ', $read);
  $read = preg_replace('/<style.*?>.*?<\/style.*?>/si', ' ', $read);
  $title = '';
  $regex = '/<title.*?>(.*?)<\/title>/si';
  if(preg_match_all($regex, $read, $mat)) $title = preg_replace('/&.*?;/si',' ',$mat[1][0]);
  $description = '';
  $regex = '/<meta\s+.*?name\s*=\s*[\'"]description[\'"].*?content\s*=\s*[\'"](.*?)[\'"].*?>/si';
  if(preg_match_all($regex, $read, $mat)) $description = $mat[1][0];
  else
  { 
	$regex = '/<meta\s+.*?content\s*=\s*[\'"](.*?)[\'"].*?name\s*=\s*[\'"]description[\'"].*?>/si';
	if(preg_match_all($regex, $read, $mat)) $description = $mat[1][0];
  }
  $keywords = "";
  $regex = '/<meta\s+.*?name\s*=\s*[\'"]keywords[\'"].*?content\s*=\s*[\'"](.*?)[\'"].*?>/si';
  if(preg_match_all($regex, $read, $mat)) $keywords = $mat[1][0];
  else
  {
	$regex = '/<meta\s+.*?content\s*=\s*[\'"](.*?)[\'"].*?name\s*=\s*[\'"]keywords[\'"].*?>/si';
	if(preg_match_all($regex, $read, $mat)) $keywords = $mat[1][0];
  }
  $h1 = '';
  $regex = '/<h1.*?>(.*?)<\/h1>/si';
  if(preg_match_all($regex, $read, $mat)) $h1 = preg_replace('/&.*?;/si',' ',preg_replace('/<[^>]*>/',' ',$mat[1][0]));
  $h2 = array();
  $c = 0;
  $regex = '/<h2.*?>(.*?)<\/h2>/si';
  if(preg_match_all($regex, $read, $mat))
  {
   foreach($mat[1] as $ind)
   {
	 $h2[$c] = preg_replace('/&.*?;/si',' ',preg_replace('/<[^>]*>/',' ',$ind));
	 $c++;
   }
  }
  $link_url = array();
  $link_text = array();
  $c = 0;
  $regex  = '/<a\s+.*?href\s*=\s*[\'"](.*?)[\'"].*?>(.*?)<\/a>/si';
  if(preg_match_all($regex, $read, $mat, PREG_SET_ORDER))
  {
   foreach($mat as $ind)
   {
    $link_url[$c] = url_to_absolute($url, $ind[1]);
    $link_text[$c] = preg_replace('/&.*?;/si',' ',preg_replace('/<[^>]*>/',' ',$ind[2]));
    $c++;
   }
  }
  $image_url = array();
  $image_alt = array();
  $c = 0;
  $regex  = '/<img\s+.*?src\s*=\s*[\'"](.*?)[\'"].*?alt\s*=\s*[\'"](.*?)[\'"].*?>/si';
  if(preg_match_all($regex, $read, $mat, PREG_SET_ORDER))
  {
   foreach($mat as $ind)
   {
    $image_url[$c] = url_to_absolute($url, $ind[1]);
	$image_alt[$c] = preg_replace('/&.*?;/si',' ',$ind[2]);
    $c++;
   }
  }
  $regex  = '/<img\s+.*?alt\s*=\s*[\'"](.*?)[\'"].*?src\s*=\s*[\'"](.*?)[\'"].*?>/si';
  if(preg_match_all($regex, $read, $mat, PREG_SET_ORDER))
  {
   foreach($mat as $ind)
   {
    $image_url[$c] = url_to_absolute($url, $ind[2]);
    $image_alt[$c] = preg_replace('/&.*?;/si',' ',$ind[1]);
    $c++;
   }
  }
  $r = preg_replace('/&.*?;/si',' ', preg_replace('/<[^>]*>/',' ',$read));
  $query = "delete from links
            where url = '$url'";
  mysqli_query($db, $query);
  $c = count($link_url);
  if($c>100)
  {
	 $c = 100;
  }
  for($i=0;$i<$c;$i++)
  {
   $link = $link_url[$i];
   $type = type($link);
   $query = "insert into links
             (url, link, type, text) values 
             ('".$url."', '".$link."', '".$type."', '".$link_text[$i]."')";
   mysqli_query($db, $query);
   if($type=='text/html')
   {
    $query = "insert into web
	          (url, year, month, day) values
	          ('".$link."', '0', '0', '0')";
    mysqli_query($db, $query);
   }
  }
  $c = count($image_url);
  if($c>10)
  {
	 $c = 10;
  }
  for($i=0;$i<$c;$i++)
  {
   $image = $image_url[$i];
   $type = type($image);
   if(preg_match_all('/image\/.*/si', $type, $mat))
   {
	$query = "insert into links
	          (url, link, type, text) values
			  ('".$url."', '".$image."', '".$type."', '".$image_alt[$i]."')";
	mysqli_query($db, $query);
   }
  }
  keywords($title, 5);
  keywords($description, 2);
  keywords($keywords, 2);
  keywords($h1, 5);
  foreach($h2 as $index)
  {
	keywords($index, 4); 
  }
  foreach($link_text as $index)
  {
	keywords($index, 3); 
  }
  foreach($image_alt as $index)
  {
	keywords($index, 3);	 
  }
  keywords($r, 1);
  $pr = pr($url, $db);
  $t = 0;
  $query = "delete from keywords
            where url = '$url'";
  mysqli_query($db, $query);
  arsort($keyd);
  foreach($keyd as $index => $value)
  {
	$t++;
	$value *= $pr;
	if($t<=50)
	{
	 $query = "insert into keywords
	           (url, keyword, density) values
			   ('".$url."', '".$index."', '".$value."')";
	 mysqli_query($db, $query);
	}
  }
  $query = "delete from web
            where url = '$url'";
  mysqli_query($db, $query);
  $query = "insert into web
            (url, year, month, day) values
			('".$url."', '".$year."', '".$month."', '".$day."')";
  mysqli_query($db, $query);
 }
 else
 {
  echo $url;
  $query = "delete from web where url = '$url'";
  mysqli_query($db, $query);
  $query = "delete from links where link = '$url'";
  mysqli_query($db, $query);
  $query = "delete from keywords where url = '$url'";
  mysqli_query($db, $query);
 }
}
mysqli_close($db);
?>