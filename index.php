<?php
if(!isset($_SESSION))
    {
        session_start();
    }
    $_SESSION['progress']=0;
    session_write_close();
//require("login.php");
require("key.php");
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <!--
  	First, include the main jQuery and jQuery UI javascripts (not included with reformed; you may use Google's CDN links as below:)
  -->
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js"></script>
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/jquery-ui.min.js"></script>

  <!--
  	Next, include links to the form's CSS, taking care to ensure the correct paths dependent upon where you have uploaded the files
  	contained within the reformed.zip and the reformed-form-(YOUR-THEME-HERE).zip files.

  	Be sure to edit the line:
  	<link rel="stylesheet" href="css/reformed-form-YOUR-THEME/jquery-ui-1.8.7.custom.css" type="text/css" />
  	replacing "YOUR-THEME" with the name of your theme (in this case, it's ui-lightness).
  -->
  <!-- necessary reformed CSS -->
  <!--[if IE]>
      <link rel="stylesheet" type="text/css" href="reformed/css/ie_fieldset_fix.css" />
  <![endif]-->
  <link rel="stylesheet" href="reformed/css/uniform.aristo.css" type="text/css" />
  <link rel="stylesheet" href="reformed/css/ui.reformed.css" type="text/css" />
  <link rel="stylesheet" href="reformed/css/jquery-ui-1.8.7.custom.css" type="text/css" />
  <!-- end necessary reformed CSS -->

  <!--
  	Finally, include the necessary javascript to enable the validation rules and style the form.

  	Be sure to edit the line:
  	$('#YOURFORMID').reformed().validate();
  	and replace YOURFORMID with the actual id attribute's value of your form (e.g., "demo" below).
  -->
  <!-- necessary reformed js -->
  <script src="reformed/js/jquery.uniform.min.js" type="text/javascript"></script>
  <script src="reformed/js/jquery.validate.min.js" type="text/javascript"></script>
  <script src="reformed/js/jquery.ui.reformed.min.js" type="text/javascript"></script>

  <script type="text/javascript">
  $(function(){ //on doc ready
      //set validation options
      //(this creates range messages from max/min values)
      $.validator.autoCreateRanges = true;
      $.validator.setDefaults({
          highlight: function(input) {
              $(input).addClass("ui-state-highlight");
          },
          unhighlight: function(input) {
              $(input).removeClass("ui-state-highlight");
          },
          errorClass: 'error_msg',
          wrapper : 'dd',
          errorPlacement : function(error, element) {
              error.addClass('ui-state-error');
              error.prepend('<span class="ui-icon ui-icon-alert"></span>');
              error.appendTo(element.closest('dl.ui-helper-clearfix').effect('highlight', {}, 2000));
          }
      });

      //call reformed on your form
      $('#ShelfLister').reformed().validate();
  });

  </script>
  <!-- end necessary reformed js -->
    <!-- start lookup Ajax js -->
  <script type="text/javascript">
  function AjaxFunction()
  {
  var httpxml;
  try
    {
    // Firefox, Opera 8.0+, Safari
    httpxml=new XMLHttpRequest();
    }
  catch (e)
    {
    // Internet Explorer
  		  try
     			 		{
     				 httpxml=new ActiveXObject("Msxml2.XMLHTTP");
      				}
    			catch (e)
      				{
      			try
        		{
        		httpxml=new ActiveXObject("Microsoft.XMLHTTP");
       		 }
      			catch (e)
        		{
        		alert("Your browser does not support AJAX!");
        		return false;
        		}
      		}
    }
  function stateck()
      {
      if(httpxml.readyState==4)
        {
  //alert(httpxml.responseText);
  var myarray = JSON.parse(httpxml.responseText);
  // Remove the options from 2nd dropdown list
  for(j=document.ShelfLister.location.options.length-1;j>=0;j--)
  {
  document.ShelfLister.location.remove(j);
  }


  for (i=0;i<myarray.locationData.length;i++)
  {
  var optn = document.createElement("OPTION");
  optn.text = myarray.locationData[i].name;
  optn.value = myarray.locationData[i].code;
  document.ShelfLister.location.options.add(optn);

  }
        }
      } // end of function stateck
  var url="almaLocationsAPI.php";
  var cat_id=document.getElementById('library').value;
  url=url+"?lib_id="+cat_id;
  url=url+"&sid="+Math.random();
  httpxml.onreadystatechange=stateck;
  //alert(url);
  httpxml.open("GET",url,true);
  httpxml.send(null);
    }
    <!-- end location lookup Ajax js -->
</script>

  <!-- The following style code is NOT necessary; just some styling to center the form on the page and set the default font size -->
  <style type="text/css">
  	body { font: 12px/14px Arial;}
  	div.reformed-form { width: 550px; margin: 5px auto;}



import url(http://fonts.googleapis.com/css?family=Expletus+Sans);

/* Basic resets */

* {
	margin:0; padding:0;
	box-sizing: border-box;
}

body {
margin: 50px auto 0;
max-width: 800px;

font-family: "Expletus Sans", sans-serif;
}

li {

	width: 50%;
	float: left;
	list-style-type: none;

	padding-right: 5.3333333%;
}

li:nth-child(even) { margin-bottom: 5em;}

h2 {
	margin: 0 0 1.5em;
	border-bottom: 1px solid #ccc;

	padding: 0 0 .25em;
}
  </style>


  </head>
  <body>

    <div class="reformed-form">
      <h1>Inventory Report <small>Fill in form and submit</small></h1>
    	<form method="post" name="ShelfLister" id="ShelfLister" action="<?php echo 'http://' . $_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']) . 'process_barcodes.php'; ?>" enctype="multipart/form-data">
    		<dl>
    			<dt>
    				<label for="flie">Barcode XLSX FIle:</label>
    			</dt>
    			<dd><input type="file" id="flie" class="required" name="file" accept=".xlsx" /></dd>
    		</dl>
    		<dl>
    			<dt>
    				<label for="cnType">Call Number<BR> Type</label>
    			</dt>
    			<dd>
    				<ul>
    					<li><input type="radio" class="required" id="cnType" name="cnType" value="lc" checked="checked" />
    						<label>LC</label>
    					</li>
    					<li><input type="radio" class="required" id="cnType" name="cnType" value="dewey" />
    						<label>Dewey</label>
    					</li>
    					<li><input type="radio" class="required" id="cnType" name="cnType" value="other" />
    						<label>Other</label>
    					</li>
    				</ul>
    						</dd>
    		</dl>
    		<dl>
    			<dt>
    				<label for="library">Library</label>
    			</dt>
    			<dd>
    				<select size="1" name="library" id="library" class="required"  onchange=AjaxFunction();>
              <?Php
$ch = curl_init();
$url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/conf/libraries';
$queryParams = '?' . urlencode('lang') . '=' . urlencode('en') . '&' . urlencode('apikey') . '=' . ALMA_SHELFLIST_API_KEY;
curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
$response = curl_exec($ch);
curl_close($ch);

$xml_result = simplexml_load_string($response);
// PARSE RESULTS
	foreach($xml_result->library as $library)
	{
echo "<option value=$library->code>$library->name</option>";
}
?>
    				</select>
    			</dd>
    		</dl>
    		<dl>
    			<dt>
    				<label for="location">Scan Location</label>
    			</dt>
    			<dd>
    				<select size="1" name="location" id="location" class="required">
    				</select>
    			</dd>
    		</dl>
    		<dl>
    			<dt>
    				<label for="itemType">Primary Item<BR> Type for Scanned Location</label>
    			</dt>
    			<dd>
    				<select size="1" name="itemType" id="itemType" class="required">
    					<option value="BOOK">Book</option>
    					<option value="PERIODICAL">Periodical</option>
              <option value="DVD">DVD</option>
    					<option value="THESIS">Thesis</option>
    				</select>
    			</dd>
    		</dl>
        <dl>
    			<dt>
    				<label for="itemType">Primary Policy<BR> Type for Scanned Location</label>
    			</dt>
    			<dd>
    				<select size="1" name="policy" id="policy" class="required">
    					<option value="core">Core</option>
    					<option value="reserve">Reserve</option>
              <option value="cont lit">Contemporary Lit</option>
    					<option value="media">Media</option>
              <option value="juvenile">Juvenile</option>
    				</select>
    			</dd>
    		</dl>
        <dl>
    			<dt>
    				<label for="cnType">Only Report<BR>CN Order Problems?</label>
    			</dt>
    			<dd>
    				<ul>
    					<li><input type="radio" class="required" id="onlyOrder" name="onlyorder" value="false" checked="checked" />
    						<label>No</label>
    					</li>
    					<li><input type="radio" class="required" id="onlyOrder" name="onlyorder" value="true" />
    						<label>Yes</label>
    					</li>
    				</ul>
    						</dd>
    		</dl>
        <dl>
    			<dt>
    				<label for="cnType">Only Report<BR>Problems Other Than CN?</label>
    			</dt>
    			<dd>
    				<ul>
    					<li><input type="radio" class="required" id="onlyOrder" name="onlyother" value="false" checked="checked" />
    						<label>No</label>
    					</li>
    					<li><input type="radio" class="required" id="onlyOrder" name="onlyother" value="true" />
    						<label>Yes</label>
    					</li>
    				</ul>
    						</dd>
    		</dl>
        <dl>
    			<dt>
    				<label for="cnType">Report Only<BR> Problems?</label>
    			</dt>
    			<dd>
    				<ul>
    					<li><input type="radio" class="required" id="onlyProblems" name="onlyproblems" value="false" checked="checked" />
    						<label>No</label>
    					</li>
    					<li><input type="radio" class="required" id="onlyProblems" name="onlyproblems" value="true" />
    						<label>Yes</label>
    					</li>
    				</ul>
    						</dd>
    		</dl>
			<dl>
    			<dt>
    				<label for="cnType">Clear Cache?</label>
    			</dt>
    			<dd>
    				<ul>
    					<li><input type="radio" class="required" id="clearCache" name="clearCache" value="false" checked="checked" />
    						<label>No</label>
    					</li>
    					<li><input type="radio" class="required" id="clearCache" name="clearCache" value="true" />
    						<label>Yes</label>
    					</li>
    				</ul>
    						</dd>
    		</dl>
    		<div id="submit_buttons">
    			<input type="submit" name="submit"/>
    		</div>
    		</form>
    </div>
  </body>
</html>
