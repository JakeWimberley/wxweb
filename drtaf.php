<!DOCTYPE html>
<!-- vim: set expandtab sw=2 ts=2: -->
<?php
  date_default_timezone_set('UTC');
  $bTestMode = FALSE;
  if ($bTestMode) {
    ini_set('display_errors',TRUE);
    error_reporting(E_ALL);
    //$_GET['sid'] = 'KORD';
  } else {
    ini_set('display_errors',FALSE);
  }
  $disclaimer = "TAF and METAR data courtesy <a href=\"http://aviationweather.gov/adds/\">Aviation Weather Center</a>, <a href=\"http://weather.gov\">National Weather Service</a>.<br><b>WARNING! DO NOT</b> use these data for flight planning. The data presented here are for informational and educational use only. Reliability cannot be guaranteed; use at your own risk.";
  $self = 'drtaf.php'; //TODO shouldn't need this
  $startTimeStr = '37 hours ago'; // anything compatible with strftime()
  $endTimeStr = '25 hours ago';   //
  $rulesets = array(
      'std' => array(
        'name' => 'Flight Categories (Standard US)',
        'catNames' => array('VLIFR','LIFR','IFR','MVFR','VFR'),
        'classNames' => array('vlifr','ifr1','ifr2','mvfr2','vfr3'),
        'cigMins' => array(-1,200,500,1000,3100),
        'visMins' => array(-1,0.5,1.0,3.0,6.0),
      ),
      'dlac' => array(
        'name' => 'Flight Categories (DLAC)',
        'catNames' => array('VLIFR','LIFR','IFR','IFR','MVFR','MVFR','VFR','VFR','VFR'),
        'classNames' => array('vlifr','lifr','ifr1','ifr2','mvfr1','mvfr2','vfr1','vfr2','vfr3'),
        'cigMins' => array(-1,200,500,500,1000,2000,3100,6500,12000),
        'visMins' => array(-1,0.5,1.0,2.0,3.0,3.0,6.0,7.0,7.0),
      ),
      'ci' => array(
        'name' => 'Commercial Impacts',
        'catNames' => array('No Ops','Unavbl as Alt','NPA Unavbl as Alt','Alt Req\'d','Low AAR','No Impact'),
        'classNames' => array('vlifr','ifr1','ifr2','mvfr1','vfr1','vfr3'),
        'cigMins' => array(-1,200,600,800,2000,6500),
        'visMins' => array(-1,0.5,2.0,2.0,3.0,6.0),
      ),
  );
  $defaultRules = 'std';
  $ruleId = '';
  $ruleName = '';
  if (isset($_GET['rules'])) {
    $ruleId = $_GET['rules'];
    $catClasses = $rulesets[$ruleId]['classNames'];
    $catText = $rulesets[$ruleId]['catNames'];
    $catCigMin = $rulesets[$ruleId]['cigMins'];
    $catVisMin = $rulesets[$ruleId]['visMins'];
    $ruleName = $rulesets[$ruleId]['name'];
  } else {
    $catClasses = $rulesets[$defaultRules]['classNames'];
    $catText = $rulesets[$defaultRules]['catNames'];
    $catCigMin = $rulesets[$defaultRules]['cigMins'];
    $catVisMin = $rulesets[$defaultRules]['visMins'];
  }
  $mySid = '';
?>
<html>
<head>
	<meta charset="utf-8">
	<title>Dr. Archibald Goodtaf, M.D.</title>
	<link href="drtaf.css" rel="stylesheet">
  <script language="javascript">
function ShowGroup(m,n) {
  // display tables metarGroup<m> to metarGroup<n> inclusive
  var displayRule;
  if (m == -1) displayRule = 'block';
  else displayRule = 'none';
  var metarTableList = document.querySelectorAll('table.metar');
  for (var i = 0; i < metarTableList.length; i++)
    metarTableList[i].style.display = displayRule;
  if (m == -1) return;
  for (var i = m; i <= n; i++)
    document.getElementById('metarGroup'+i).style.display = 'block';
}
  </script>
</head>
<body>
  <p style="font-size: 20px; margin: 10px 0px 10px 0px;"><span style="font-size: 36px;">Dr. GoodTAF</span> or: <i>How I Learned To Stop Worrying and Love VLIFR</i></p>
<?php
  // ADDS server does not understand a "real" ISO 8601 date
  $tafSearchStartTime = preg_replace('/\+00:00/','Z',date('c',strtotime($startTimeStr)));
  $tafSearchEndTime = preg_replace('/\+00:00/','Z',date('c',strtotime($endTimeStr)));
  if (isset($_GET['sid'])) {
    $mySid = strtoupper(substr($_GET['sid'],0,4));
    if (strlen($mySid) == 3) $mySid = "K$mySid";

    // Fetch TAF and obs from valid period
    $addsTafUrl = "http://aviationweather.gov/adds/dataserver_current/httpparam?dataSource=tafs&requestType=retrieve&format=xml&startTime=$tafSearchStartTime&endTime=$tafSearchEndTime&timeType=issue&stationString=$mySid";
    $tafXml = simplexml_load_file($addsTafUrl);
    $leftDiv = '';
    $rightDiv = '';

    // Make our first pass thru the TAF data.
    // We'll figure out the earliest and latest times covered by all the TAFs,
    // and request one set of METARs covering that whole period.
    $validStart = date_create('3000-01-01'); // Fry?!
    $validEnd = date_create('1800-01-01');
    $foundTaf = FALSE;
    foreach ($tafXml->data->TAF as $issuanceXml) {
      $foundTaf = TRUE;
      $dtStart = date_create($issuanceXml->valid_time_from);
      $dtEnd = date_create($issuanceXml->valid_time_to);
      if ($dtStart < $validStart) $validStart = $dtStart;
      if ($dtEnd > $validEnd) $validEnd = $dtEnd;
    }
    if (! $foundTaf) {
      echo "<p>Whoa there! Apparently <b>$mySid</b> ain't a TAF site. Go figure.</p>\n";
      echo PrintForm();
      break;
    }
    $validStart = preg_replace('/\+00:00/','Z',date_format($validStart,'c'));
    $validEnd = preg_replace('/\+00:00/','Z',date_format($validEnd,'c'));
    $addsMetarUrl = "http://aviationweather.gov/adds/dataserver_current/httpparam?dataSource=metars&requestType=retrieve&format=xml&startTime=$validStart&endTime=$validEnd&stationString=$mySid";
    $obsXml = simplexml_load_file($addsMetarUrl);
    //// Make another pass thru the TAFs in the XML.
    //// Here, we'll bin obs into the ordered TAF group periods.
    //// While we've got the period object, we'll also determine the category.
    $tafs = [];
    $obsBinnedByPeriod = [];
    $categories = [];
    $tafGroup = 0;
    $obGroup = 0;
    foreach ($tafXml->data->TAF as $issuanceXml) {
      array_push($tafs,(string)$issuanceXml->raw_text);
      // We can't rely on the TAF periods in the XML to be in the same order as
      // they were in the actual TAF. So we're going to step thru the periods
      // and put them into an array, which can then be sorted.
      $periods = [];
      foreach ($issuanceXml->forecast as $periodXml)
        array_push($periods,$periodXml);
      usort($periods, 'sortPeriods');
      foreach ($periods as $periodXml) {
        $begin = date_create($periodXml->fcst_time_from);
        $end = date_create($periodXml->fcst_time_to);
        array_push($categories, CategoryOfGroup($periodXml));
        array_push($obsBinnedByPeriod, []);
        foreach ($obsXml->data->METAR as $obXml) {
          $obTime = date_create($obXml->observation_time);
          if ($obTime > $begin && $obTime <= $end) {
            // note that though we are not saving $periods for later use,
            // $obGroup does not get reset between TAFs.
            // $obsBinnedByPeriod thus reflects all periods from all TAFs.
            if ($bTestMode) array_unshift($obsBinnedByPeriod[$obGroup],(string)$obXml->raw_text.' ['.date_format($begin,'dH').'/'.date_format($end,'dH').']');
            else array_unshift($obsBinnedByPeriod[$obGroup],(string)$obXml->raw_text);
          }
        }
        $obGroup++;
      }
    }

    //// Final pass thru TAF data: Fill left div with output with the
    //// grouping/ordering determined in the second pass. We'll use $tafs this
    //// time, which only contains the TAF strings themselves.
    $tafGroup = 0;
    $rightDiv .= "<p id=\"metarTitle\">Observations during the selected period(s) - place cursor over table to scroll</p>\n";
    foreach ($tafs as $taf) {
      list($fm,$cond) = SplitTaf($taf);
      $lastGroupInTaf = $tafGroup + count($fm) - 1 + count(preg_grep('/./',$cond));
      $leftDiv .= "<p id=\"tafTitle\" onclick=\"javascript:ShowGroup($tafGroup,$lastGroupInTaf)\">TAF - click group for obs only from that period; click here for all from this TAF</p>\n";
      $leftDiv .= "<table class=\"left\">\n";
      list($fmTime,$condTime) = TafTimes($fm,$cond);
      // N.B.: tafGroup must be handled separately since the block arrays make
      // no distinction between FM and conditional groups
      for ($i = 0; $i < count($fm); $i++, $tafGroup++) {
        list($cat,$catName) = $categories[$tafGroup];
        $leftDiv .= "  <tr onclick=\"javascript:ShowGroup($tafGroup,$tafGroup)\">\n";
        if ($bTestMode) {
          $timeLimits = $fmTime[$i][0][0] . $fmTime[$i][0][1] . '/' .
                        $fmTime[$i][1][0] . $fmTime[$i][1][1];
          $leftDiv .= "    <td class=\"num\">" . $tafGroup . "<br><small>$timeLimits</small></td>\n";
        } else {
          $leftDiv .= "    <td class=\"num\">" . $tafGroup . "</td>\n";
        }
        $leftDiv .= "    <td class=\"catname$cat\">$catName</td>\n";
        $leftDiv .= "    <td class=\"tafmetar$cat\">";
        if (preg_match('/^FM/',$fm[$i])) $leftDiv .= str_repeat('&nbsp;',5);
        $leftDiv .= $fm[$i]."</td>\n";
        $leftDiv .= "  </tr>\n";
        if (! empty($cond[$i])) {
          $tafGroup++;
          list($cat,$catName) = $categories[$tafGroup];
          $leftDiv .= "  <tr onclick=\"javascript:ShowGroup($tafGroup,$tafGroup)\">\n";
          if ($bTestMode) {
            $timeLimits = $condTime[$i][0][0] . $condTime[$i][0][1] . '/' .
                          $condTime[$i][1][0] . $condTime[$i][1][1];
            $leftDiv .= "    <td class=\"num\">" . $tafGroup . "<br><small>$timeLimits</small></td>\n";
          } else {
            $leftDiv .= "    <td class=\"num\">" . $tafGroup . "</td>\n";
          }
          $leftDiv .= "    <td class=\"catname$cat\">$catName</td>\n";
          $leftDiv .= "    <td class=\"tafmetar$cat\">".str_repeat('&nbsp;',6).$cond[$i]."</td>\n";
          $leftDiv .= "  </tr>\n";
        }
      }
      $leftDiv .= "</table>\n";
    } // end TAF object loop
    //// Step thru the METAR groups and put the tables in the right div.
    $obGroup = 0;
    foreach ($obsBinnedByPeriod as $periodObs) {
      $rightDiv .= "<table class=\"metar right\" id=\"metarGroup$obGroup\">\n";
      foreach ($periodObs as $metar) {
        $metar = str_replace("\n",' ',$metar);
        list($cat,$catName) = CategoryFromCoded($metar);
        $rightDiv .= "  <tr>\n";
        $rightDiv .= "    <td class=\"num\">$obGroup</td>\n";
        $rightDiv .= "    <td class=\"catname$cat\">$catName</td>\n";
        $rightDiv .= "    <td class=\"tafmetar$cat\">$metar</td>\n";
        $rightDiv .= "  </tr>\n";
      }
      $rightDiv .= "</table>\n";
      $obGroup++;
    }
    // Print the div content just like I said we would.
    $disclaimer .= "<br><br>Input data: <a href=\"$addsTafUrl\">TAF</a>, <a href=\"$addsMetarUrl\">obs</a></p>\n";
    $leftDiv .= "      <p>Still feeling lucky? Roll again.</p>\n";
    $leftDiv .= PrintForm();
    echo PrintLegend();
    echo "<div class=\"left\">\n";
    echo $leftDiv;
    echo "</div>\n";
    echo "<div class=\"right\">\n";
    echo $rightDiv;
    echo "</div>\n";
  } else { // ! isset sid
    echo "  <p>This is a TAF verification tool. Enter a TAF site, and the forecasts for that site issued between <b>$endTimeStr</b> and <b>$startTimeStr</b> will be displayed, along with the verifying obs for each. Be aware that Dr. GoodTAF presently has the following limitations:</p>\n";
?>
  <ul>
    <li>Only observations <i>during periods</i> are processed. E.g., you won't be able to tell what the category was at the start of the first period.</li>
    <li>Only North American style TAFs are supported (no metric cigs, no &quot;CAVOK,&quot; etc.)</li>
    <li>Similarly, BECMG groups will cause a spectacular explosion. So stay away from military or Canadian TAFs.</li>
    <li>&quot;Commercial impacts&quot; may not be the same at all airports or for all operations; they are a guideline.</li>
  </ul>
<?php
    echo PrintForm();
  }
?>
  </body>
</html>
<?php
// Determine the start and end times of TAF groups.
function TafTimes($fms,$conds) {
  global $fm, $cond;
  $pds = array(); // $pds[0][0] = start of first group, $pds[2][1] = end of third group, etc.
  $pdsC = array(); // same format; only assigned value if a conditional group exists for the matching FM group
  $fMax = count($fms) - 1;
  for ($f = 0; $f <= $fMax; $f++) {
    if ($f == 0) {
      if (preg_match('| (\d\d)(\d\d)/(\d\d)(\d\d) |',$fm[$f],$_times)) {
        list($startDay,$startHour,$endDay,$endHour) = array_slice($_times,1,4);
        $startMinute = 0;
        $endMinute = 0;
        // set start of first period, then end of last period
        list($pds[$f][0][0],$pds[$f][0][1],$pds[$f][0][2]) = array($startDay,$startHour,$startMinute);
        list($pds[$fMax][1][0],$pds[$fMax][1][1],$pds[$fMax][1][2]) = array($endDay,$endHour,$endMinute);
      } // TODO a failure mode
    } else {
      if (preg_match('/FM(\d\d)(\d\d)(\d\d) /',$fm[$f],$_times)) {
        // set end of previous period to start of this one
        // then set start of this period
        list($pds[$f-1][1][0],$pds[$f-1][1][1],$pds[$f-1][1][2]) = array_slice($_times,1,3);
        list($pds[$f][0][0],$pds[$f][0][1],$pds[$f][0][2]) = array_slice($_times,1,3);
      }
    }
    if (strlen($cond[$f]) > 0) {
      if (preg_match('| (\d\d)(\d\d)/(\d\d)(\d\d) |',$cond[$f],$_times)) {
        list($startDay,$startHour,$endDay,$endHour) = array_slice($_times,1,4);
        $startMinute = 0;
        $endMinute = 0;
        list($pdsC[$f][0][0],$pdsC[$f][0][1],$pdsC[$f][0][2]) = array($startDay,$startHour,$startMinute);
        list($pdsC[$f][1][0],$pdsC[$f][1][1],$pdsC[$f][1][2]) = array($endDay,$endHour,$endMinute);
      } // TODO a failure mode
    } else {
      $pdsC[$f] = array();
    }
  }
  return array($pds,$pdsC);
}

// Get category of a TAF group or METAR based on the coded wx data therein.
function CategoryFromCoded($group) {
  global $catClasses, $catText, $catCigMin, $catVisMin, $bTestMode;
  $catCig = count($catClasses)-1; // default to VFR if not defined in group
  if (preg_match('/(?:BKN|OVC|VV)(\d{3})/',$group,$_cig)) {
    $_cig[1] = ltrim($_cig[1],'0');
    for ($i = 1; $i < count($catCigMin); $i++) {
      if ($_cig[1] * 100 < $catCigMin[$i]) {
        $catCig = $i-1;
        break;
      }
    }
  } else {
    $_cig[1] = -1;
  }
  $vis = 99; // default to VFR if not defined in group or is P6SM
  $catVis = count($catClasses)-1;
  $vmatch = preg_match('!([MP]?(?:\d{1,2}|(?:\d )?\d{1,2}/\d{1,2}))SM!',$group,$_vis);
  if ($vmatch > 0) {
    if ($_vis[1] == 'M1/4') {
      $catVis = 0;
    } else if ($_vis[1] != 'P6') {
      $vis = $_vis[1];
      $fracPat = '|(\d+)/(\d+)|';
      if (preg_match($fracPat,$_vis[1],$frac)) { // find fraction, if any
        $_vis[1] = preg_replace($fracPat,'',$_vis[1]);
        $vis = $_vis[1] + $frac[1] / $frac[2];
      }
      for ($i = 1; $i < count($catVisMin); $i++) {
        if ($vis < $catVisMin[$i]) {
          $catVis = $i-1;
          break;
        }
      }
    }
  }
  if ($bTestMode)
  return array(' '.$catClasses[min($catCig,$catVis)],
               'cig: '.$catText[$catCig]." ($_cig[1]00 ft) vis: ".$catText[$catVis]." ($vis mi) ---> ".$catText[min($catCig,$catVis)]);
  else return array(' '.$catClasses[min($catCig,$catVis)],$catText[min($catCig,$catVis)]);
}

// Get category of a TAF period based on the met data in XML fields.
function CategoryOfGroup($xmlObj) {
  global $catClasses, $catText, $catCigMin, $catVisMin, $bTestMode;
  $catCig = count($catClasses)-1; // default to VFR if not defined in group
  // Find the cig in this group.
  $lowestCig = 99999;
  $cloudBaseHeight = 99999;
  foreach ($xmlObj->sky_condition as $sky) {
    if (preg_match('/(?:BKN|OVC|VV)/',(string)$sky['sky_cover'])) {
      $cloudBaseHeight = (int)$sky['cloud_base_ft_agl'];
      if ($cloudBaseHeight < $lowestCig) $lowestCig = $cloudBaseHeight;
    }
  }
  // Find which category that cig falls into.
  for ($i = 1; $i < count($catCigMin); $i++) {
    if ($lowestCig < $catCigMin[$i]) {
      $catCig = $i-1;
      break;
    }
  }
  // Vsby easier since it is a single value.
  // However it might be omitted, hence property_exists() call
  $vis = 99;
  $catVis = count($catVisMin)-1;
  if (property_exists($xmlObj,'visibility_statute_mi')) {
    $vis = (float)$xmlObj->visibility_statute_mi;
    if ($vis > 6) $vis = 10;
  }
  for ($i = 1; $i < count($catVisMin); $i++) {
    if ($vis < $catVisMin[$i]) {
      $catVis = $i-1;
      break;
    }
  }
  if ($bTestMode)
  return array(' '.$catClasses[min($catCig,$catVis)],
               'cig: '.$catText[$catCig]." ($cloudBaseHeight ft) vis: ".$catText[$catVis]." ($vis mi) ---> ".$catText[min($catCig,$catVis)]);
  else return array(' '.$catClasses[min($catCig,$catVis)],$catText[min($catCig,$catVis)]);
}

// Get arrays of FM and conditional groups based on a TAF string.
// Input and output are both in coded format.
function SplitTaf($rawStr) {
  global $mySid;
  $tafStr = preg_replace("/\s+/",' ',$rawStr);
//  echo "<pre>$tafStr</pre>\n";
  $fmOffset = 0;
  $fm = array();
  while ($fmOffset < strlen($tafStr)) {
    $fmMatch = preg_match("/(?:$mySid|FM\d{6}).+?(?:FM\d{6}|=|$)/",$tafStr,$_fm,PREG_OFFSET_CAPTURE,$fmOffset);
    if ($fmMatch < 1) break;
    array_push($fm,preg_replace("/\s*(?:FM\d{6})?$/",'',$_fm[0][0]));
    $fmOffset = $_fm[0][1] + strlen($_fm[0][0]) - 8; // 8 is len of FMdddddd
  }
//  $fmStr = implode("\n",$fm);
//  echo "<pre>$fmStr</pre>\n";
  $cond = array();
  for ($i = 0; $i < count($fm); $i++) {
    // TODO support BECMG?
    $condPat = '/((?:TEMPO|PROB).+$)/';
    if (preg_match($condPat,$fm[$i],$_cond)) {
      $cond[$i] = $_cond[1];
      $fm[$i] = preg_replace($condPat,'',$fm[$i]);
    } else {
      $cond[$i] = '';
    }
  }
  return([$fm,$cond]);
}

// Custom function for asort() to put the TAF period XML blocks in
// ascending chronological order.
function sortPeriods($a,$b) {
      $beginA = date_create($a->fcst_time_from);
      $beginB = date_create($b->fcst_time_from);
      if (property_exists($a,'change_indicator'))
        $groupTypeA = (string)$a->change_indicator;
      else
        $groupTypeA = '';
      if (property_exists($b,'change_indicator'))
        $groupTypeB = (string)$b->change_indicator;
      else
        $groupTypeB = '';
      if ($beginA < $beginB) return -1;
      else if ($beginA > $beginB) return 1;
      // if start times are equal, the FM group is first
      else if ($groupTypeA === 'FM' || empty($groupTypeA)) return -1;
      else if ($groupTypeB === 'FM' || empty($groupTypeB)) return 1;
      else return 0; // should never get here
}

function PrintForm() {
  global $self, $mySid, $ruleId, $disclaimer, $rulesets;
  $value = (strlen($mySid) > 0) ? ' value="'.$mySid.'"' : '';
  $out = '';
  $out .= <<<EOT
  <form action="$self" method="get">
    <p>
      <label for="sid">Airport ICAO ID <i>(e.g. KJFK)</i></label>
      <span style="padding-left: 20px">
      <input type="text" name="sid" size="5" maxlength="4"$value>
      </span>
    </p>
    <p>
      <label for="rules">Ruleset</label>
      <span style="padding-left: 20px">
      <select name="rules">

EOT;
  foreach ($rulesets as $id => $ruleset) {
    $name = $ruleset['name'];
    $sel = ($id === $ruleId) ? ' selected' : '';
    $out .= "        <option value=\"$id\"$sel>$name</option>\n";
  }
  $out .= <<<EOT
      </select>
      </span>
    </p>
    <input type="submit" value="Go">
  </form>
  <p class="disclaimer">$disclaimer</p>

EOT;
  return $out;
}

function PrintLegend() {
  global $catClasses, $catText, $catCigMin, $catVisMin, $ruleName;
  // reverse the arrays as we have to start from the front,
  // and we want the front to be the VFR end
  $classes = array_reverse($catClasses);
  $names = array_reverse($catText);
  $cigs = array_reverse($catCigMin);
  $vsbys = array_reverse($catVisMin);
  $colspan = count($names);
  $out = '';
  $out .= <<<EOT
  <table class="legend">
    <tr>
      <td class="legend" colspan="$colspan">Verifying Minima for $ruleName</td>
    </tr>
    <tr>

EOT;
  for ($z = 0; $z < count($names); $z++) {
    $cat = ' '.$classes[$z];
    $opr = '> ';
    if ($z == count($names)-1) {
      $cigs[$z] = $cigs[$z-1];
      $vsbys[$z] = $vsbys[$z-1];
      $opr = '< ';
    }
    else $opr = '';
    $out .= <<<EOT
      <td class="legend catname$cat">$names[$z]<br>$opr$cigs[$z] ft, $opr$vsbys[$z]SM</td>

EOT;
  }
    $out .= <<<EOT
    </tr>
  </table>

EOT;
  return $out;
}
?>
