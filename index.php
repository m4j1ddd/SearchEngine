<?php
require('functions.php');
$search = '';
$page = 1;
$num = 10;
if(isset($_GET['search'])) $search = $_GET['search'];
if(isset($_GET['page'])) $page = $_GET['page'];
function on_request_done($content, $url, $ch, $searchk)
{
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);    
    if ($httpcode !== 200) {
        return;
    }
             $url = urldecode($url);
			 $read = $content;
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
			 $r = preg_replace('/<title.*?>.*?<\/title>/si', " ", $read);
			 $r = preg_replace('/&.*?;/si',' ', preg_replace('/<[^>]*>/',' ',$r));
			 $r = trim($r);
			 $description .= ' '.$r;
			 $description = trim($description);
			 $ltitle = strtolower($title);
			 $ldescription = strtolower($description);
			 $ar_des = explode(' ', $description);
			 $ar_title = explode(' ', $title);
			 $lar_des = explode(' ', $ldescription);
			 $lar_title = explode(' ', $ltitle);
			 $keys_title = array();
	         $keys_des = array();
	         foreach($searchk as $ind)
	         {
			  $ind = strtolower($ind);
	          $keys_title = array_merge($keys_title, array_keys($lar_title, $ind));
	          $keys_des = array_merge($keys_des, array_keys($lar_des, $ind));
	         }
	         sort($keys_title);
	         sort($keys_des);
			 ?><div>
			 <a class="result" href="<?php echo $url ?>">
			 <?php echo show($keys_title, $ar_title) ?>
             </a>
             </div>
             <div>
             <?php echo show($keys_des, $ar_des) ?>
             </div>
             <div style="color: green; direction: ltr; float: right;">
             <?php
			 echo $show_url = substr($url, 0, 70);
             if($show_url!=$url) echo '...';
			 ?>
             </div>
             <br>
			 <?php
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" >
<title><?php if(trim($search) != '') echo $search.' - جستجوی '; ?><?php echo $main_title; ?></title>
<meta name="description" content="موتور جستجوی فارسی میهن جو یک جستجوگر ایرانی است که دارای بخش های جستجوی وب ، عکس ها ، آهنگ ها و فیلم ها می باشد" />
<meta name="keywords" content="موتور جستجو, جستجو, جستجوگر, میهن جو, میهن, جو, ایران, ایرانی, فارسی, جستجوگر ایرانی, جستجوی فارسی" />
<link rel="stylesheet" type="text/css" href="style.css">
<link rel="shortcut icon" href="/images/favicon.ico" />
<script type="text/javascript" src="js.js"></script>
</head>
<body onLoad='ajaxFunction();'>
<?php
menu('/', $search); 
if(trim($search) != '')
{
 ?><div class="body"><?php
 $d = array();
 $searchk = preg_split('/[\s,\-\.]+/si', $search);
 $query = "select * from keywords where ";
 $n = 0;
 foreach($searchk as $index)
 {
  if(trim($index) != "")
  {
	if($n == 0)
	{
     $query .= "keyword = '$index' ";
	 $n++;
	}
	else
	{
		$query .= "or keyword = '$index' ";
	}
  }
 }
 $query .= "order by density Desc";
 $result = mysqli_query($db, $query);
 $count = mysqli_num_rows($result);
 if($count == 0)
  echo 'نتیجه ای یافت نشد';
 else
 {
  echo $count.' نتیجه';
  $i = 1;
  while($i <= ($num * $page) && $i <= $count)
  {
	  $row = mysqli_fetch_row($result);
	  $url = $row[0];
	  $density = $row[2];
      @ $d[$url] += $density;
	  $i++;
  }
  arsort($d);
  $parallel_curl = new ParallelCurl($num);
  $i = 1;
  foreach($d as $index => $value)
  {
	  if($i<=$page*$num && $i>($page-1)*$num)
	  	$parallel_curl->startRequest($index, 'on_request_done', $searchk);
	  $i++;
  }
  $parallel_curl->finishAllRequests();
  page($search, $page, $count, $num);
 }
 ?></div><?php
}
@ mysqli_close($db);
foot();
?>
</body>
</html>