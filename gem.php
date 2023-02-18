<!DOCTYPE html>
<?php
	//date_default_timezone_set('UTC');
	$runList = array();
	$now = time();
	// from 1700z-0459z, use 12z run time; otherwise use 00z
	if (gmdate('G',$now) >= 17)
		$latestRun = $now - ($now % 86400) + 12*3600;
	else if (gmdate('G',$now) < 5)
		$latestRun = $now - ($now % 86400) - 86400 + 12*3600;
	else $latestRun = $now - ($now % 86400);
	for ($iRun = 0; $iRun < 6; $iRun++) {
		$rT = $latestRun - $iRun * 12*3600;
		$runList[gmdate('YmdH',$rT)] = gmdate('c',$rT);
	}
		//array_push($runList,gmdate('YmdH',$latestRun - $iRun * 12*3600));
?>
<html>
<head>
	<title>Canadian GEPS ensemble viewer Deluxe</title>
	<style>
p#heading {
	font-size: 20px;
	font-weight: bold;
	padding-top: 0px;
	padding-bottom: 0px;
	margin-top: 10px;
	margin-bottom: 5px;
}
td.bannerFlag {
	padding-right: 24px;
}
td.bannerText {
	padding-right: 24px;
	vertical-align: top;
	font-weight: bold;
}
div#mean {
	width: 45%;
}
img#mean1 {
	width: 100%;
}
div#thumbs {
	position: absolute;
	top: 10px;
	left: 50%;
	height: 96%;
	overflow-y: scroll;
}
img#thumbs1, img#thumbs2, img#thumbs3 {
	width: 100%;
}
span#status {
	font-size: 16px;
}
div#help {
	position: fixed;
	font: bold 18px sans-serif;
	color: white;
	top: 0px;
	left: 0px;
	width: 100%;
	height: 100%;
	background: rgba(200, 200, 200, 0.5);
	z-index: 1;
	display: none;
}
	</style>
	<script lang="javascript">
var projTime = 84;
var maxProjTime = 384;

function LoadNew() {
	var projTimeFormatted = new Intl.NumberFormat('en-US', { minimumIntegerDigits: 3 }).format(projTime);
	var runSelect = document.getElementById('runSelect');
	var chosenRun = runSelect.options[runSelect.selectedIndex];
	var productSelect = document.getElementById('productSelect');
	var chosenProduct = productSelect.options[productSelect.selectedIndex];
	var newUrl = 'http://weather.gc.ca/data/ensemble/images/' + chosenRun.value + '_054_E1_north@america_I_ENSEMBLE_' + chosenProduct.value + '@moy_' + projTimeFormatted + '.png';
	console.log('loading ' + newUrl);
	document.getElementById('mean1').src = newUrl;
	newUrl = 'http://weather.gc.ca/data/ensemble/images/' + chosenRun.value + '_054_E1_north@america_I_ENSEMBLE_' + chosenProduct.value + '@gemglb@gem007_' + projTimeFormatted + '.png';
	console.log('loading ' + newUrl);
	document.getElementById('thumbs1').src = newUrl;
	newUrl = 'http://weather.gc.ca/data/ensemble/images/' + chosenRun.value + '_054_E1_north@america_I_ENSEMBLE_' + chosenProduct.value + '@gem008@gem016_' + projTimeFormatted + '.png';
	console.log('loading ' + newUrl);
	document.getElementById('thumbs2').src = newUrl;
	newUrl = 'http://weather.gc.ca/data/ensemble/images/' + chosenRun.value + '_054_E1_north@america_I_ENSEMBLE_' + chosenProduct.value + '@gem017@gem020_' + projTimeFormatted + '.png';
	console.log('loading ' + newUrl);
	document.getElementById('thumbs3').src = newUrl;
	var runDate = new Date(chosenRun.text);
	var validDate = new Date(runDate.getTime() + projTime * 3600000);
	document.getElementById('status').innerHTML = chosenProduct.text + ' valid at ' + validDate.toUTCString();
	document.getElementById('projTime').innerHTML = 'hour ' + projTimeFormatted;
	//document.getElementById('status').innerHTML = 'Displaying ' + chosenProduct.text + ' at valid hour ' + projTime + ' from run ' + chosenRun.text;
}

function PreviousFrame() {
	projTime -= 12;
	if (projTime < 12) projTime = 12;
	console.log('setting projection time to ' + projTime);
	LoadNew();
}

function NextFrame() {
	projTime += 12;
	if (projTime > maxProjTime) projTime = maxProjTime;
	console.log('setting projection time to ' + projTime);
	LoadNew();
}

function ChangeRun(incr) {
	// if incr == -1, add 12 hr and increment selectedIndex of run
	// (since they are in reverse order)
	var runSelect = document.getElementById('runSelect');
	if (runSelect.selectedIndex == 0 && incr > 0) return; 
	if (runSelect.selectedIndex == runSelect.length - 1 && incr < 0) return;
	projTime += incr * -12; 
	runSelect.selectedIndex += incr * -1;
	LoadNew();
}
	</script>
</head>
<body style="font-family: sans-serif; font-size: 13px;">
<div id="help" onclick="javascript:document.getElementById('help').style.display = 'none';">
	<p style="position: fixed; background-color: black; top: 10px; left: 110px; width: 400px;">Run times are determined based on time of page load. 12z is expected to be newest between 1700z-0459z<br>&#8595;&#8595;&#8595;</p>
	<p style="position: fixed; background-color: black; top: 220px; left: 5%; width: 400px;">Ensemble mean<br><br>red and blue characters represent local minima/maxima on &quot;thumbnails&quot; at right<br>(0 is not plotted, 1-9, A=10, F=15)<br>QPF values are in mm on all plots<br>(25.4 mm is 1 inch)</p>
	<p style="position: fixed; background-color: black; top: 70px; left: 55%; width: 500px;">Individual ensemble members out to hour 384<br><br>GEM GLOBAL is the deterministic GDPS, aka CMCnh.<br>12z GDPS runs go to hr 144, 00z runs go to hr 240; if timestep is beyond those limits, global thumbnail is left blank.<br><br>These images can be scrolled separately from the rest of the page.</p>
	<p style="position: fixed; top: 80%; left: 40%; background-color: black;">Click anywhere to close Help</p>
</div>
	<p id="heading">Canadian GEPS ensemble viewer</p>
	<table>
		<tr>
			<td class="bannerFlag"><img style="height: 32px; width: 64px" src="https://upload.wikimedia.org/wikipedia/en/thumb/c/cf/Flag_of_Canada.svg/320px-Flag_of_Canada.svg.png"></td>
			<td class="bannerText">Environment<br>Canada</td>
			<td class="bannerText">Environnement<br>Canada</td>
		</tr>
		<tr>
			<td class="bannerFlag"><img style="padding-left: 16px; height: 32px; width: 32px" src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/ff/US-NationalWeatherService-Logo.svg/240px-US-NationalWeatherService-Logo.svg.png"></td>
			<td class="bannerText">National Weather<br>Service</td>
			<td class="bannerText">Service m&eacute;t&eacute;orologique<br>national</td>
		</tr>
	</table>
	<label for="runSelect">Run: </label>
	<select name="run" id="runSelect" onchange="javascript:LoadNew()">
<?php
	foreach ($runList as $coded => $human) echo "\t\t<option value=\"$coded\">$human</option>\n";
?>
	</select>
	<label for="productSelect">Product: </label>
	<select name="product" id="productSelect" onchange="javascript:LoadNew()">
		<option value="pnm" selected>MSLP</option>
		<option value="pcp12">12-hour QPF</option>
		<option value="gz">500mb height</option>
	</select>
	<br><br>
	<button onclick="javascript:PreviousFrame()">&lt;&lt;</button>
	<span id="projTime"></span>
	<button onclick="javascript:NextFrame()">&gt;&gt;</button>
	<span style="padding-left: 50px;">&nbsp;</span>
	<button onclick="javascript:ChangeRun(-1)">Older</button>
	dProg/dt
	<button onclick="javascript:ChangeRun(1)">Newer</button>
	<span style="padding-left: 50px;">&nbsp;</span>
	<button onclick="javascript:document.getElementById('help').style.display = 'block';">Help!</button><br><br>
	<span id="status">Models take a long time to load, eh?</span>
	<div id="mean">
		<img id="mean1">
	</div>
	<div id="thumbs">
		<img id="thumbs1">
		<img id="thumbs2">
		<img id="thumbs3">
	</div>
	<script lang="javascript">LoadNew();</script>
</body>
</html>
